<?php

/**
 * Oude URL — doorverwijzing naar rol-dashboard.
 */
require_once dirname(__DIR__) . '/includes/ensure-app.php';

header('Location: ' . src_url(srcDashboardPath()));
exit;
