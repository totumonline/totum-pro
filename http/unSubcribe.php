<?php

use totum\config\Conf;

require __DIR__ . '/../vendor/autoload.php';

$Config = new Conf();
if (is_callable([$Config, 'setHostSchema'])) {
    $Config->setHostSchema($_SERVER['HTTP_HOST']);
}

$Config->unsubscribe($_GET['d'] ?? '');