<?php

/**
 * Created by PhpStorm.
 * User: xiangdong
 * Date: 16/9/26
 * Time: 上午9:42
 */
abstract class  Paxos
{
    const REQUEST_CLI = 1, REQUEST_ACCEPTOR_AGREE = 2, REQUEST_ACCEPTOR_REFUSE = 3;
    const REQUEST_CONFIRM = 4, REQUEST_CONFIRM_AGREE = 5, REQUEST_CONFIRM_REFUSE = 6;
    const REQUEST_KEEP_ALIVE = 9;
    const EOF = "\r\n\r\n";

    abstract function onConnect($serv, $fd);

    abstract function onReceive(swoole_server $serv, $fd, $from_id, $data);

    abstract function onClose($serv, $fd);

    function start()
    {
        $this->server->on('Connect', array($this, 'onConnect'));
        $this->server->on('Receive', array($this, 'onReceive'));
        $this->server->on('Close', array($this, 'onClose'));
        $this->server->start();
    }

    /**
     * 解析包头
     *
     * @param $data
     * @return array|string
     */
    public function unPack($data)
    {
        $header = substr($data, 0, 16);
        $header = unpack('Ntime/Nip/Ntype/NlogId', $header);
        return $header;
    }

    public function doPack($time, $ip, $type, $logId)
    {
        return pack('N4', $time, $ip, $type, $logId);
    }
}