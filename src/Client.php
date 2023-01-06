<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Webman\NatClient;

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
/**
 * 内网穿透客户端
 */
class Client
{

    /**
     * @var string
     */
    protected $auth;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var string
     */
    protected $remoteIp;

    /**
     * @var int
     */
    protected $remotePort;

    /**
     * @var string
     */
    protected $localIp;

    /**
     * @var int
     */
    protected $localPort;

    /**
     * @var int
     */
    protected $connectionCount;

    /**
     * Construct
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->auth = $config['auth'];
        $this->remoteIp = $config['remote_ip'];
        $this->remotePort = $config['remote_port'];
        $this->localIp = $config['local_ip'];
        $this->localPort = $config['local_port'];
        $this->timeout = $config['timeout'];
        $this->connectionCount = $config['connection_count'];
    }

    /**
     * onWorkerStart
     * @return void
     */
    public function onWorkerStart()
    {
        for ($i = 0; $i < $this->connectionCount; $i++) {
            Timer::add(1, function (){
                $this->createConnectionToServer();
            }, null, false);
        }
    }

    /**
     * createConnectionToServer
     * @return void
     */
    public function createConnectionToServer()
    {
        $serverConnection = new AsyncTcpConnection("tcp://{$this->remoteIp}:{$this->remotePort}");
        $serverConnection->onConnect = function ($serverConnection) {
            $serverConnection->send("OPTIONS / HTTP/1.1\r\nauth:{$this->auth}\r\n\r\n");
        };
        $serverConnection->onMessage = function ($serverConnection, $data) {
            $localConnection = new AsyncTcpConnection("tcp://{$this->localIp}:{$this->localPort}");
            $localConnection->send($data);
            $localConnection->pipe($serverConnection);
            $serverConnection->pipe($localConnection);
            $localConnection->connect();
            $this->createConnectionToServer();
        };
        $serverConnection->onClose = function ($serverConnection) {
            if (!empty($serverConnection->timeoutTimer)) {
                Timer::del($serverConnection->timeoutTimer);
                $serverConnection->timeoutTimer = null;
            }
            Timer::add(random_int(1, 9)/10, function () {
                $this->createConnectionToServer();
            }, null, false);
        };
        $serverConnection->timeoutTimer = Timer::add($this->timeout, function () use ($serverConnection){
            $serverConnection->timeoutTimer = null;
            $serverConnection->close();
        }, null, false);
        $serverConnection->connect();
    }
}
