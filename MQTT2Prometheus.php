<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$web_worker = new Worker('http://100.77.158.125:2396');
$web_worker->onWorkerStart = function () {
    global $global;
    $global->MQTT_recv = array();
    $global->MQTT_count = array();
    $global->MQTTLastResult = '';
    $mqtt = new Workerman\Mqtt\Client(
        'mqtt://crazy.ala.cn-hangzhou.emqxsl.cn:8883',
        [
            'username' => 'admin',
            'password' => 'crazy',
            'ssl' => true,
            'debug' => true,
        ]
    );
    $mqtt->onConnect = function ($mqtt) {
        $mqtt->subscribe('Crazy/+/+');
    };
    $mqtt->onMessage = function ($topic, $content) {
        global $global;
        $temp = explode('/', $topic);
        if (count($temp) > 2) {
            $id = $topic;
            $global->MQTT_recv[$id]['content'] = $content;
            $global->MQTT_recv[$id]['time'] = GetMicrotime();
            $global->MQTT_recv[$id]['name'] = $temp[2];
            $global->MQTT_count[$topic] = $global->MQTT_count[$topic] + 1;
            $global->MQTTLastResult = '';
        } else {
            $id = $topic;
            $global->MQTT_recv[$id]['content'] = $content;
            $global->MQTT_recv[$id]['time'] = GetMicrotime();
            $global->MQTT_count[$topic] = $global->MQTT_count[$topic] + 1;
            $global->MQTTLastResult = '';
        }
        foreach ($global->MQTT_recv as $key => $rnow) {
            if (isset($rnow['name'])) {
                $tmp = explode('/', $key);
                $global->MQTTLastResult .= sprintf(
                    '%s{name="%s"} %s %s',
                    $tmp[1],
                    $rnow['name'],
                    $rnow['content'],
                    $rnow['time'],
                ) . "\n";
            } else {
                $global->MQTTLastResult .= sprintf(
                    '%s %s %s',
                    $key,
                    $rnow['content'],
                    $rnow['time'],
                ) . "\n";
            }

        }
        foreach ($global->MQTT_count as $key => $val) {
            $global->MQTTLastResult .= sprintf(
                'received_messages{topic="%s"} %s',
                $key,
                $val,
            ) . "\n";
        }
        //var_dump($global->MQTTLastResult);
    };
    $mqtt->connect();
};

$web_worker->name = 'MQTT Client';
$web_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $global;
    switch ($request->path()) {
        case '/metrics':
            $connection->close($global->MQTTLastResult);
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
