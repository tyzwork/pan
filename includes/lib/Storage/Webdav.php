<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * WebDAV 存储驱动
 * 使用 cURL 实现 WebDAV 协议的 PROPFIND/GET/PUT/DELETE/MKCOL
 * 仅支持网站中转上传/下载，不支持浏览器直传
 */
class Webdav implements IStorage {
	private $config;
	private $url;
	private $user;
	private $pwd;
	private $errmsg;
	private $filepath = 'file/';
	private $dirChecked = false;

	public function __construct($config) {
		$this->config = $config;
		$this->url = rtrim($config['url'], '/').'/';
		$this->user = !empty($config['user']) ? $config['user'] : '';
		$this->pwd = !empty($config['pwd']) ? $config['pwd'] : '';
	}

	public function getClient(){
		return $this->url;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	private function request($method, $name, $headers = [], $body = null, $file = null, $out = null){
		$url = $this->url.$this->filepath.$name;
		$curlHeaders = [];
		foreach($headers as $k=>$v){
			$curlHeaders[] = $k.': '.$v;
		}
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => ($out === null),
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_HTTPHEADER => $curlHeaders,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 3600,
		]);
		if($this->user !== ''){
			curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->pwd);
		}
		if($out !== null){
			curl_setopt($ch, CURLOPT_FILE, $out);
		}
		if($body !== null){
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		if($file !== null){
			$fp = fopen($file, 'rb');
			curl_setopt($ch, CURLOPT_PUT, true);
			curl_setopt($ch, CURLOPT_INFILE, $fp);
			curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
		}
		$response = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		if($file !== null) fclose($fp);
		if($error){
			$this->errmsg = $error;
			return [false, $status];
		}
		return [$response, $status];
	}

	//确保存储目录存在
	private function ensureDir(){
		if($this->dirChecked) return;
		$this->dirChecked = true;
		$ch = curl_init($this->url.$this->filepath);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'MKCOL',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 60,
		]);
		if($this->user !== ''){
			curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->pwd);
		}
		curl_exec($ch);
		curl_close($ch);
	}

	public function exists($name) {
		$body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:getcontenttype/></d:prop></d:propfind>';
		$headers = ['Content-Type' => 'application/xml', 'Depth' => '0'];
		list($res, $status) = $this->request('PROPFIND', $name, $headers, $body);
		return $status == 207 || $status == 200;
	}

	public function get($name) {
		list($res, $status) = $this->request('GET', $name);
		if($status == 200) return $res;
		$this->errmsg = 'HTTP '.$status;
		return false;
	}

	public function downfile($name, $range = false) {
		$headers = [];
		if($range){
			$headers['Range'] = 'bytes='.$range[0].'-'.$range[1];
		}
		$out = fopen('php://output', 'wb');
		list($res, $status) = $this->request('GET', $name, $headers, null, null, $out);
		fclose($out);
		return $status == 200 || $status == 206;
	}

	public function upload($name, $tmpfile, $content_type = null) {
		$this->ensureDir();
		$headers = [];
		if($content_type) $headers['Content-Type'] = $content_type;
		list($res, $status) = $this->request('PUT', $name, $headers, null, $tmpfile);
		if($status >= 200 && $status < 300) return true;
		$this->errmsg = 'HTTP '.$status.' '.$res;
		return false;
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile, $content_type);
	}

	public function getinfo($name) {
		$body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:getcontenttype/></d:prop></d:propfind>';
		$headers = ['Content-Type' => 'application/xml', 'Depth' => '0'];
		list($res, $status) = $this->request('PROPFIND', $name, $headers, $body);
		if($status != 207 && $status != 200){
			$this->errmsg = 'HTTP '.$status;
			return false;
		}
		$result = ['length' => 0, 'content_type' => ''];
		if(preg_match('/<d:getcontentlength[^>]*>(\d+)<\/d:getcontentlength>/', $res, $m)) $result['length'] = intval($m[1]);
		if(preg_match('/<d:getcontenttype[^>]*>([^<]+)<\/d:getcontenttype>/', $res, $m)) $result['content_type'] = trim($m[1]);
		return $result;
	}

	public function delete($name) {
		list($res, $status) = $this->request('DELETE', $name);
		return $status >= 200 && $status < 300;
	}

	//WebDAV 不支持浏览器直传，返回 false 走网站中转
	public function getUploadParam($name, $filename, $max_file_size = 0){
		$this->errmsg = 'WebDAV 不支持直传，请使用网站中转方式上传';
		return false;
	}

	//WebDAV 地址需要鉴权，不支持直接链接下载
	public function getDownUrl($name, $filename, $content_type = null){
		$this->errmsg = 'WebDAV 不支持直接链接下载，请使用网站中转方式下载';
		return false;
	}
}
