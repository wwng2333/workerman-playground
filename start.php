<?php

use Workerman\Worker;
define('GLOBAL_START', true);
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/GlobalDataServer.php';
#require_once __DIR__ . '/generate_204.php';
require_once __DIR__ . '/jsdelivr.php';
require_once __DIR__ . '/time.php';
#require_once __DIR__ . '/http-proxy.php';
require_once __DIR__ . '/ip.php';

Worker::runAll();
