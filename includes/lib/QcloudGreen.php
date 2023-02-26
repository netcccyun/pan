<?php
namespace lib;

class QcloudGreen {
	private $SecretId;
	private $SecretKey;
	private $endpoint = "ims.tencentcloudapi.com";
	private $service = "ims";
	private $version = "2020-12-29";
	private $region = "ap-guangzhou";

	function __construct($SecretId, $SecretKey, $region){
        $this->SecretId = $SecretId;
        $this->SecretKey = $SecretKey;
		$this->region = $region;
    }

	public function ImageModeration($FileUrl){
		$action = 'ImageModeration';
		$param = [
			'FileUrl' => $FileUrl,
		];
		return $this->send_reuqest($action, $param);
	}

	private function send_reuqest($action, $param){
		$payload = json_encode($param);
		$time = time();
		$authorization = $this->generateSign($payload, $time);
		$header = [
			'Authorization: '.$authorization,
			'Content-Type: application/json; charset=utf-8',
			'X-TC-Action: '.$action,
			'X-TC-Timestamp: '.$time,
			'X-TC-Version: '.$this->version,
			'X-TC-Region: '.$this->region,
		];
		return $this->curl_post($payload, $header);
	}

	private function generateSign($payload, $time){
		$algorithm = "TC3-HMAC-SHA256";

		// step 1: build canonical request string
		$httpRequestMethod = "POST";
		$canonicalUri = "/";
		$canonicalQueryString = "";
		$canonicalHeaders = "content-type:application/json; charset=utf-8\n"."host:".$this->endpoint."\n";
		$signedHeaders = "content-type;host";
		$hashedRequestPayload = hash("SHA256", $payload);
		$canonicalRequest = $httpRequestMethod."\n"
			.$canonicalUri."\n"
			.$canonicalQueryString."\n"
			.$canonicalHeaders."\n"
			.$signedHeaders."\n"
			.$hashedRequestPayload;
		
		// step 2: build string to sign
		$date = gmdate("Y-m-d", $time);
		$credentialScope = $date."/".$this->service."/tc3_request";
		$hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
		$stringToSign = $algorithm."\n"
			.$time."\n"
			.$credentialScope."\n"
			.$hashedCanonicalRequest;
		
		// step 3: sign string
		$secretDate = hash_hmac("SHA256", $date, "TC3".$this->SecretKey, true);
		$secretService = hash_hmac("SHA256", $this->service, $secretDate, true);
		$secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
		$signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

		// step 4: build authorization
		$authorization = $algorithm
			." Credential=".$this->SecretId."/".$credentialScope
			.", SignedHeaders=content-type;host, Signature=".$signature;

		return $authorization;
	}

	private function curl_post($payload, $header){
		$url = 'https://'.$this->endpoint.'/';
		$ch=curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		$json=curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if($httpCode==200){
			$arr=json_decode($json,true);
			return $arr['Response'];
		}else{
			return false;
		}
	}
}
