<?php

/**
 * Inlogpagina — http://localhost:8888/src/login.php
 * Scherm: views/login.view.php | Accounts: config/app.php
 */
require_once dirname(__DIR__) . '/includes/ensure-app.php';

if (isset($_GET['logout'])) {
    header('Location: ' . logout_url());
    exit;
}

if (isLoggedIn()) {
    header('Location: ' . dashboardUrlForRole());
    exit;
}

$error = '';

if (isset($_GET['test'])) {
    $result = attemptTestLogin($_GET['test']);
    if ($result === null) {
        header('Location: ' . dashboardUrlForRole());
        exit;
    }
    $error = $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = attemptLogin(
        trim($_POST['email'] ?? ''),
        $_POST['password'] ?? ''
    );

    if ($result === null) {
        header('Location: ' . dashboardUrlForRole());
        exit;
    }
    $error = $result;
}

include dirname(__DIR__) . '/views/login.view.php';
