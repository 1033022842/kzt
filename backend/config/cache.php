<?php

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => 'File',
            'path' => app()->getRuntimePath() . 'cache',
            'expire' => 0,
            'tag_prefix' => 'tag:',
            'serialize' => [],
        ],
    ],
];
