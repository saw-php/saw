<?php

namespace Maestroprog\Saw\Command;

use Esockets\Client;
use Maestroprog\Saw\Thread\AbstractThread;

/**
 * Общая команда "Запуск потока".
 * От воркера отправляется контроллеру для постановки в очередь на запуск.
 * От контроллера отправляется воркеру в виде приказа для запуска задачи.
 *
 * Результат выполнения команды - успешный/неуспешный запуск выполнения задачи.
 */
final class ThreadRun extends AbstractCommand
{
    const NAME = 'trun';

    private $runId;
    private $applicationId;
    private $uniqueId;
    private $arguments;

    public function __construct(Client $client, int $runId, string $appId, string $uniqueId, array $arguments = null)
    {
        parent::__construct($client);
        $this->runId = $runId;
        $this->applicationId = $appId;
        $this->uniqueId = $uniqueId;
        $this->arguments = $arguments;
    }

    public function getRunId(): int
    {
        return $this->runId;
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function getArguments(): array
    {
        return $this->arguments ?? [];
    }

    /**
     * Команда сама знает, что ей нужно знать о задаче
     * - поэтому дадим ей задачу, пускай возьмёт все, что ей нужно.
     *
     * @param AbstractThread $thread
     * @return array
     * @deprecated
     */
    public static function serializeThread(AbstractThread $thread): array
    {
    }


    public function toArray(): array
    {
        return [
            'run_id' => $this->getRunId(),
            'application_id' => $this->getApplicationId(),
            'unique_id' => $this->getUniqueId(),
            'arguments' => $this->getArguments(),
        ];
    }

    public static function fromArray(array $data, Client $client)
    {
        return new self($client, $data['run_id'], $data['application_id'], $data['unique_id'], $data['arguments']);
    }
}
