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

return [
    'client' => [
        'handler' => Webman\NatClient\Client::class,
        'constructor' => [
            'config' => [
                'auth' => 'diw84hytq740qjrhqa810ufnau71', // 鉴权，需要与nat-server的一致
                'remote_ip' => '127.0.0.1', // 这里填写运行nat-server的服务端ip
                'remote_port' => 8181,      // 这里填写运行nat-server的端口
                'local_ip' => '127.0.0.1',
                'local_port' => 8787,
                'timeout' => 50,
                'connection_count' => 10,
            ]
        ]
    ]
];
