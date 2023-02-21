<?php
/**
 * FileName: Amqp.php
 * ==============================================
 * Copy right 2016-2023
 * ----------------------------------------------
 * This is not a free software, without any authorization is not allowed to use and spread.
 * ==============================================
 * @author: coffin_laughter | <chuanshuo_yongyuan@163.com>
 * @date  : 2023-02-21 09:21
 */

declare(strict_types = 1);

namespace think\queue\connector;

use Exception;
use think\helper\Str;
use think\facade\Cache;
use think\queue\Connector;
use PhpAmqpLib\Wire\AMQPTable;
use think\queue\InteractsWithTime;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use think\queue\job\Amqp as AmqpJob;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class Amqp extends Connector
{
    use InteractsWithTime;

    protected $autoAck = false;

    /**
     * The maximum number of seconds to block for a job.
     *
     * @var int|null
     */
    protected $blockFor = null;

    /** @var  AMQPChannel */
    protected $channel;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    protected $exchange = '';

    protected $msg = null;

    /**
     * The expiration time of a job.
     *
     * @var int|null
     */
    protected $retryAfter = 60;

    private static $instance;

    public function __construct(AMQPChannel $channel, $queue = 'default', $exchange = 'default', $retryAfter = 60, $blockFor = null, $autoAck = false)
    {
        $this->channel = $channel;
        $this->default = $queue;
        $this->retryAfter = $retryAfter;
        $this->blockFor = $blockFor;
        $this->autoAck = $autoAck;
        $this->exchange = $exchange;
    }

    public static function __make($config): Amqp
    {
        try {
            if ( ! extension_loaded('sockets')) {
                throw new Exception('Sockets扩展未安装');
            }
            if ( ! self::$instance instanceof AMQPStreamConnection) {
                self::$instance = new AMQPStreamConnection($config['host'], $config['port'], $config['username'], $config['password'], $config['vhost']);
            }

            $channel = self::$instance->channel();

            return new self($channel, $config['queue'], $config['exchange'], $config['retry_after'] ?? 60, $config['block_for'] ?? null, $config['auto_ack']);
        } catch (\Exception $e) {
            self::$instance = new AMQPStreamConnection($config['host'], $config['port'], $config['username'], $config['password'], $config['vhost']);
            $channel = self::$instance->channel();

            return new self($channel, $config['queue'], $config['exchange'], $config['retry_after'] ?? 60, $config['block_for'] ?? null, $config['auto_ack']);
        }
    }

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param string      $queue
     * @param AmqpJob     $job
     * @param AMQPMessage $msg
     * @param int         $delay
     *
     * @return void
     */
    public function deleteAndRelease(string $queue, AmqpJob $job, AMQPMessage $msg, int $delay)
    {
        $this->deleteReserved($queue, $job, $msg);

        return $this->pushRaw($msg->getBody(), $queue, [], $delay, true);
    }

    /**
     * 删除任务
     *
     * @param string      $queue
     * @param AmqpJob     $job
     * @param AMQPMessage $msg
     *
     * @return void
     */
    public function deleteReserved(string $queue, AmqpJob $job, AMQPMessage $msg)
    {
        $this->blockFor = null;
        if ( ! $this->autoAck) {
            $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, [], $delay);
    }

    public function pop($queue = null)
    {
        $queueName = $this->getQueue($queue);
//        $exchangeName = "topic.{$queueName}";

        $this->channel->exchange_declare($this->exchange, 'x-delayed-message', false, true, false, false, false, new AMQPTable(['x-delayed-type' => 'direct']));
        $this->channel->queue_declare($queueName, false, true, false, false);
        $this->channel->queue_bind($queueName, $this->exchange);
        $this->channel->basic_qos(0, 1, false);
        $msg = $this->channel->basic_get($queueName, $this->autoAck); //no_ack: 是否自动ACK
        if ( ! empty($msg)) {
            $job = $msg->body;
            //自动确认
            $payload = json_decode($job, true);
            $jobAttempts = Cache::get("QUEUE:{$payload['id']}") ?: 0;
            if ($jobAttempts > $payload['attempts']) {
                $payload['attempts'] = $jobAttempts;
            }
            $job = json_encode($payload);

            return new AmqpJob($this->app, $this, $job, $this->connection, $queue, $msg);
        }

        return false;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = [], $delayTime = 0, $release = false)
    {
        $queueName = $this->getQueue($queue);
//        $exchangeName = "topic.{$queueName}";
        $this->channel->exchange_declare($this->exchange, 'x-delayed-message', false, true, false, false, false, new AMQPTable(['x-delayed-type' => 'direct']));
        $this->channel->queue_declare($queueName, false, true, false, false);
        $this->channel->queue_bind($queueName, $this->exchange);
        $msg = new AMQPMessage($payload);
        if ($delayTime > 0) {
            $msg->set('application_headers', new AMQPTable(['x-delay' => $delayTime > 0 ? intval($delayTime) * 1000 : 5000]));
        }
        $this->channel->basic_publish($msg, $this->exchange);

        $jobId = json_decode($payload, true)['id'];
        if ( ! empty($jobId) && ! $release) {
            \think\facade\Cache::set("QUEUE:{$jobId}", 0, 3600);
        }

        return $jobId ?? null;
    }

    public function size($queue = null)
    {
//        $queue = $this->getQueue($queue);
        return 0;
    }


    protected function createPayloadArray($job, $data = ''): array
    {
        return array_merge(parent::createPayloadArray($job, $data), [
            'id'       => $this->getRandomId(),
            'attempts' => 0,
        ]);
    }

    /**
     * 获取队列名
     *
     * @param string|null $queue
     *
     * @return string
     */
    protected function getQueue(?string $queue): string
    {
        return $queue ?: $this->default;
    }

    /**
     * 随机id
     *
     * @return string
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }

    private function declareAmqp($queueName, $topicName)
    {
    }
}
