<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

$WhatIsYourIP = new Worker("http://[::]:2335");
$WhatIsYourIP->count = 1;
$WhatIsYourIP->name = 'WhatIsYourIP';
$WhatIsYourIP->onMessage = function (TcpConnection $connection, Request $request) {
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
