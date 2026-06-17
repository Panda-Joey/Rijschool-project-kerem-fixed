<?php

/**
 * Start altijd automatisch (php.ini auto_prepend_file).
 * Laadt sessie/config/auth en beveiligt src/-pagina's via HTTP-redirects.
 */

if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

function ensureLegacySrcSession(): void
{
    if (!isset($_SESSION['user'])) {
        return;
    }

    $email = $_SESSION['user'];
    $role = $_SESSION['role'] ?? 'leerling';
    $demoUser = DEMO_USERS[$email] ?? null;

    if ($demoUser !== null) {
        setLegacySessionForSrc($email, $role, $demoUser);
        return;
    }

    if (!isset($_SESSION['userID'], $_SESSION['rol'], $_SESSION['naam'])) {
        setLegacySessionForSrc($email, $role);
    }
}

function redirectToLogin(): void
{
    header('Location: ' . login_url());
    exit;
}

function redirectToDashboard(): void
{
    header('Location: ' . dashboardUrlForRole());
    exit;
}

function guardLogin(): void
{
    if (!isLoggedIn()) {
        redirectToLogin();
    }
    ensureLegacySrcSession();
}

function guardRole(string $legacyRole): void
{
    guardLogin();
    if (($_SESSION['rol'] ?? '') !== $legacyRole) {
        redirectToDashboard();
    }
}

function guardAdmin(): void
{
    guardLogin();
    if (($_SESSION['role'] ?? '') !== 'eigenaar') {
        redirectToLogin();
    }
}

$scriptPath = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$srcDir = realpath(dirname(__DIR__) . '/src');

if ($srcDir === false || !str_starts_with($scriptPath, $srcDir)) {
    return;
}

$page = basename($scriptPath);

$publicPages = ['aanmelden.php', 'homepage.php', 'index.php', 'login.php', 'logout.php'];
if (in_array($page, $publicPages, true)) {
    return;
}

$roleByPage = [
    'StudentDashboard.php'     => 'student',
    'InstructeurDashboard.php' => 'instructeur',
    'beschikbaarheid.php'      => 'instructeur',
    'examen.php'               => 'instructeur',
    'AdminDashboard.php'       => 'admin',
    'AdminGebruikers.php'      => 'admin',
    'AdminWagenpark.php'       => 'admin',
    'Wagenpark.php'            => 'instructeur',
];

if (isset($roleByPage[$page])) {
    $rule = $roleByPage[$page];
    if ($rule === 'admin') {
        guardAdmin();
    } else {
        guardRole($rule);
    }
    return;
}

guardLogin();
