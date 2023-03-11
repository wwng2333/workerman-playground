<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

$GlobalData = new GlobalData\Server('127.0.0.1', 2207);

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
