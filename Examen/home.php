<?php

/**
 * Homepage — doorverwijzing naar startpagina.
 */
require_once __DIR__ . '/includes/ensure-app.php';

header('Location: ' . src_url('homepage.php'));
exit;
