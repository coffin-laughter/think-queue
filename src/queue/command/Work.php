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

use think\queue\Job;
use think\facade\Cache;
use think\queue\Worker;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\console\input\Option;
use think\queue\event\JobFailed;
use think\console\input\Argument;
use think\queue\event\JobProcessed;
use think\queue\event\JobProcessing;
use think\queue\event\WorkerStopping;
use think\queue\event\JobExceptionOccurred;

class Work extends Command
{
    /**
     * The queue worker instance.
     * @var Worker
     */
    protected $worker;

    public function __construct(Worker $worker)
    {
        parent::__construct();
        $this->worker = $worker;
    }

    /**
     * Execute the console command.
     *
     * @param Input  $input
     * @param Output $output
     *
     * @return int|void|null
     * @throws \Exception
     */
    public function execute(Input $input, Output $output)
    {
        $connection = $input->getArgument('connection') ?: $this->app->config->get('queue.default');

        $queue = $input->getOption('queue') ?: $this->app->config->get("queue.connections.{$connection}.queue", 'default');
        $delay = $input->getOption('delay');
        $sleep = $input->getOption('sleep');
        $tries = $input->getOption('tries');

        $this->listenForEvents();

        if ($input->getOption('once')) {
            $this->worker->runNextJob($connection, $queue, $delay, $sleep, $tries);
        } else {
            $memory = $input->getOption('memory');
            $timeout = $input->getOption('timeout');
            $this->worker->daemon($connection, $queue, $delay, $sleep, $tries, $memory, $timeout);
        }
    }

    protected function configure()
    {
        $this->setName('queue:work')
            ->addArgument('connection', Argument::OPTIONAL, 'The name of the queue connection to work', null)
            ->addOption('queue', null, Option::VALUE_OPTIONAL, 'The queue to listen on')
            ->addOption('once', null, Option::VALUE_NONE, 'Only process the next job on the queue')
            ->addOption('delay', null, Option::VALUE_OPTIONAL, 'Amount of time to delay failed jobs', 0)
            ->addOption('force', null, Option::VALUE_NONE, 'Force the worker to run even in maintenance mode')
            ->addOption('memory', null, Option::VALUE_OPTIONAL, 'The memory limit in megabytes', 64)
            ->addOption('timeout', null, Option::VALUE_OPTIONAL, 'The number of seconds a child process can run', 30)
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3)
            ->addOption('tries', null, Option::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 10)
            ->addOption('start', null, Option::VALUE_OPTIONAL, 'The float of microseconds a child process start time', 0)
            ->setDescription('Process the next job on a queue');
    }

    /**
     * 注册事件
     */
    protected function listenForEvents()
    {
        $this->app->event->listen(JobProcessing::class, function (JobProcessing $event) {
            $this->writeOutput($event->job, 'starting');
        });

        $this->app->event->listen(JobProcessed::class, function (JobProcessed $event) {
            $this->writeOutput($event->job, 'success');
        });

        $this->app->event->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) {
            $this->writeOutput($event->job, 'exception');
        });

        $this->app->event->listen(JobFailed::class, function (JobFailed $event) {
            $this->writeOutput($event->job, 'failed');

            $this->logFailedJob($event);
        });
        $this->app->event->listen(WorkerStopping::class, function (WorkerStopping $event) {
            $cacheKey = strtoupper($event->queue) . ':QUEUE:PROCESS:NUM';
            $processNum = Cache::get($cacheKey);
            if ( ! empty($event->queue) && intval($processNum) > 0) {
                Cache::dec($cacheKey, 1);
            }

//            $this->output->writeln(sprintf(
//                "<comment>[%s][%s]</comment> %s",
//                date('Y-m-d H:i:s'),
//                'QUEUE:PROCESS',
//                "CLOSE:IdleExit"
//            ));
        });
    }

    /**
     * 记录失败任务
     *
     * @param JobFailed $event
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->app['queue.failer']->log(
            $event->connection,
            $event->job->getQueue(),
            $event->job->getRawBody(),
            $event->exception
        );
    }

    /**
     * Write the status output for the queue worker.
     *
     * @param Job $job
     * @param     $status
     */
    protected function writeOutput(Job $job, $status)
    {
        switch ($status) {
            case 'starting':
                $this->writeStatus($job, 'Processing', 'comment');

                break;
            case 'success':
                $this->writeStatus($job, 'Processed', 'info');

                break;
            case 'exception':
                $this->writeStatus($job, 'Exception', 'highlight');

                break;
            case 'failed':
                $this->writeStatus($job, 'Failed', 'error');

                break;
            case 'idle':
                $this->writeStatus($job, 'IdleClose', 'comment');

                break;
        }
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param Job    $job
     * @param string $status
     * @param string $type
     *
     * @return void
     */
    protected function writeStatus(Job $job, $status, $type)
    {
//        $this->output->writeln(sprintf(
//            "<{$type}>[%s][%s] %s</{$type}> %s",
//            date('Y-m-d H:i:s'),
//            $job->getJobId(),
//            str_pad("{$status}:", 11),
//            $job->getName()
//        ));
    }
}
