<?php

namespace Saw\Command;

use Saw\Heading\dispatcher\Command;

/**
 * Команда "Задача добавлена".
 * От воркера отправляется контроллеру для извещения.
 * От контроллера такая команда не отправляется (пока такое не предусмотрено).
 *
 * Результат выполнения команды - успешное/неуспешное добавление в известные команды.
 */
class TaskAdd extends Command
{
    const NAME = 'tadd';

    protected $needData = ['name'];

    public function getCommand(): string
    {
        return self::NAME;
    }
}
