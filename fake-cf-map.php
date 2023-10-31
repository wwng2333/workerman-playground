<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use SebastianBergmann\Timer\Timer;
use GeoIp2\Database\Reader;

$ip_worker = new Worker("http://0.0.0.0:2339");
$ip_worker->count = 1;
$ip_worker->name = 'fake-cf-map';
$ip_worker->onWorkerStart = function (Worker $worker) {
    global $city_reader, $asn_reader;
    $city_reader = new Reader('/usr/local/share/GeoIP/GeoLite2-City.mmdb');
    $asn_reader = new Reader('/usr/local/share/GeoIP/GeoLite2-ASN.mmdb');
};
$ip_worker->onMessage = function (TcpConnection $connection, Request $request) {
    global $city_reader, $asn_reader;
    $timer = new Timer;
    $timer->start();
    $ip = ($request->header('X-Real-IP')) ?
        $request->header('X-Real-IP') : $connection->getRemoteIp();
    //if($request->path() == '/favicon.ico') $connection->close(new Response(204));
    if ($request->header('x-vercel-id')) {
        list($cdn, ) = explode('::', $request->header('x-vercel-id'));
    } else if ($request->header('CF-RAY')) {
        list(, $cdn) = explode('-', $request->header('CF-RAY'));
        $ip = $request->header('CF-Connecting-IP');
    }
    switch ($request->path()) {
        case '/ip':
            $info = $ip;
            break;
        case '/asn':
            $info = $asn_reader->asn($ip)->autonomousSystemNumber;
            break;
        case '/country':
            $info = $city_reader->city($ip)->country->isoCode;
            break;
        case '/ua':
            $info = $request->header()['user-agent'];
            break;
        default:
            $arr = [
                'ip' => '127.0.0.1',
                'ip_version' => 1,
                'protocol' => 'udp',
                'dnssec' => true,
                'edns' => 0,
                'client_subnet' => -1,
                'qname_minimization' => false,
                'isp' => [
                    'asn' => $asn_reader->asn($ip)->autonomousSystemNumber,
                    'name' => $asn_reader->asn($ip)->autonomousSystemOrganization.' with Crazy DNS'
                ]
            ];
            $info = json_encode($arr);
    }
    $response = new Response(200, [
        'X-Powered-By' => 'Workerman ' . Worker::VERSION,
        'Connection' => 'close',
        'Content-Security-Policy' => "img-src 'none'",
        'Content-Type' => 'text/plain; charset=UTF-8',
    ], $info . "\n");
    $response->header('Server-Timing', $timer->stop()->asMilliseconds());
    $connection->close($response);
};

Worker::runAll();
