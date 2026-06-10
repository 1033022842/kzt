<?php

return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'type' => 'file',
            'path' => app()->getRuntimePath() . 'log',
            'level' => ['error', 'warning'],
            'apart_level' => ['error', 'sql'],
        ],
    ],
];
