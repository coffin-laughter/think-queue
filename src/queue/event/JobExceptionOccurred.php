<?php

namespace think\queue\event;

use Exception;
use think\queue\Job;

class JobExceptionOccurred
{
    /**
     * The connection name.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The exception instance.
     *
     * @var Exception
     */
    public $exception;

    /**
     * The job instance.
     *
     * @var Job
     */
    public $job;

    /**
     * Create a new event instance.
     *
     * @param string    $connectionName
     * @param Job       $job
     * @param Exception $exception
     *
     * @return void
     */
    public function __construct($connectionName, $job, $exception)
    {
        $this->job = $job;
        $this->exception = $exception;
        $this->connectionName = $connectionName;
    }
}
