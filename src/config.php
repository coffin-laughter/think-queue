<?php

return [
    'default'       => 'sync',
    'connections'   => [
        'sync'     => [
            'type' => 'sync',
        ],
        'database' => [
            'type'       => 'database',
            'queue'      => 'default',
            'table'      => 'jobs',
            'connection' => null,
        ],
        'redis'    => [
            'type'       => 'redis',
            'queue'      => 'default',
            'host'       => '127.0.0.1',
            'port'       => 6379,
            'password'   => '',
            'select'     => 0,
            'timeout'    => 0,
            'persistent' => false,
        ],
        'amqp'     => [
            'type'       => 'amqp',
            'host'       => '127.0.0.1',
            'username'   => 'guest',
            'password'   => 'guest',
            'port'       => 5672,
            'vhost'      => '/',
            'exchange'   => '/',
            'queue'      => 'default',
            'timeout'    => 0,
            'auto_ack'   => false,
            'persistent' => false,
        ],
    ],
    'failed'        => [
        'type'  => 'none',
        'table' => 'failed_jobs',
    ],
    'job_namespace' => '',
];
