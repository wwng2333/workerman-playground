<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$http_worker = new Worker("http://0.0.0.0:9101");
$http_worker->count = 1;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    switch ($request->path()) {
        case '/metrics':
            $hdd = new CrazyHDDExporter();
            $connection->close($hdd->return);
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};

class CrazyHDDExporter
{
    public $return = "# HELP crazy_hdd_temp HDD TEMP\n# TYPE crazy_hdd_temp gauge\n";
    private function exec_hddtemp()
    {
        $res = explode("\n", exec('hddtemp'));
        foreach ($res as $line) {
            list($dev, $model, $temp) = explode(": ", $line);
            $temp = filter_var($temp, FILTER_SANITIZE_NUMBER_INT);
            $this->return .= sprintf(
                'crazy_hdd_temp{dev_name="%s",dev_model="%s"} %s'."\n",
                $dev,
                $model,
                $temp
            );
        }
    }

    public function __construct()
    {
        $this->exec_hddtemp();
    }
}

Worker::runAll();
