<?php

namespace Webman\NatClient;

use support\Log;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

/**
 * 内网穿透客户端
 */
class Client
{

    /**
     * 是否开启debug
     * @var bool
     */
    protected $debug = false;

    /**
     * 内网穿透服务端地址
     * @var string
     */
    protected $host = '';

    /**
     * 内网穿透给服务端鉴权用的Token
     * @var string
     */
    protected $token = '';

    /**
     * 当前用户应用列表
     * [domain=>[name,domain,localIp,localPort], domain=>[...]...]
     * @var array
     */
    protected $apps = [];

    /**
     * 与服务端连接失败次数
     * @var array
     */
    protected $connectFailCount = 0;

    /**
     * 每个域名预创建连接数
     */
    const PRE_CONNECTION_COUNT = 5;

    /**
     * 连接空闲58秒后断开重连
     */
    const IDLE_TIMEOUT = 58;

    /**
     * 构造函数
     * @param bool $debug
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * 进程启动时
     * @return void
     */
    public function onWorkerStart()
    {
        $this->createSettingConnection();
    }

    /**
     * 创建接收配置推送的连接
     * @return void
     */
    protected function createSettingConnection()
    {
        $host = config('plugin.webman.nat-client.app.host');
        $token = config('plugin.webman.nat-client.app.token');
        $this->token = $token;
        $this->host = $host;
        if (empty($host) || empty($token)) {
            echo $logs = "内网穿透客户端：客户端未设置服务端地址或token\n";
            Log::error($logs);
            return;
        }

        // 创建连接
        $connection = new AsyncTcpConnection("frame://$host");
        // 发起验证
        $connection->onConnect = function ($connection) use ($token, $host) {
            $connection->send("OPTIONS / HTTP/1.1\r\nNat-host: $host\r\nNat-token: $token\r\nnat-setting-client: yes\r\nHost: $host\r\n\r\n", true);
        };
        // 收到消息
        $connection->onMessage = function ($connection, $buffer) {
            $data = json_decode($buffer, true);
            if (!$data) {
                echo $logs = "内网穿透客户端：下发配置错误 $buffer\n";
                Log::error($logs);
                return;
            }
            $type = $data['type'] ?? '';
            switch ($type) {
                // 心跳
                case 'ping':
                    return;
                // 下发配置
                case 'setting':
                    $apps = $data['setting'];
                    $this->debugLog("内网穿透客户端：收到下发配置 \n" . var_export($apps, true));
                    $this->createConnections($apps);
                    return;
                // 未知命令
                default :
                    $type = var_export($type, true);
                    echo $logs = "内网穿透客户端：未知命令 $type \n";
                    Log::error($logs);
            }
        };
        // 断开重连
        $connection->onClose = function ($connection) {
            $connection->reConnect(1);
        };
        // 向服务端定时发心跳
        Timer::add(55, function () use ($connection) {
            if ($connection->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $connection->send(json_encode(['type' => 'ping']));
            }
        });
        // 执行连接
        $connection->connect();
    }

    /**
     * 创建与服务端连接，用于接收外网浏览器请求
     * @return void
     */
    protected function createConnections($apps)
    {
        foreach ($apps as $app) {
            $domain = $app['domain'];
            if (isset($this->apps[$domain])) {
                continue;
            }
            for ($i = 0; $i < static::PRE_CONNECTION_COUNT; $i++) {
                Timer::add($i + 0.001, function () use ($domain) {
                    $this->createConnectionToServer($domain);
                }, null, false);
            }
        }
        $this->apps = $apps;
    }

    /**
     * 创建连接
     * @return void
     */
    public function createConnectionToServer($domain)
    {
        if (!isset($this->apps[$domain])) {
            $this->debugLog("内网穿透客户端：因域名 $domain 在配置中不存在而忽略");
            return;
        }
        $this->debugLog("内网穿透客户端：连接服务端 $this->host with $domain");
        // 连接
        $serverConnection = new AsyncTcpConnection("tcp://$this->host");
        $serverConnection->lastBytesReaded = 0;
        // 通过Nat-host传递该连接属于哪个域名
        $serverConnection->onConnect = function ($serverConnection) use ($domain) {
            $this->connectFailCount = 0;
            $serverConnection->send("OPTIONS / HTTP/1.1\r\nHost: $this->host\r\nNat-host: $domain\r\nNat-token:$this->token\r\n\r\n");
        };
        // 收到浏览器请求时
        $serverConnection->onMessage = function ($serverConnection, $data) use ($domain) {
            $this->debugLog("内网穿透客户端：处理浏览器请求");
            $localIp = $this->apps[$domain]['local_ip'];
            $localPort = $this->apps[$domain]['local_port'];
            // 创建本地的连接并与浏览器连接互推数据
            $localConnection = new AsyncTcpConnection("tcp://$localIp:$localPort");
            $localConnection->send($data);
            $localConnection->pipe($serverConnection);
            $serverConnection->pipe($localConnection);
            $localConnection->connect();
            $this->createConnectionToServer($domain);
        };
        // 连接关闭时
        $serverConnection->onClose = function ($serverConnection) use ($domain) {
            // 判断域名是否还在配置中
            if (isset($this->apps[$domain])) {
                // 如果连接失败，则定时重连时间累加
                $count = $this->connectFailCount;
                if($count === 0) {
                    $this->createConnectionToServer($domain);
                } else {
                    $time = min($count * 0.1, 10);
                    $this->debugLog("内网穿透客户端：定时 $time 秒重连服务端");
                    Timer::add($time, function () use ($domain) {
                        $this->createConnectionToServer($domain);
                    }, null, false);
                }
            }
            // 清除定时器
            if ($serverConnection->timeoutTimer) {
                Timer::del($serverConnection->timeoutTimer);
                $serverConnection->timeoutTimer = null;
            }
        };

        // 连接失败时
        $serverConnection->onError = function ($serverConnection, $code) {
            // code=1为连接失败
            if ($code === 1) {
                $this->connectFailCount++;
                $this->debugLog("内网穿透客户端：连接服务端 $this->host 失败 $this->connectFailCount 次");
            }
        };

        // 添加一个定时器，如长时间未通讯则关闭连接
        $serverConnection->timeoutTimer = Timer::add(static::IDLE_TIMEOUT, function () use ($serverConnection, $domain) {
            if ($serverConnection->lastBytesReaded == $serverConnection->bytesRead || $serverConnection->getStatus() === TcpConnection::STATUS_CLOSED) {
                if($serverConnection->timeoutTimer) {
                    Timer::del($serverConnection->timeoutTimer);
                    $serverConnection->timeoutTimer = null;
                }
                $serverConnection->close();
                $this->debugLog("内网穿透客户端：连接 Host:$domain 空闲" . static::IDLE_TIMEOUT . "秒执行正常关闭");
            }
            $serverConnection->lastBytesReaded = $serverConnection->bytesRead;
        });
        // 执行连接
        $serverConnection->connect();
    }

    /**
     * 记录日志
     * @param $msg
     * @return void
     */
    protected function debugLog($msg)
    {
        if ($this->debug) {
            echo date('Y-m-d H:i:s') . " $msg" . PHP_EOL;
        }
    }
}
