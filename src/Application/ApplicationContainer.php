<?php

namespace Saw\Application;

use Saw\Application\ApplicationInterface as App;

/**
 * Контейнер приложений.
 * Нужен для управления приложениями контроллером и воркерами.
 */
final class ApplicationContainer
{
    /**
     * @var App[]
     */
    private $apps = [];
    private $currentApp;

    public function add(App $application)
    {
        if (isset($this->apps[$application->getId()])) {
            throw new \RuntimeException('The application has already added.');
        }
        $this->apps[$application->getId()] = $application;
    }

    public function get(string $id): App
    {
        if (!isset($this->apps[$id])) {
            throw new \UnexpectedValueException('Unexpected application id not found: ' . $id);
        }
        return $this->apps[$id];
    }

    public function getCurrentApp(): App
    {
        return $this->currentApp;
    }

    public function switchTo(App $application): App
    {
        $this->currentApp = $application;
        return $application;
    }

    public function switchReset()
    {
        $this->currentApp = null;
    }

    /**
     * Запускает все приложения в контейнере.
     */
    public function run()
    {
        foreach ($this->apps as $app) {
            $this->switchTo($app)->run();
        }
    }
}