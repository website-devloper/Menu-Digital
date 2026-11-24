<?php
// Vercel Serverless Function entry point
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rootDir = dirname(__DIR__);

// Normalize path
if ($uri === '/' || $uri === '' || $uri === false) {
    $target = $rootDir . DIRECTORY_SEPARATOR . 'index.php';
} elseif (is_dir($rootDir . $uri)) {
    $target = rtrim($rootDir . $uri, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
} else {
    $target = $rootDir . str_replace('/', DIRECTORY_SEPARATOR, $uri);
}

// Check if file exists
$realRoot = realpath($rootDir);
$realTarget = realpath($target);

if (!$realTarget || strpos($realTarget, $realRoot) !== 0 || !is_file($realTarget)) {
    http_response_code(404);
    echo "404 Not Found";
    exit;
}

$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
$mimes = [
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'svg'  => 'image/svg+xml',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'ico'  => 'image/x-icon',
    'json' => 'application/json',
];

if (isset($mimes[$ext])) {
    header('Content-Type: ' . $mimes[$ext]);
    readfile($realTarget);
    exit;
}

if ($ext === 'php') {
    chdir(dirname($realTarget));
    require $realTarget;
    exit;
}

http_response_code(403);
echo "Forbidden";
exit;
