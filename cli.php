<?php
$time = time();
$ip = ip2long('255.255.255.255');

$header = pack('N4', $time, $ip, 1, 0);
$body = "123";
$data = $header . $body;

$ips = ['127.0.0.1:9500', '127.0.0.1:9501', '127.0.0.1:9502'];
for ($i = 0; $i < 20; $i++) {
    shuffle($ips);
    $ip = $ips[0];
    $ip = explode(':', $ip);
    $client = new swoole_client(SWOOLE_SOCK_TCP);
    if (!$client->connect($ip[0], $ip[1], -1)) {
        exit("connect failed. Error: {$client->errCode}\n");
    }
    $client->send($data . "\r\n\r\n");
    echo $client->recv();
    $client->close();
}

