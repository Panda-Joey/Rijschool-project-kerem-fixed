<?php

require_once dirname(__DIR__) . '/includes/ensure-app.php';

header('Location: ' . (isLoggedIn() ? dashboardUrlForRole() : src_url('homepage.php')));
exit;
