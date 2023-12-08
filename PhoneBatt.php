<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$batt = 0;
$last_time = 0;

$http_worker = new Worker("http://0.0.0.0:2485");
$http_worker->count = 1;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $batt, $last_time;
    #var_dump($request);
    if ($request->get('batt')) {
        echo date('Y-m-d H:i:s').": ".$request->get('batt') . "\n";
        $batt = $request->get('batt');
        $last_time = GetMicrotime();
        $connection->close("recv ok!");
    }
    switch ($request->path()) {
        case '/metrics':
            $connection->close(sprintf("crazy_phone_batt %s %s", $batt, $last_time));
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};

Worker::runAll();

function GetMicrotime(): int
{
    return (int) (microtime(true) * 1000);
}
