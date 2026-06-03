<?php
session_start();

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$fout = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim($_POST['email']);
    $wachtw  = trim($_POST['wachtwoord']);

    // 1. Probeer eerst de instructeur (Veilig met Prepared Statement)
    $stmt = $conn->prepare("SELECT * FROM instructeurs WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($wachtw, $user['wachtwoord'])) {
            $_SESSION['userID']   = $user['instructeurID'];
            $_SESSION['rol']      = 'instructeur';
            $_SESSION['naam']     = $user['voornaam'] . ' ' . $user['achternaam'];
            
            // VERWIJZING NAAR INSTRUCTEUR DASHBOARD
            header("Location: InstructeurDashboard.php");
            exit;
        } else {
            $fout = "Verkeerd wachtwoord.";
        }
        $stmt->close();
    } else {
        $stmt->close(); // Sluit de vorige statement voordat we de nieuwe openen

        // 2. Probeer de student (Veilig met Prepared Statement)
        $stmt2 = $conn->prepare("SELECT * FROM studenten WHERE email = ?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2 && $result2->num_rows > 0) {
            $user = $result2->fetch_assoc();
            if (password_verify($wachtw, $user['wachtwoord'])) {
                
                // Controleer status van de student
                if ($user['status'] !== 'actief') {
                    if ($user['status'] === 'pending') {
                        $fout = "Je account is nog niet geactiveerd door de rijschoolhouder.";
                    } else {
                        $fout = "Je account is niet actief (Status: " . htmlspecialchars($user['status']) . ").";
                    }
                } else {
                    $_SESSION['userID'] = $user['studentID'];
                    $_SESSION['rol']    = 'student';
                    $_SESSION['naam']   = $user['voornaam'] . ' ' . $user['achternaam'];
                    
                    // VERWIJZING NAAR STUDENT DASHBOARD
                    header("Location: StudentDashboard.php");
                    exit;
                }
            } else {
                $fout = "Verkeerd wachtwoord.";
            }
        } else {
            $fout = "E-mailadres niet gevonden.";
        }
        $stmt2->close();
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
                placeholder="wachtwoord"
                required
            >
        </div>
        <button type="submit" class="btn-login">Inloggen →</button>
    </form>

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