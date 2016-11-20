<?php
/**
 * Created by PhpStorm.
 * User: Руслан
 * Date: 20.09.2015
 * Time: 21:44
 */

namespace maestroprog\Saw;

use maestroprog\esockets\Peer;
use maestroprog\esockets\TcpServer;
use maestroprog\esockets\debug\Log;

/**
 * Связующее звено между входным скриптом,
 * обеспечивающее контроль за работой Worker-ов.
 */
class Controller extends Singleton
{
    use Executor;

    protected static $instance;
    /**
     * Константы возможных типов подключающихся клиентов
     */
    const CLIENT_INPUT = 1; // input-клиент, передающий запрос
    const CLIENT_WS_INPUT = 2; // WS input-клиент, передающий запрос (зарезервировано)
    const CLIENT_WORKER = 3; // воркер
    const CLIENT_CONTROLLER = 4; // контроллер. (зарезервировано)
    const CLIENT_DEBUG = 5; // отладчик
    /**
     * Константы возможных состояний подключения с клиентом
     */
    const STATE_ACCEPTED = 1; // соединение принято

    /**
     * @var bool
     */
    public $work = true;

    /**
     * @var bool вызывать pcntl_dispatch_signals()
     */
    public $dispatch_signals = false;

    /**
     * @var TcpServer
     */
    protected $server;


    /**
     * @var string path to php binaries
     */
    public $php_binary_path = 'php';

    /**
     * @var TcpServer socket connection
     */
    private $ss;

    /**
     * Инициализация
     *
     * @param array $config
     * @return bool
     */
    public function init(array &$config)
    {
        // настройка сети
        if (isset($config['net'])) {
            $this->ss = new TcpServer($config['net']);
        } else {
            trigger_error('Net configuration not found', E_USER_NOTICE);
            unset($config);
            return false;
        }
        // настройка доп. параметров
        if (isset($config['params'])) {
            foreach ($config['params'] as $key => &$param) {
                if (isset($this->$key)) $this->$key = $param;
                unset($param);
            }
        }
        unset($config);
        return true;
    }

    public function start()
    {
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGINT, function ($sig) {
                $this->work = false;
            });
            $this->dispatch_signals = true;
        }

        if (!$this->ss->connect()) {
            throw new \Exception('Cannot start: not connected');
        }
        Log::log('start');
        $this->ss->onConnectPeer(function (Peer $peer) {
            Log::log('peer connected ' . $peer->getAddress());
            $peer->set('state', self::STATE_ACCEPTED);
            $peer->onRead(function ($data) use ($peer) {
                if (!is_array($data) || !isset($data['command'])) {
                    return $peer->send('BYE');
                }
                return $this->handle($data, $peer);
            });
            $peer->onDisconnect(function () use ($peer) {
                Log::log('peer disconnected');
            });
            if (!$peer->send('HELLO')) {
                Log::log('HELLO FAIL SEND!');
            }
        });
        register_shutdown_function(function () {
            $this->stop();
            Log::log('stopped');
        });
        return true;
    }

    protected function handle(array $data, Peer $peer)
    {
        switch ($data['command']) {
            case 'wadd': // add worker
                $this->wadd($peer->getDsc(), $peer->getAddress());
                break;
            case 'wdel': // del worker
                $this->wdel($peer->getDsc());
                break;
            case 'tadd': // add new task (сообщает что воркеру стала известна новая задача)
                $this->tadd($peer->getDsc(), $data['name']);
                break;
            case 'trun': // run task (name) (передает на запуск задачи в очередь)
        }
        return null;
    }

    /**
     * Известные воркеры.
     *
     * @var array
     */
    private $workers = [];

    /**
     * Известные известным воркерам задачи.
     *
     * @var array
     */
    private $tasks = [];

    private function wadd(int $dsc, string $address)
    {
        $this->workers[$dsc] = ['address' => $address, 'state' => 'ready', 'tasks' => []];
    }

    private function wdel(int $dsc)
    {
        unset($this->workers[$dsc]);
        foreach ($this->tasks as $name => $workers) {
            if (isset($workers[$dsc])) {
                unset($this->tasks[$name][$dsc]);
            }
        }
    }

    private function tadd(int $dsc, string $name)
    {
        if (!isset($this->workers[$dsc]['tasks'][$name])) {
            $this->workers[$dsc]['tasks'][$name] = true;
            $this->tasks[$name][$dsc] = true;
        }
    }

    private function wbalance()
    {

    }

    private function tbalance()
    {

    }

    public function work()
    {
        while ($this->work) {
            $this->ss->listen();
            $this->ss->read();
            $this->wbalance();
            $this->tbalance();
            if ($this->dispatch_signals) {
                pcntl_signal_dispatch();
            }
            usleep(INTERVAL);
        }
    }

    public function stop()
    {
        $this->work = false;
        $this->ss->disconnect();
        Log::log('closed');
    }
}
