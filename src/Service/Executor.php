<?php

namespace Saw\Service;

use Esockets\debug\Log;
use Saw\ValueObject\ProcessStatus;

final class Executor
{
    /**
     * @var string path to php binaries
     */
    public $phpBinaryPath = 'php';

    public function __construct(string $phpBinaryPath = null)
    {
        if (!is_null($phpBinaryPath)) {
            $this->phpBinaryPath = $phpBinaryPath;
        }
    }

    /**
     * Выполняет команду, и возвращает ID запущенного процесса.
     *
     * @param $cmd
     * @return ProcessStatus
     */
    public function exec($cmd): ProcessStatus
    {
        $cmd = sprintf('%s %s', $this->phpBinaryPath, $cmd);
        if (PHP_OS === 'WINNT') {
            $cmd = str_replace('/', '\\', $cmd);
            $cmd = str_replace('\\', '\\\\', $cmd);
        }
        if (PHP_SAPI !== 'cli') {
            define('STDIN', fopen('php://stdin', 'r'));
            define('STDOUT', fopen('php://stdout', 'w'));
            define('STDERR', fopen('php://stderr', 'w'));
        }
        $pipes = [STDIN, STDOUT, STDERR];
        Log::log($cmd);
        $resource = proc_open($cmd, [], $pipes, null, null, null);
        if (false === $resource) {
            throw new \RuntimeException('Cannot be run ' . $cmd);
        }
        return new ProcessStatus($resource);
    }

    /**
     * Прихлопывает запущенный процесс.
     * @param ProcessStatus $processStatus
     */
    public function kill(ProcessStatus $processStatus)
    {
        proc_close($processStatus->getResource());
    }
}