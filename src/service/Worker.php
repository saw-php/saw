<?php
/**
 * Created by PhpStorm.
 * User: Руслан
 * Date: 20.09.2015
 * Time: 21:56
 */

namespace maestroprog\saw\service;

use maestroprog\saw\library\worker\Core;
use maestroprog\saw\command\TaskAdd;
use maestroprog\saw\command\TaskRes;
use maestroprog\saw\command\TaskRun;
use maestroprog\saw\command\WorkerAdd;
use maestroprog\saw\command\WorkerDelete;
use maestroprog\saw\entity\Task;
use maestroprog\saw\library\dispatcher\Command;
use maestroprog\saw\library\CommandDispatcher;
use maestroprog\saw\library\Factory;
use maestroprog\saw\library\Singleton;
use maestroprog\saw\library\Application;
use maestroprog\saw\library\TaskManager;
use maestroprog\esockets\TcpClient;
use maestroprog\esockets\debug\Log;
use maestroprog\saw\entity\Command as EntityCommand;

/**
 * Воркер, использующийся воркер-скриптом.
 * Используется для выполнения отдельных задач.
 * Работает в качестве демона в нескольких экземплярах.
 */
class Worker extends Singleton
{
    public $work = true;

    public $worker_app;

    public $worker_app_class;

    /**
     * @var TcpClient socket connection
     */
    protected $sc;

    /**
     * @var TaskManager
     */
    protected $taskManager;

    /**
     * @var CommandDispatcher
     */
    protected $dispatcher;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Core only for Worker
     */
    protected $core;

    /**
     * Инициализация
     *
     * @param array $config
     * @return bool
     */
    public function init(array &$config): bool
    {
        // настройка сети
        if (isset($config['net'])) {
            $this->sc = new TcpClient($config['net']);

            $this->sc->onRead($this->onRead());

            $this->sc->onDisconnect(function () {
                Log::log('i disconnected!');
                $this->work = false;
            });
        } else {
            trigger_error('Net configuration not found', E_USER_NOTICE);
            return false;
        }
        $this->configure($config);
        return true;
    }

    /**
     * @throws \Exception
     */
    public function start()
    {
        $this->core = new Core($this->sc, $this->worker_app, $this->worker_app_class);
        $this->dispatcher = Factory::getInstance()->createDispatcher([
            new EntityCommand(
                WorkerAdd::NAME,
                WorkerAdd::class,
                function (Command $context) {
                    $this->core->run();
                }
            ),
            new EntityCommand(
                WorkerDelete::NAME,
                WorkerDelete::class,
                function (Command $context) {
                    $this->stop();
                }
            ),
            new EntityCommand(TaskAdd::NAME, TaskAdd::class),
            new EntityCommand(
                TaskRun::NAME,
                TaskRun::class,
                function (TaskRun $context) {
                    // выполняем задачу
                    //$result = $this->core->runTask($context->getName());
                    //$task = new Task($context->getRunId(), $context->getName(), $context->getFromDsc());
                    //$task->setResult($result);
                    /*$this->dispatcher->create(TaskRes::NAME, $this->sc)
                        ->onError(function () {
                            //todo
                        })
                        ->run(TaskRes::serializeTask($task));*/
                }
            ),
            new EntityCommand(
                TaskRes::NAME,
                TaskRes::class,
                function (TaskRes $context) {
                    $this->core->receiveTask(
                        $context->getRunId(),
                        $context->getResult()
                    );
                }
            ),
        ]);
    }

    private function configure(array &$config)
    {
        // настройка доп. параметров
        if (isset($config['params'])) {
            foreach ($config['params'] as $key => &$param) {
                if (property_exists($this, $key)) {
                    $this->$key = $param;
                }
                unset($param);
            }
        }
    }

    public function connect()
    {
        return $this->sc->connect();
    }

    public function stop()
    {
        $this->work = false;
        $this->sc->disconnect();
    }

    public function work()
    {
        $this->sc->setBlock();
        while ($this->work) {
            $this->sc->read();
            usleep(INTERVAL);
        }
    }

    public function run()
    {
        $this->core->run();
    }

    /**
     * Метод под нужды таскера - запускает ожидание завершения выполнения указанных в массиве задач.
     *
     * @param Task[] $tasks
     * @return bool
     */
    public function sync(array $tasks, float $timeout = 0.1)
    {
        return $this->core->syncTask($tasks, $timeout);
    }

    /**
     * Добавляет задачу на выполнение.
     *
     * @param Task $task
     */
    public function addTask(Task $task)
    {
        $this->core->addTask($task);
        $this->dispatcher->create(TaskAdd::NAME, $this->sc)
            ->onError(function () use ($task) {
                $this->addTask($task); // опять пробуем добавить команду
            })
            ->run(['name' => $task->getName()]);
    }

    /**
     * Настраивает текущий таск-менеджер.
     *
     * @param TaskManager $taskManager
     * @return $this
     */
    public function setTask(TaskManager $taskManager)
    {
        $this->core->setTaskManager($taskManager);
        return $this;
    }

    protected function onRead(): callable
    {
        return function ($data) {
            Log::log('I RECEIVED  :)');
            var_dump($data);

            switch ($data) {
                case 'HELLO':
                    $this->sc->send('HELLO');
                    break;
                case 'ACCEPT':
                    $this->dispatcher
                        ->create(WorkerAdd::NAME, $this->sc)
                        ->onError(function () {
                            $this->stop();
                        })
                        ->onSuccess(function () {
                            //todo
                        })
                        ->run();
                    break;
                case 'INVALID':
                    // todo
                    break;
                case 'BYE':
                    $this->work = false;
                    break;
                default:
                    if (is_array($data) && $this->dispatcher->valid($data)) {
                        $this->dispatcher->dispatch($data, $this->sc);
                    } else {
                        $this->sc->send('INVALID');
                    }
            }
        };
    }

    /**
     * @param array $config
     * @return Worker
     * @throws \Exception
     */
    public static function create(array $config): Worker
    {
        $init = self::getInstance();
        if ($init->init($config)) {
            Log::log('configured. input...');
            try {
                $init->connect();
                $init->start();
            } catch (\Exception $e) {
                Log::log(sprintf('Worker connect or start failed with error: %s', $e->getMessage()));
                throw new \Exception('Worker starting fail');
            }
            register_shutdown_function(function () use ($init) {
                $init->stop();
                Log::log('closed');
            });
            return $init->setTask(Factory::getInstance()->createTaskManager($init));
        } else {
            throw new \Exception('Cannot initialize Worker');
        }
    }
}
