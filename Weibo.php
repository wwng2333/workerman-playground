<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Timer;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use WpOrg\Requests\Requests;

function Cron_Run()
{
    global $global;
    $crazy = new CrazyWeibo('1393786362');
    $global->wb_last_result = $crazy->return;
    sleep(1);
    $crazy = new CrazyWeibo('7426493874');
    $global->wb_last_result .= $crazy->return;
    sleep(1);
    $crazy = new CrazyWeibo('1239246050');
    $global->wb_last_result .= $crazy->return;
}

$dianfei_worker = new Worker('http://100.77.158.125:2397');
$dianfei_worker->name = 'Weibo';
$dianfei_worker->onWorkerStart = function (Worker $worker) {
    Cron_Run();
    Timer::add(
        300,
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
            $connection->close($global->wb_last_result);
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};
class CrazyWeibo
{
    private $url = 'https://m.weibo.cn/api/container/getIndex?type=uid&value=%s';
    public $result = array();
    public $return = '';
    public function __construct($uid)
    {
        global $global;
        $this->url = sprintf($this->url, $uid);
        $response = Requests::get($this->url);
        if ($response->success) {
            $temp = json_decode($response->body, true);
            //var_dump($temp);
            $this->return .= sprintf(
                'CrazyWeiboFans{name="%s"} %s' . "\n",
                $temp['data']['userInfo']['screen_name'],
                str_replace('万', '', $temp['data']['userInfo']['followers_count'])
            );
            var_dump($this->return);
        }
    }
}

Worker::runAll();
