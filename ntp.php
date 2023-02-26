<?php
require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\UdpConnection;

$udp_worker = new Worker('udp://127.0.0.1:123');
$udp_worker->onMessage = function($connection, $data){
    var_dump($connection->getRemoteIp());
    $connection->send('get');
};
Worker::runAll();
