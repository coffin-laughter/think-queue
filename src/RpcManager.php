<?php
/**
 * FileName: RpcManager.php
 * ==============================================
 * Copy right 2016-2023
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 * @author: coffin_laughter | <chuanshuo_yongyuan@163.com>
 * @date  : 2023-02-21 08:58
 */

declare(strict_types = 1);

namespace think\swoole;

use think\App;
use Throwable;
use think\Event;
use Swoole\Server;
use Swoole\Coroutine;
use think\helper\Str;
use Swoole\Server\Port;
use think\swoole\rpc\Error;
use think\swoole\rpc\Packer;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\server\Channel;
use think\swoole\rpc\server\Dispatcher;
use think\swoole\concerns\WithApplication;
use think\swoole\concerns\InteractsWithPools;
use think\swoole\concerns\InteractsWithServer;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\concerns\InteractsWithRpcClient;
use think\swoole\concerns\InteractsWithSwooleTable;

class RpcManager
{
    use InteractsWithServer;
    use InteractsWithSwooleTable;
    use InteractsWithPools;
    use InteractsWithRpcClient;
    use WithApplication;

    /** @var Channel[] */
    protected $channels = [];

    /**
     * @var App
     */
    protected $container;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start',
        'shutDown',
        'workerStart',
        'workerStop',
        'workerError',
        'workerExit',
        'packet',
        'task',
        'finish',
        'pipeMessage',
        'managerStart',
        'managerStop',
    ];

    protected $rpcEvents = [
        'connect',
        'receive',
        'close',
    ];

    /**
     * Manager constructor.
     *
     * @param App $container
     */
    public function __construct(App $container)
    {
        $this->container = $container;
    }

    public function attachToServer(Port $port)
    {
        $port->set([]);
        foreach ($this->rpcEvents as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->triggerEvent('rpc.' . $event, func_get_args());
            };

            $port->on($event, $callback);
        }

        $this->onEvent('workerStart', function (App $app) {
            $this->app = $app;
        });
        $this->prepareRpcServer();
    }

    public function onClose(Server $server, int $fd, int $reactorId)
    {
        unset($this->channels[ $fd ]);
        $args = func_get_args();
        $this->runInSandbox(function (Event $event) use ($args) {
            $event->trigger('swoole.rpc.Close', $args);
        }, $fd);
    }

    public function onConnect(Server $server, int $fd, int $reactorId)
    {
        $args = func_get_args();
        $this->runInSandbox(function (Event $event) use ($args) {
            $event->trigger('swoole.rpc.Connect', $args);
        }, $fd, true);
    }

    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
        $this->waitCoordinator('workerStart');

        $this->recv($server, $fd, $data, function ($data) use ($fd) {
            $this->runInSandbox(function (App $app, Dispatcher $dispatcher) use ($fd, $data) {
                $dispatcher->dispatch($app, $fd, $data);
            }, $fd, true);
        });
    }

    protected function bindRpcDispatcher()
    {
        $services = $this->getConfig('rpc.server.services', []);
        $middleware = $this->getConfig('rpc.server.middleware', []);

        array_push($services, \think\queue\services\queue::class);

        $this->app->make(Dispatcher::class, [$services, $middleware]);
    }

    protected function bindRpcParser()
    {
        $parserClass = $this->getConfig('rpc.server.parser', JsonParser::class);

        $this->app->bind(ParserInterface::class, $parserClass);
        $this->app->make(ParserInterface::class);
    }

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->events = array_merge($this->events ?? [], $this->rpcEvents);
        $this->prepareTables();
        $this->preparePools();
        $this->setSwooleServerListeners();
        $this->prepareRpcServer();
        $this->prepareRpcClient();
    }

    protected function prepareRpcServer()
    {
        $this->onEvent('workerStart', function () {
            $this->bindRpcParser();
            $this->bindRpcDispatcher();
        });
    }

    protected function recv(Server $server, $fd, $data, $callback)
    {
        if ( ! isset($this->channels[ $fd ]) || empty($handle = $this->channels[ $fd ]->pop())) {
            //解析包头
            try {
                [$header, $data] = Packer::unpack($data);

                $this->channels[ $fd ] = new Channel($header);
            } catch (Throwable $e) {
                //错误的包头
                Coroutine::create($callback, Error::make(Dispatcher::INVALID_REQUEST, $e->getMessage()));

                return $server->close($fd);
            }

            $handle = $this->channels[ $fd ]->pop();
        }

        $result = $handle->write($data);

        if ( ! empty($result)) {
            Coroutine::create($callback, $result);
            $this->channels[ $fd ]->close();
        } else {
            $this->channels[ $fd ]->push($handle);
        }

        if ( ! empty($data)) {
            $this->recv($server, $fd, $data, $callback);
        }
    }
}
