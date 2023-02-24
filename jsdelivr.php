<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use SebastianBergmann\Timer\Timer;
use WpOrg\Requests\Requests;

require_once __DIR__ . '/vendor/autoload.php';
define('UPSTREAM', ['/npm', '/gh']);
$http_worker = new Worker("http://0.0.0.0:2334");
$http_worker->count = 2;
$http_worker->name = 'jsdelivr';
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $timer = new Timer;
    $timer->start();
    $memcached = new Memcached();
    $memcached->addServer('localhost', 11211);
    $is_cached = 'missedCache';
    if (in_array($request->path(), UPSTREAM) and $request->get('family')) {
        $key_name = md5($request->uri());
        if ($res = $memcached->get($key_name)) {
            $is_cached = 'cache; desc="Cache Read"';
        } else {
            $list = stristr($request->get('family'), '|') ? explode('|', $request->get('family')) : [$request->get('family')];
            $res = '';
            foreach ($list as $addr) {
                $url = sprintf('https://fastly.jsdelivr.net%s/%s', $request->path(), $addr);
                $response = Requests::get($url);
                $res .= $response->body . "\n";
            }
            $memcached->set($key_name, $res, 86400);
        }
        $memcached->quit();
        if (stristr($request->header('Accept-Encoding'), 'gzip'))
            $res = gzencode($res);
        $response = new Response(200, [
            'Connection' => 'close',
            'Cache-control' => 'max-age=86400',
            'Access-Control-Allow-Origin' => '*'
        ], $res);
        if (stristr($request->header('Accept-Encoding'), 'gzip'))
            $response->header('Content-Encoding', 'gzip');
        $response->header('Content-Type', 'text/javascript;charset=UTF-8');
    } else {
        $response = new Response(403, ['Connection' => 'close'], '<html><head><title>403 Forbidden</title></head><body><center><h1>403 Forbidden</h1></center><hr><center>workerman</center></body></html>');
    }
    $duration = $timer->stop();
    $response->header('Server-Timing', $is_cached . '; dur=' . $duration->asMilliseconds());
    $connection->close($response);
};

Worker::runAll();
