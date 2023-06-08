<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$http_worker = new Worker("http://[::]:2399");
$http_worker->count = 1;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $response = new Response(200, [
        'Content-Security-Policy' => "img-src 'none'",
        'X-Powered-by' => 'workerman',
        'Connection' => 'close',
    ], "");
    $url = ltrim($request->uri(), '/');
    echo "recv url:$url, ";
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        echo "url is ok, calling you-get;\n";
        exec("you-get --json $url", $output, $errno);
        echo "exec you-get result: $errno;\n";
        $output = implode("\n", $output);
        //var_dump($output);
        if ($errno == 0) {
            $res = [];
            $arr = json_decode($output, true);
            foreach ($arr["streams"] as $now) {
                //var_dump($now["src"]);
                $res = array_merge($res, $now["src"]);
            }
            //var_dump($res);
            foreach($res as $r)
            {
                //var_dump($r);
                if(strstr($r, 'akamai')) {
                    $response->withStatus(301);
                    $response->header('Location', $r);
                    echo "output: 301 to $r\n";
                    break;
                }
            }
        }
    } else {
        echo "bad url\n";
        $response->withStatus(403);
    }
    $connection->close($response);
};

Worker::runAll();
