<?php
/**
 * FileName: queue.php
 * ==============================================
 * Copy right 2016-2023
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 * @author: coffin_laughter | <chuanshuo_yongyuan@163.com>
 * @date  : 2023-02-21 09:23
 */

declare(strict_types = 1);

namespace think\queue\interfaces;

interface queue
{
    /**
     * 推送Job到队列
     *
     * @param string $job   任务名称
     * @param array  $data  任务参数
     * @param int    $delay 延时
     * @param mixed  $queue 目标队列
     *
     * @return mixed
     */
    public function push(string $job, array $data, int $delay, $queue);
}
