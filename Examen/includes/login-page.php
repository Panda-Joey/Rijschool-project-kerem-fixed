<?php

/**
 * Login-logica (één plek).
 * URL blijft: /login.php
 * Scherm aanpassen: views/login.view.php
 * Accounts & rollen: config/app.php + includes/auth.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

function redirectAfterLogin(): void
{
    header('Location: ' . dashboardUrlForRole());
    exit;
}

if (isset($_GET['logout'])) {
    header('Location: ' . logout_url());
    exit;
}

if (isLoggedIn()) {
    header('Location: ' . app_url());
    exit;
}

$error = '';

if (isset($_GET['test'])) {
    $result = attemptTestLogin($_GET['test']);
    if ($result === null) {
        redirectAfterLogin();
    }
    $error = $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = attemptLogin(
        trim($_POST['email'] ?? ''),
        $_POST['password'] ?? ''
    );

    if ($result === null) {
        redirectAfterLogin();
    }
    $error = $result;
}

require dirname(__DIR__) . '/views/login.view.php';
