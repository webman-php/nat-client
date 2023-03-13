<?php

return [
    'client' => [
        'handler' => Webman\NatClient\Client::class,
        'reloadable' => false,
        'constructor' => [
            'debug' => false
        ]
    ]
];
