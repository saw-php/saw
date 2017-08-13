<?php

namespace Maestroprog\Saw\Command;

use Esockets\Client;

abstract class AbstractCommand
{
    const STATE_NEW = 0;
    const STATE_RUN = 1;
    const STATE_RES = 2;

    const RES_VOID = 0;
    const RES_SUCCESS = 1;
    const RES_ERROR = 2;

    const NAME = 'void';

    /**
     * Массив данных, поступиших в команде.
     * Формируется в методе @see AbstractCommand::handle(),
     * который можно переопределить.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Массив полей, присутствие которых необходимо для поступившей команды.
     * Этот массив используется для валидации при отправке команды.
     *
     * @var array
     */
    protected $needData = [];

    private $id;
    private $peer;
    private $state;
    private $code;

    private $onSuccess;
    private $onError;

    public static function create(string $class, int $id, Client $peer): self
    {
        if (!self::isValidClass($class)) {
            throw new \InvalidArgumentException('Invalid command class.');
        }
        return new $class($id, $peer);
    }

    public static function instance(string $class, int $id, Client $peer, int $state, int $code): self
    {
        if (!self::isValidClass($class)) {
            throw new \InvalidArgumentException('Invalid command class.');
        }
        return new $class($id, $peer, $state, $code);
    }

    final private static function isValidClass(string $class): bool
    {
        return is_subclass_of($class, AbstractCommand::class);
    }

    protected function __construct(int $id, Client $peer, int $state = self::STATE_NEW, int $code = self::RES_VOID)
    {
        $this->id = $id;
        $this->peer = $peer;
        $this->state = $state;
        $this->code = $code;
    }

    /**
     * Todo doc
     *
     * @param int $state
     * @param int $code
     */
    final public function reset(int $state, int $code)
    {
        $this->state = $state;
        $this->code = $code;
    }

    /**
     * Вернёт ID поступившей команды.
     * Не путать с ID Thread!
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Возвращает состояние команды.
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * Вернёт клиента, от которого поступила команда/кому отправляется команда.
     *
     * @return Client
     */
    public function getPeer(): Client
    {
        return $this->peer;
    }

    /**
     * Отправляет команду на выполнение.
     *
     * @param array $data
     * @throws \Exception
     * @throws \Throwable
     */
    final public function run(array $data = [])
    {
        if (!$this->isValid($data)) {
            throw new \Exception('Invalid command ' . $this->getCommandName());
        }
        $this->state = self::STATE_RUN;
        if (!$this->peer->send([
            'command' => $this->getCommandName(),
            'state' => $this->state,
            'id' => $this->id,
            'code' => $this->code,
            'data' => $data])
        ) {
            throw new \Exception('Fail run command ' . $this->getCommandName());
        }
    }

    /**
     * Отправляет результат выполнения команды.
     *
     * @throws \Exception
     */
    final public function result()
    {
        $this->state = self::STATE_RES;
        if (!$this->peer->send([
            'command' => $this->getCommandName(),
            'state' => $this->state,
            'id' => $this->id,
            'code' => $this->code,
            'data' => $this->getData()])
        ) {
            throw new \RuntimeException('Fail for send result of command ' . $this->getCommandName());
        }
    }

    /**
     * Возвращает кастомные данные, которые нужно передать вместе с командой.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * С помощью позщнего статического связывания возвращает имя команды,
     * определённой в константе @see AbstractCommand::NAME текущего объекта,
     * которую можно переопределить.
     *
     * @return string
     */

    public function getCommandName(): string
    {
        return static::NAME;
    }

    /**
     * Инициализирует кастомные данные, поступившие вместе с командой.
     *
     * @param $data
     * @return self
     */
    public function handle(array $data): self
    {
        $this->data = array_merge($this->data, array_intersect_key($data, array_flip($this->needData)));

        return $this;
    }

    /**
     * Выполняет необходимые проверки перед запуском задачи,
     * а именно - есть ли все необходимые данные для запуска задачи.
     *
     * todo final!
     * @param array $data
     * @return bool
     */
    protected function isValid(array $data): bool
    {
        return count(array_diff_key(array_flip($this->needData), $data)) === 0;
    }


    /**
     * Метод вызывается диспетчером команд,
     * он реализует механизм вызова колбэков,
     * назначаемых в @see CommandCode::onSuccess()
     * и @see CommandCode::onError().
     *
     * @param $result array
     * @return void
     * @throws \RuntimeException Исключение бросается в случае получения неизвестного статуса выполнения команды
     */
    public function dispatchResult(&$result)
    {
        if ($this->isSuccess()) {
            if (is_callable($this->onSuccess)) {
                call_user_func($this->onSuccess, $this, $result);
            }
        } elseif ($this->isError()) {
            if (is_callable($this->onError)) {
                call_user_func($this->onError, $this, $result);
            }
        } else {
            throw new \RuntimeException('Why is not success and is not error?');
        }
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->code === AbstractCommand::RES_SUCCESS;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->code === AbstractCommand::RES_ERROR;
    }

    /**
     * Задает колбэк, вызывающийся при успешном выполнении команды.
     * Колбэк следует передавать на той стороне, которая посылала команду,
     * и должна обработать результат выполнения команды.
     *
     * @param callable $callback
     * @return $this
     */
    final public function onSuccess(callable $callback): self
    {
        $this->onSuccess = $callback;
        return $this;
    }

    /**
     * Задает колбэк, вызывающийся при неудачном выполнении команды.
     * Колбэк следует передавать на той стороне, которая посылала команду,
     * и должна обработать результат выполнения команды.
     *
     * @param callable $callback
     * @return $this
     */

    final public function onError(callable $callback): self
    {
        $this->onError = $callback;
        return $this;
    }

    /**
     * Сообщает об успешном выполнении команды.
     * Вызывается на той стороне, которая должна обработать поступившую команду.
     *
     * @throws \Exception
     */
    final public function success()
    {
        $this->code = self::RES_SUCCESS;
        $this->result(); // отправляем результаты работы
    }

    /**
     * Сообщает об ошибке выполнении команды.
     * Вызывается на той стороне, которая должна обработать поступившую команду.
     *
     * @throws \Exception
     */
    final public function error()
    {
        $this->code = self::RES_ERROR;
        $this->result(); // отправляем результаты работы
    }

    abstract public function toArray(): array;

    abstract public function fromArray();
}
