<?php

namespace Swover\Utils;

/**
 * Response
 */
class Response
{
    protected $instance = null;

    /**
     * @param $resource mixed | \Swoole\Http\Response
     * @param $server \Swoole\Http\Server | \Swoole\Server
     * @return bool
     */
    public function send($resource, $server)
    {
        if (!$server instanceof \Swoole\Server) {
            return false;
        }

        $this->instance = Cache::getInstance('response');

        if ($resource instanceof \Swoole\Http\Response) {
            return $this->sendHttpResponse($resource);
        }

        return $server->send($resource, $this->instance['body']);
    }

    /**
     * @param $response \Swoole\Http\Response
     * @return mixed
     */
    private function sendHttpResponse($response)
    {
        $this->build();

        foreach ($this->instance['header'] as $key=>$value) {
            $response->header($key, $value);
        }
        foreach ($this->instance['cookie'] as $cKey=>$cVal) {
            $response->cookie($cKey, $cVal['value'], $cVal['expire'], $cVal['path'], $cVal['domain'], $cVal['secure'], $cVal['httponly']);
        }

        $response->status($this->instance['status']);

        return $response->end($this->instance['body']);
    }

    private function build()
    {
        if (!isset($this->instance['header']) || !is_array($this->instance['header'])) {
            $this->instance['header'] = [];
        }

        if (!isset($this->instance['body'])) {
            $this->instance['body'] = '';
        }

        if (!isset($this->instance['status'])) {
            $this->instance['status'] = 200;
        }

        if (!isset($this->instance['cookie'])) {
            $this->instance['cookie'] = [];
        }
    }

    public function body($body)
    {
        $this->instance['body'] = $body;
    }

    public function header($key, $value)
    {
        if (!isset($this->instance['header'])) {
            $this->instance['header'] = [];
        }
        $this->instance['header'][$key] = $value;
    }

    public function status($http_status_code)
    {
        $this->instance['status'] = $http_status_code;
    }

    public function cookie($key, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        if (!isset($this->instance['cookie'])) {
            $this->instance['cookie'] = [];
        }
        $this->instance['cookie'][$key] = [
            'value'  => $value,
            'expire' => $expire,
            'path'   => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly
        ];
    }
}