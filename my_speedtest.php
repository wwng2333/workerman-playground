<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Timer;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

function Cron_Run()
{
    global $global;
    $crazy = new CrazySpeedTest('ams', 'ipv4');
    $global->speedtest_last_result = $crazy->return;
    $crazy = new CrazySpeedTest('lon', 'ipv4');
    $global->speedtest_last_result .= $crazy->return;
    $crazy = new CrazySpeedTest('nrt', 'ipv4');
    $global->speedtest_last_result .= $crazy->return;
    $crazy = new CrazySpeedTest('kix', 'ipv4');
    $global->speedtest_last_result .= $crazy->return;
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
    private $url = 'https://%s.lg.v.ps/100MB.test';
    public $result = array();
    public $return = '';
    public function __construct($node, $ip_version)
    {
        $this->url = sprintf($this->url, $node);
        $cmd = sprintf('/usr/local/bin/my_speedtest %s %s', $this->url, $ip_version);
        exec($cmd, $exec_result, $return);
        var_dump($cmd, $exec_result);
        if ($return == 0) {
            $temp = json_decode($exec_result[0], true);
            //var_dump($temp);
	    if(!$temp['speed']) $temp['speed'] = 0;
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

Worker::runAll();
