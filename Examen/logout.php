<?php

/**
 * Uitloggen — altijd: http://localhost/logout.php
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

logout();

header('Location: ' . app_url());
exit;
