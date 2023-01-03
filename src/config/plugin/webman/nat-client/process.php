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
            'auth' => 'diw84hytq740qjrhqa810ufnau71',
            'remote_ip' => '127.0.0.1',
            'remote_port' => 8181,
            'local_ip' => '127.0.0.1',
            'local_port' => 8787,
        ]
    ]
];
