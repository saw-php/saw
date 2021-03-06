<?php

namespace Maestroprog\Saw\Thread\Pool;

use Maestroprog\Saw\Thread\AbstractThread;

class RunnableThreadPool extends AbstractThreadPool
{
    public function getThreadId(AbstractThread $thread)
    {
        return $thread->getId();
    }
}
