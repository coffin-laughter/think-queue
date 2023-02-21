<?php
/**
 * FileName: Job.php
 * ==============================================
 * Copy right 2016-2022
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 * @author: coffin_laughter | <chuanshuo_yongyuan@163.com>
 * @date  : 2022-09-02 09:06
 */

namespace coffin\queue\job;

use Exception;
use think\App;
use think\facade\Log;
use function Co\run;

abstract class Job
{
    /**
     * @var App
     */
    protected $app;

    /**
     * The name of the connection the job belongs to.
     */
    protected $connection;

    /**
     * Indicates if the job has been deleted.
     * @var bool
     */
    protected $deleted = false;

    /**
     * Indicates if the job has failed.
     *
     * @var bool
     */
    protected $failed = false;

    /**
     * The job handler instance.
     * @var mixed
     */
    protected $instance;

    /**
     * The name of the queue the job belongs to.
     * @var string
     */
    protected $queue;

    /**
     * Indicates if the job has been released.
     * @var bool
     */
    protected $released = false;

    /**
     * Get the number of times the job has been attempted.
     * @return int
     */
    abstract public function attempts();

    /**
     * Delete the job from the queue.
     * @return void
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param Exception $e
     *
     * @return void
     */
    public function failed($e)
    {
        $this->markAsFailed();

        $payload = $this->payload();

        list($class, $method) = $this->parseJob($payload['job']);

        if (method_exists($this->instance = $this->resolve($class), 'failed')) {
            $this->instance->failed($payload['data'], $e);
        }
    }

    /**
     * Fire the job.
     * @return void
     */
    public function fire()
    {
        $payload = $this->payload();

        list($class, $method) = $this->parseJob($payload['job']);

        $this->instance = $this->resolve($class);
        if ($this->instance) {
            $this->instance->{$method}($this, $payload['data']);
        }
    }

    /**
     * Get the name of the connection the job belongs to.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    abstract public function getJobId();

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName()
    {
        return $this->payload()['job'];
    }

    /**
     * Get the name of the queue the job belongs to.
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Get the raw body string for the job.
     * @return string
     */
    abstract public function getRawBody();

    /**
     * Determine if the job has been marked as a failure.
     *
     * @return bool
     */
    public function hasFailed()
    {
        return $this->failed;
    }

    /**
     * Determine if the job has been deleted.
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Determine if the job has been deleted or released.
     * @return bool
     */
    public function isDeletedOrReleased()
    {
        return $this->isDeleted() || $this->isReleased();
    }

    /**
     * Determine if the job was released back into the queue.
     * @return bool
     */
    public function isReleased()
    {
        return $this->released;
    }

    /**
     * Mark the job as "failed".
     *
     * @return void
     */
    public function markAsFailed()
    {
        $this->failed = true;
    }

    /**
     * Get the number of times to attempt a job.
     *
     * @return int|null
     */
    public function maxTries()
    {
        return $this->payload()['maxTries'] ?? null;
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload()
    {
        return json_decode($this->getRawBody(), true);
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0)
    {
        $this->released = true;
    }

    /**
     * Get the number of seconds the job can run.
     *
     * @return int|null
     */
    public function timeout()
    {
        return $this->payload()['timeout'] ?? null;
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return int|null
     */
    public function timeoutAt()
    {
        return $this->payload()['timeoutAt'] ?? null;
    }

    /**
     * Parse the job declaration into class and method.
     *
     * @param string $job
     *
     * @return array
     */
    protected function parseJob($job)
    {
        $segments = explode('@', $job);

        return count($segments) > 1 ? $segments : [$segments[0], 'fire'];
    }

    /**
     * Resolve the given job handler.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function resolve($name)
    {
        if (strpos($name, '\\') === false) {
            if (strpos($name, '/') === false) {
                $app = '';
            } else {
                list($app, $name) = explode('/', $name, 2);
            }

            $name = ($this->app->config->get('app.app_namespace') ?: 'app\\') . ($app ? strtolower($app) . '\\' : '') . 'job\\' . $name;
        }
        if ( ! class_exists($name)) {
            Log::queue("[RESOLVE][ERROR]:Class {$name} not exists!");
        }

        return $this->app->make($name);
    }
}
