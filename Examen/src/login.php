<?php
session_start();

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$fout = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $conn->real_escape_string(trim($_POST['email']));
    $wachtw   = trim($_POST['wachtwoord']);

    // Probeer instructeur
    $r = $conn->query("SELECT * FROM instructeurs WHERE email = '$email'");
    if ($r && $r->num_rows > 0) {
        $user = $r->fetch_assoc();
        if ($wachtw === $user['wachtwoord']) {
            $_SESSION['userID']   = $user['instructeurID'];
            $_SESSION['rol']      = 'instructeur';
            $_SESSION['naam']     = $user['voornaam'] . ' ' . $user['achternaam'];
            header("Location: dashboard.php");
            exit;
        } else {
            $fout = "Verkeerd wachtwoord.";
        }
    } else {
        // Probeer student
        $r2 = $conn->query("SELECT * FROM studenten WHERE email = '$email'");
        if ($r2 && $r2->num_rows > 0) {
            $user = $r2->fetch_assoc();
            if ($wachtw === $user['wachtwoord']) {
                $_SESSION['userID'] = $user['studentID'];
                $_SESSION['rol']    = 'student';
                $_SESSION['naam']   = $user['voornaam'] . ' ' . $user['achternaam'];
                header("Location: dashboard.php");
                exit;
            } else {
                $fout = "Verkeerd wachtwoord.";
            }
        } else {
            $fout = "E-mailadres niet gevonden.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inloggen</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<div class="login-box">
    <h1>🚗 Rijschool</h1>
    <p class="sub">Log in met je account</p>

    <?php if ($fout): ?>
        <div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label>E-mailadres</label>
            <input
                type="email"
                name="email"
                id="email"
                placeholder="jouw@email.nl"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
                autocomplete="email"
            >
        </div>
        <div class="form-group">
            <label>Wachtwoord</label>
            <input
                type="password"
                name="wachtwoord"
                id="wachtwoord"
                placeholder="••••••••"
                required
                autocomplete="current-password"
            >
        </div>
        <button type="submit" class="btn-login">Inloggen →</button>
    </form>

    <!-- Tijdelijke demo-accounts tabel -->
    <div class="demo-box">
        <h4>🧪 Tijdelijke testaccounts</h4>
        <table class="demo-table">
            <tr>
                <th>Naam</th>
                <th>Rol</th>
                <th>Email</th>
                <th></th>
            </tr>
            <tr>
                <td>Piet Pietersen</td>
                <td><span class="rol-badge rol-instructeur">Instructeur</span></td>
                <td>piet@test.nl</td>
                <td><button class="fill-btn" onclick="vul('piet@test.nl','123456')">Invullen</button></td>
            </tr>
            <tr>
                <td>Jan Jansen</td>
                <td><span class="rol-badge rol-student">Student</span></td>
                <td>jan@test.nl</td>
                <td><button class="fill-btn" onclick="vul('jan@test.nl','123456')">Invullen</button></td>
            </tr>
        </table>
    </div>
</div>

<script>
function vul(email, wachtwoord) {
    document.getElementById('email').value      = email;
    document.getElementById('wachtwoord').value = wachtwoord;
}
</script>
</body>
</html>