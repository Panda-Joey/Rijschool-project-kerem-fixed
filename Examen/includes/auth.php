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
        return src_url('AdminDashboard.php');
    }

    if ($role === 'leerling') {
        return src_url('StudentDashboard.php');
    }

    return src_url('InstructeurDashboard.php');
}

function srcDashboardPath(): string
{
    $legacyRol = $_SESSION['rol'] ?? '';

    if ($legacyRol === 'student') {
        return 'StudentDashboard.php';
    }

    if (($_SESSION['role'] ?? '') === 'eigenaar') {
        return 'AdminDashboard.php';
    }

    return 'InstructeurDashboard.php';
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

function setLegacySessionForSrc($email, $role, $user = null)
{
    $user = $user ?? (DEMO_USERS[$email] ?? null);

    if ($user !== null && isset($user['userID'])) {
        $_SESSION['userID'] = (int) $user['userID'];
    } else {
        // Fallback: past binnen MySQL INT (crc32 kan > 2^31-1 zijn)
        $_SESSION['userID'] = abs(crc32($email) % 2147483647) ?: 1;
    }

    $_SESSION['rol'] = legacyRoleFromAppRole($role);
    $_SESSION['naam'] = $user['naam'] ?? displayNameFromEmail($email);
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
            setLegacySessionForSrc($email, $role, $user);
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

    if ($user !== null && password_verify($password, DEMO_PASSWORD_HASH)) {
        $_SESSION['user'] = $email;
        $_SESSION['role'] = $user['role'];
        setLegacySessionForSrc($email, $user['role'], $user);
        touchSessionActivity();

        return null;
    }

    require_once __DIR__ . '/database.php';
    $conn = getDbConnection();

    $stmt = $conn->prepare(
        'SELECT studentID, voornaam, tussenvoegsel, achternaam, wachtwoord, status
         FROM studenten WHERE email = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student !== null) {
        if (!password_verify($password, $student['wachtwoord'])) {
            return 'Ongeldige e-mail of wachtwoord.';
        }
        if ($student['status'] === 'pending') {
            return 'Je account is nog niet geactiveerd. De rijschool moet je account eerst goedkeuren.';
        }

        $naam = trim(
            $student['voornaam'] . ' '
            . ($student['tussenvoegsel'] ?? '') . ' '
            . $student['achternaam']
        );
        $dbUser = [
            'role'   => 'leerling',
            'userID' => (int) $student['studentID'],
            'naam'   => $naam,
        ];

        $_SESSION['user'] = $email;
        $_SESSION['role'] = 'leerling';
        setLegacySessionForSrc($email, 'leerling', $dbUser);
        touchSessionActivity();

        return null;
    }

    $stmt = $conn->prepare(
        'SELECT instructeurID, voornaam, tussenvoegsel, achternaam, wachtwoord, rol
         FROM instructeurs WHERE email = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $instructeur = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($instructeur !== null) {
        if (!password_verify($password, $instructeur['wachtwoord'])) {
            return 'Ongeldige e-mail of wachtwoord.';
        }

        $role = $instructeur['rol'] === 'admin' ? 'eigenaar' : 'instructeur';
        $naam = trim(
            $instructeur['voornaam'] . ' '
            . ($instructeur['tussenvoegsel'] ?? '') . ' '
            . $instructeur['achternaam']
        );
        $dbUser = [
            'role'   => $role,
            'userID' => (int) $instructeur['instructeurID'],
            'naam'   => $naam,
        ];

        $_SESSION['user'] = $email;
        $_SESSION['role'] = $role;
        setLegacySessionForSrc($email, $role, $dbUser);
        touchSessionActivity();

        return null;
    }

    return 'Ongeldige e-mail of wachtwoord.';
}
