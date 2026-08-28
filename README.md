# TVB Go 掃碼登錄 PHP SDK

TVB Go OAuth 掃碼登錄客戶端，封裝授權跳轉、`code` 換令牌、刷新令牌、獲取用戶信息 PHP SDK。

要求 **PHP 5.6+**。HTTP 請求使用 `guzzlehttp/guzzle`：`6.*` 覆蓋 PHP 5.6，`7.*` 覆蓋 PHP 7 / 8。

```bash
composer require tvb-sz/tvbgo-scan-login-php-sdk
```

## 使用步驟

### 1. 申請接入

聯系 **mark.he#tvb.com.cn**（請將郵箱中的 `#` 換爲正確字符）申請接入。接入後會提供：

- `client_id`
- `client_secret`
- 各環境 Host（也可直接使用 SDK 常量）

| 環境        | 構造函數的 host 參數                       | Host                           |
|-------------|--------------------------------------------|--------------------------------|
| 生產 `prod` | 字符串 `"prod"` 或常量 `Oauth::HOST_PROD` | `https://api.tvbgo.tvb.com`    |
| QA `qa`     | 字符串 `"qa"` 或常量 `Oauth::HOST_QA`     | `https://qa-api.tvbgo.tvb.com` |
| 開發 `dev`  | 字符串 `"dev"` 或常量 `Oauth::HOST_DEV`   | `https://mytvb.tvb-sz.com`     |

`host` 只允許上述環境名或這三個 Host；空值默認 `prod`。

`redirect_uri` 需事先在應用的「重定向 URI」中配置，申請接入時就要提供。

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use TvbGo\Oauth;

function newOauth()
{
    return new Oauth(
        'your-client-id',
        'your-client-secret',
        'https://your.app/oauth/callback',
        Oauth::HOST_PROD // 或 "prod" / "qa" / "dev"
    );
}
```

### 2. 獲取 code（跳轉掃碼登錄）

由服務端生成授權 URL，將瀏覽器 **302/301** 跳轉到這個授權 URL。用戶掃碼同意後，會帶着 `code`、`state` 跳回你配置的 `redirect_uri`。

**`code` 僅 5 分鍾有效，請拿到後立即換令牌，不要緩存。**

`state` 會原樣帶回（128 字符以內），請務必在換令牌前自行比對，防止 CSRF。

```php
function login()
{
    $oauth = newOauth();
    $state = 'random-csrf-token'; // 請自行生成並寫入 session / cookie
    $_SESSION['oauth_state'] = $state;
    $redirectURL = $oauth->generateRedirectURL($state, Oauth::LANG_SC);
    header('Location: ' . $redirectURL, true, 302);
    exit;
}
```

回調裏先校驗 `state`，再取出 `code`：

```php
function callback()
{
    $state = isset($_GET['state']) ? $_GET['state'] : '';
    $code  = isset($_GET['code']) ? $_GET['code'] : '';

    // 重要，一定要核對 state，避免 CSRF 攻擊
    if ($state === '' || !isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
        http_response_code(400);
        echo 'invalid state';
        return;
    }
    if ($code === '') {
        http_response_code(400);
        echo 'missing code';
        return;
    }

    // 進入第 3 步：用 code 換 access_token
}
```

### 3. code 換 access_token

```php
use TvbGo\OauthException;

try {
    $token = $oauth->code2accessToken($code);
    error_log('openid=' . $token['openid'] . ' access_token=' . $token['access_token']);
} catch (OauthException $e) {
    // $e->getError() 爲 RFC 6749 錯誤碼，如 invalid_grant
    error_log('code2token failed: ' . $e->getError() . ' (' . $e->getErrorDescription() . ')');
    return;
}
```

成功時返回數組，主要字段：

| 字段             | 說明                                                                                |
|------------------|-------------------------------------------------------------------------------------|
| `access_token`   | 調用授權接口的憑證，有效期 **2 小時**                                               |
| `refresh_token`  | 用於刷新 `access_token`，有效期 **30 天**                                           |
| `expires_in`     | `access_token` 剩餘秒數                                                             |
| `openid`         | 用戶標識，**在當前 `client_id` 維度全局唯一**（同一用戶在不同應用下的 openid 不同） |
| `token_type` / `scope` | 令牌類型與授權範圍                                                            |

請將 `openid`、`access_token`、`refresh_token` 一並持久化，後續刷新令牌需要用到。

### 4. access_token 獲取用戶信息

```php
try {
    $user = $oauth->token2userInfo($token['access_token']);
    error_log('openid=' . $user['openid'] . ' email=' . $user['email'] . ' name=' . $user['chi_name']);
} catch (OauthException $e) {
    error_log('userinfo failed: ' . $e->getError() . ' (' . $e->getErrorDescription() . ')');
    return;
}
```

用戶信息包含 `openid`、`email`、`employee_id`、`chi_name`、`eng_name`、`department`。

### 5. refresh_token 刷新 access_token

`access_token` 是調用授權關系接口的憑證，有效期目前爲 **2 小時**。超時後可用 `refresh_token` 刷新。

| 令牌            | 有效期     | 說明                                   |
|-----------------|------------|----------------------------------------|
| `access_token`  | **2 小時** | 可續期                                 |
| `refresh_token` | **30 天**  | **無法續期**；失效後需用戶重新掃碼授權 |

刷新結果有兩種：

1. **`access_token` 已超時**：會拿到一個**新的** `access_token` 以及新的超時時間。
2. **`access_token` 未超時**：`access_token` 本身不變，但超時時間會刷新，相當於續期。

**`refresh_token` 只支持使用 1 次。** 調用成功後會返回新的 `refresh_token`，必須覆蓋保存。
爲降低網絡抖動影響，生成新 `refresh_token` 後，舊的仍會保留約 **5 分鍾** 存活，在此 5 分鍾內使用舊 `refresh_token` 調用本接口返回的新 `refresh_token` 不會變化。

當 `refresh_token` 失效後，需要用戶重新授權才能繼續獲取用戶信息。

```php
try {
    $refreshed = $oauth->refreshAccessToken($savedRefreshToken);
    // 務必保存新的 refresh_token；access_token 也以本次返回爲準
    saveTokens($refreshed['access_token'], $refreshed['refresh_token'], $refreshed['openid']);
} catch (OauthException $e) {
    // 常見：invalid_grant（refresh_token 無效、過期或已使用）
    error_log('refresh failed: ' . $e->getError() . ' (' . $e->getErrorDescription() . ')');
    return;
}
```

建議在 `access_token` 臨近過期（例如剩餘不足 10 分鍾）時主動刷新，而不是等接口報錯後再刷新。

## 錯誤處理

失敗時拋出 `OauthException`：

- `getError()` / `$e->error`：RFC 6749 錯誤碼（如 `invalid_request`、`invalid_grant`、`invalid_client`）
- `getErrorDescription()`：服務端具體描述
- `getStatusCode()`：HTTP 狀態碼
- `getMessage()`：拼接後的可讀信息

參數爲空、網絡失敗等本地錯誤也會包裝成 `OauthException`。

## 完整流程小結

```
申請接入（client_id / client_secret / host）
        │
        ▼
generateRedirectURL(state)  ──302──►  TVB Go 掃碼頁
        │
        ▼
回調 redirect_uri?code=...&state=...     code 僅 5 分鍾有效
        │  校驗 state
        ▼
code2accessToken(code)  →  access_token（2h）/ refresh_token（30天）/ openid
        │
        ├──────────────► token2userInfo(access_token)
        │
        └──────────────► refreshAccessToken(refresh_token)
                         （一次性，成功後保存新的 refresh_token）
```
