<?php
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';
$udp_worker = new Worker('udp://0.0.0.0:514');
$udp_worker->name = 'rsyslogd';
$udp_worker->onMessage = function ($connection, $data) {
    list(, $data) = explode('>', $data);
    $file = sprintf('/var/log/PSG1218/%s.log', $connection->getRemoteIp());
    file_put_contents($file, $data, FILE_APPEND | LOCK_EX);
};
Worker::runAll();
