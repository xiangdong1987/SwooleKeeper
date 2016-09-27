<?php
/**
 * Created by PhpStorm.
 * User: xiangdong
 * Date: 16/9/22
 * Time: 上午10:10
 */
require 'Paxos.php';
class Acceptor extends Paxos
{
    public $server;

    private $proposeTime = 0;
    private $proposeIp = 0;
    private $max_logId;
    private $hadAccept = 0;
    private $hadLogId = 0;

    function __construct($config)
    {
        if (!$this->server) {
            $ip = explode(':', $config['ip']);
            unset($config['ip']);
            $this->server = new swoole_server($ip[0], $ip[1], SWOOLE_BASE);
        }
        $this->server->set($config);
        return $this->server;
    }

    function onConnect($serv, $fd)
    {
        echo "[#" . posix_getpid() . "]\tClient:Connect.\n";
    }

    function onReceive(swoole_server $serv, $fd, $from_id, $data)
    {
        echo '#' . $serv->worker_id . " recv: " . strlen($data) . "\n";
        $header = $this->unPack($data);
        if ($header) {
            //投票是否可以执行决议
            if ($header['type'] == self::REQUEST_CLI) {
                //同意
                if ($header['time'] . $header['ip'] > $this->proposeTime . $this->proposeIp && $this->hadAccept < $header['time'] . $header['ip']) {
                    $this->proposeTime = $header['time'];
                    $this->proposeIp = $header['ip'];
                    $header_respond = $this->doPack($header['time'], $header['ip'], self::REQUEST_ACCEPTOR_AGREE,$this->max_logId);
                    $this->max_logId = $header['logId'];
                    $this->hadAccept = $header['time'] . $header['ip'];
                    $serv->send($fd, $header_respond . self::EOF);
                    //拒绝
                } else {
                    $header = $this->doPack($this->proposeTime, $this->proposeIp, self::REQUEST_ACCEPTOR_REFUSE, $this->max_logId);
                    $serv->send($fd, $header . self::EOF);
                }
                //确定是否可以执行决议
            } elseif ($header['type'] == self::REQUEST_CONFIRM) {
                if ($header['logId'] >= $this->hadLogId && $header['time'] . $header['ip'] > $this->proposeTime . $this->proposeIp) {
                    //已经接受了一个大于等于它的值就不可以写入重新再来
                    $this->proposeTime = $header['time'];
                    $this->proposeIp = $header['ip'];
                    $header_respond = $this->doPack($header['time'], $header['ip'], self::REQUEST_CONFIRM_REFUSE, $header['logId']);
                    $serv->send($fd, $header_respond . self::EOF);
                } else {
                    //可以执行决议
                    $this->proposeTime = $header['time'];
                    $this->proposeIp = $header['ip'];
                    $header_respond = $this->doPack($header['time'], $header['ip'], self::REQUEST_CONFIRM_AGREE,
                        $this->max_logId);
                    $this->max_logId = $header['logId'];
                    echo "accept logID : $this->max_logId\n";
                    $serv->send($fd, $header_respond . self::EOF);
                }
            }
        } else {
            $serv->send($fd, 'error header!');
        }
    }


    function onClose($serv, $fd)
    {
        echo "[#" . posix_getpid() . "]\tClient: Close.\n";
    }


}