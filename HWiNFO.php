<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';
function GetData(): array
{
    #$json = file_get_contents("http://100.65.167.54:60000/json.json");
    $json = file_get_contents("http://100.108.164.82:60000/json.json");
    $arr = json_decode($json, true);
    if ($arr)
        return $arr;
    else
        return [false];
}

function GetMicrotime(): int
{
    return (int) (microtime(true) * 1000);
}

$HWiNFO_worker = new Worker('http://100.77.158.125:2395');
$HWiNFO_worker->name = 'HWiNFO worker';
$HWiNFO_worker->onMessage = function (TcpConnection $connection, Request $request) {
    switch ($request->path()) {
        case '/metrics':
            $ret = '';
            $arr = GetData();
            var_dump($arr);
            if(isset($arr['hwinfo']['readings']))
            {
                $data_arr = $arr['hwinfo']['readings'];
            }
            else if(isset($arr['hwinfo']['sensors']))
            {
                $data_arr = $arr['hwinfo']['sensors'];
            }
            else
            {
                $ret = 'err: not found';
            }
            foreach ($arr['hwinfo']['readings'] as $r) {
                var_dump($r);
                $ret .= sprintf(
                    'Crazy_RemoteHWInfo{type="%s", label="%s", unit="%s"} %s %s' . "\n",
                    $r['sensorIndex'],
                    $r['labelOriginal'],
                    $r['unit'],
                    $r['value'],
                    GetMicrotime()
                );
            }
            $connection->close($ret);
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};

Worker::runAll();
