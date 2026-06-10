<?php

declare(strict_types=1);
ini_set('post_max_size', '10M');
ini_set('upload_max_filesize', '10M');
require __DIR__ . '/../vendor/autoload.php';

$http = (new think\App())->http;
$response = $http->run();
$response->send();
$http->end($response);
