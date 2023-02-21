<?php

namespace think\queue\event;

use think\queue\Job;

class JobFailed
{
    /** @var string */
    public $connection;

    /** @var \Exception */
    public $exception;

    /** @var Job */
    public $job;

    public function __construct($connection, $job, $exception)
    {
        $this->connection = $connection;
        $this->job = $job;
        $this->exception = $exception;
    }
}
