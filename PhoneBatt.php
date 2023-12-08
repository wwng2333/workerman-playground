<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$batt = 0;

$http_worker = new Worker("http://0.0.0.0:2485");
$http_worker->count = 1;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $batt;
    #var_dump($request);
    if ($request->get('batt')) {
        echo $request->get('batt') . "\n";
        $batt = $request->get('batt');
        $connection->close("recv ok!");
    }
    switch ($request->path()) {
        case '/metrics':
            $connection->close("crazy_phone_batt ".$batt);
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};

Worker::runAll();
