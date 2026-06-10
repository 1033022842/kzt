<?php

declare(strict_types=1);

if (is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $_SERVER['SCRIPT_NAME'])) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'];

require __DIR__ . '/index.php';
