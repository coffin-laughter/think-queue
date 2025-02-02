<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\queue\command;

use think\facade\Log;
use think\facade\Cache;
use think\console\Input;
use think\console\Output;
use think\queue\Listener;
use think\console\Command;
use think\console\input\Option;
use think\console\input\Argument;
use Symfony\Component\Process\Process;

class Listen extends Command
{
    /** @var  Listener */
    protected $listener;

    public function __construct(Listener $listener)
    {
        parent::__construct();
        $this->listener = $listener;
        $this->listener->setOutputHandler(function ($type, $line) {
            $this->output->write($line);
        });
    }

    public function execute(Input $input, Output $output)
    {
        $connection = $input->getArgument('connection') ?: $this->app->config->get('queue.default');

        $queue = $input->getOption('queue') ?: $this->app->config->get("queue.connections.{$connection}.queue", 'default');
        $maximum = $input->getOption('maximum');
        $delay = $input->getOption('delay');
        $memory = $input->getOption('memory');
        $timeout = $input->getOption('timeout');
        $sleep = $input->getOption('sleep');
        $tries = $input->getOption('tries');
        $rpc = $input->getOption('rpc');

        Cache::set(strtoupper($queue) . ':QUEUE:PROCESS:NUM', 0);

        if (boolval($rpc)) {
            $this->runRpcService();
        }

        $this->listener->listen($connection, $maximum, $queue, $delay, $sleep, $tries, $memory, $timeout);
    }

    protected function configure()
    {
        $this->setName('queue:listen')
            ->addArgument('connection', Argument::OPTIONAL, 'The name of the queue connection to work', null)
            ->addOption('queue', null, Option::VALUE_OPTIONAL, 'The queue to listen on', null)
            ->addOption('delay', null, Option::VALUE_OPTIONAL, 'Amount of time to delay failed jobs', 10)
            ->addOption('memory', null, Option::VALUE_OPTIONAL, 'The memory limit in megabytes', 64)
            ->addOption('timeout', null, Option::VALUE_OPTIONAL, 'Seconds a job may run before timing out', 60)
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Seconds to wait before checking queue for jobs', 3)
            ->addOption('maximum', null, Option::VALUE_REQUIRED, 'Maximum number of processes', 200)
            ->addOption('rpc', null, Option::VALUE_REQUIRED, 'Start an RPC service.', false)
            ->addOption('tries', null, Option::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 10)
            ->setDescription('Listen to a given queue');
    }

    private function runRpcService()
    {
        $command = array_filter([$this->listener->phpBinary(), 'think', 'swoole:rpc'], function ($value) {
            return ! is_null($value);
        });

        $rpcProcess = new Process($command, $this->listener->commandPath, null, null, 0);
        Log::debug("[Queue RPC Service]: {$rpcProcess->getPid()}");
        $rpcProcess->start(function ($type, $line) {
            $this->listener->handleWorkerOutput($type, $line);
        });
    }
}
