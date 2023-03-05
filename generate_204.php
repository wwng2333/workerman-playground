<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$generate_204_response = new Response(204, [
    'X-Powered-by' => 'github.com/wwng2333/generate_204',
    'Connection' => 'close'
]);

$generate_204_worker = new Worker("http://0.0.0.0:2333");
$generate_204_worker->count = 1;
$generate_204_worker->name = 'generate_204';
$generate_204_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $generate_204_response;
    $connection->close($generate_204_response);
};

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
