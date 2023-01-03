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
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

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
     * Construct
     * @param string $auth
     * @param string $remote_ip
     * @param int $remote_port
     * @param string $local_ip
     * @param int $local_port
     */
    public function __construct(string $auth, string $remote_ip, int $remote_port, string $local_ip, int $local_port)
    {
        $this->auth = $auth;
        $this->remoteIp = $remote_ip;
        $this->remotePort = $remote_port;
        $this->localIp = $local_ip;
        $this->localPort = $local_port;
    }

    /**
     * onWorkerStart
     * @return void
     */
    public function onWorkerStart()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->connectServer();
        }
    }

    /**
     * connectServer
     * @return void
     */
    public function connectServer()
    {
        $serverConnection = new AsyncTcpConnection("tcp://{$this->remoteIp}:{$this->remotePort}");
        $serverConnection->send("OPTIONS / HTTP/1.1\r\nauth:{$this->auth}\r\n\r\n");
        $serverConnection->onMessage = function ($serverConnection, $data) {
            $localConnection = new AsyncTcpConnection("tcp://{$this->localIp}:{$this->localPort}");
            $localConnection->send($data);
            $localConnection->pipe($serverConnection);
            $serverConnection->pipe($localConnection);
            $serverConnection->onClose = [$this, 'connectServer'];
            $localConnection->connect();
        };
        $serverConnection->connect();
    }
}
