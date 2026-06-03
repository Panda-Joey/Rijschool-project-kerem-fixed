<?php

/**
 * Inlogpagina — http://localhost/login.php
 * Layout staat in views/login.view.php
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Alles wat login nodig heeft staat hier, zodat je niet hoeft te zoeken in andere files.
function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function roleLabel(string $role): string
{
    return ROLES[$role] ?? 'Gebruiker';
}

function dashboardUrlForRole(?string $role = null): string
{
    $role = $role ?? ($_SESSION['role'] ?? null);

    if ($role === 'eigenaar') {
        return app_url('src/AdminDashboard.php');
    }

    return app_url('src/dashboard.php');
}

function legacyRoleFromAppRole(string $role): string
{
    if ($role === 'leerling') {
        return 'student';
    }

    if ($role === 'instructeur') {
        return 'instructeur';
    }

    // src/ kent geen eigenaar; map naar instructeur zodat pagina's blijven werken
    return 'instructeur';
}

function displayNameFromEmail(string $email): string
{
    $localPart = explode('@', $email)[0] ?? $email;
    $localPart = str_replace(['.', '_', '-'], ' ', $localPart);
    $localPart = trim($localPart);
    if ($localPart === '') {
        return 'Gebruiker';
    }

    return ucwords($localPart);
}

function setLegacySessionForSrc(string $email, string $role): void
{
    // src/ verwacht deze keys voor authorisatie + UI
    $_SESSION['userID'] = abs(crc32($email)) ?: 1;
    $_SESSION['rol'] = legacyRoleFromAppRole($role);
    $_SESSION['naam'] = displayNameFromEmail($email);
}

function attemptTestLogin(string $role): ?string
{
    if (!ENABLE_TEST_LOGIN || !isset(ROLES[$role])) {
        return 'Test-inlog niet beschikbaar.';
    }

    foreach (DEMO_USERS as $email => $user) {
        if (($user['role'] ?? null) === $role) {
            $_SESSION['user'] = $email;
            $_SESSION['role'] = $role;
            setLegacySessionForSrc($email, $role);
            touchSessionActivity();
            return null;
        }
    }

    return 'Test-account niet gevonden.';
}

function attemptLogin(string $email, string $password): ?string
{
    if ($email === '' || $password === '') {
        return 'Vul e-mail en wachtwoord in.';
    }

    $user = DEMO_USERS[$email] ?? null;
    if ($user === null || !password_verify($password, DEMO_PASSWORD_HASH)) {
        return 'Ongeldige e-mail of wachtwoord.';
    }

    $_SESSION['user'] = $email;
    $_SESSION['role'] = $user['role'];
    setLegacySessionForSrc($email, $user['role']);
    touchSessionActivity();

    return null;
}

function redirectAfterLogin(): void
{
    header('Location: ' . dashboardUrlForRole());
    exit;
}

// support: oude link login.php?logout=1
if (isset($_GET['logout'])) {
    header('Location: ' . logout_url());
    exit;
}

// Als iemand al ingelogd is en toch naar /login.php gaat: terug naar homepage
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

require __DIR__ . '/views/login.view.php';
