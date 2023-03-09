<?php
ini_set('memory_limit', '-1');
date_default_timezone_set('PRC');
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

class CrazyList
{
	private $FakeRoot = '/root';
	private $FakePath = '';
	private $RealPath = '';
	private static $GoBackHtml = '</br><img src="?gif=parentdir" alt="[PARENTDIR]"> <a href="#" onClick="javascript:history.go(-1);">Back to last page.</a>';
	private $_HeaderHtml = "<!DOCTYPE html PUBLIC \"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" \"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<title>%s 的索引</title>\n<style type=\"text/css\" media=\"screen\">pre{background:0 0}body{margin:2em}tb{width:600px;margin:0 auto}</style>\n<script>if(window.name!=\"bencalie\"){location.reload();window.name=\"bencalie\"}else{window.name=\"\"}function del(){return confirm('Really delete?')}</script>\n</head>\n<body>\n<strong>%s 的索引</strong>\n";
	private $StartTime = 0;
	private $Request = array();
	private $HtmlTempArr = array();
	private $TotalSize = 0;
	private $TotalFiles = 0;
	private function HeaderHtml()
	{
		return sprintf($this->_HeaderHtml, $this->FakePath, $this->FakePath);
	}
	public function __construct($connection, $request)
	{
		echo date("Y-m-d h:i:s ", time()) . $request->uri() . ' ';
		if (substr($this->FakeRoot, '-1') !== '/')
			$this->FakeRoot .= '/';
		$this->StartTime = microtime(true);
		$this->Request = $request;
		$this->FakePath = $request->path();
		$this->RealPath = $this->RemoveTooManySlash($this->FakeRoot . $this->FakePath);
		echo sprintf(' F:%s R:%s ', $this->FakePath, $this->RealPath);
		if ($request->get('gif')) {
			echo 'hit gif' . "\n";
			if ($gif = $this->OutputGIF($request->get('gif'))) {
				$connection->send(new Response(200, [
					'Content-Type' => 'image/gif'
				], base64_decode($gif)));
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
					$connection->send('Upload failed.' . $this->GoBackHtml);
				}
			}
			$connection->send('Upload success.' . $this->GoBackHtml);
		} elseif ($request->get('delete')) {
			echo 'hit delete' . "\n";
			unlink($this->FakeRoot . $request->get('delete'));
			$connection->send('<script>history.go(-1)</script>');
		} elseif (is_dir($this->RealPath) or is_file($this->RealPath)) {
			echo 'hit file or dir' . "\n";
			if (is_file($this->RealPath)) {
				echo 'sending file to download' . "\n";
				$response = (new Response())->withFile($this->RealPath);
				$connection->send($response);
			} else {
				$connection->send($this->GenerateOutputHtml());
			}
		} else {
			echo 'not hit, return 403' . "\n";
			$connection->send(new Response(403));
		}
	}
	private function FormatSize($size, $key = 0)
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
	private function GetDiskUsage()
	{
		$total = disk_total_space(".");
		$free = disk_free_space(".");
		$html = '%s available of %s, %s used.</br>';
		return sprintf($html, $this->FormatSize($free), $this->FormatSize($total), round(($total - $free) / $total * 100, 2) . '%');
	}

	private function HtmlGenVersion()
	{
		list($host, $port) = explode(':', $this->Request->host());
		$time_usage = round((microtime(true) - $this->StartTime) * 1000, 4);
		$mem_usage = round(memory_get_usage() / 1024 / 1024, 2);
		$_s = "Processed in {$time_usage} ms , {$mem_usage} MB memory used.\n";
		return $this->GetDiskUsage() . sprintf($_s . '</br>Workerman %s Server at %s Port %s', Worker::VERSION, $host, $port);
	}

	private function ReadDirToArray()
	{
		$order = SORT_DESC;
		$sort = $this->Request->get('sort');
		$list = scandir($this->RealPath);
		unset($list[0], $list[1]);
		foreach ($list as $name) {
			$file_name[] = $name;
			$real_path = $this->RemoveTooManySlash($this->RealPath . '/' . $name);
			$fake_path[] = $this->RemoveTooManySlash($this->FakePath . '/' . $name);
			$is_dir[] = @scandir($real_path) ? true : false;
			$file_size[] = filesize($real_path);
			$file_mtime[] = filemtime($real_path);
		}
		switch ($sort) {
			case 'name':
				array_multisort($file_name, $order, $file_size, $file_mtime, $is_dir, $fake_path);
				break;
			case 'size':
				array_multisort($file_size, $order, $file_name, $file_mtime, $is_dir, $fake_path);
				break;
			case 'mtime':
				array_multisort($file_mtime, $order, $file_size, $file_name, $is_dir, $fake_path);
				break;
			default:
				break;
		}
		return (isset($file_name)) ? array(
			'name' => $file_name,
			'size' => $file_size,
			'mtime' => $file_mtime,
			'dir' => $is_dir,
			'fake_path' => $fake_path,
		) : false;
	}

	private function GenParentDir($where)
	{
		return "<tr><td valign=\"top\"><img src=\"?gif=parentdir\" alt=\"[PARENTDIR]\"></td><td><a href=\"$where\">Parent Directory</a></td><td>&nbsp;</td><td align=\"right\">  - </td><td>&nbsp;</td></tr>";
	}

	private function GenDelHtml($name)
	{
		$name = urlencode($name);
		return "<td align=\"right\"><a href=\"?delete=$name\" onclick=\"del()\"> 删除</a></td>\n";
	}

	private function HtmlMTime($mtime)
	{
		return "<td align=\"right\">" . date("Y-m-d H:i", $mtime) . "</td>\n";
	}

	private function HtmlSize($size)
	{
		return "<td align=\"right\"> $size</td><td>&nbsp;</td>\n";
	}

	private function HtmlGIF($gif, $alt)
	{
		return "<td><img src=\"?gif={$gif}\" alt=\"[{$alt}]\"></td>";
	}

	private function HtmlLab($what)
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

	private function HtmlLinkTo($mode, $real_path, $name)
	{

		if ($mode == 'dir') {
			$real_path .= '/';
			$name .= '/';
		}
		return "<a href=\"$real_path\">$name</a>";
	}

	private function MakeFileList()
	{
		$array = $this->ReadDirToArray();
		var_dump($array);
		if ($array === false)
			return false;
		$str = '';
		if (!empty($path = rtrim($this->FakePath, '/'))) {
			$tmp = explode('/', $path);
			if (count($tmp) == 2) {
				$tmp = '/';
			} else {
				$count = count($tmp) - 1;
				unset($tmp[$count]);
				$tmp = implode('/', $tmp);
			}
			$str .= $this->GenParentDir($tmp);
		}
		for ($i = 0; $i < count($array['name']); $i++) {
			$name = $array['name'][$i];
			$real_path = $array['fake_path'][$i];
			if ($array['dir'][$i]) {
				$str .= $this->HtmlLab('tr') . $this->HtmlGIF('dir', 'DIR') . $this->HtmlLab('td') . $this->HtmlLinkTo('dir', $real_path, $name) . $this->HtmlLab('td');
				$str .= $this->HtmlMTime($array['mtime'][$i]) . $this->HtmlSize('-') . $this->GenDelHtml($real_path) . $this->HtmlLab('tr');
				$this->TotalFiles++;
			} else {
				$str .= $this->HtmlLab('tr') . $this->HtmlGIF('blank', '   ') . $this->HtmlLab('td') . $this->HtmlLinkTo('download', $real_path, $name) . $this->HtmlLab('td');
				$str .= $this->HtmlMTime($array['mtime'][$i]) . $this->HtmlSize($this->FormatSize($array['size'][$i])) . $this->GenDelHtml($real_path) . $this->HtmlLab('tr');
				$this->TotalFiles++;
				$this->TotalSize += $array['size'][$i];
			}
		}
		return $str;
	}

	private function GenerateUploadHtml()
	{
		return '<form action="upload" method="post" enctype="multipart/form-data"><input type="hidden" name="topath"  value="' . $this->FakePath . '" /><input type="file" name="file" id="file" /><input type="submit" name="submit" value="上传" /></form>';
	}

	private function RemoveTooManySlash($input)
	{
		while (substr_count($input, '//') > 0)
			$input = str_replace('//', '/', $input);
		return $input;
	}
	private function GenerateOutputHtml()
	{
		$table = $this->MakeFileList();
		$this->TotalSize = $this->FormatSize($this->TotalSize);
		$footer = $this->GenerateUploadHtml() . "<address>%s</address>\n</body>\n</html>";
		$template = $this->HeaderHtml() . "<table><th><img src=\"?gif=ico\" alt=\"[ICO]\"></th><th><a href=\"?dir=$this->FakePath&sort=name\">名称</a></th><th><a href=\"?dir=$this->FakePath&sort=mtime\">最后更改</a></th><th><a href=\"?dir=$this->FakePath&sort=size\">大小</a></th></tr><tr><th colspan=\"6\"><hr></th></tr>%s<tr><th colspan=\"6\"><hr></th></tr></table>" . $footer;
		if ($table === false) {
			return sprintf($this->HeaderHtml() . '<p>No files.</p>' . $footer, $this->HtmlGenVersion());
		} else {
			return sprintf($template, $table, "Total {$this->TotalFiles} file(s), {$this->TotalSize}; " . $this->HtmlGenVersion());
		}
	}

	private static function OutputGIF($name)
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
$http_worker->name = 'Crazy List';
$http_worker->count = 2;
$http_worker->onMessage = function (TcpConnection $connection, Request $request) {
	new CrazyList($connection, $request);
};

Worker::runAll();
