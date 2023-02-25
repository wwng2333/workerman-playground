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

$http_worker = new Worker("http://0.0.0.0:2335");
$http_worker->count = 1;
$http_worker->name = 'ip';
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $timer = new Timer;
    $timer->start();
    global $city_reader, $asn_reader;
    $city = $city_reader->city($connection->getRemoteIp());
    $asn = $asn_reader->asn($connection->getRemoteIp());
    $info = sprintf(
        "%s\n%s / %s\nAS%s / %s\n\n%s",
        $connection->getRemoteIp(),
        $city->country->isoCode,
        $city->country->name,
        $asn->autonomousSystemNumber,
        $asn->autonomousSystemOrganization,
        $request->header()['user-agent']
    );
    $response = new Response(200, [
        'Server' => 'Workerman '.Worker::VERSION,
        'X-Powered-by' => 'github.com/wwng2333',
        'Connection' => 'close',
        'Content-Type' => 'text/plain; charset=UTF-8'
    ], $info);
    $duration = $timer->stop();
    $response->header('Server-Timing', $duration->asMilliseconds());
    $connection->close($response);
};

Worker::runAll();
