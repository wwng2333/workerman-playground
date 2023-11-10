<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Timer;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

$nodes = [
    'ams.lg.v.ps',
    'lon.lg.v.ps',
    'fra.lg.v.ps',
    'nrt.lg.v.ps',
    'kix.lg.v.ps',
];

function Cron_Run()
{
    global $global, $nodes;
    $crazy = new CrazySpeedTest($nodes[0], 'ipv4');
    $global->speedtest_last_result = $crazy->return;
    $crazy = new CrazyMTRRouteTest($nodes[0], 4);
    $global->speedtest_last_result .= $crazy->return;
    for ($i = 1; $i < count($nodes); $i++) {
        $crazy = new CrazySpeedTest($nodes[$i], 'ipv4');
        $global->speedtest_last_result .= $crazy->return;
        $crazy = new CrazyMTRRouteTest($nodes[$i], 4);
        $global->speedtest_last_result .= $crazy->return;
    }
}

$dianfei_worker = new Worker('http://100.72.128.74:2401');
$dianfei_worker->name = 'speedtest';
$dianfei_worker->onWorkerStart = function (Worker $worker) {
    global $global;
    $global = new \GlobalData\Client('127.0.0.1:2207');
    Cron_Run();
    Timer::add(
        600,
        function () {
            Cron_Run();
        }
    );
    echo "Cron started.\n";
};
$dianfei_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $global;
    switch ($request->path()) {
        case '/metrics':
            $connection->close($global->speedtest_last_result);
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};
class CrazySpeedTest
{
    private $url = 'https://%s/100MB.test';
    public $result = array();
    public $return = '';
    public function __construct($node, $ip_version)
    {
        $this->url = sprintf($this->url, $node);
        $cmd = sprintf('/usr/local/bin/my_speedtest %s %s', $this->url, $ip_version);
        exec($cmd, $exec_result, $return);
        //var_dump($cmd, $exec_result);
        if ($return == 0) {
            $temp = json_decode($exec_result[0], true);
            //var_dump($temp);
            if (!$temp['speed'])
                $temp['speed'] = 0;
            $this->return .= sprintf(
                'CrazySpeedTest{node="%s", ip_version="%s"} %s' . "\n",
                $node,
                $ip_version,
                $temp['speed']
            );
            var_dump($this->return);
        }
    }
}

class CrazyMTRRouteTest
{
    private $cmd = 'mtr %s -%d -z -c5 -j';
    public $result = array();
    public $return = '';
    public function __construct($hostname, $ip_version)
    {
        $cmd = sprintf($this->cmd, $hostname, $ip_version);
        exec($cmd, $exec_result, $return);
        $json = implode('', $exec_result);
        $asn = [];
        //var_dump($cmd, $exec_result, $json);
        if ($return == 0) {
            $temp = json_decode($json, true);
            var_dump($temp);
            foreach ($temp['report']['hubs'] as $arrnow) {
                if ($arrnow['ASN'] != 'AS???') {
                    $asn[] = $arrnow['ASN'];
                }
            }
            $asn = array_unique($asn);
            $this->return = sprintf(
                'CrazyASNTest{target="%s", ip_version="%s"} %s' . "\n",
                $hostname,
                $ip_version,
                implode('->', $asn)
            );
            var_dump($this->return);
        }
    }
}

Worker::runAll();
