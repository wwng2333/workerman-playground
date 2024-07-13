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
            try {
                $info = $asn_reader->asn($ip)->autonomousSystemNumber;
            } catch (GeoIp2\Exception\AddressNotFoundException $e) {
                echo "The address is not in the database.\n";
            }
            break;
        case '/country':
            try {
                $info = $city_reader->city($ip)->country->isoCode;
            } catch (GeoIp2\Exception\AddressNotFoundException $e) {
                echo "The address is not in the database.\n";
            }
            break;
        case '/ua':
            $info = $request->header()['user-agent'];
            break;
        default:
            try {
                $isoCode = $city_reader->city($ip)->country->isoCode;
                $city_name = $city_reader->city($ip)->country->name;
                $asn_num = $asn_reader->asn($ip)->autonomousSystemNumber;
                $asn_org = $asn_reader->asn($ip)->autonomousSystemOrganization;
            } catch (GeoIp2\Exception\AddressNotFoundException $e) {
                echo "The address is not in the database.\n";
            }
            $info = sprintf(
                "%s\n%s / %s\nAS%s / %s\n\n%s\n%s",
                $ip,
                $isoCode,
                $city_name,
                $asn_num,
                $asn_org,
                $request->header()['user-agent'],
                $cdn
            );
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
