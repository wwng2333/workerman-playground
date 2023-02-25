<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use SebastianBergmann\Timer\Timer;
use GeoIp2\Database\Reader;

$city_reader = new Reader('/usr/local/share/GeoIP/GeoLite2-City.mmdb');
$asn_reader = new Reader('/usr/local/share/GeoIP/GeoLite2-ASN.mmdb');

$ip_worker = new Worker("http://0.0.0.0:2335");
$ip_worker->count = 1;
$ip_worker->name = 'ip';

$ip_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $ip = ($request->header('X-Real-IP')) ? 
        $request->header('X-Real-IP') : $connection->getRemoteIp();
    $timer = new Timer;
    $timer->start();
    global $city_reader, $asn_reader;
    $city = $city_reader->city($ip);
    $asn = $asn_reader->asn($ip);
    $info = sprintf(
        "%s\n%s / %s\nAS%s / %s\n\n%s\n",
        $ip,
        $city->country->isoCode,
        $city->country->name,
        $asn->autonomousSystemNumber,
        $asn->autonomousSystemOrganization,
        $request->header()['user-agent']
    );
    $response = new Response(200, [
        'X-Powered-By' => 'Workerman ' . Worker::VERSION,
        'Connection' => 'close',
        'Content-Type' => 'text/plain; charset=UTF-8'
    ], $info);
    $duration = $timer->stop();
    $response->header('Server-Timing', $duration->asMilliseconds());
    $connection->close($response);
};

Worker::runAll();
