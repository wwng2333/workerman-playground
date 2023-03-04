<?php
ini_set('memory_limit', '-1');
date_default_timezone_set('PRC');
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$GLOBALS['path'] = '/';
if (substr($GLOBALS['path'], '-1') !== '/')
	$GLOBALS['path'] .= '/';

class CrazyList
{
	private $RealPath = '';
	public $FullHtml = '';
	public static $GoBackHtml = '</br><img src="?gif=parentdir" alt="[PARENTDIR]"> <a href="#" onClick="javascript:history.go(-1);">Back to last page.</a>';
	private $StartTime = 0;
	private $Request = array();
	private $HtmlTempArr = array();
	public $TotalSize = 0;
	public $TotalFiles = 0;
	public function __construct($request)
	{
		$this->StartTime = microtime(true);
		$this->Request = $request;
		$this->RealPath = $this->RemoveTooManySlash($GLOBALS['path'] . $this->Request->path() . '/');
	}
	private function formatsize($size, $key = 0)
	{
		if ($size < 0) {
			return '0B';
		} else {
			$danwei = array('B', 'K', 'M', 'G', 'T', 'P');
			while ($size > 1024) {
				$size = $size / 1024;
				$key++;
			}
			return round($size, 1) . $danwei[$key];
		}
	}
	private function disk_usage()
	{
		$total = disk_total_space(".");
		$free = disk_free_space(".");
		$used = round(($total - $free) / $total * 100, 2) . '%';
		$html = '%s available of %s, %s used.</br>';
		return sprintf($html, $this->formatsize($free), $this->formatsize($total), $used);
	}

	private function get_ver($data)
	{
		list($host, $port) = explode(':', $data);
		$time_usage = round((microtime(true) - $this->StartTime) * 1000, 4);
		$mem_usage = round(memory_get_usage() / 1024 / 1024, 2);
		$_s = "Processed in {$time_usage} ms , {$mem_usage} MB memory used.\n";
		return $this->disk_usage() . sprintf($_s . '</br>Workerman %s Server at %s Port %s', Worker::VERSION, $host, $port);
	}

	private function read_dir($dir, $sort = 'name', $order = SORT_DESC)
	{
		$list = scandir($dir);
		unset($list[0], $list[1]);
		foreach ($list as $name) {
			$file_name[] = $name;
			$real_path = $dir . '/' . $name;
			$is_dir[] = @scandir($real_path) ? true : false;
			$file_size[] = filesize($real_path);
			$file_mtime[] = filemtime($real_path);
		}
		switch ($sort) {
			case 'name':
				array_multisort($file_name, $order, $file_size, $file_mtime, $is_dir);
				break;
			case 'size':
				array_multisort($file_size, $order, $file_name, $file_mtime, $is_dir);
				break;
			case 'mtime':
				array_multisort($file_mtime, $order, $file_size, $file_name, $is_dir);
				break;
			default:
				break;
		}
		return (isset($file_name)) ? array('name' => $file_name, 'size' => $file_size, 'mtime' => $file_mtime, 'dir' => $is_dir) : false;
	}

	private function parentdir($where)
	{
		return "<tr><td valign=\"top\"><img src=\"?gif=parentdir\" alt=\"[PARENTDIR]\"></td><td><a href=\"$where\">Parent Directory</a></td><td>&nbsp;</td><td align=\"right\">  - </td><td>&nbsp;</td></tr>";
	}

	private function del($name)
	{
		$name = urlencode($name);
		return "<td align=\"right\"><a href=\"?delete=$name\" onclick=\"del()\"> 删除</a></td>\n";
	}

	private function mtime($mtime)
	{
		return "<td align=\"right\"> $mtime</td>\n";
	}

	private function size($size)
	{
		return "<td align=\"right\"> $size</td><td>&nbsp;</td>\n";
	}

	private function gif($gif, $alt)
	{
		return "<td><img src=\"?gif={$gif}\" alt=\"[{$alt}]\"></td>";
	}

	private function html($what)
	{
		$arr = $this->HtmlTempArr;
		if (!isset($arr[$what]))
			$arr[$what] = false;
		if ($arr[$what]) {
			$arr[$what] = false;
			return "</$what>";
		} else {
			$arr[$what] = true;
			return "<$what>";
		}
	}

	private function LinkTo($mode, $real_path, $name)
	{
		if ($mode == 'dir') {
			$real_path .= '/';
			$name .= '/';
		}
		return "<a href=\"$real_path\">$name</a>";
	}

	private function make_list($dir, $array, $path)
	{
		if (!$array)
			return false;
		$path = rtrim($path, '/');
		$str = '';
		$this->TotalFiles = 0;
		$this->TotalSize = 0;
		if (!empty($path)) {
			$tmp = explode('/', $path);
			if (count($tmp) == 2) {
				$tmp = '/';
			} else {
				$count = count($tmp) - 1;
				unset($tmp[$count]);
				$tmp = implode('/', $tmp);
			}
			$str .= $this->parentdir($tmp);
		}
		for ($i = 0; $i < count($array['name']); $i++) {
			$name = $array['name'][$i];
			$real_path = $this->RemoveTooManySlash($dir . $name);
			if ($array['dir'][$i]) {
				$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
				$str .= $this->html('tr') . $this->gif('dir', 'DIR') . $this->html('td') . $this->LinkTo('dir', $real_path, $name) . $this->html('td');
				$str .= $this->mtime($mtime_now) . $this->size('-') . $this->del($real_path) . $this->html('tr');
				$this->TotalFiles++;
			} else {
				$size_now = $this->formatsize($array['size'][$i]);
				$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
				$str .= $this->html('tr') . $this->gif('blank', '   ') . $this->html('td') . $this->LinkTo('download', $real_path, $name) . $this->html('td');
				$str .= $this->mtime($mtime_now) . $this->size($size_now) . $this->del($real_path) . $this->html('tr');
				$this->TotalFiles++;
				$this->TotalSize += $array['size'][$i];
			}
		}
		return $str;
	}

	private function GenerateUploadHtml($path)
	{
		return '<form action="upload" method="post" enctype="multipart/form-data"><input type="hidden" name="topath"  value="' . $this->RealPath . '" /><input type="file" name="file" id="file" /><input type="submit" name="submit" value="上传" /></form>';
	}

	private function RemoveTooManySlash($input)
	{
		while (substr_count($input, '//') > 0)
			$input = str_replace('//', '/', $input);
		return $input;
	}
	public function GenerateOutputHtml()
	{
		$path = $this->Request->path();
		$sort = $this->Request->get('sort');
		$real_path = $this->RealPath;
		$table = $this->make_list($real_path, $this->read_dir($real_path, $sort), $path);
		$this->TotalSize = $this->formatsize($this->TotalSize);
		$header = "<!DOCTYPE html PUBLIC \"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" \"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<title>%s 的索引</title>\n<style type=\"text/css\" media=\"screen\">pre{background:0 0}body{margin:2em}tb{width:600px;margin:0 auto}</style>\n<script>if(window.name!=\"bencalie\"){location.reload();window.name=\"bencalie\"}else{window.name=\"\"}function del(){return confirm('Really delete?')}</script>\n</head>\n<body>\n<strong>$real_path 的索引</strong>\n";
		$footer = $this->GenerateUploadHtml($path) . "<address>%s</address>\n</body>\n</html>";
		$template = sprintf($header, $real_path) . "<table><th><img src=\"?gif=ico\" alt=\"[ICO]\"></th><th><a href=\"?dir=$real_path&sort=name\">名称</a></th><th><a href=\"?dir=$real_path&sort=mtime\">最后更改</a></th><th><a href=\"?dir=$real_path&sort=size\">大小</a></th></tr><tr><th colspan=\"6\"><hr></th></tr>%s<tr><th colspan=\"6\"><hr></th></tr></table>" . $footer;
		if (!$table)
			$this->FullHtml = sprintf(sprintf($header, $real_path) . '<p>No files.</p>' . $footer, $this->get_ver($this->Request->host()));
		$this->FullHtml = sprintf($template, $table, "Total {$this->TotalFiles} file(s), {$this->TotalSize}; " . $this->get_ver($this->Request->host()));
	}

	public static function GenerateGIF($name)
	{
		switch ($name) {
			case 'parentdir':
				$gif = 'R0lGODlhFAAWAMIAAP///8z//5mZmWZmZjMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAADSxi63P4jEPJqEDNTu6LO3PVpnDdOFnaCkHQGBTcqRRxuWG0v+5LrNUZQ8QPqeMakkaZsFihOpyDajMCoOoJAGNVWkt7QVfzokc+LBAA7';
				break;
			case 'dir':
				$gif = 'R0lGODlhFAAWAMIAAP/////Mmcz//5lmMzMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAACACwAAAAAFAAWAAADVCi63P4wyklZufjOErrvRcR9ZKYpxUB6aokGQyzHKxyO9RoTV54PPJyPBewNSUXhcWc8soJOIjTaSVJhVphWxd3CeILUbDwmgMPmtHrNIyxM8Iw7AQA7';
				break;
			case 'ico':
				$gif = 'R0lGODlhFAAWAKEAAP///8z//wAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAACE4yPqcvtD6OctNqLs968+w+GSQEAOw==';
				break;
			case 'blank':
				$gif = 'R0lGODlhFAAWAMIAAP///8z//8zMzJmZmTMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAADaUi6vPEwEECrnSS+WQoQXSEAE6lxXgeopQmha+q1rhTfakHo/HaDnVFo6LMYKYPkoOADim4VJdOWkx2XvirUgqVaVcbuxCn0hKe04znrIV/ROOvaG3+z63OYO6/uiwlKgYJJOxFDh4hTCQA7';
				break;
			default:
				$gif = false;
				break;
		}
		return $gif;
	}

}

$http_worker = new Worker("http://0.0.0.0:12101");
$http_worker->count = 4;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
	echo date("Y-m-d h:i:s ", time()) . $request->uri() . ' ';
	if ($request->get('gif')) {
		echo 'hit gif' . "\n";
		if ($gif = CrazyList::GenerateGIF($request->get('gif'))) {
			$response = new Response(200, [
				'Content-Type' => 'image/gif'
			], base64_decode($gif));
			$connection->send($response);
		} else {
			$connection->send(new Response(404));
		}
	} elseif (count($files = $request->file()) > 0) {
		echo 'hit upload' . "\n";
		$topath = $request->post('topath', '/');
		if (substr($topath, '-1') !== '/')
			$topath .= '/';
		foreach ($files as $array) {
			if ($array['error'] === UPLOAD_ERR_OK) {
				rename($array['tmp_name'], $topath . $array['name']);
			} else {
				$connection->send('Upload failed.' . CrazyList::$GoBackHtml);
			}
		}
		$connection->send('Upload success.' . CrazyList::$GoBackHtml);
	} elseif ($request->get('delete')) {
		echo 'hit delete' . "\n";
		unlink($GLOBALS['path'] . $request->get('delete'));
		$connection->send('<script>history.go(-1)</script>');
	} elseif (is_dir($request->path()) or is_file($request->path())) {
		echo 'hit file or dir' . "\n";
		if (is_file($request->path())) {
			echo 'sending file to download' . "\n";
			$response = (new Response())->withFile($request->path());
			$connection->send($response);
		} else {
			$crazy = new CrazyList($request);
			$crazy->GenerateOutputHtml();
			$connection->send($crazy->FullHtml);
		}
	} else {
		echo 'not hit, return 403' . "\n";
		$connection->send(new Response(403));
	}
};

Worker::runAll();
