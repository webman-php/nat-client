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

namespace Webman\NatServer;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 内网穿透服务端
 */
class Server
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
     * @var TcpConnection[]
     */
    protected $clientConnections = [];

    /**
     * @param string $auth
     * @param int $timeout
     */
    public function __construct(string $auth, int $timeout)
    {
        $this->auth = $auth;
        $this->timeout = $timeout;
    }

    /**
     * onMessage
     * @param TcpConnection $connection
     * @param Request $request
     * @return void
     */
    public function onMessage(TcpConnection $connection, Request $request)
    {
        if ($request->method() === 'OPTIONS') {
            if ($request->header('auth') !== $this->auth) {
                echo "auth failed\n";
            }
            $connection->protocol = null;
            $connection->onClose = function ($connection) {
                unset($this->clientConnections[$connection->id]);
                if (!empty($connection->timeoutTimer)) {
                    Timer::del($connection->timeoutTimer);
                    $connection->timeoutTimer = null;
                }
            };
            $this->clientConnections[$connection->id] = $connection;
            $connection->timeoutTimer = Timer::add($this->timeout, function ($connection) {
                $connection->timeoutTimer = null;
                $connection->close();
            }, null, false);
            return;
        }
        if (empty($this->clientConnections)) {
            $connection->send(new Response(503, [], 'Service Unavailable'));
            return;
        }
        $clientConnection = array_pop($this->clientConnections);
        $clientConnection->send((string)$request);
        $clientConnection->pipe($connection);
        $connection->protocol = null;
        $connection->pipe($clientConnection);
        $clientConnection->onClose = function ($clientConnection) use ($connection) {
            $connection->close();
            unset($this->clientConnections[$clientConnection->id]);
        };
    }
}
