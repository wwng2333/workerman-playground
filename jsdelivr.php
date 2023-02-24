<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use SebastianBergmann\Timer\Timer;
use WpOrg\Requests\Requests;

require_once __DIR__ . '/vendor/autoload.php';

$http_worker = new Worker("http://0.0.0.0:2334");
$http_worker->count = 2;
$http_worker->name = 'jsdelivr';
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $memcached = new Memcached();
    $memcached->addServer('localhost', 11211);
    $timer = new Timer;
    $timer->start();
    if (($request->path() == '/npm' or $request->path() == '/gh') and $request->get('family')) {
        $key_name = md5($request->uri());
        if ($res = $memcached->get($key_name)) {
            $is_cached = 'cache; desc="Cache Read"';
            echo $request->uri() . " cache hit\n";
        } else {
            $is_cached = 'missedCache';
            echo $request->uri() . " new query\n";
            $list = stristr($request->get('family'), '|') ? explode('|', $request->get('family')) : [$request->get('family')];
            $res = '';
            foreach ($list as $addr) {
                $url = sprintf('https://fastly.jsdelivr.net%s/%s', $request->path(), $addr);
                $res .= Requests::get($url) . "\n";
            }
            $memcached->set($key_name, $res, 86400);
        }
        $memcached->quit();
        $response = new Response(200, [
            'Connection' => 'close',
            'Cache-control' => 'max-age=86400',
            'Content-Encoding' => 'gzip',
            'Access-Control-Allow-Origin' => '*'
        ], gzencode($res));
        $response->header('Content-Type', 'text/javascript;charset=UTF-8');
    } else {
        echo $request->uri() . " err\n";
        $response = new Response(403, ['Connection' => 'close'], '<html><head><title>403 Forbidden</title></head><body><center><h1>403 Forbidden</h1></center><hr><center>workerman</center></body></html>');
    }
    $duration = $timer->stop();
    $response->header('Server-Timing', $is_cached . '; dur=' . $duration->asMilliseconds());
    $connection->close($response);
};

Worker::runAll();
