<?php
/**
 * Created by PhpStorm.
 * User: xiangdong
 * Date: 16/9/27
 * Time: 下午2:11
 */
require "../lib/autoload.php";
$config = [
    'swoole' => [
        'package_eof' => "\r\n\r\n",
        'open_eof_check' => true,
        'open_eof_split' => true,
        'worker_num' => 1,
        'dispatch_mode' => 3,
        'package_max_length' => 1024 * 1024 * 2, //2M
    ],
    'job_ip' => '127.0.0.1:9500',
    'acceptors' => [
        '127.0.0.1:9503',
        '127.0.0.1:9603',
        '127.0.0.1:9703',
    ]
];
$server = new Job($config);
$server->start();