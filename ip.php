<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use SebastianBergmann\Timer\Timer;
use GeoIp2\Database\Reader;

$ip_worker = new Worker("http://0.0.0.0:2335");
$ip_worker->count = 1;
$ip_worker->name = 'ip';
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
    switch ($request->path()) {
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
            $info = sprintf(
                "%s\n%s / %s\nAS%s / %s\n\n%s",
                $ip, $city_reader->city($ip)->country->isoCode,
                $city_reader->city($ip)->country->name,
                $asn_reader->asn($ip)->autonomousSystemNumber,
                $asn_reader->asn($ip)->autonomousSystemOrganization,
                $request->header()['user-agent']
            );
    }
    $response = new Response(200, [
        'X-Powered-By' => 'Workerman ' . Worker::VERSION,
        'Connection' => 'close',
        'Content-Type' => 'text/plain; charset=UTF-8'
    ], $info . "\n");
    $response->header('Server-Timing', $timer->stop()->asMilliseconds());
    $connection->close($response);
};

Worker::runAll();
