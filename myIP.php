<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

$myip_v6 = new Worker("http://[::]:2331");
$myip_v4 = new Worker("http://0.0.0.0:2332");
$myip_v6->name = 'WhatIsYourIP';
$myip_v4->name = 'WhatIsYourIP';
$myip_v6->onMessage = function (TcpConnection $connection, Request $request) {
    $ip = ($request->header('X-Real-IP')) ? 
        $request->header('X-Real-IP') : $connection->getRemoteIp();
    $response = new Response(200, [
        'X-Powered-By' => 'Workerman ' . Worker::VERSION,
        'Connection' => 'close',
        'Content-Type' => 'text/plain; charset=UTF-8'
    ], $ip);
    $connection->close($response);
};

$myip_v4->onMessage = function (TcpConnection $connection, Request $request) {
    $ip = ($request->header('X-Real-IP')) ? 
        $request->header('X-Real-IP') : $connection->getRemoteIp();
    $response = new Response(200, [
        'X-Powered-By' => 'Workerman ' . Worker::VERSION,
        'Connection' => 'close',
        'Content-Type' => 'text/plain; charset=UTF-8'
    ], $ip);
    $connection->close($response);
};

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
