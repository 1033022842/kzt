<?php

return [
    'default_timezone' => 'Asia/Shanghai',
    'default_lang' => 'zh-cn',
    'auto_multi_app' => false,
    'show_error_msg' => true,
    'error_message' => '页面错误！请稍后再试～',
    'http_exception_template' => [
        404 => app()->getAppPath() . '404.html',
        500 => app()->getAppPath() . '500.html',
    ],
];
