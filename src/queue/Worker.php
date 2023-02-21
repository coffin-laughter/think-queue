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

namespace think\queue;

use Exception;
use Throwable;
use think\Cache;
use think\Event;
use think\Queue;
use Carbon\Carbon;
use RuntimeException;
use think\exception\Handle;
use think\queue\event\JobFailed;
use think\queue\event\JobProcessed;
use think\queue\event\JobProcessing;
use think\queue\event\WorkerStopping;
use think\queue\event\JobExceptionOccurred;
use think\queue\exception\MaxAttemptsExceededException;

class Worker
{
    /**
     * Indicates if the worker is paused.
     *
     * @var bool
     */
    public $paused = false;

    /**
     * Indicates if the worker should exit.
     *
     * @var bool
     */
    public $shouldQuit = false;

    /** @var Cache */
    protected $cache;
    /** @var Event */
    protected $event;
    /** @var Handle */
    protected $handle;
    protected $idleTime;
    /** @var Queue */
    protected $queue;

    protected $runTime;

    public function __construct(Queue $queue, Event $event, Handle $handle, Cache $cache = null)
    {
        $this->queue = $queue;
        $this->event = $event;
        $this->handle = $handle;
        $this->cache = $cache;
        $this->runTime = time();
        $this->idleTime = 0;
    }

    /**
     * @param string $connection
     * @param string $queue
     * @param int    $delay
     * @param int    $sleep
     * @param int    $maxTries
     * @param int    $memory
     * @param int    $timeout
     */
    public function daemon(string $connection, string $queue, int $delay = 0, int $sleep = 3, int $maxTries = 0, int $memory = 64, int $timeout = 60)
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            $job = $this->getNextJob(
                $this->queue->connection($connection),
                $queue
            );

            if ($this->supportsAsyncSignals()) {
                $this->registerTimeoutHandler($job, $timeout);
            }

            if ($job) {
                $this->runTime = time();
                $this->runJob($job, $connection, $maxTries, $delay);
            } else {
                $this->idleTime = (time() - $this->runTime);
                $this->sleep($sleep);
            }

            $this->stopIfNecessary($queue, $lastRestart, $memory);
        }
    }

    /**
     * Kill the process.
     *
     * @param int $status
     *
     * @return void
     */
    public function kill($status = 0)
    {
        $this->event->trigger(new WorkerStopping($status));

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param int $memoryLimit
     *
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Process a given job from the queue.
     *
     * @param     $connection
     * @param Job $job
     * @param int $maxTries
     * @param int $delay
     *
     * @throws Throwable
     */
    public function process($connection, $job, $maxTries = 0, $delay = 0)
    {
        try {
            $this->event->trigger(new JobProcessing($connection, $job));

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $connection,
                $job,
                (int) $maxTries
            );

            $job->fire();

            $this->event->trigger(new JobProcessed($connection, $job));
        } catch (Exception|Throwable $e) {
            try {
                if ( ! $job->hasFailed()) {
                    $this->markJobAsFailedIfWillExceedMaxAttempts($connection, $job, (int) $maxTries, $e);
                }

                $this->event->trigger(new JobExceptionOccurred($connection, $job, $e));
            } finally {
                if ( ! $job->isDeleted() && ! $job->isReleased() && ! $job->hasFailed()) {
                    \think\facade\Cache::set("QUEUE:{$job->getJobId()}", $job->attempts(), 3600);
                    $job->release($delay);
                }
            }

            throw $e;
        }
    }

    /**
     * 执行下个任务
     *
     * @param string $connection
     * @param string $queue
     * @param int    $delay
     * @param int    $sleep
     * @param int    $maxTries
     *
     * @return void
     * @throws Exception
     */
    public function runNextJob($connection, $queue, $delay = 0, $sleep = 3, $maxTries = 0)
    {
        $job = $this->getNextJob($this->queue->connection($connection), $queue);

        if ($job) {
            $this->runJob($job, $connection, $maxTries, $delay);
        } else {
            $this->sleep($sleep);
        }
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int $seconds
     *
     * @return void
     */
    public function sleep($seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param int    $status
     * @param string $msg
     * @param bool   $isIdle
     * @param string $queue
     *
     * @return void
     */
    public function stop(int $status = 0, string $msg = '', bool $isIdle = false, string $queue = '')
    {
        $this->event->trigger(new WorkerStopping($status, $msg, $isIdle, $queue));
//        $this->event->trigger(new WorkerStopping($status));

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * @param string    $connection
     * @param Job       $job
     * @param Exception $e
     */
    protected function failJob($connection, $job, $e)
    {
        $job->markAsFailed();

        if ($job->isDeleted()) {
            return;
        }

        try {
            $job->delete();

            $job->failed($e);
        } finally {
            $this->event->trigger(new JobFailed(
                $connection,
                $job,
                $e ?: new RuntimeException('ManuallyFailed')
            ));
        }
    }

    /**
     * 获取下个任务
     *
     * @param Connector $connector
     * @param string    $queue
     *
     * @return Job
     */
    protected function getNextJob($connector, $queue)
    {
        try {
            foreach (explode(',', $queue) as $queue) {
                if ( ! is_null($job = $connector->pop($queue))) {
                    return $job;
                }
            }
        } catch (Exception|Throwable $e) {
            $this->handle->report($e);
            $this->sleep(1);
        }
    }

    /**
     * 获取队列重启时间
     * @return mixed
     */
    protected function getTimestampOfLastQueueRestart()
    {
        if ($this->cache) {
            return $this->cache->get('think:queue:restart');
        }
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals()
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGUSR2, function () {
            $this->paused = true;
        });

        pcntl_signal(SIGCONT, function () {
            $this->paused = false;
        });
    }

    /**
     * @param string $connection
     * @param Job    $job
     * @param int    $maxTries
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connection, $job, $maxTries)
    {
        $maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        $timeoutAt = $job->timeoutAt();

        if ($timeoutAt && Carbon::now()->getTimestamp() <= $timeoutAt) {
            return;
        }

        if ( ! $timeoutAt && (0 === $maxTries || $job->attempts() <= $maxTries)) {
            return;
        }

        $this->failJob($connection, $job, $e = new MaxAttemptsExceededException(
            $job->getName() . " 执行次数超过最大重试限制({$maxTries}次)或者执行时间过长"
        ));

        throw $e;
    }

    /**
     * @param string    $connection
     * @param Job       $job
     * @param int       $maxTries
     * @param Exception $e
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts($connection, $job, $maxTries, $e)
    {
        $maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($job->timeoutAt() && $job->timeoutAt() <= Carbon::now()->getTimestamp()) {
            $this->failJob($connection, $job, $e);
        }

        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($connection, $job, $e);
        }
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param int|null $lastRestart
     *
     * @return bool
     */
    protected function queueShouldRestart($lastRestart)
    {
        return $this->getTimestampOfLastQueueRestart() != $lastRestart;
    }

    /**
     * Register the worker timeout handler.
     *
     * @param Job|null $job
     * @param int      $timeout
     *
     * @return void
     */
    protected function registerTimeoutHandler($job, $timeout)
    {
        pcntl_signal(SIGALRM, function () {
            $this->kill(1);
        });

        pcntl_alarm(
            max($this->timeoutForJob($job, $timeout), 0)
        );
    }

    /**
     * 执行任务
     *
     * @param Job    $job
     * @param string $connection
     * @param int    $maxTries
     * @param int    $delay
     *
     * @return void
     */
    protected function runJob($job, $connection, $maxTries, $delay)
    {
        try {
//            $pid = pcntl_fork();
//            if ($pid === -1) {
//                $this->process($connection, $job, $maxTries, $delay);
//            } elseif ($pid) {
//                pcntl_wait($status, WNOHANG);
//            } else {
//                try {
//                    $this->process($connection, $job, $maxTries, $delay);
//                } finally {
//                    posix_kill(posix_getpid() , SIGTERM);//自杀
//                }
//            }
            $this->process($connection, $job, $maxTries, $delay);
        } catch (Exception|Throwable $e) {
            $this->handle->report($e);
        }
    }

    protected function stopIfNecessary($queue, $lastRestart, $memory)
    {
        if ($this->idleTime >= 30) {
            $this->stop(0, '空闲超过30秒', true, $queue);
        }
        if ($this->shouldQuit || $this->queueShouldRestart($lastRestart)) {
            $this->stop();
        } elseif ($this->memoryExceeded($memory)) {
            $this->stop(12);
        }
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals()
    {
        return extension_loaded('pcntl');
    }

    /**
     * Get the appropriate timeout for the given job.
     *
     * @param Job|null $job
     * @param int      $timeout
     *
     * @return int
     */
    protected function timeoutForJob($job, $timeout)
    {
        return $job && ! is_null($job->timeout()) ? $job->timeout() : $timeout;
    }
}
