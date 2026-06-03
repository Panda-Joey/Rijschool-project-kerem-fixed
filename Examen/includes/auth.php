<?php

function isLoggedIn()
{
    return isset($_SESSION['user']);
}

function roleLabel($role)
{
    return ROLES[$role] ?? 'Gebruiker';
}

function dashboardUrlForRole($role = null)
{
    $role = $role ?? ($_SESSION['role'] ?? null);

    if ($role === 'eigenaar') {
        return app_url('src/AdminDashboard.php');
    }

    return app_url('src/dashboard.php');
}

function legacyRoleFromAppRole($role)
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

function displayNameFromEmail($email)
{
    $localPart = explode('@', $email)[0] ?? $email;
    $localPart = str_replace(['.', '_', '-'], ' ', $localPart);
    $localPart = trim($localPart);
    if ($localPart === '') {
        return 'Gebruiker';
    }

    return ucwords($localPart);
}

function setLegacySessionForSrc($email, $role)
{
    // src/ verwacht deze keys voor authorisatie + UI
    $_SESSION['userID'] = abs(crc32($email)) ?: 1;
    $_SESSION['rol'] = legacyRoleFromAppRole($role);
    $_SESSION['naam'] = displayNameFromEmail($email);
}

function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function attemptTestLogin($role)
{
    if (!ENABLE_TEST_LOGIN || !isset(ROLES[$role])) {
        return 'Test-inlog niet beschikbaar.';
    }

    foreach (DEMO_USERS as $email => $user) {
        if ($user['role'] === $role) {
            $_SESSION['user'] = $email;
            $_SESSION['role'] = $role;
            setLegacySessionForSrc($email, $role);
            touchSessionActivity();
            return null;
        }
    }

    return 'Test-account niet gevonden.';
}

function attemptLogin($email, $password)
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
