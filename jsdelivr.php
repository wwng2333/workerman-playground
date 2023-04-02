<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use SebastianBergmann\Timer\Timer;
use WpOrg\Requests\Requests;

require_once __DIR__ . '/vendor/autoload.php';

define('UPSTREAM', ['/npm', '/gh', '/wp']);
define('GITHUB_HOSTNAME', [
    'raw.githubusercontent.com',
    'github.com',
    'gist.github.com',
    'gist.githubusercontent.com'
]);
define('_403_CODE', '<html><head><title>403 Forbidden</title></head><body><center><h1>403 Forbidden</h1></center><hr><center>workerman</center></body></html>');
$jsdelivr_worker = new Worker("http://0.0.0.0:2334");
$jsdelivr_worker->count = 2;
$jsdelivr_worker->name = 'jsdelivr';
$jsdelivr_worker->onWorkerStart = function (Worker $worker) {
    global $global;
    $global = new GlobalData\Client('127.0.0.1:2207');
};
$jsdelivr_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $timer = new Timer;
    $timer->start();
    $response = new Response(200, [
        'Content-Security-Policy' => "img-src 'none'",
        'X-Powered-by' => 'workerman',
        'Connection' => 'close'
    ]);
    global $global;
    $url = substr($request->uri(), 1);
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $parse_url = parse_url($url);
        $is_cached = 'no-cache; ';
        if (in_array($parse_url['host'], GITHUB_HOSTNAME)) {
            $temp = Requests::get($url);
            $response->withStatus($temp->status_code);
            $response->header('Content-Type', $temp->headers['Content-Type']);
            $response->withBody($temp->body);
        } else {
            $response->withStatus(403);
            $response->withBody(_403_CODE);
        }
    } else {
        switch ($request->path()) {
            case '/myipv4addr':
                $ip = ($request->header('X-Real-IP')) ?
                    $request->header('X-Real-IP') : $connection->getRemoteIp();
                $response->withHeaders(['Content-Type' => 'text/javascript']);
                $response->withBody(sprintf('var ipv4addr= document.getElementById("ipv4addr"); ipv4addr.innerHTML=\'Your IP: %s\'', $ip));
                break;
            case '/generate_204':
                $response->withStatus(204);
                break;
            default:
                $is_cached = 'missedCache; ';
                if (in_array($request->path(), UPSTREAM) and $request->get('family')) {
                    $key_name = md5($request->uri());
                    if ($res = $global->$key_name) {
                        $is_cached = 'cache; desc="Cache Read"; ';
                    } else {
                        $list = stristr($request->get('family'), '|') ? explode('|', $request->get('family')) : [$request->get('family')];
                        $res = '';
                        foreach ($list as $addr) {
                            $temp = Requests::get(sprintf('https://fastly.jsdelivr.net%s/%s', $request->path(), $addr));
                            $res .= $temp->body . "\n";
                        }
                        $global->$key_name = $res;
                    }
                    $response->withHeaders([
                        'Cache-control' => 'max-age=86400',
                        'Access-Control-Allow-Origin' => '*',
                        'Content-Type' => 'text/plain; charset=UTF-8'
                    ]);
                    $response->withBody($res);
                } else {
                    $response->withStatus(403);
                    $response->withBody(_403_CODE);
                }
                break;
        }
    }
    if (stristr($request->header('Accept-Encoding'), 'gzip')) {
        $response->withBody(gzencode($response->rawBody()));
        $response->header('Content-Encoding', 'gzip');
    }
    $duration = $timer->stop();
    $response->header('Server-Timing', $is_cached . 'dur=' . $duration->asMilliseconds());
    $connection->close($response);
};

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
