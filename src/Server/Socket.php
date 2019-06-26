<?php
namespace Swover\Server;

use Swover\Utils\Event;
use Swover\Utils\Request;
use Swover\Utils\Response;
use Swover\Worker;

/**
 * Socket Server || HTTP Server
 *
 * @property $async Is it asynchronous？
 */
class Socket extends Base
{
    /**
     * @var \Swoole\Http\Server | \Swoole\Server
     */
    private $server;

    public function boot()
    {
        if (!isset($this->config['host']) || !isset($this->config['port'])) {
            throw new \Exception('Has Not Host or Port!');
        }

        if (!is_bool($this->async)) {
            $this->async = boolval($this->async);
        }

        $this->start();
    }

    private function start()
    {
        $className = ($this->server_type == 'http') ? \Swoole\Http\Server::class : \Swoole\Server::class;
        $this->server = new $className($this->config['host'], $this->config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $setting = [
            'worker_num'      => $this->worker_num,
            'task_worker_num' => $this->task_worker_num,
            'daemonize'       => $this->daemonize,
            'max_request'     => $this->max_request
        ];

        $setting = array_merge($setting, $this->getConfig('setting', []));

        $this->server->set($setting);

        $this->onStart()->onReceive()->onRequest()->onTask()->onStop();

        $this->server->start();
        return $this;
    }

    private function onStart()
    {
        $this->server->on('Start', function ($server) {
            Event::getInstance()->trigger('master_start', $server->master_pid);
            Worker::setMasterPid($server->master_pid);
            $this->_setProcessName('master');
        });

        $this->server->on('ManagerStart', function($server) {
            $this->_setProcessName('manager');
        });

        $this->server->on('WorkerStart', function ($server, $worker_id){
            $str = ($worker_id >= $server->setting['worker_num']) ? 'task' : 'event';
            $this->_setProcessName('worker_'.$str);
            Event::getInstance()->trigger('worker_start', $worker_id);
        });

        return $this;
    }

    private function onReceive()
    {
        if ($this->server_type == 'http') return $this;

        $this->server->on('connect', function ($server, $fd, $from_id) {
            Event::getInstance()->trigger('connect', $fd);
        });

        $this->server->on('receive', function (\Swoole\Server $server, $fd, $from_id, $data) {

            $info = $server->getClientInfo($fd);

            $request = [
                'input' => $data,
                'server' => [
                    'request_time' => $info['connect_time'],
                    'request_time_float' => $info['connect_time'] . '.000',
                    'server_port' => $info['server_port'],
                    'remote_port' => $info['remote_port'],
                    'remote_addr' => $info['remote_ip'],
                    'master_time' => $info["last_time"],
                ]
            ];

            $result = $this->execute($request);

            return $result->send($fd, $this->server);
        });
        return $this;
    }

    private function onRequest()
    {
        if ($this->server_type !== 'http') return $this;

        $this->server->on('request', function ($request, $response) {

            if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
                return $response->end();
            }

            $result = $this->execute($request);

            return $result->send($response, $this->server);
        });
        return $this;
    }

    private function onTask()
    {
        $this->server->on('Task', function ($server, $task_id, $src_worker_id, $data)  {
            Event::getInstance()->trigger('task_start', $task_id, $data);
            $this->entrance($data);
            $server->finish($data);
        });
        return $this;
    }

    private function onStop()
    {
        $this->server->on('WorkerStop', function ($server, $worker_id){
            Event::getInstance()->trigger('worker_stop', $worker_id);
        });
        $this->server->on('Finish', function ($server, $task_id, $data) {
            Event::getInstance()->trigger('task_finish', $task_id, $data);
        });

        $this->server->on('close', function ($server, $fd, $from_id) {
            Event::getInstance()->trigger('close', $fd);
        });
    }

    /**
     * @param $data \Swoole\Http\Request|array
     * @return mixed|Response
     */
    protected function execute($data = null)
    {
        Event::getInstance()->trigger('request', $data);
        $request = new Request($data);

        if ($this->async === true) {
            $this->server->task($request);
            //TODO 异步测试
            $response = new Response();
            $response->setBody('success');
        } else {
            $response = $this->entrance($request);
        }
        Event::getInstance()->trigger('response', $response);
        return $response;
    }
}