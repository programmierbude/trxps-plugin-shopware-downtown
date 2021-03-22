<?php

include_once __DIR__ . '/vendor/autoload.php';

$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4("Etbag\\TrxpsPayments\\", __DIR__ . '/src', true);
$classLoader->addPsr4("Etbag\\TrxpsPayments\\Tests\\", __DIR__ . '/tests', true);
$classLoader->register();