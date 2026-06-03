<?php

/**
 * Startpagina — http://localhost/ (Docker) of http://localhost/JOUW-MAP/
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$root = __DIR__;
$required = [
    'home.php',
    'includes/bootstrap.php',
    'includes/auth.php',
    'config/app.php',
    'views/homepage.php',
    'views/partials/header.php',
];

$missing = [];
foreach ($required as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        $missing[] = $file;
    }
}

if ($missing !== []) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Setup</title>';
    echo '<style>body{font-family:sans-serif;max-width:520px;margin:2rem auto;padding:0 1rem}code{background:#eee;padding:2px 6px}</style></head><body>';
    echo '<h1>Homepage kan niet laden</h1>';
    echo '<p>Bestanden ontbreken. In de projectmap: <code>git pull</code></p><ul>';
    foreach ($missing as $file) {
        echo '<li><code>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</code></li>';
    }
    echo '</ul>';
    echo '<p>Start daarna Docker: <code>docker compose up</code> en open <code>http://localhost</code> (niet poort 8000).</p>';
    echo '</body></html>';
    exit;
}

require $root . '/home.php';
