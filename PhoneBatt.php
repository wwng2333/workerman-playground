<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$batt = [
    "last_time" => 0,
    "batt" => 0,
    "voltage" => 0,
    "current" => 0,
    "temperature" => 0,
];

$http_worker = new Worker("http://0.0.0.0:2485");
$http_worker->count = 1;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $batt;
    if ($request->path() == '/metrics') {
        $txt = sprintf("crazy_phone_batt %s %s\n", $batt["batt"], $batt["last_time"]);
        $txt .= sprintf("crazy_phone_batt_v %s %s\n", $batt["voltage"], $batt["last_time"]);
        $txt .= sprintf("crazy_phone_batt_c %s %s\n", $batt["current"], $batt["last_time"]);
        $txt .= sprintf("crazy_phone_batt_t %s %s\n", $batt["temperature"], $batt["last_time"]);
        $connection->close($txt);
    }
    if ($request->get('batt')) {
        var_dump($request->get());
        $batt["batt"] = $request->get('batt');
        $batt["last_time"] = GetMicrotime();
        $batt["voltage"] = $request->get('v');
        $batt["current"] = $request->get('c');
        $batt["temperature"] = $request->get('t');
        $connection->close("recv ok!");
    }
    $connection->close(new Response(404, [], '404 not found'));
};

Worker::runAll();

function GetMicrotime(): int
{
    return (int) (microtime(true) * 1000);
}
