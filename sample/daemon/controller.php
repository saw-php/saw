<?php
/**
 ** Start main controller for php
 *
 * Created by PhpStorm.
 * User: Руслан
 * Date: 23.09.2015
 * Time: 0:22
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 503 Service Unavailable');
    echo sprintf('<p style="color:red">%s</p>', 'Saw worker must be run in cli mode.');
}

define('SAW_ENVIRONMENT', 'Controller');

$config = require __DIR__ . '/../common.php';
$controller = maestroprog\saw\service\Controller::create($config);

Esockets\debug\Log::log('work start');
$controller->work();
Esockets\debug\Log::log('work end');

exit(0);
