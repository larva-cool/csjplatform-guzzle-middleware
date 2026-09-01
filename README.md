# 穿山甲（CSJ）广告平台 API 中间件

[![Total Downloads](https://img.shields.io/packagist/dt/larva/csjplatform-guzzle-middleware)](https://packagist.org/packages/larva/csjplatform-guzzle-middleware)
[![Latest Stable Version](https://img.shields.io/packagist/v/larva/csjplatform-guzzle-middleware)](https://packagist.org/packages/larva/csjplatform-guzzle-middleware)
[![License](https://img.shields.io/packagist/license/larva/csjplatform-guzzle-middleware)](https://packagist.org/packages/larva/csjplatform-guzzle-middleware)

基于 [Guzzle](http://docs.guzzlephp.org/) 的穿山甲（巨量引擎 / CSJ）开放平台 API 签名中间件，自动为请求注入 `user_id`、`role_id`、`current_time`、`timestamp`、`sign_type`、`version`、`sign` 等公共参数，免去手写签名逻辑。

- 仓库地址：<https://github.com/larva-cool/csjplatform-guzzle-middleware>
- 适用 PHP 版本：`>= 8.0`
- 依赖：`guzzlehttp/guzzle ~7.0`、`guzzlehttp/psr7 ~2.0`

## 安装

推荐通过 [composer](http://getcomposer.org/download/) 安装：

```bash
composer require larva/csjplatform-guzzle-middleware
```

或者在 `composer.json` 的 `require` 段中添加：

```json
"larva/csjplatform-guzzle-middleware": "~1.0"
```

然后执行 `composer update`。

## 使用方式

### 1. 准备穿山甲凭证

在穿山甲开放平台创建应用后，会获得：

- `user_id`：开发者 user_id
- `role_id`：广告主 role_id
- `secure_key`：签名密钥

### 2. 挂载到 Guzzle Handler Stack

将 `CsjMiddleware` 通过 `HandlerStack::push` 加入 Guzzle Client 的 handler stack，中间件会在请求发出前自动注入签名参数。

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Larva\GuzzleHttp\Csj\CsjMiddleware;

$userId    = 1000000;                 // 穿山甲 user_id
$roleId    = 2000000;                 // 穿山甲 role_id
$secureKey = 'your-secure-key';       // 穿山甲 secure_key

$stack = HandlerStack::create();
$stack->push(new CsjMiddleware($userId, $roleId, $secureKey));

$client = new Client([
    'base_uri' => 'https://api.oceanengine.com/open_api/v2.0/',
    'handler'  => $stack,
    'timeout'  => 10,
]);

// 业务请求，中间件会自动追加公共参数和 sign
$response = $client->post('advertiser/info/', [
    'query' => [
        'advertiser_ids' => [123456],
    ],
    'headers' => [
        'Access-Token' => 'your-access-token',
    ],
]);

$result = json_decode((string) $response->getBody(), true);
```

> **注意**：中间件会移除请求中已有的 `Date` 和 `Authorization` 头，避免与穿山甲签名体系冲突。鉴权请使用 `Access-Token` 等穿山甲自有头信息。

### 3. 与 Laravel 配合（可选）

在 `config/services.php` 中新增配置：

```php
'csj' => [
    'user_id'    => env('CSJ_USER_ID'),
    'role_id'    => env('CSJ_ROLE_ID'),
    'secure_key' => env('CSJ_SECURE_KEY'),
    'base_uri'   => env('CSJ_BASE_URI', 'https://api.oceanengine.com/open_api/v2.0/'),
],
```

在服务提供者中构建带中间件的 Guzzle Client：

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Larva\GuzzleHttp\Csj\CsjMiddleware;

$config = config('services.csj');

$stack = HandlerStack::create();
$stack->push(new CsjMiddleware(
    $config['user_id'],
    $config['role_id'],
    $config['secure_key']
));

$this->app->instance('csj.client', new Client([
    'base_uri' => $config['base_uri'],
    'handler'  => $stack,
    'timeout'  => 10,
]));
```

## 签名规则

中间件在转发请求前，会按以下流程生成 `sign`：

1. 合并请求原始 query 与公共参数：
   - `user_id`
   - `role_id`
   - `current_time`（格式 `Y-m-d H:i:s`）
   - `timestamp`（Unix 时间戳，秒）
   - `sign_type` = `MD5`
   - `version` = `2.0`
2. 将所有参数按键名按 ASCII 升序排序。
3. 拼接为 `key1=value1&key2=value2&...` 格式的字符串。
4. 在串尾追加 `secure_key`。
5. 对最终字符串计算 `MD5`（32 位小写）作为 `sign`。

该算法与穿山甲官方 Python 示例保持一致。

## 中间件行为说明

- 自动在请求的 query 字符串中追加公共参数。
- 移除原请求中的 `Date`、`Authorization` 头。
- 不修改 `body`、`method`、其他头信息。
- 不会对 `body` 内容做 JSON 解析或编码，业务侧请按穿山甲接口要求自行处理。

## 许可证

本扩展使用 [MIT License](LICENSE) 发布。
