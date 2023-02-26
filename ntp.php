<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/NTPLite.php';

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\UdpConnection;

$GlobalData = new GlobalData\Server('127.0.0.1', 2207);

$is_limited = false;
$query_now = 0;

$ntp_limiter = new Worker();
$ntp_limiter->count = 1;
$ntp_limiter->name = 'NTP Limiter';
$ntp_limiter->onWorkerStart = function(Worker $task)
{
    $time_interval = 1;
    Timer::add($time_interval, function()
    {
        $global = new GlobalData\Client('127.0.0.1:2207');
        echo "Timer act, clear list.\n";
        $global->query_now = 0;
        $global->is_limited = false;
    });
};

$ntp_worker = new Worker('udp://127.0.0.1:123');
$ntp_worker->name = 'NTP Service';
$ntp_worker->onMessage = function($connection, $data){
    $global = new GlobalData\Client('127.0.0.1:2207');
    $global->query_now++;
    var_dump($global->query_now, $global->is_limited);
    if($global->query_now > 2) $global->is_limited = true;
    if(!$global->is_limited and $connection->getRemoteIp())
    {
        $NTP = new NTPLite();
        if (!$NTP->readMessage($data)) 
        {
            $hex = '';
            for ($i = 0; $i < strlen($data); $i++) {
                $hex .= sprintf('%02x', ord($data[$i]));
            }
            echo "Bad request, aborted\n$hex\n";
        } else {
            $NTP->dump();
            echo "\n", $NTP;
            $NTP->leapIndicator = 0;
            $NTP->mode = 4;
            $NTP->stratum = 6;
            $NTP->precision = -20;
            $NTP->rootDelay = 0;
            $NTP->rootDispersion = 0.0120;
            $NTP->referenceIdentifier = ip2long('202.38.64.7');
            $now = new DateTime(NULL);
            $NTP->referenceTimestamp = NTPLite::convertDateTimeToSntp($now);
            $NTP->originateTimestamp = $NTP->transmitTimestamp;
            $NTP->receiveTimestamp   = NTPLite::convertDateTimeToSntp($now);
            $NTP->transmitTimestamp  = NTPLite::convertDateTimeToSntp($now);

            $connection->close($NTP->writeMessage());
            unset($NTP);
        }
    }
};
Worker::runAll();
