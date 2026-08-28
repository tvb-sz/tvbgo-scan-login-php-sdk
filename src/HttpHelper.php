<?php
/**
 * Guzzle 請求方法簡單封裝（兼容 PHP 5.6+ / Guzzle 6 與 7）
 */

namespace TvbGo;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

class HttpHelper
{
    const StatusContinue           = 100; // RFC 9110, 15.2.1
    const StatusSwitchingProtocols = 101; // RFC 9110, 15.2.2
    const StatusProcessing         = 102; // RFC 2518, 10.1
    const StatusEarlyHints         = 103; // RFC 8297

    const StatusOK                   = 200; // RFC 9110, 15.3.1
    const StatusCreated              = 201; // RFC 9110, 15.3.2
    const StatusAccepted             = 202; // RFC 9110, 15.3.3
    const StatusNonAuthoritativeInfo = 203; // RFC 9110, 15.3.4
    const StatusNoContent            = 204; // RFC 9110, 15.3.5
    const StatusResetContent         = 205; // RFC 9110, 15.3.6
    const StatusPartialContent       = 206; // RFC 9110, 15.3.7
    const StatusMultiStatus          = 207; // RFC 4918, 11.1
    const StatusAlreadyReported      = 208; // RFC 5842, 7.1
    const StatusIMUsed               = 226; // RFC 3229, 10.4.1

    const StatusMultipleChoices   = 300; // RFC 9110, 15.4.1
    const StatusMovedPermanently  = 301; // RFC 9110, 15.4.2
    const StatusFound             = 302; // RFC 9110, 15.4.3
    const StatusSeeOther          = 303; // RFC 9110, 15.4.4
    const StatusNotModified       = 304; // RFC 9110, 15.4.5
    const StatusUseProxy          = 305; // RFC 9110, 15.4.6
    const _                       = 306; // RFC 9110, 15.4.7 (Unused)
    const StatusTemporaryRedirect = 307; // RFC 9110, 15.4.8
    const StatusPermanentRedirect = 308; // RFC 9110, 15.4.9

    const StatusBadRequest                   = 400; // RFC 9110, 15.5.1
    const StatusUnauthorized                 = 401; // RFC 9110, 15.5.2
    const StatusPaymentRequired              = 402; // RFC 9110, 15.5.3
    const StatusForbidden                    = 403; // RFC 9110, 15.5.4
    const StatusNotFound                     = 404; // RFC 9110, 15.5.5
    const StatusMethodNotAllowed             = 405; // RFC 9110, 15.5.6
    const StatusNotAcceptable                = 406; // RFC 9110, 15.5.7
    const StatusProxyAuthRequired            = 407; // RFC 9110, 15.5.8
    const StatusRequestTimeout               = 408; // RFC 9110, 15.5.9
    const StatusConflict                     = 409; // RFC 9110, 15.5.10
    const StatusGone                         = 410; // RFC 9110, 15.5.11
    const StatusLengthRequired               = 411; // RFC 9110, 15.5.12
    const StatusPreconditionFailed           = 412; // RFC 9110, 15.5.13
    const StatusRequestEntityTooLarge        = 413; // RFC 9110, 15.5.14
    const StatusRequestURITooLong            = 414; // RFC 9110, 15.5.15
    const StatusUnsupportedMediaType         = 415; // RFC 9110, 15.5.16
    const StatusRequestedRangeNotSatisfiable = 416; // RFC 9110, 15.5.17
    const StatusExpectationFailed            = 417; // RFC 9110, 15.5.18
    const StatusTeapot                       = 418; // RFC 9110, 15.5.19 (Unused)
    const StatusMisdirectedRequest           = 421; // RFC 9110, 15.5.20
    const StatusUnprocessableEntity          = 422; // RFC 9110, 15.5.21
    const StatusLocked                       = 423; // RFC 4918, 11.3
    const StatusFailedDependency             = 424; // RFC 4918, 11.4
    const StatusTooEarly                     = 425; // RFC 8470, 5.2.
    const StatusUpgradeRequired              = 426; // RFC 9110, 15.5.22
    const StatusPreconditionRequired         = 428; // RFC 6585, 3
    const StatusTooManyRequests              = 429; // RFC 6585, 4
    const StatusRequestHeaderFieldsTooLarge  = 431; // RFC 6585, 5
    const StatusUnavailableForLegalReasons   = 451; // RFC 7725, 3

    const StatusInternalServerError           = 500; // RFC 9110, 15.6.1
    const StatusNotImplemented                = 501; // RFC 9110, 15.6.2
    const StatusBadGateway                    = 502; // RFC 9110, 15.6.3
    const StatusServiceUnavailable            = 503; // RFC 9110, 15.6.4
    const StatusGatewayTimeout                = 504; // RFC 9110, 15.6.5
    const StatusHTTPVersionNotSupported       = 505; // RFC 9110, 15.6.6
    const StatusVariantAlsoNegotiates         = 506; // RFC 2295, 8.1
    const StatusInsufficientStorage           = 507; // RFC 4918, 11.5
    const StatusLoopDetected                  = 508; // RFC 5842, 7.2
    const StatusNotExtended                   = 510; // RFC 2774, 7
    const StatusNetworkAuthenticationRequired = 511; // RFC 6585, 6

    /**
     * @var Client
     */
    protected static $guzzleHttpClient;
    protected static $ua = 'Mozilla/5.0 AppleWebKit/537.00 Composer/tvbgo-scan-login-php-sdk';

    /**
     * 執行 guzzleHttp 的請求方法，與 guzzleHttp 參數非常類似
     * @param string $method 請求方式
     * @param string $api 請求的 api|guzzleHttp 原生傳入請求的 URL
     * @param array $options [guzzleHttp 數組形式的參數]
     * @return array
     * @throws Exception
     */
    public static function request($method, $api = '', array $options = array())
    {
        if (is_null(self::$guzzleHttpClient)) {
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : static::$ua;
            self::$guzzleHttpClient = new Client(
                array(
                    'base_uri'         => '',
                    'timeout'          => 30,    // 建立起連接後等待數據返回的超時時間--單位：秒
                    'connect_timeout'  => 5,     // 建立連接超時時間--單位：秒
                    'force_ip_resolve' => 'v4',  // 強制使用 ipV4 協議
                    'http_errors'      => false, // http 非 200 狀態不拋出異常
                    'allow_redirects'  => false, // http 重定向不執行
                    'decode_content'   => false, // 是否解碼結果集
                    'headers'          => array(
                        'user-agent' => $userAgent,
                        'Referer'    => 'https://github.com/tvb-sz/tvbgo-scan-login-php-sdk',
                    ),
                )
            );
        }
        // 將不標準的 GuzzleException 轉換爲 Exception
        try {
            $response = self::$guzzleHttpClient->request($method, ltrim($api, '/'), $options);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode());
        }
        // 處理結果集
        $result           = array();
        $result['code']   = $response->getStatusCode();
        $result['header'] = $response->getHeaders();
        $result['body']   = $response->getBody()->getContents();

        // 返回統一的數組結果集
        return $result;
    }

    /**
     * 執行 get 請求
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對，即用於 url 中的 get 變量
     * @param array $header 發送請求中的 header 數組鍵值對
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function get($api, array $query = array(), array $header = array())
    {
        return self::request('GET', $api, array('headers' => $header, 'query' => $query));
    }

    /**
     * 默認 post 請求用於發送 application/x-www-form-urlencoded 形式的表單數據
     * @param string $api 請求地址，完整的 url
     * @param array $query 需附帶在 url 中的鍵值對
     * @param array $header post 提交時需附帶在 header 中的鍵值對
     * @param array $body post 提交的鍵值對
     * @return array
     * @throws Exception
     */
    public static function post($api, array $query = array(), array $header = array(), array $body = array())
    {
        return self::postFormFiled($api, $query, $header, $body);
    }

    /**
     * 執行 post 發送 application/x-www-form-urlencoded 形式的表單數據
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對，即用於 url 中的 get 變量
     * @param array $header 發送請求中的 header 數組鍵值對
     * @param array $body 發送的 body 參數數組
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function postFormFiled($api, array $query = array(), array $header = array(), array $body = array())
    {
        return self::request('POST', $api, array(
            'headers'     => $header,
            'query'       => $query,
            'form_params' => $body
        ));
    }

    /**
     * put 發送表單、putFormFiled 的別名
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對
     * @param array $header 發送請求中的 header 數組鍵值對
     * @param array $body 發送的 body 參數數組
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function put($api, array $query = array(), array $header = array(), array $body = array())
    {
        return self::putFormFiled($api, $query, $header, $body);
    }

    /**
     * 執行 put 發送 application/x-www-form-urlencoded 形式的表單數據
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對
     * @param array $header 發送請求中的 header 數組鍵值對
     * @param array $body 發送的 body 參數數組
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function putFormFiled($api, array $query = array(), array $header = array(), array $body = array())
    {
        return self::request('PUT', $api, array(
            'headers'     => $header,
            'query'       => $query,
            'form_params' => $body
        ));
    }

    /**
     * 執行 post 發送 multipart/form-data 形式文件
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對
     * @param array $header 發送請求中的 header 數組鍵值對
     * @param array $body 發送的 body 參數數組
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function postFormData($api, array $query = array(), array $header = array(), array $body = array())
    {
        return self::request('POST', $api, array(
            'headers'   => $header,
            'query'     => $query,
            'multipart' => $body
        ));
    }

    /**
     * 執行 post 發送 json 字符串 body 的請求
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對
     * @param array $header 發送請求中的 header 數組鍵值對
     * @param array|string $body 發送的 body 參數數組|json 字面量
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function postJson($api, array $query = array(), array $header = array(), $body = array())
    {
        if (empty($header)) {
            $header = array(
                'Content-Type' => 'application/json'
            );
        }
        $param = array(
            'headers' => $header,
            'query'   => $query,
        );
        if (is_array($body)) {
            $param['json'] = $body;
        } else {
            $param['body'] = $body;
        }
        return self::request('POST', $api, $param);
    }

    /**
     * put 方式提交 json
     * @param string $api 請求的 api
     * @param array $query Query 的數組鍵值對
     * @param array $header 發送請求中的 header 數組鍵值對
     * @param array|string $body 發送的 body 參數數組|json 字面量
     * @return array  ['code'=>,'header'=>,'body'=>]
     * @throws Exception
     */
    public static function putJson($api, array $query = array(), array $header = array(), $body = array())
    {
        $param = array(
            'headers' => $header,
            'query'   => $query,
        );
        if (is_array($body)) {
            $param['json'] = $body;
        } else {
            $param['body'] = $body;
        }
        return self::request('PUT', $api, $param);
    }

    /**
     * guzzle 流式下載保存文件
     * @param string $url 待下載的 URL
     * @param string $filePath 本地存儲文件絕對路徑
     * @return bool
     */
    public static function download($url, $filePath)
    {
        try {
            $resource = fopen($filePath, 'w+');
            $client   = new Client(
                array(
                    'timeout'          => 600,
                    'connect_timeout'  => 5,
                    'force_ip_resolve' => 'v4',
                    'http_errors'      => false,
                    'allow_redirects'  => false,
                    'decode_content'   => false,
                )
            );
            $response = $client->get($url, array(RequestOptions::SINK => $resource));

            return $response->getStatusCode() == static::StatusOK;
        } catch (GuzzleException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
