<?php
namespace lib;

class HttpHelper
{
	public static $connectTimeout = 10;
	public static $readTimeout = 30;

	/**
	 * @param string $url
	 * @param string $httpMethod
	 * @param null   $postFields
	 * @param null   $headers
	 *
	 * @return HttpResponse
	 * @throws ClientException
	 */
	public static function curl($url, $httpMethod = 'GET', $postFields = null, $headers = null)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postFields) ? self::getPostHttpBody($postFields) : $postFields);

		if (self::$readTimeout) {
			curl_setopt($ch, CURLOPT_TIMEOUT, self::$readTimeout);
		}
		if (self::$connectTimeout) {
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectTimeout);
		}
		//https request
		if (strlen($url) > 5 && stripos($url, 'https') === 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		if (is_array($headers) && 0 < count($headers)) {
			$httpHeaders = self::getHttpHearders($headers);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
		}
		$httpResponse = new HttpResponse();
		$httpResponse->setBody(curl_exec($ch));
		$httpResponse->setStatus(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		if (curl_errno($ch)) {
			throw new Exception('Server unreachable: Errno: ' . curl_errno($ch) . ' ' . curl_error($ch), 'SDK.ServerUnreachable');
		}
		curl_close($ch);
		return $httpResponse;
	}

	/**
	 * @param $postFildes
	 *
	 * @return bool|string
	 */
	public static function getPostHttpBody($postFildes)
	{
		$content = '';
		foreach ($postFildes as $apiParamKey => $apiParamValue) {
			$content .= "$apiParamKey=" . urlencode($apiParamValue) . '&';
		}
		return substr($content, 0, -1);
	}

	/**
	 * @param $headers
	 *
	 * @return array
	 */
	public static function getHttpHearders($headers)
	{
		$httpHeader = array();
		foreach ($headers as $key => $value) {
			$httpHeader[] = $key . ':' . $value;
		}
		return $httpHeader;
	}
}

class HttpResponse
{
	private $body;
	private $status;

	public function getBody()
	{
		return $this->body;
	}

	public function setBody($body)
	{
		$this->body = $body;
	}

	public function getStatus()
	{
		return $this->status;
	}

	public function setStatus($status)
	{
		$this->status = $status;
	}

	public function isSuccess()
	{
		return 200 <= $this->status && 300 > $this->status;
	}
}

class AliyunGreen {
	private $AccessKeyId;
	private $AccessKeySecret;

	private $headers = array();
	private $content;
	private $dateTimeFormat = "D, d M Y H:i:s \G\M\T";
	private static $headerSeparator = "\n";
	private static $querySeparator = '&';
	private static $autoRetry = true; //是否请求错误自动重试
	private static $maxRetryNumber = 3; //自动重试次数
	
	private $requestScheme = 'http';
	private $domain = 'green.cn-beijing.aliyuncs.com';
	private $regionId = 'cn-beijing';
	private $uriPattern = '/green/image/scan';
	private $method = 'POST';
	private $acceptFormat = 'JSON';
	private $version = '2018-05-09';

	function __construct($AccessKeyId, $AccessKeySecret, $regionId){
		if(empty($regionId)) $regionId = 'cn-beijing';
		$this->AccessKeyId = $AccessKeyId;
		$this->AccessKeySecret = $AccessKeySecret;
		$this->regionId = $regionId;
		$this->domain = 'green.'.$regionId.'.aliyuncs.com';
	}

	public function doCheck($dataParameters){
		$this->content = json_encode($dataParameters);

		$requestUrl = $this->composeUrl();

		$httpResponse = HttpHelper::curl($requestUrl, $this->method, $this->content, $this->headers);

		$retryTimes = 1;
		while (500 <= $httpResponse->getStatus() && self::$autoRetry && $retryTimes < self::maxRetryNumber) {
			$requestUrl = $request->composeUrl();

			$httpResponse = HttpHelper::curl($requestUrl, $this->method, $this->content, $this->headers);

			$retryTimes++;
		}
		$respObject = json_decode($httpResponse->getBody());
		
		return $respObject;
	}

	private function signString($source, $accessSecret){
		return base64_encode(hash_hmac('sha1', $source, $accessSecret, true));
	}

	/**
	 * @param $iSigner
	 * @param $credential
	 * @param $domain
	 *
	 * @return mixed|string
	 */
	private function composeUrl()
	{
		$this->headers['x-acs-version'] = $this->version;

		$this->prepareHeader();

		$signString = $this->method . self::$headerSeparator;
		if (isset($this->headers['Accept'])) {
			$signString .= $this->headers['Accept'];
		}
		$signString .= self::$headerSeparator;

		if (isset($this->headers['Content-MD5'])) {
			$signString .= $this->headers['Content-MD5'];
		}
		$signString .= self::$headerSeparator;

		if (isset($this->headers['Content-Type'])) {
			$signString .= $this->headers['Content-Type'];
		}
		$signString .= self::$headerSeparator;

		if (isset($this->headers['Date'])) {
			$signString .= $this->headers['Date'];
		}
		$signString .= self::$headerSeparator;
		$signString .= $this->buildCanonicalHeaders();
		$queryString = $this->uriPattern;
		$signString					 .= $queryString;
		$this->stringToBeSigned		 = $signString;
		$this->headers['Authorization'] = 'acs ' . $this->AccessKeyId . ':' . $this->signString($signString, $this->AccessKeySecret);
		$requestUrl = $this->requestScheme . '://' . $this->domain . $queryString;
		return $requestUrl;
	}

	/**
	 * @return string
	 */
	private function concatQueryString()
	{
		$sortMap = $this->queryParameters;
		if (null == $sortMap || count($sortMap) == 0) {
			return '';
		}
		$queryString = '';
		ksort($sortMap);
		foreach ($sortMap as $sortMapKey => $sortMapValue) {
			$queryString .= $sortMapKey;
			if (isset($sortMapValue)) {
				$queryString = $queryString . '=' . urlencode($sortMapValue);
			}
			$queryString .= self::$querySeparator;
		}

		if (count($sortMap) > 0) {
			$queryString = substr($queryString, 0, -1);
		}
		return '?' . $queryString;
	}

	/**
	 * @param $iSigner
	 * @param $credential
	 */
	private function prepareHeader()
	{
		$this->headers['Date'] = gmdate($this->dateTimeFormat);
		if (null == $this->acceptFormat) {
			$this->acceptFormat = 'RAW';
		}
		if ($this->acceptFormat == 'JSON') {
			$accept = 'application/json';
		}elseif ($this->acceptFormat == 'XML') {
			$accept = 'application/xml';
		}else{
			$accept = 'application/octet-stream';
		}
		$this->headers['Accept']				  = $accept;
		$this->headers['x-acs-signature-method']  = 'HMAC-SHA1';
		$this->headers['x-acs-signature-version'] = '1.0';
		$this->headers['x-acs-region-id'] = $this->regionId;
		$content						  = $this->content;
		if ($content != null) {
			$this->headers['Content-MD5'] = base64_encode(md5($this->content, true));
		}
		if ($this->acceptFormat === 'JSON') {
			$this->headers['Content-Type'] = 'application/json;charset=utf-8';
		} else {
			$this->headers['Content-Type'] = 'application/octet-stream;charset=utf-8';
		}
	}

	/**
	 * @return string
	 */
	private function buildCanonicalHeaders()
	{
		$sortMap = array();
		foreach ($this->headers as $headerKey => $headerValue) {
			$key = strtolower($headerKey);
			if (strpos($key, 'x-acs-') === 0) {
				$sortMap[$key] = $headerValue;
			}
		}
		ksort($sortMap);
		$headerString = '';
		foreach ($sortMap as $sortMapKey => $sortMapValue) {
			$headerString = $headerString . $sortMapKey . ':' . $sortMapValue . self::$headerSeparator;
		}
		return $headerString;
	}
}
