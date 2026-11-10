<?php

/**
 * Copyright 2019 Huawei Technologies Co.,Ltd.
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use
 * this file except in compliance with the License.  You may obtain a copy of the
 * License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed
 * under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
 * CONDITIONS OF ANY KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations under the License.
 *
 */

namespace Obs\Internal\Signature;

use GuzzleHttp\Psr7\Stream;
use Obs\Internal\Common\Model;
use Obs\Internal\Common\ObsTransform;
use Obs\Internal\Common\SchemaFormatter;
use Obs\Internal\Common\V2Transform;
use Obs\Internal\Resource\Constants;
use Obs\Log\ObsLog;
use Obs\ObsException;
use Psr\Http\Message\StreamInterface;

abstract class AbstractSignature implements SignatureInterface
{

    protected $ak;

    protected $sk;

    protected $pathStyle;

    protected $endpoint;

    protected $methodName;

    protected $securityToken;

    protected $signature;

    protected $isCname;

    public static function urlencodeWithSafe($val, $safe = '/')
    {
        $len = strlen($val);
        if ($len === 0) {
            return '';
        }
        $buffer = [];
        for ($index = 0; $index < $len; $index++) {
            $str = $val[$index];
            $pos = strpos($safe, $str);
            $buffer[] = !$pos && $pos !== 0 ? rawurlencode($str) : $str;
        }
        return implode('', $buffer);
    }

    protected function __construct($ak, $sk, $pathStyle, $endpoint, $methodName, $signature, $securityToken = false, $isCname = false)
    {
        $this->ak = $ak;
        $this->sk = $sk;
        $this->pathStyle = $pathStyle;
        $this->endpoint = $endpoint;
        $this->methodName = $methodName;
        $this->signature = $signature;
        $this->securityToken = $securityToken;
        $this->isCname = $isCname;
    }

    protected function transXmlByType($key, &$value, &$subParams, $transHolder)
    {
        $xml = [];
        $treatAsString = false;
        if (isset($value['type'])) {
            $type = $value['type'];
            if ($type === 'array') {
                $name = isset($value['sentAs']) ? $value['sentAs'] : $key;
                $subXml = [];
                foreach ($subParams as $item) {
                    $temp = $this->transXmlByType($key, $value['items'], $item, $transHolder);
                    if ($temp !== '') {
                        $subXml[] = $temp;
                    }
                }
                if (!empty($subXml)) {
                    if (!isset($value['data']['xmlFlattened'])) {
                        $xml[] = '<' . $name . '>';
                        $xml[] = implode('', $subXml);
                        $xml[] = '</' . $name . '>';
                    } else {
                        $xml[] = implode('', $subXml);
                    }
                }
            } elseif ($type === 'object') {
                $name = $this->getNameByObjectType($key, $value);
                $properties = $value['properties'];
                $subXml = [];
                $attr = [];
                foreach ($properties as $pkey => $pvalue) {
                    if (isset($pvalue['required']) && $pvalue['required'] && !isset($subParams[$pkey])) {
                        $obsException = new ObsException('param:' . $pkey . ' is required');
                        $obsException->setExceptionType('client');
                        throw $obsException;
                    }
                    if (isset($subParams[$pkey])) {
                        if (isset($pvalue['data'])
                            && isset($pvalue['data']['xmlAttribute'])
                            && $pvalue['data']['xmlAttribute']
                        ) {
                            $attrValue = $this->xmlTransfer(trim(strval($subParams[$pkey])));
                            $attr[$pvalue['sentAs']] = '"' . $attrValue . '"';
                            if (isset($pvalue['data']['xmlNamespace'])) {
                                $ns = substr($pvalue['sentAs'], 0, strpos($pvalue['sentAs'], ':'));
                                $attr['xmlns:' . $ns] = '"' . $pvalue['data']['xmlNamespace'] . '"';
                            }
                        } else {
                            $subXml[] = $this->transXmlByType($pkey, $pvalue, $subParams[$pkey], $transHolder);
                        }
                    }
                }
                $val = implode('', $subXml);
                if ($val !== '') {
                    $newName = $name;
                    if (!empty($attr)) {
                        foreach ($attr as $akey => $avalue) {
                            $newName .= ' ' . $akey . '=' . $avalue;
                        }
                    }
                    if (!isset($value['data']['xmlFlattened'])) {
                        $xml[] = '<' . $newName . '>';
                        $xml[] = $val;
                        $xml[] = '</' . $name . '>';
                    } else {
                        $xml[] = $val;
                    }
                }
            } else {
                $treatAsString = true;
            }
        } else {
            $treatAsString = true;
            $type = null;
        }

        if ($treatAsString) {
            if ($type === 'boolean') {
                if (!is_bool($subParams) && strval($subParams) !== 'false' && strval($subParams) !== 'true') {
                    $obsException = new ObsException('param:' . $key . ' is not a boolean value');
                    $obsException->setExceptionType('client');
                    throw $obsException;
                }
            } elseif ($type === 'numeric') {
                if (!is_numeric($subParams)) {
                    $obsException = new ObsException('param:' . $key . ' is not a numeric value');
                    $obsException->setExceptionType('client');
                    throw $obsException;
                }
            } elseif ($type === 'float') {
                if (!is_float($subParams)) {
                    $obsException = new ObsException('param:' . $key . ' is not a float value');
                    $obsException->setExceptionType('client');
                    throw $obsException;
                }
            } elseif ($type === 'int' || $type === 'integer') {
                if (!is_int($subParams)) {
                    $obsException = new ObsException('param:' . $key . ' is not a int value');
                    $obsException->setExceptionType('client');
                    throw $obsException;
                }
            } else {
                // nothing handle
            }

            $name = isset($value['sentAs']) ? $value['sentAs'] : $key;
            if (is_bool($subParams)) {
                $val = $subParams ? 'true' : 'false';
            } else {
                $val = strval($subParams);
            }
            if (isset($value['format'])) {
                $val = SchemaFormatter::format($value['format'], $val);
            }
            if (isset($value['transform'])) {
                $val = $transHolder->transform($value['transform'], $val);
            }
            if (isset($val) && $val !== '') {
                $val = $this->xmlTransfer($val);
                if (!isset($value['data']['xmlFlattened'])) {
                    $xml[] = '<' . $name . '>';
                    $xml[] = $val;
                    $xml[] = '</' . $name . '>';
                } else {
                    $xml[] = $val;
                }
            } elseif (isset($value['canEmpty']) && $value['canEmpty']) {
                $xml[] = '<' . $name . '>';
                $xml[] = $val;
                $xml[] = '</' . $name . '>';
            } else {
                // nothing handle
            }
        }
        $ret = implode('', $xml);

        if (isset($value['wrapper'])) {
            $ret = '<' . $value['wrapper'] . '>' . $ret . '</' . $value['wrapper'] . '>';
        }

        return $ret;
    }

    private function xmlTransfer($tag)
    {
        $search = array('&', '<', '>', '\'', '"');
        $repalce = array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;');
        return str_replace($search, $repalce, $tag);
    }

    private function getNameByObjectType($key, $value)
    {
        return isset($value['sentAs']) ? $value['sentAs'] : (isset($value['name']) ? $value['name'] : $key);
    }

    private function getNameByArrayType($key, $value)
    {
        return isset($value['sentAs']) ? $value['sentAs'] : (isset($value['items']['sentAs']) ? $value['items']['sentAs'] : $key);
    }

    protected function prepareAuth(array &$requestConfig, array &$params, Model $model)
    {
        $transHolder = strcasecmp($this->signature, 'obs') === 0
        ? ObsTransform::getInstance()
        : V2Transform::getInstance();
        $method = $requestConfig['httpMethod'];
        $requestUrl = $this->endpoint;
        $headers = [];
        $pathArgs = [];
        $dnsParam = null;
        $uriParam = null;
        $body = [];
        $xml = [];

        if (isset($requestConfig['specialParam'])) {
            $pathArgs[$requestConfig['specialParam']] = '';
        }

        $result = ['body' => null];
        $url = parse_url($requestUrl);
        $host = $url['host'];

        $fileFlag = false;

        if (isset($requestConfig['requestParameters'])) {
            $paramsMetadata = $requestConfig['requestParameters'];
            foreach ($paramsMetadata as $key => $value) {
                if (isset($value['required']) && $value['required'] && !isset($params[$key])) {
                    $obsException = new ObsException('param:' . $key . ' is required');
                    $obsException->setExceptionType('client');
                    throw $obsException;
                }
                if (isset($params[$key]) && isset($value['location'])) {
                    $location = $value['location'];
                    $val = $params[$key];
                    $type = 'string';
                    if ($val !== '' && isset($value['type'])) {
                        $type = $value['type'];
                        if ($type === 'boolean') {
                            if (!is_bool($val) && strval($val) !== 'false' && strval($val) !== 'true') {
                                $obsException = new ObsException('param:' . $key . ' is not a boolean value');
                                $obsException->setExceptionType('client');
                                throw $obsException;
                            }
                        } elseif ($type === 'numeric') {
                            if (!is_numeric($val)) {
                                $obsException = new ObsException('param:' . $key . ' is not a numeric value');
                                $obsException->setExceptionType('client');
                                throw $obsException;
                            }
                        } elseif ($type === 'float') {
                            if (!is_float($val)) {
                                $obsException = new ObsException('param:' . $key . ' is not a float value');
                                $obsException->setExceptionType('client');
                                throw $obsException;
                            }
                        } elseif ($type === 'int' || $type === 'integer') {
                            if (!is_int($val)) {
                                $obsException = new ObsException('param:' . $key . ' is not a int value');
                                $obsException->setExceptionType('client');
                                throw $obsException;
                            }
                        } else {
                            // nothing handle
                        }
                    }

                    if ($location === 'header') {
                        if ($type === 'object') {
                            if (is_array($val)) {
                                $sentAs = strtolower($value['sentAs']);
                                foreach ($val as $k => $v) {
                                    $k = AbstractSignature::urlencodeWithSafe(strtolower($k), ' ;/?:@&=+$,');
                                    $name = strpos($k, $sentAs) === 0 ? $k : $sentAs . $k;
                                    $headers[$name] = AbstractSignature::urlencodeWithSafe($v, ' ;/?:@&=+$,\'*');
                                }
                            }
                        } elseif ($type === 'array') {
                            if (is_array($val)) {
                                $name = $this->getNameByArrayType($key, $value);
                                $temp = [];
                                foreach ($val as $v) {
                                    $v = strval($v);
                                    if ($v !== '') {
                                        $temp[] = AbstractSignature::urlencodeWithSafe($val, ' ;/?:@&=+$,\'*');
                                    }
                                }

                                $headers[$name] = $temp;
                            }
                        } elseif ($type === 'password') {
                            $val = strval($val);
                            if ($val !== '') {
                                $name = isset($value['sentAs']) ? $value['sentAs'] : $key;
                                $pwdName = isset($value['pwdSentAs']) ? $value['pwdSentAs'] : $name . '-MD5';
                                $headers[$name] = base64_encode($val);
                                $headers[$pwdName] = base64_encode(md5($val, true));
                            }
                        } else {
                            if (isset($value['transform'])) {
                                $val = $transHolder->transform($value['transform'], strval($val));
                            }
                            if (isset($val)) {
                                if (is_bool($val)) {
                                    $val = $val ? 'true' : 'false';
                                } else {
                                    $val = strval($val);
                                }
                                if ($val !== '') {
                                    $name = isset($value['sentAs']) ? $value['sentAs'] : $key;
                                    if (isset($value['format'])) {
                                        $val = SchemaFormatter::format($value['format'], $val);
                                    }
                                    $headers[$name] = AbstractSignature::urlencodeWithSafe($val, ' ;/?:@&=+$,\'*');
                                }
                            }
                        }
                    } elseif ($location === 'uri' && $uriParam === null) {
                        $uriParam = AbstractSignature::urlencodeWithSafe($val);
                    } elseif ($location === 'dns' && $dnsParam === null) {
                        $dnsParam = $val;
                    } elseif ($location === 'query') {
                        $name = isset($value['sentAs']) ? $value['sentAs'] : $key;
                        if (strval($val) !== '') {
                            if (strcasecmp($this->signature, 'v4') === 0) {
                                $pathArgs[rawurlencode($name)] = rawurlencode(strval($val));
                            } else {
                                $pathArgs[AbstractSignature::urlencodeWithSafe($name)] = AbstractSignature::urlencodeWithSafe(strval($val));
                            }
                        }
                    } elseif ($location === 'xml') {
                        $val = $this->transXmlByType($key, $value, $val, $transHolder);
                        if ($val !== '') {
                            $xml[] = $val;
                        }
                    } elseif ($location === 'body') {

                        if (isset($result['body'])) {
                            $obsException = new ObsException('duplicated body provided');
                            $obsException->setExceptionType('client');
                            throw $obsException;
                        }

                        if ($type === 'file') {
                            if (!file_exists($val)) {
                                $obsException = new ObsException('file[' . $val . '] does not exist');
                                $obsException->setExceptionType('client');
                                throw $obsException;
                            }
                            $result['body'] = new Stream(fopen($val, 'r'));
                            $fileFlag = true;
                        } elseif ($type === 'stream') {
                            $result['body'] = $val;
                        } elseif ($type === 'json') {
                            $jsonData = json_encode($val);
                            if (!$jsonData) {
                                $obsException = new ObsException('input is invalid, since it is not json data');
                                $obsException->setExceptionType('client');
                                throw $obsException;
                            }
                            $result['body'] = strval($jsonData);
                        } else {
                            $result['body'] = strval($val);
                        }
                    } elseif ($location === 'response') {
                        $model[$key] = ['value' => $val, 'type' => $type];
                    } else {
                        // nothing handle
                    }
                }
            }

            if ($dnsParam) {
                if ($this->pathStyle) {
                    $requestUrl = $requestUrl . '/' . $dnsParam;
                } else {
                    $defaultPort = strtolower($url['scheme']) === 'https' ? '443' : '80';
                    $host = $this->isCname ? $host : $dnsParam . '.' . $host;
                    $port = isset($url['port']) ? $url['port'] : $defaultPort;
                    $requestUrl = $url['scheme'] . '://' . $host . ':' . $port;
                }
            }
            if ($uriParam) {
                $requestUrl = $requestUrl . '/' . $uriParam;
            }

            if (!empty($pathArgs)) {
                $requestUrl .= '?';
                $newPathArgs = [];
                foreach ($pathArgs as $key => $value) {
                    $newPathArgs[] = $value === null || $value === '' ? $key : $key . '=' . $value;
                }
                $requestUrl .= implode('&', $newPathArgs);
            }
        }

        if ($xml || (isset($requestConfig['data']['xmlAllowEmpty']) && $requestConfig['data']['xmlAllowEmpty'])) {
            $body[] = '<';
            $xmlRoot = $requestConfig['data']['xmlRoot']['name'];

            $body[] = $xmlRoot;
            $body[] = '>';
            $body[] = implode('', $xml);
            $body[] = '</';
            $body[] = $xmlRoot;
            $body[] = '>';
            $headers['Content-Type'] = 'application/xml';
            $result['body'] = implode('', $body);

            ObsLog::commonLog(DEBUG, 'request content ' . $result['body']);

            if (isset($requestConfig['data']['contentMd5']) && $requestConfig['data']['contentMd5']) {
                $headers['Content-MD5'] = base64_encode(md5($result['body'], true));
            }
        }

        if ($fileFlag && ($result['body'] instanceof StreamInterface)) {
            if ($this->methodName === 'uploadPart' && (isset($model['Offset']) || isset($model['PartSize']))) {
                $bodySize = $result['body']->getSize();
                if (isset($model['Offset'])) {
                    $offset = intval($model['Offset']['value']);
                    $offset = $offset >= 0 && $offset < $bodySize ? $offset : 0;
                } else {
                    $offset = 0;
                }

                if (isset($model['PartSize'])) {
                    $partSize = intval($model['PartSize']['value']);
                    $partSize = $partSize > 0 && $partSize <= ($bodySize - $offset) ? $partSize : $bodySize - $offset;
                } else {
                    $partSize = $bodySize - $offset;
                }
                $result['body']->rewind();
                $result['body']->seek($offset);
                $headers['Content-Length'] = $partSize;
            } elseif (isset($headers['Content-Length'])) {
                $bodySize = $result['body']->getSize();
                if (intval($headers['Content-Length']) > $bodySize) {
                    $headers['Content-Length'] = $bodySize;
                }
            } else {
                // nothing handle
            }
        }

        $constants = Constants::selectConstants($this->signature);

        if ($this->securityToken) {
            $headers[$constants::SECURITY_TOKEN_HEAD] = $this->securityToken;
        }

        $headers['Host'] = $host;

        $result['host'] = $host;
        $result['method'] = $method;
        $result['headers'] = $headers;
        $result['pathArgs'] = $pathArgs;
        $result['dnsParam'] = $dnsParam;
        $result['uriParam'] = $uriParam;
        $result['requestUrl'] = $requestUrl;

        return $result;
    }
}