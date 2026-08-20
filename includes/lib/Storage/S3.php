<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * AWS S3 兼容存储驱动
 * 支持 AWS S3、MinIO、Cloudflare R2、Backblaze B2 等兼容 S3 协议的对象存储
 */
class S3 implements IStorage {
	private $config;
	private $bucket;
	private $region;
	private $endpoint;
	private $ak;
	private $sk;
	private $domain;
	private $errmsg;
	private $filepath = 'file/';

	public function __construct($config) {
		$this->config = $config;
		$this->bucket = $config['bucket'];
		$this->region = !empty($config['region']) ? $config['region'] : 'us-east-1';
		$this->endpoint = rtrim($config['endpoint'], '/');
		$this->ak = $config['accessKey'];
		$this->sk = $config['secretKey'];
		$this->domain = !empty($config['domain']) ? $config['domain'] : '';
	}

	public function getClient(){
		return $this->endpoint;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	//对象URL（路径风格，兼容所有S3实现）
	private function objectUrl($name){
		return $this->endpoint.'/'.$this->bucket.'/'.$this->filepath.$name;
	}

	//派生S3签名密钥
	private function signingKey($shortDate){
		$kDate = hash_hmac('sha256', $shortDate, 'AWS4'.$this->sk, true);
		$kRegion = hash_hmac('sha256', $this->region, $kDate, true);
		$kService = hash_hmac('sha256', 's3', $kRegion, true);
		$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
		return $kSigning;
	}

	//发起签名请求
	//$out 传入资源句柄时，响应内容将直接写入该句柄（用于流式下载）
	private function request($method, $name, $headers = [], $file = null, $out = null){
		$url = $this->objectUrl($name);
		$host = parse_url($this->endpoint, PHP_URL_HOST);
		$port = parse_url($this->endpoint, PHP_URL_PORT);
		if($port) $host .= ':'.$port;
		$time = gmdate('Ymd\THis\Z');
		$shortDate = substr($time, 0, 8);
		$scope = $shortDate.'/'.$this->region.'/s3/aws4_request';

		$headers['host'] = $host;
		$headers['x-amz-date'] = $time;
		if($file !== null){
			$headers['x-amz-content-sha256'] = hash_file('sha256', $file);
		}else{
			$headers['x-amz-content-sha256'] = 'UNSIGNED-PAYLOAD';
		}

		$signedHeaders = [];
		foreach($headers as $k=>$v){
			$signedHeaders[] = strtolower($k);
		}
		sort($signedHeaders);
		$canonicalHeaders = '';
		foreach($signedHeaders as $k){
			$canonicalHeaders .= $k.':'.trim($headers[$k])."\n";
		}
		$signedHeadersStr = implode(';', $signedHeaders);

		$uri = parse_url($url, PHP_URL_PATH);
		$canonicalRequest = $method."\n".$uri."\n\n".$canonicalHeaders."\n".$signedHeadersStr."\n".$headers['x-amz-content-sha256'];
		$stringToSign = "AWS4-HMAC-SHA256\n".$time."\n".$scope."\n".hash('sha256', $canonicalRequest);
		$signature = hash_hmac('sha256', $stringToSign, $this->signingKey($shortDate));
		$authorization = 'AWS4-HMAC-SHA256 Credential='.$this->ak.'/'.$scope.', SignedHeaders='.$signedHeadersStr.', Signature='.$signature;

		$curlHeaders = [];
		foreach($headers as $k=>$v){
			if($k=='host') continue;
			$curlHeaders[] = ucwords($k, '-').': '.$v;
		}
		$curlHeaders[] = 'Authorization: '.$authorization;

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
		if($out !== null){
			curl_setopt($ch, CURLOPT_FILE, $out);
		}
		if($method == 'HEAD'){
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
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

	public function exists($name) {
		list($res, $status) = $this->request('HEAD', $name);
		return $status == 200;
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
		list($res, $status) = $this->request('GET', $name, $headers, null, $out);
		fclose($out);
		return $status == 200 || $status == 206;
	}

	public function upload($name, $tmpfile, $content_type = null) {
		$headers = [];
		if($content_type) $headers['Content-Type'] = $content_type;
		list($res, $status) = $this->request('PUT', $name, $headers, $tmpfile);
		if($status >= 200 && $status < 300) return true;
		$this->errmsg = 'HTTP '.$status.' '.$res;
		return false;
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile, $content_type);
	}

	public function getinfo($name) {
		list($res, $status) = $this->request('HEAD', $name);
		if($status != 200){
			$this->errmsg = 'HTTP '.$status;
			return false;
		}
		$result = ['length' => 0, 'content_type' => ''];
		if(preg_match('/Content-Length:\s*(\d+)/i', $res, $m)) $result['length'] = intval($m[1]);
		if(preg_match('/Content-Type:\s*([^\r\n]+)/i', $res, $m)) $result['content_type'] = trim($m[1]);
		return $result;
	}

	public function delete($name) {
		list($res, $status) = $this->request('DELETE', $name);
		return $status >= 200 && $status < 300;
	}

	//生成S3 POST直传参数（浏览器直传到S3）
	public function getUploadParam($name, $filename, $max_file_size = 0){
		$key = $this->filepath.$name;
		$url = $this->endpoint.'/'.$this->bucket.'/';
		$expire = time() + 3600;
		$time = gmdate('Ymd\THis\Z', $expire);
		$shortDate = substr($time, 0, 8);
		$credential = $this->ak.'/'.$shortDate.'/'.$this->region.'/s3/aws4_request';

		$conditions = [
			['bucket' => $this->bucket],
			['eq', '$key', $key],
			['eq', '$x-amz-algorithm', 'AWS4-HMAC-SHA256'],
			['eq', '$x-amz-credential', $credential],
			['eq', '$x-amz-date', $time],
		];
		if($max_file_size > 0){
			$conditions[] = ['content-length-range', 1, $max_file_size];
		}
		$policy = base64_encode(json_encode([
			'expiration' => gmdate('Y-m-d\TH:i:s\Z', $expire),
			'conditions' => $conditions
		], JSON_UNESCAPED_SLASHES));
		$signature = hash_hmac('sha256', $policy, $this->signingKey($shortDate));

		$post = [
			'key' => $key,
			'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
			'x-amz-credential' => $credential,
			'x-amz-date' => $time,
			'policy' => $policy,
			'x-amz-signature' => $signature,
		];
		return ['url' => $url, 'post' => $post];
	}

	//生成预签名下载地址（S3预签名有效期最长7天）
	public function getDownUrl($name, $filename, $content_type = null){
		global $conf;
		$expires = 604800;
		$time = gmdate('Ymd\THis\Z');
		$shortDate = substr($time, 0, 8);
		$credential = $this->ak.'/'.$shortDate.'/'.$this->region.'/s3/aws4_request';
		$host = parse_url($this->endpoint, PHP_URL_HOST);
		$port = parse_url($this->endpoint, PHP_URL_PORT);
		if($port) $host .= ':'.$port;

		$filename = '"'.$filename.'"; filename*=utf-8\'\''.rawurlencode($filename);
		$params = [
			'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
			'X-Amz-Credential' => $credential,
			'X-Amz-Date' => $time,
			'X-Amz-Expires' => $expires,
			'X-Amz-SignedHeaders' => 'host',
			'response-content-disposition' => ($content_type ? 'inline' : 'attachment').'; filename='.$filename,
		];
		if($content_type){
			$params['response-content-type'] = $content_type;
		}
		ksort($params);
		$canonicalQuery = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
		$uri = '/'.$this->bucket.'/'.$this->filepath.$name;
		$scope = $shortDate.'/'.$this->region.'/s3/aws4_request';
		$canonicalRequest = "GET\n".$uri."\n".$canonicalQuery."\nhost:".$host."\n\nhost\nUNSIGNED-PAYLOAD";
		$stringToSign = "AWS4-HMAC-SHA256\n".$time."\n".$scope."\n".hash('sha256', $canonicalRequest);
		$signature = hash_hmac('sha256', $stringToSign, $this->signingKey($shortDate));
		$params['X-Amz-Signature'] = $signature;

		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
		$url = $this->endpoint.'/'.$this->bucket.'/'.$this->filepath.$name.'?'.$query;

		$domain = $this->domain;
		if(!empty($conf['downfile_domain'])) $domain = $conf['downfile_domain'];
		if($domain){
			$protocol = !empty($conf['downfile_protocol']) ? 'https://' : 'http://';
			$url = $protocol.$domain.'/'.$this->filepath.$name.'?'.$query;
		}
		return $url;
	}
}
