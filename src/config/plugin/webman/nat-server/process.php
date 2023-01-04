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
    'server' => [
        'handler' => \Webman\NatServer\Server::class,
        'listen' => 'http://0.0.0.0:8181',
        'count' => 1, // 注意只能是1
        'constructor' => [
            'auth' => 'diw84hytq740qjrhqa810ufnau71', // 鉴权，需要与nat-client一致
            'timeout' => 50,
        ]
    ]
];
