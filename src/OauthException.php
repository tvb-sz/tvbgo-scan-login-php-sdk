<?php

namespace TvbGo;

use Exception;

/**
 * RFC 6749 §5.2 錯誤響應，同時作爲本 SDK 接口方法的統一異常。
 */
class OauthException extends Exception
{
    /** @var string RFC6749 錯誤碼 */
    public $error;
    /** @var string 具體錯誤描述 */
    public $errorDescription;
    /** @var string 可選的錯誤說明 URI */
    public $errorUri;
    /** @var int HTTP 狀態碼，網絡錯誤時爲 0 */
    public $statusCode;

    /**
     * @param string $error RFC6749 錯誤碼
     * @param string $errorDescription 具體錯誤描述
     * @param int $statusCode HTTP 狀態碼
     * @param string $errorUri 可選的錯誤說明 URI
     */
    public function __construct($error, $errorDescription = '', $statusCode = 0, $errorUri = '')
    {
        $this->error            = (string)$error;
        $this->errorDescription = (string)$errorDescription;
        $this->errorUri         = (string)$errorUri;
        $this->statusCode       = (int)$statusCode;

        $message = $this->error;
        if ($this->errorDescription !== '') {
            if ($message !== '') {
                $message .= ': ' . $this->errorDescription;
            } else {
                $message = $this->errorDescription;
            }
        }
        if ($message === '') {
            $message = 'oauth error';
        }

        parent::__construct($message);
    }

    /**
     * RFC 6749 錯誤碼，如 invalid_request、invalid_grant
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getErrorDescription()
    {
        return $this->errorDescription;
    }

    /**
     * @return string
     */
    public function getErrorUri()
    {
        return $this->errorUri;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
