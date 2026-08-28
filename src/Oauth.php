<?php

namespace TvbGo;

use Exception;

/**
 * TVB Go OAuth 掃碼登錄客戶端
 */
class Oauth
{
    /** 生產環境 */
    const HOST_PROD = 'https://api.tvbgo.tvb.com';
    /** QA 環境 */
    const HOST_QA = 'https://qa-api.tvbgo.tvb.com';
    /** 開發環境 */
    const HOST_DEV = 'https://mytvb.tvb-sz.com';
    /** 掃碼界面顯示繁體中文，默認 */
    const LANG_SC = 'zh-HK';
    /** 掃碼界面顯示英文 */
    const LANG_EN = 'en';

    const MSG_PARAM_INVALID = 'callback URL needed params is missing';

    /** @var string */
    protected $clientId;
    /** @var string */
    protected $clientSecret;
    /** @var string */
    protected $redirectUri;
    /** @var string */
    protected $host;

    /**
     * 構造一個 TVB Go OAuth 授權管理器對象
     * @param string $clientId     應用程序(客戶端) ID
     * @param string $clientSecret 應用秘鑰，在具體應用的「客戶端憑據」裏創建客戶端密碼，注意有輪轉有效期
     * @param string $redirectUri  在具體應用的客戶端憑據裏的「重定向 URI」添加設置，支持多個
     * @param string $host         環境切換：prod / qa / dev，或 HOST_PROD / HOST_QA / HOST_DEV；空值默認 prod
     */
    public function __construct($clientId, $clientSecret, $redirectUri, $host = '')
    {
        $this->clientId     = (string)$clientId;
        $this->clientSecret = (string)$clientSecret;
        $this->redirectUri  = (string)$redirectUri;
        $this->host         = self::resolveHost($host);
    }

    /**
     * @param string $host
     * @return string
     */
    protected static function resolveHost($host)
    {
        $normalized = rtrim(trim((string)$host), '/');
        $lower      = strtolower($normalized);

        if ($lower === '' || $lower === 'prod' || $lower === 'production') {
            return self::HOST_PROD;
        }
        if ($lower === 'qa') {
            return self::HOST_QA;
        }
        if ($lower === 'dev' || $lower === 'develop' || $lower === 'development') {
            return self::HOST_DEV;
        }
        if ($lower === strtolower(self::HOST_PROD)) {
            return self::HOST_PROD;
        }
        if ($lower === strtolower(self::HOST_QA)) {
            return self::HOST_QA;
        }
        if ($lower === strtolower(self::HOST_DEV)) {
            return self::HOST_DEV;
        }

        trigger_error(
            'Warning: unsupported host "' . $host . '", want string prod/qa/dev or constant HOST_PROD/HOST_QA/HOST_DEV, fallback to HOST_PROD',
            E_USER_WARNING
        );
        return self::HOST_PROD;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function apiURL($path)
    {
        return $this->host . $path;
    }

    /**
     * 生成 301/302 跳轉到 TVB Go 的授權 URL
     * @param string $state 跳轉去 oauth 授權後原樣帶回的任意字符串（128 字符以內）
     * @param string $lang  掃碼界面文字語言，取值字符串 en、zh-HK，或使用類常量 LANG_SC、LANG_EN
     * @return string
     */
    public function generateRedirectURL($state, $lang = self::LANG_SC)
    {
        $param = array(
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri,
            'scope'         => 'scan_login',
            'state'         => (string)$state,
            'lang'          => ($lang === self::LANG_EN) ? self::LANG_EN : self::LANG_SC,
        );

        return $this->apiURL('/connect/qrconnect') . '?' . http_build_query($param);
    }

    /**
     * TVB Go 授權後回調 callback 後使用 code 去換令牌，請務必同時取出 state 進行比對後再調用本方法
     * @param string $code 回到 callback URL 後從 query-string 裏取出的 code 值
     * @return array token_type / scope / expires_in / access_token / refresh_token / openid
     * @throws OauthException
     */
    public function code2accessToken($code)
    {
        if ($code === '' || $code === null) {
            throw $this->invalidParamError();
        }
        return $this->tvbGoCode2accessToken($code);
    }

    /**
     * 使用 refresh_token 刷新 access_token 的有效期
     * @param string $refreshToken code2accessToken 獲取到的 refresh_token
     * @return array token_type / scope / expires_in / access_token / refresh_token / openid
     * @throws OauthException
     */
    public function refreshAccessToken($refreshToken)
    {
        if ($refreshToken === '' || $refreshToken === null) {
            throw $this->invalidParamError();
        }
        return $this->tvbGoRefreshAccessToken($refreshToken);
    }

    /**
     * 獲取到令牌值後獲取用戶信息（可以獲取到郵箱）
     * @param string $token 有效的令牌，code2accessToken 獲取到的 access_token
     * @return array openid / email / employee_id / chi_name / eng_name / department
     * @throws OauthException
     */
    public function token2userInfo($token)
    {
        if ($token === '' || $token === null) {
            throw $this->invalidParamError();
        }
        return $this->tvbGoAccessToken2UserInfo($token);
    }

    /**
     * @param string $code
     * @return array
     * @throws OauthException
     */
    protected function tvbGoCode2accessToken($code)
    {
        $param = array(
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirectUri,
        );

        return $this->exchangeToken($this->apiURL('/connect/oauth/access_token'), $param);
    }

    /**
     * @param string $refreshToken
     * @return array
     * @throws OauthException
     */
    protected function tvbGoRefreshAccessToken($refreshToken)
    {
        $param = array(
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
            'redirect_uri'  => $this->redirectUri,
        );

        return $this->exchangeToken($this->apiURL('/connect/oauth/refresh_token'), $param);
    }

    /**
     * @param string $endpoint
     * @param array $param
     * @return array
     * @throws OauthException
     */
    protected function exchangeToken($endpoint, array $param)
    {
        try {
            $result = HttpHelper::postFormFiled($endpoint, array(), array(), $param);
        } catch (Exception $e) {
            throw new OauthException('server_error', $e->getMessage());
        }

        $this->assertHttpOK($result);

        $accessToken = $this->decodeJSON($result['body']);
        if (!isset($accessToken['access_token']) || $accessToken['access_token'] === '') {
            throw new OauthException(
                'server_error',
                'missing access_token in token response',
                isset($result['code']) ? (int)$result['code'] : 0
            );
        }

        return $accessToken;
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws OauthException
     */
    protected function tvbGoAccessToken2UserInfo($accessToken)
    {
        $head = array(
            'Authorization' => 'Bearer ' . $accessToken,
        );

        try {
            $result = HttpHelper::get($this->apiURL('/connect/oauth/userinfo'), array(), $head);
        } catch (Exception $e) {
            throw new OauthException('server_error', $e->getMessage());
        }

        $this->assertHttpOK($result);
        return $this->decodeJSON($result['body']);
    }

    /**
     * @return OauthException
     */
    protected function invalidParamError()
    {
        return new OauthException('invalid_request', self::MSG_PARAM_INVALID);
    }

    /**
     * 非 200 時按 RFC 6749 解析 body 中的 error / error_description
     * @param array $result HttpHelper 返回的 ['code'=>,'header'=>,'body'=>]
     * @throws OauthException
     */
    protected function assertHttpOK(array $result)
    {
        $statusCode = isset($result['code']) ? (int)$result['code'] : 0;
        if ($statusCode === HttpHelper::StatusOK) {
            return;
        }

        $error            = 'server_error';
        $errorDescription = 'unexpected http status ' . $statusCode;
        $errorUri         = '';

        if (!empty($result['body'])) {
            $decoded = json_decode($result['body'], true);
            if (is_array($decoded)) {
                if (!empty($decoded['error'])) {
                    $error = (string)$decoded['error'];
                }
                if (isset($decoded['error_description']) && $decoded['error_description'] !== '') {
                    $errorDescription = (string)$decoded['error_description'];
                }
                if (!empty($decoded['error_uri'])) {
                    $errorUri = (string)$decoded['error_uri'];
                }
            }
        }

        throw new OauthException($error, $errorDescription, $statusCode, $errorUri);
    }

    /**
     * @param string $body
     * @return array
     * @throws OauthException
     */
    protected function decodeJSON($body)
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $msg = 'invalid json response';
            if (function_exists('json_last_error_msg') && json_last_error() !== JSON_ERROR_NONE) {
                $msg = json_last_error_msg();
            }
            throw new OauthException('server_error', $msg);
        }
        return $decoded;
    }
}
