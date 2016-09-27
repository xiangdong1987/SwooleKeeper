<?php
$time = time();
$ip = ip2long('255.255.255.255');
var_dump($time . $ip);
var_dump(bindec(decbin($time) . decbin($ip)));
$header = pack('N4', $time, $ip, 1,0);
$body = "123";
$data = $header . $body;
$client = new swoole_client(SWOOLE_SOCK_TCP);
if (!$client->connect('127.0.0.1', 9501, -1)) {
    exit("connect failed. Error: {$client->errCode}\n");
}
$client->send($data . "\r\n\r\n");
echo $client->recv();
$client->close();