<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace Larva\GuzzleHttp\Csj;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

/**
 * 穿山甲 请求中间件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CsjMiddleware
{
    /**
     * 穿山甲user_id
     *
     * @var int
     */
    protected int $userId;

    /**
     * 穿山甲role_id
     *
     * @var int
     */
    protected int $roleId;

    /**
     * 穿山甲secure_key
     *
     * @var string
     */
    protected string $secureKey;

    public function __construct(int|string $userId, int|string $roleId, string $secureKey)
    {
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->secureKey = $secureKey;
    }

    /**
     * Called when the middleware is handled.
     *
     * @param  callable  $handler
     *
     * @return \Closure
     */
    public function __invoke(callable $handler): \Closure
    {
        return function ($request, array $options) use ($handler) {
            $currentTime = time();
            $parsed = $this->parseRequest($request);
            $parsed['query']['user_id'] = $this->userId;
            $parsed['query']['role_id'] = $this->roleId;
            $parsed['query']['current_time'] = date('Y-m-d H:i:s', $currentTime);
            $parsed['query']['timestamp'] = $currentTime;
            $parsed['query']['sign_type'] = 'MD5';
            $parsed['query']['version'] = '2.0';
            $parsed['query']['sign'] = $this->sign($parsed['query']);

            $request = $this->buildRequest($parsed);
            return $handler($request, $options);
        };
    }

    /**
     * 生成签名
     * @param $params
     * @return string
     */
    private function sign($params): string
    {
        // 按key正序排序（和Python sorted一致）
        ksort($params);
        $rawStr = '';
        // 拼接 key=value& 格式字符串
        foreach ($params as $k => $v) {
            $rawStr .= (string) $k.'='.(string) $v.'&';
        }
        // 去掉最后一个 & 并拼接密钥
        $signStr = substr($rawStr, 0, -1).$this->secureKey;
        // MD5 加密（32位小写，和Python一致）
        return md5($signStr);
    }

    /**
     * 解析请求
     * @param  RequestInterface  $request
     * @return array
     */
    private function parseRequest(RequestInterface $request): array
    {
        // Clean up any previously set headers.
        /** @var RequestInterface $request */
        $request = $request
            ->withoutHeader('Date')
            ->withoutHeader('Authorization');
        $uri = $request->getUri();

        return [
            'method' => $request->getMethod(),
            'path' => $uri->getPath(),
            'query' => Query::parse($uri->getQuery()),
            'uri' => $uri,
            'headers' => $request->getHeaders(),
            'body' => $request->getBody(),
            'version' => $request->getProtocolVersion()
        ];
    }

    /**
     * 根据提供的参数构建一个新的请求
     * @param  array  $req
     * @return RequestInterface
     */
    private function buildRequest(array $req): RequestInterface
    {
        if ($req['query']) {
            $req['uri'] = $req['uri']->withQuery(Query::build($req['query']));
        }

        return new Request($req['method'], $req['uri'], $req['headers'], $req['body'], $req['version']);
    }
}
