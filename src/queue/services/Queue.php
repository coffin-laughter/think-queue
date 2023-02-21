<?php
/**
 * FileName: queue.php
 * ==============================================
 * Copy right 2016-2023
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 * @author: coffin_laughter | <chuanshuo_yongyuan@163.com>
 * @date  : 2023-02-21 09:25
 */

declare(strict_types = 1);

namespace think\queue\services;

use think\App;

class Queue implements \think\queue\interfaces\Queue
{
    /**
     * @var App
     */
    protected $app;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    /**
     * 推送Job到队列
     *
     * @param string $job   任务名称
     * @param array  $data  任务参数
     * @param int    $delay 延时
     * @param mixed  $queue 目标队列
     */
    public function push(string $job, array $data, int $delay, $queue = null)
    {
        $jobClassic = ($this->app->config->get('queue.job_namespace') ?: 'job') . '\\' . trim($job);
        if (\class_exists($jobClassic)) {
            if ($delay > 0) {
                \think\facade\Queue::later($delay, $job, $data, $queue);
            } else {
                \think\facade\Queue::push($job, $data, $queue);
            }
        }
    }
}
