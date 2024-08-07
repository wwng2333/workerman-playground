<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';
$http_worker = new Worker("http://127.0.0.1:2349");
$http_worker->count = 2;
$http_worker->name = 'Crazy Clock';
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
    $GLOBALS['time_start'] = microtime(true);
    switch ($request->path()) {
        case '/local.min.css':
            $text = '/*! time.curby.net - local.css */.nobr,dt{white-space:nowrap}body{margin:1em .6em;font:100%/1.25em Helvetica,sans-serif}.semistrong,.strong,dt{font-weight:700}.container{max-width:32em;margin-left:auto;margin-right:auto}.boxtop{margin:0;padding:9pt 14pt 5pt}h1{font-size:1.7em}h2{font-size:1.3em}.box{margin:0 0 1em;padding:5pt 14pt 9pt}dl,img,ol,p,table,ul{margin:.7em 0}.box dl:first-child,.box p.first-child,.box p:first-child{margin:0 0 .7em}.box dl:last-child,.box ol:last-child,.box p:last-child{margin:.7em 0 0}.box dl:only-child,.box p:only-child,.box table:only-child,.box ul:only-child{margin:0}a img{border:0}div.image-border{float:right;margin:4pt 0 0 7pt;padding:5px 5px 0}.callout img{margin:0;padding:5px 5px 0}.tagline a{color:inherit}#servertime{text-align:center;font-family:Consolas,\'Liberation Mono\',Menlo,Courier,monospace;font-size:3em;margin:4pt 0 0;padding:.5em 0 .4em}#servertime span{margin-left:-.3em;font-variant:small-caps;font-weight:700}#syncnote{margin-top:1em}dt{width:90px;float:left;clear:left;overflow:hidden}dd{margin-left:90px}dd,li{margin-bottom:.5em}dd:last-child,li:last-child{margin-bottom:0}ol,ul{padding-left:1.5em}ul.taglines{list-style-type:none}td,th{vertical-align:top}th{padding-right:10px;text-align:right}.copyright{text-align:center;font-size:.8em}.code,.codebubble,code{font-family:\'Input Mono\',Consolas,\'Liberation Mono\',Menlo,Courier,monospace;font-size:.85em}.codebubble{padding:.2em .3em 0;margin-top:-2px;background-color:#ccc}body{background:#ccc;color:#333}.boxtop{background:#eee;border-radius:10px 25px 0 0}.box{background:#ddd;border-bottom:5px solid #bbb;border-bottom-right-radius:10px 15px;border-bottom-left-radius:10px 15px}.copyright,.gray,.semistrong,dt,th{color:#888}.gray .semistrong,.semistrong .gray{color:#bbb}.green{color:#090}.yellow{color:#880}.red{color:#900}.tagline{color:#777}.tagline .gray{color:#aaa}a{color:#349}a:visited{color:#555}a:hover{color:#01c}h1 a,h1 a:hover,h1 a:visited{color:#333;text-decoration:none}h1 a:hover:after{color:#888;content:"\0000A0\0000A0(\0021a9\0000A0home)";font-size:.5em;line-height:.5em;position:relative;top:-4px}.tagline a,.tagline a:hover,.tagline a:visited{color:inherit;text-decoration:none}h2.callout{background:#fdd;color:#933}.box.callout{background:#e5c5c5;color:#700}.callout img{background:#fff}#servertime{background:#ccc;color:#888}.box,.boxtop{box-shadow:4px 3px 4px 1px #aaa}.boxtop.callout{border-radius:25px 25px 0 0}.box.callout{border-bottom:10px solid #c7abab;border-bottom-right-radius:25px 35px;border-bottom-left-radius:25px 35px}.box.callout,.boxtop.callout{box-shadow:8px 6px 6px 2px #aaa}div.image-border{background:linear-gradient(155deg,#eee,#ccc);border-radius:15px}.callout img{border-radius:10px}#servertime{border-radius:5px;-webkit-touch-callout:none;-webkit-user-select:none;-khtml-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}.codebubble{border-radius:3px}@media (max-width:25em){dt{width:auto;float:none;clear:none;white-space:normal;overflow:visible;color:#777}dd{margin-left:0;margin-bottom:.7em}#servertime{font-size:2.3em}.hidephone{display:none}}';
            $response = new Response(200, [
                'Content-Type' => 'text/css'
            ], $text);
            $connection->send($response);
            break;
        case '/local.js':
            $text = 'function pad(num){if(num<10){return"0"+num}return""+num}function updateHomeClock(){var client=new Date();var hours=ServerDate.getHours();period="<span> am</span>";if(hours>=12){hours-=12;period="<span> pm</span>"}if(hours==0){hours=12}document.getElementById("servertime").innerHTML=hours+":"+pad(ServerDate.getMinutes())+":"+pad(ServerDate.getSeconds())+period}function updateSyncNote(){var client=new Date();var offset=Math.abs(ServerDate-client);var syncNoteElem=document.getElementById("syncnote");console.log("Updating syncnote at "+client.toString()+", offset is "+offset);if(offset<1000){syncNoteElem.innerHTML="Congratulations, your clock is accurate!";syncNoteElem.className="green"}else if(offset<=60*1000){syncNoteElem.innerHTML="Your clock is reasonably accurate, but you may still see problems in timing-critical applications.";syncNoteElem.className="yellow"}else{syncNoteElem.innerHTML="Your clock does <em>not</em> appear to be synchronized.  You may see problems in timing-sensitive applications.";syncNoteElem.className="red"}}function updateClocks(){var client=new Date();document.getElementById("server").innerHTML="<span class=\"hidephone\">"+ServerDate.toDateString()+"</span> "+pad(ServerDate.getHours())+":"+pad(ServerDate.getMinutes())+":"+pad(ServerDate.getSeconds())+"."+pad(ServerDate.getMilliseconds()/10|0);document.getElementById("client").innerHTML="<span class=\"hidephone\">"+client.toDateString()+"</span> "+pad(client.getHours())+":"+pad(client.getMinutes())+":"+pad(client.getSeconds())+"."+pad(client.getMilliseconds()/10|0)}function updateMetaData(firstRun){var client=new Date();var waitText="";if(firstRun==true){waitText=" (refining &hellip; please wait)"}var offset=ServerDate-client;console.log("Updating metadata at "+client.toString()+", offset is "+offset);document.getElementById("timezone").innerHTML=client.toTimeString().replace(/^[\d:]+ /,\'\').replace(/\(([A-Z]{3})\)/,\'(<a href="https://duckduckgo.com/?q=$1%20time%20zone">$1</a>)\');if(offset<10000){document.getElementById("offset").innerHTML=offset+" ms"+waitText}else{document.getElementById("offset").innerHTML=(offset/1000)+" s"+waitText}document.getElementById("delay").innerHTML=ServerDate.getPrecision()+" ms"}function resetAmortization(){ServerDate.amortizationThreshold=2000;ServerDate.amortizationRate=50;console.log("Set clock amortization threshold/rate to "+ServerDate.amortizationThreshold+"/"+ServerDate.amortizationRate)}';
            $response = new Response(200, [
                'Content-Type' => 'text/javascript'
            ], $text);
            $connection->send($response);
            break;
        case '/ServerDate.php':
            $time = new DateTime(NULL);
            if ($request->get('time') == 'now') {
                $response = new Response(200, [
                    'Content-Type' => 'text/json'
                ], $time->format('Uv'));
                $connection->send($response);
            } else {
                $text = '\'use strict\';var ServerDate=(function(serverNow){var scriptLoadTime=Date.now(),scripts=document.getElementsByTagName("script"),URL=scripts[scripts.length-1].src,synchronizationIntervalDelay,synchronizationInterval,precision,offset,target=null,synchronizing=false;function ServerDate(){return this?ServerDate:ServerDate.toString()}ServerDate.parse=Date.parse;ServerDate.UTC=Date.UTC;ServerDate.now=function(){return Date.now()+offset};["toString","toDateString","toTimeString","toLocaleString","toLocaleDateString","toLocaleTimeString","valueOf","getTime","getFullYear","getUTCFullYear","getMonth","getUTCMonth","getDate","getUTCDate","getDay","getUTCDay","getHours","getUTCHours","getMinutes","getUTCMinutes","getSeconds","getUTCSeconds","getMilliseconds","getUTCMilliseconds","getTimezoneOffset","toUTCString","toISOString","toJSON"].forEach(function(method){ServerDate[method]=function(){return new Date(ServerDate.now())[method]()}});ServerDate.getPrecision=function(){if(typeof target.precision!="undefined")return target.precision+Math.abs(target-offset)};ServerDate.amortizationRate=25;ServerDate.amortizationThreshold=2000;Object.defineProperty(ServerDate,"synchronizationIntervalDelay",{get:function(){return synchronizationIntervalDelay},set:function(value){synchronizationIntervalDelay=value;clearInterval(synchronizationInterval);synchronizationInterval=setInterval(synchronize,ServerDate.synchronizationIntervalDelay);log("Set synchronizationIntervalDelay to "+value+" ms.")}});ServerDate.synchronizationIntervalDelay=60*60*1000;function Offset(value,precision){this.value=value;this.precision=precision}Offset.prototype.valueOf=function(){return this.value};Offset.prototype.toString=function(){return this.value+(typeof this.precision!="undefined"?" +/- "+this.precision:"")+" ms"};function setTarget(newTarget){var message="Set target to "+String(newTarget),delta;if(target)message+=" ("+(newTarget>target?"+":"-")+" "+Math.abs(newTarget-target)+" ms)";target=newTarget;log(message+".");delta=Math.abs(target-offset);if(delta>ServerDate.amortizationThreshold){log("Difference between target and offset too high ("+delta+" ms); skipping amortization.");offset=target}}function synchronize(){var iteration=1,requestTime,responseTime,best;function requestSample(){var request=new XMLHttpRequest();request.open("GET",URL+"?time=now");request.onreadystatechange=function(){if((this.readyState==this.HEADERS_RECEIVED)&&(this.status==200))responseTime=Date.now()};request.onload=function(){if(this.status==200){try{processSample(JSON.parse(this.response))}catch(exception){log("Unable to read the server\'s response.")}}};requestTime=Date.now();request.send()}function processSample(serverNow){var precision=(responseTime-requestTime)/2,sample=new Offset(serverNow+precision-responseTime,precision);log("sample: "+iteration+", offset: "+String(sample));if((iteration==1)||(precision<=best.precision))best=sample;if(iteration<10){iteration++;requestSample()}else{setTarget(best);synchronizing=false}}if(!synchronizing){synchronizing=true;setTimeout(function(){synchronizing=false},10*1000);requestSample()}}function log(message){console.log("[ServerDate] "+message)}offset=serverNow-scriptLoadTime;if(typeof performance!="undefined"){precision=(scriptLoadTime-performance.timing.domLoading)/2;offset+=precision}setTarget(new Offset(offset,precision));setInterval(function(){var delta=Math.max(-ServerDate.amortizationRate,Math.min(ServerDate.amortizationRate,target-offset));offset+=delta;if(delta)log("Offset adjusted by "+delta+" ms to "+offset+" ms (target: "+target.value+" ms).")},1000);window.addEventListener(\'pageshow\',synchronize);synchronize();return ServerDate})(%s);';
                $text = sprintf($text, $time->format('Uv'));
                $response = new Response(200, [
                    'Content-Type' => 'text/javascript'
                ], $text);
                $connection->send($response);
            }
            break;
        default:
            if (is_readable('/usr/bin/chronyc')) {
                echo "exec chronyc\n";
                exec('chronyc tracking', $sync_result, $errno);
            } else if (is_readable('/usr/bin/timedatectl')) {
                echo "exec timedatectl\n";
                exec('timedatectl timesync-status', $sync_result, $errno);
            } else {
                echo "exec nothing\n";
            }
            $sync_status = implode("\n", $sync_result);
            $text = '<!DOCTYPE html><html lang="en"><head><title>Crazy Web Clock</title><meta charset="utf-8"><meta name="viewport"content="width=device-width, initial-scale=1.0"><meta name="author"content="Michael Lee"><link rel="shortcut icon"href="/includes/favicon.ico"type="image/ico"><link crossorigin="anonymous"integrity="sha384-i/ZLCOBtDmoxztrtShNvc3vGe1+IbOGDzkZNC4KLXurv/BT7QInnM2AsPnvbgXH/"href="https://lib.baomitu.com/normalize/5.0.0/normalize.min.css"rel="stylesheet"><link href="./local.min.css"rel="stylesheet"type="text/css"></head><body><div class="container"><h1 class="boxtop"id="sec-clock"><a href="/"><span class="hidephone">Crazy</span> Web Clock</a></h1><div class="box"><table><tr><th>Server</th><td id="server"><em class="red">This clock requires JavaScript.</em></td></tr><tr><th>Client</th><td id="client"></td></tr><tr><th class="nobr">Time Zone</th><td id="timezone"></td></tr><tr><th>Offset</th><td id="offset"></td></tr><tr><th>Delay</th><td id="delay"></td></tr></table><pre><code>%s</code></pre></div><div class="copyright"><p>Copyright&copy;2011&ndash;2023 Michael Lee, Edited by wwng</p><p>%s</p></div></div><script src="./ServerDate.php"></script><script src="./local.js"></script><script>ServerDate.amortizationThreshold=0;setTimeout(resetAmortization,1000*5);updateClocks();updateMetaData(true);setTimeout(updateMetaData,1000*2);setInterval(updateClocks,25);setInterval(updateMetaData,1000*60*5);</script></body></html>';
            $time_usage = round((microtime(true) - $GLOBALS['time_start']) * 1000, 4);
            $mem_usage = round(memory_get_usage() / 1024 / 1024, 2);
            $_s = "Processed in {$time_usage} ms , {$mem_usage} MB memory used.\n";
            $host = $request->header('x-forwarded-host', $request->host(true));
            $version = sprintf($_s . '</br>Workerman %s Server at %s', Worker::VERSION, $host);
            $connection->send(sprintf($text, $sync_status, $version));
    }
};

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
