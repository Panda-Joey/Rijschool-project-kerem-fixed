<?php

/**
 * Uitloggen — http://localhost:8888/src/logout.php
 */
require_once dirname(__DIR__) . '/includes/ensure-app.php';

logout();

header('Location: ' . src_url('login.php'));
exit;
