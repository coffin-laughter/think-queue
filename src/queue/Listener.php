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

use Closure;
use think\App;
use think\facade\Log;
use think\facade\Cache;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class Listener
{
    /**
     * @var string
     */
    public $commandPath;

    /**
     * @var \Closure|null
     */
    protected $outputHandler;

    /**
     * @var string
     */
    protected $workerCommand;

    /**
     * @param string $commandPath
     */
    public function __construct($commandPath)
    {
        $this->commandPath = $commandPath;
    }

    public static function __make(App $app): Listener
    {
        return new self($app->getRootPath());
    }

    /**
     * @param int    $type
     * @param string $line
     *
     * @return void
     */
    public function handleWorkerOutput(int $type, string $line)
    {
        if (isset($this->outputHandler)) {
            call_user_func($this->outputHandler, $type, $line);
        }
    }

    /**
     * @param string $connection
     * @param string $queue
     * @param int    $maximum
     * @param int    $delay
     * @param int    $sleep
     * @param int    $maxTries
     * @param int    $memory
     * @param int    $timeout
     *
     * @return void
     */
    public function listen(string $connection, int $maximum, string $queue, int $delay = 0, int $sleep = 300, int $maxTries = 0, int $memory = 64, int $timeout = 0)
    {
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGCHLD, SIG_IGN);
        }

        try {
            while (true) {
                $cacheKey = strtoupper($queue) . ':QUEUE:PROCESS:NUM';
                $execKey = sprintf("ps aux | grep 'queue:work amqp --queue=%s' | wc -l", strtoupper($queue));
                if (exec($execKey, $result)) {
                    $processNum = intval($result[0]) - 1;
                    Cache::set($cacheKey, $processNum);
                } else {
                    $processNum = Cache::get($cacheKey);
                }
                if ($processNum < $maximum) {
                    $process = $this->makeProcess($connection, $queue, $delay, $sleep, $maxTries, $memory, $timeout);
                    $this->runProcess($process, $memory);
                    Cache::inc($cacheKey);
                }
                sleep(2);
            }
        } catch (\Exception $e) {
            Log::debug('ProcessException: ' . $e->getMessage());
        }
    }

    /**
     * @param string $connection
     * @param string $queue
     * @param int    $delay
     * @param int    $sleep
     * @param int    $maxTries
     * @param int    $memory
     * @param int    $timeout
     *
     * @return Process
     */
    public function makeProcess(string $connection, string $queue, int $delay, int $sleep, int $maxTries, int $memory, int $timeout): Process
    {
        $command = array_filter([
            $this->phpBinary(),
            'think',
            'queue:work',
            $connection,
//            '--once',
            "--queue={$queue}",
            "--delay={$delay}",
            "--memory={$memory}",
            "--sleep={$sleep}",
            "--tries={$maxTries}",
            '--start=' . microtime(true),
        ], function ($value) {
            return ! is_null($value);
        });

        return new Process($command, $this->commandPath, null, null, $timeout);
    }

    /**
     * @param int $memoryLimit
     *
     * @return bool
     */
    public function memoryExceeded(int $memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Get the PHP binary.
     *
     * @return string
     */
    public function phpBinary(): string
    {
        return (new PhpExecutableFinder())->find(false);
    }

    /**
     * @param Process $process
     * @param int     $memory
     */
    public function runProcess(Process $process, int $memory)
    {
        $process->start(function ($type, $line) {
            $this->handleWorkerOutput(intval($type), $line);
        });

        if ($this->memoryExceeded($memory)) {
            $this->stop();
        }
    }

    /**
     * @param \Closure $outputHandler
     *
     * @return void
     */
    public function setOutputHandler(Closure $outputHandler)
    {
        $this->outputHandler = $outputHandler;
    }

    /**
     * @return void
     */
    public function stop()
    {
        die;
    }
}
