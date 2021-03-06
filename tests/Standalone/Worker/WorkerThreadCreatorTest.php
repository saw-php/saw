<?php

namespace tests\Standalone\Worker;

use Esockets\Client;
use Maestroprog\Container\Container;
use Maestroprog\Saw\Application\ApplicationContainer;
use Qwerty\Application\ApplicationInterface;
use Maestroprog\Saw\Command\ContainerOfCommands;
use Maestroprog\Saw\Saw;
use Maestroprog\Saw\Service\CommandDispatcher;
use Maestroprog\Saw\Service\Commander;
use Maestroprog\Saw\Standalone\Controller\CycleInterface;
use Maestroprog\Saw\Standalone\Worker\WorkerThreadCreator;
use Maestroprog\Saw\Thread\Pool\ContainerOfThreadPools;
use Maestroprog\Saw\Thread\Pool\PoolOfUniqueThreads;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maestroprog\Saw\Standalone\Worker\WorkerThreadCreator
 */
class WorkerThreadCreatorTest extends TestCase
{
    /**
     * Тестирование отправки воркером команды об известном потоке.
     *
     * @return WorkerThreadCreator
     */
    public function testWorkerSendKnowOfCreatedThread()
    {
        Saw::instance()->init(__DIR__ . '/../../../sample/config.php')->instanceWorker();
        $app = $this->createMock(ApplicationInterface::class);
        $app->method('getId')->willReturn('1');
        Container::instance()->get(ApplicationContainer::class)->add($app);

        $poolsContainer = new ContainerOfThreadPools();
        $pool = new PoolOfUniqueThreads();
        $poolsContainer->add(1, $pool);

        $commander = new Commander($this->createMock(CycleInterface::class), new ContainerOfCommands());
        $client = $this->createMock(Client::class);
        $threadCreator = new WorkerThreadCreator($poolsContainer, $commander, $client);

        $client
            ->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $threadCreator->thread('TEST', function () {
        });
        $threadCreator->thread('TEST', function () {
        });
        return $threadCreator;
    }

    /**
     * Тестирование ситуации когда поток уже добавлен, и сообщение отправлять не нужно.
     *
     * @*param $threadCreator WorkerThreadCreator
     * @*depends testWorkerSendKnowOfCreatedThread
     *
     * public function testNotAddCurrentlyAddedThread(WorkerThreadCreator $threadCreator)
     * {
     * }*/
}
