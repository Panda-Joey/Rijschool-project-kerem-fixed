<?php

/**
 * Alleen ingelogde eigenaar (admin). Gebruik bovenaan src/Admin*.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'eigenaar') {
    header('Location: ' . app_url('login.php'));
    exit;
}
