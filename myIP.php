<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

$context = array(
    'ssl' => array(
        'local_cert' => '/path_to/your.cer',
        'local_pk' => '/path_to/your.key',
        'verify_peer' => false,
        'allow_self_signed' => false,
    )
);

$myip = new Worker("http://[::]:443", $context);
$myip->transport = 'ssl';
$myip->name = 'WhatIsYourIP';
$myip->onMessage = function (TcpConnection $connection, Request $request) {
    $ip = ($request->header('X-Real-IP')) ? 
        $request->header('X-Real-IP') : $connection->getRemoteIp();
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        list(, $ip) = explode('ffff:', $ip);
        $ip = rtrim($ip, ']');
    }
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
