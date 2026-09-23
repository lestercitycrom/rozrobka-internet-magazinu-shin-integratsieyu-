<?php

declare(strict_types=1);

$applicationRoot = dirname(__DIR__, 2);
$profile = getenv('STOREFRONT_DESIGN_PROFILE');
$expectedPort = $profile === 'modern' ? 8046 : 8047;
$expectedStorage = $applicationRoot."/tmp/classic-preview/ci/{$profile}/storage";
$normalizePath = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');

if (getenv('CLASSIC_PREVIEW_BOOTSTRAP') !== '1'
    || getenv('APP_ENV') !== 'testing'
    || ! in_array($profile, ['modern', 'classic'], true)
    || $normalizePath((string) getenv('LARAVEL_STORAGE_PATH')) !== $normalizePath($expectedStorage)
    || ($_SERVER['HTTP_HOST'] ?? '') !== "127.0.0.1:{$expectedPort}") {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Classic preview router refused the request.';

    return true;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if (! str_starts_with($requestPath, '/storage/')) {
    return require __DIR__.'/server.php';
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (! in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');

    return true;
}

$relativePath = substr($requestPath, strlen('/storage/'));
$segments = explode('/', str_replace('\\', '/', $relativePath));

if ($relativePath === ''
    || str_contains($relativePath, "\0")
    || str_contains($relativePath, '\\')
    || in_array('', $segments, true)
    || in_array('.', $segments, true)
    || in_array('..', $segments, true)) {
    http_response_code(404);

    return true;
}

$publicStorageRoot = realpath($expectedStorage.'/app/public');
$requestedFile = realpath($expectedStorage.'/app/public/'.$relativePath);

if ($publicStorageRoot === false || $requestedFile === false) {
    http_response_code(404);

    return true;
}

$normalizedRoot = $normalizePath($publicStorageRoot);
$normalizedFile = $normalizePath($requestedFile);
$extension = strtolower(pathinfo($normalizedFile, PATHINFO_EXTENSION));

if (! str_starts_with($normalizedFile, $normalizedRoot.'/')
    || ! is_file($requestedFile)
    || in_array($extension, ['php', 'php3', 'php4', 'php5', 'phtml', 'phar'], true)) {
    http_response_code(404);

    return true;
}

$mimeType = 'application/octet-stream';

if (class_exists(finfo::class)) {
    $detector = new finfo(FILEINFO_MIME_TYPE);
    $detectedMimeType = $detector->file($requestedFile);

    if (is_string($detectedMimeType) && $detectedMimeType !== '') {
        $mimeType = $detectedMimeType;
    }
}

header('Content-Type: '.$mimeType);
header('Content-Length: '.(string) filesize($requestedFile));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($method === 'GET') {
    readfile($requestedFile);
}

return true;
