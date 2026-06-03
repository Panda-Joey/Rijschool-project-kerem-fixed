<?php

/**
 * Homepage entry point — laat dit bestand met rust.
 * Anna past de inhoud aan in: views/homepage.php
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$active = 'home';
require __DIR__ . '/views/homepage.php';
