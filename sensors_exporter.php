<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$http_worker = new Worker("http://0.0.0.0:9102");
$http_worker->count = 1;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    switch ($request->path()) {
        case '/metrics':
            $s = new CrazySensorsExporter();
            $connection->close($s->return);
            break;
        default:
            $connection->close(new Response(404, [], '404 not found'));
    }
};

class CrazySensorsExporter
{
    public $return = '';
    private $_template = "# HELP %s TEMP\n# TYPE %s gauge\n";
    private $sensors = array();
    private function Output()
    {
        foreach ($this->sensors as $s) {
            $label_now = 'crazy_sensor_' . $s['name'];
            $this->return .= sprintf($this->_template, $label_now, $label_now);
            foreach ($s['temp'] as $s1) {
                $this->return .= sprintf(
                    '%s{name="%s"} %s' . "\n",
                    $label_now,
                    $s1['name'],
                    $s1['temp']
                );
            }
        }
    }

    private function ReadTemp()
    {
        $root = '/sys/class/hwmon/';
        $list = scandir($root);
        unset($list[0], $list[1]);
        foreach ($list as $hw) {
            $path = $root . $hw;
            $this->sensors[$hw]['name'] = $this->TrimRead($path . '/name');
            $this->sensors[$hw]['temp'] = [];
            $i = 1;
            while (true) {
                $temp_now = sprintf($path . '/temp%s_input', $i);
                $label_now = sprintf($path . '/temp%s_label', $i);
                if (is_readable($label_now)) {
                    $this->sensors[$hw]['temp'][$i] = [
                        'name' => $this->TrimRead($label_now),
                        'temp' => $this->TempRead($temp_now),
                    ];
                    $i++;
                } else if (is_readable($temp_now)) {
                    $this->sensors[$hw]['temp'][$i] = [
                        'name' => 'temp' . $i,
                        'temp' => $this->TempRead($temp_now),
                    ];
                    $i++;
                } else {
                    break;
                }
            }
        }
    }
    private function TrimRead($file)
    {
        return trim(file_get_contents($file));
    }

    private function TempRead($file)
    {
        return (float) trim(file_get_contents($file)) / 1000;
    }

    public function __construct()
    {
        $this->ReadTemp();
        $this->Output();
    }
}

Worker::runAll();
