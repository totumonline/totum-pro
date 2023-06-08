<?php

use totum\config\Conf;

require __DIR__ . '/../vendor/autoload.php';

$Config = new Conf();
if (is_callable([$Config, 'setHostSchema'])) {
    $Config->setHostSchema($_SERVER['HTTP_HOST']);
}

if ($check = $Config->unsubscribe($_GET['d'] ?? '', true)) {
    if ($check === 'not in base') {
        $Config->getLangObj()->translate('list-ubsubscribe-Blocked-from-sending');
    } else {
        if (!($_GET['ok'] ?? false)) {
            $unsubscribeText = $Config->getLangObj()->translate('list-ubsubscribe-link-text');
            echo <<<html
<html><body><form><input type="hidden" name="d" value="{$_GET['d']}"><button name="ok" value="1">{$unsubscribeText}</button></form></body></html>
html;
        } elseif ($Config->unsubscribe($_GET['d'] ?? '', false)) {
            echo $Config->getLangObj()->translate('list-ubsubscribe-done');
        }

    }
} else {
    echo $Config->getLangObj()->translate('list-ubsubscribe-wrong-link');
}