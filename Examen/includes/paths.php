<?php

/**
 * Basis-URL van het project (altijd projectroot, niet /src/).
 */
function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $projectRoot = realpath(dirname(__DIR__));
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($projectRoot && $docRoot && str_starts_with($projectRoot, $docRoot)) {
        $relative = substr($projectRoot, strlen($docRoot));
        $relative = str_replace('\\', '/', $relative);
        $cached = ($relative === '' || $relative === '/') ? '' : rtrim($relative, '/');
        return $cached;
    }

    // php -S: script in /src/ → basis is map boven src
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('#^(.*)/src/[^/]+\.php$#', $script, $m)) {
        $cached = $m[1] === '' ? '' : $m[1];
        return $cached;
    }

    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $cached = '';
    } else {
        $cached = rtrim($dir, '/');
    }

    return $cached;
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    $path = str_replace('\\', '/', $path);

    $query = '';
    if (($pos = strpos($path, '?')) !== false) {
        $query = substr($path, $pos);
        $path = substr($path, 0, $pos);
    }

    $path = ltrim($path, '/');

    if ($path === '') {
        $url = $base === '' ? '/' : $base . '/';
    } else {
        $url = ($base === '' ? '' : $base) . '/' . $path;
    }

    return $url . $query;
}

function logout_url(): string
{
    return app_url('logout.php');
}
