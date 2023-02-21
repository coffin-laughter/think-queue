<?php

namespace think\queue\event;

use think\queue\Job;
use think\facade\Cache;

class JobProcessed
{
    /** @var string */
    public $connection;

    /** @var Job */
    public $job;

    public function __construct($connection, Job $job)
    {
        $this->connection = $connection;
        $this->job = $job;
        Cache::set("QUEUE:{$job->getJobId()}", $job->attempts(), 3600);
        //TODO: 操作向Amqp发送确认消息
    }
}
