<?php

$publicPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public';
$publicRoot = realpath($publicPath).DIRECTORY_SEPARATOR;
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$asset = realpath($publicPath.$uri);

if ($uri !== '/' && $asset !== false && str_starts_with($asset, $publicRoot)) {
    return false;
}

require $publicPath.DIRECTORY_SEPARATOR.'index.php';
