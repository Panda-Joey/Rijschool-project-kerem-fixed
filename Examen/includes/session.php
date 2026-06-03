<?php

if (!defined('SESSION_LIFETIME_SECONDS')) {
    require_once __DIR__ . '/../config/app.php';
}

$sessionLifetime = SESSION_LIFETIME_SECONDS;

ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['user'], $_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $sessionLifetime) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        session_set_cookie_params([
            'lifetime' => $sessionLifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

function touchSessionActivity(): void
{
    $_SESSION['last_activity'] = time();
}
