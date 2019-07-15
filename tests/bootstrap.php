<?php

$autoloaderPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    echo "Composer autoloader not found: $autoloaderPath" . PHP_EOL;
    echo "Please issue 'composer install' and try again." . PHP_EOL;
    exit(1);
}

$autoloader = require $autoloaderPath;
$autoloader->addPsr4('Tests\\', __DIR__);
