<?php
/**
 * FileName: Amqp.php
 * ==============================================
 * Copy right 2016-2023
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 * @author: coffin_laughter | <chuanshuo_yongyuan@163.com>
 * @date  : 2023-02-21 09:23
 */

declare(strict_types = 1);

namespace think\queue\job;

use think\App;
use think\queue\Job;
use PhpAmqpLib\Message\AMQPMessage;
use think\queue\connector\Amqp as AmqpQueue;

class Amqp extends Job
{
    /**
     * The amqp queue instance.
     * @var AmqpQueue
     */
    protected $amqp;

    /**
     * The JSON decoded version of "$job".
     *
     * @var array
     */
    protected $decoded;

    /**
     * The database job payload.
     * @var Object
     */
    protected $job;

    /**
     * @var AMQPMessage
     */
    protected $msg;

    public function __construct(App $app, AmqpQueue $amqp, $job, $connection, $queue, $msg)
    {
        $this->app = $app;
        $this->job = $job;
        $this->queue = $queue;
        $this->connection = $connection;
        $this->amqp = $amqp;
        $this->msg = $msg;

        $this->decoded = $this->payload();
    }

    /**
     * Get the number of times the job has been attempted.
     * @return int
     */
    public function attempts()
    {
        return ($this->decoded['attempts'] ?? null) + 1;
    }

    /**
     * 删除任务
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->amqp->deleteReserved($this->queue, $this, $this->msg);
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->decoded['id'] ?? null;
    }

    /**
     * Get the raw body string for the job.
     * @return string
     */
    public function getRawBody()
    {
        return $this->job;
    }

    /**
     * 重新发布任务
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->amqp->deleteAndRelease($this->queue, $this, $this->msg, $delay);
    }
}
