<?php

namespace think\queue\event;

class WorkerStopping
{
    /**
     * @var bool
     */
    public $isIdle;

    /**
     * @var string
     */
    public $msg;
    public $queue;
    /**
     * The exit status.
     *
     * @var int
     */
    public $status;

    /**
     * Create a new event instance.
     *
     * @param int    $status
     * @param string $msg
     *
     * @return void
     */
    public function __construct(int $status = 0, string $msg = '', $isIdle = false, $queue = '')
    {
        $this->msg = $msg;
        $this->status = $status;
        $this->isIdle = $isIdle;
        $this->queue = $queue;
    }
}
