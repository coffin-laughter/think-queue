<?php

namespace think\queue;

use think\Queue;
use think\helper\Arr;
use think\helper\Str;
use think\queue\command\Work;
use think\queue\command\Retry;
use think\queue\command\Table;
use think\queue\command\Listen;
use think\queue\command\Restart;
use think\queue\command\ListFailed;
use think\queue\command\FailedTable;
use think\queue\command\FlushFailed;
use think\queue\command\ForgetFailed;

class Service extends \think\Service
{
    public function boot()
    {
        $this->commands([
            FailedJob::class,
            Table::class,
            FlushFailed::class,
            ForgetFailed::class,
            ListFailed::class,
            Retry::class,
            Work::class,
            Restart::class,
            Listen::class,
            FailedTable::class,
        ]);
    }

    public function register()
    {
        $this->app->bind('queue', Queue::class);
        $this->app->bind('queue.failer', function () {
            $config = $this->app->config->get('queue.failed', []);

            $type = Arr::pull($config, 'type', 'none');

            $class = false !== strpos($type, '\\') ? $type : '\\think\\queue\\failed\\' . Str::studly($type);

            return $this->app->invokeClass($class, [$config]);
        });
    }
}
