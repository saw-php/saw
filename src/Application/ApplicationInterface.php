<?php

namespace Saw\Application;

use Saw\Dto\Result;
use Saw\Thread\MultiThreadingInterface;

interface ApplicationInterface
{
    /**
     * Возвращает уникальный идентификатор приложения.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Выполняется, после получения нового запроса.
     * Метод должен выполнять инициализацию приложения
     * - загружать все необходимые данные
     * для дальнейшей обработки нового запроса.
     *
     * Метод не выполняется на воркерах.
     *
     * @return mixed
     */
    public function init();

    /**
     * Запускает работу приложения.
     *
     * @return mixed
     */
    public function run();

    /**
     * Вызывается после завершения работы всех потоков.
     *
     * Собирает результаты выполнения потоков.
     * На основе полученных результатов конструирует общий
     * результат выполнения приложения, и возвращает его.
     */
    public function end();
}
