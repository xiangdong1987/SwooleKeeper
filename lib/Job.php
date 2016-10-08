<?php
require 'Paxos.php';

class Job extends Paxos
{
    public $server;
    private $acceptors;
    private $max_id = 0;

    function __construct($config)
    {
        if (!$this->server) {
            $job_ip = $config['job_ip'];
            $job_ip = explode(':', $job_ip);
            $this->server = new swoole_server($job_ip[0], $job_ip[1], SWOOLE_BASE);
        }
        //批量链接Accptor
        foreach ($config['acceptors'] as $ip) {
            $this->connectAccept($ip);
        }
        $this->server->set($config['swoole']);
        return $this->server;
    }

    function onConnect($serv, $fd)
    {
        echo "[#" . posix_getpid() . "]\tClient:Connect.\n";
    }

    function onWorkerStart(swoole_server $serv, $worker_id)
    {
        //每1秒钟检查所有acceptor是否存活
        $serv->tick(1000, function ($id) {
            foreach ($this->acceptors as $key => $acceptor) {
                if ($acceptor->isConnected()) {
                    $message = $this->doPack(time(), 0, self::REQUEST_KEEP_ALIVE, 0);
                    if ($acceptor->send($message . "\r\n\r\n")) {
                        //链接存活中
                    } else {
                        $this->connectAccept($key);
                    }
                } else {
                    //重连Acceptor
                    $this->connectAccept($key);
                }
            }
        });
    }

    private function connectAccept($ip)
    {
        //重连Acceptor
        $client = new swoole_client(SWOOLE_SOCK_TCP);
        $ipList = explode(':', $ip);
        if (!$client->connect($ipList[0], $ipList[1], -1)) {
            //可以添加报警
            var_dump("connect failed. Error: {$client->errCode}\n");
        } else {
            $this->acceptors[$ip] = $client;
        }
    }

    function onReceive(swoole_server $serv, $fd, $from_id, $data)
    {
        echo '#' . $serv->worker_id . " recv: " . strlen($data) . "\n";
        $header = $this->unPack($data);
        $body = substr($data, 16, -1);
        if ($header) {
            if ($header['type'] == self::REQUEST_CLI) {
                start:
                $header['time'] = time();
                $header_prepare = $this->doPack($header['time'], $header['ip'], self::REQUEST_CLI, 0);
                //调用proposer提议
                if ($this->propose($header_prepare, $body)) {
                    //选主成功写日志
                    $header_confirm = $this->doPack($header['time'], $header['ip'], self::REQUEST_CONFIRM, $this->max_id + 1);
                    //选主成功后写日志
                    if ($this->propose($header_confirm, $body)) {
                        //学习所有相差的日志
                        echo "log:" . $body . "\n";
                        $serv->send($fd, 'save success!');
                    } else {
                        goto start;
                    }
                } else {
                    //重新选主
                    goto start;
                }
            } else {
                $serv->send($fd, 'You are other!');
            }
        } else {
            $serv->send($fd, 'error header!');
        }
    }

    function propose($header, $body)
    {
        $max_id = $this->max_id;
        $i = 0;
        foreach ($this->acceptors as $acceptor) {
            $acceptor->send($header . $body . self::EOF);
            $result = $acceptor->recv();
            $header_after = $this->unPack($result);
            if ($header_after['type'] == self::REQUEST_ACCEPTOR_AGREE) {
                if ($header_after['logId'] > $max_id) {
                    $max_id = $header_after['logId'];
                }
                $i++;
            }
            if ($header_after['type'] == self::REQUEST_CONFIRM_AGREE) {
                $i++;
            }
            if ($header_after['type'] == self::REQUEST_CONFIRM_REFUSE) {
                if ($header_after['logId'] > $max_id) {
                    $max_id = $header_after['logId'];
                    $this->max_id = $max_id;
                }
                return false;
            }
        }
        if ($i > floor(count($this->acceptors) / 2)) {
            $this->max_id = $max_id;
            return true;
        } else {
            return false;
        }
    }

    function onClose($serv, $fd)
    {
        echo "[#" . posix_getpid() . "]\tClient: Close.\n";
    }

    function start()
    {
        $this->server->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->server->on('Connect', array($this, 'onConnect'));
        $this->server->on('Receive', array($this, 'onReceive'));
        $this->server->on('Close', array($this, 'onClose'));
        $this->server->start();
    }
}
