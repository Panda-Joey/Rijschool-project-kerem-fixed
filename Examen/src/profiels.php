<?php

// Controleren of de student is ingelogd
if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'student') {
    header("Location: /login.php");
    exit;
}

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userID = intval($_SESSION['userID']);
$naam   = $_SESSION['naam'];
$success_msg = "";
$error_msg = "";

// ── Formulierverwerking (Gegevens updaten) ───────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $voornaam = trim($_POST['voornaam']);
    $tussenvoegsel = trim($_POST['tussenvoegsel']);
    $achternaam = trim($_POST['achternaam']);
    $email = trim($_POST['email']);
    $telefoon = trim($_POST['telefoon']);
    $omschrijving = trim($_POST['omschrijving']);
    $wachtwoord = $_POST['wachtwoord'];

    // Validatie: check of verplichte velden niet leeg zijn
    if (empty($voornaam) || empty($achternaam) || empty($email) || empty($telefoon)) {
        $error_msg = "Vul aanzienlijk alle verplichte velden in.";
    } else {
        // Basis query opbouwen
        if (!empty($wachtwoord)) {
            // Als er een nieuw wachtwoord is ingevuld, hash deze dan veilig
            $hashed_password = password_hash($wachtwoord, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE studenten SET voornaam = ?, tussenvoegsel = ?, achternaam = ?, email = ?, telefoon = ?, omschrijving = ?, wachtwoord = ? WHERE studentID = ?");
            $stmt->bind_param("sssssssi", $voornaam, $tussenvoegsel, $achternaam, $email, $telefoon, $omschrijving, $hashed_password, $userID);
        } else {
            // Als het wachtwoord leeg blijft, updaten we het wachtwoord niet
            $stmt = $conn->prepare("UPDATE studenten SET voornaam = ?, tussenvoegsel = ?, achternaam = ?, email = ?, telefoon = ?, omschrijving = ? WHERE studentID = ?");
            $stmt->bind_param("ssssssi", $voornaam, $tussenvoegsel, $achternaam, $email, $telefoon, $omschrijving, $userID);
        }

        if ($stmt->execute()) {
            $success_msg = " Je profiel is succesvol bijgewerkt!";
            // Update de sessienaam voor het geval dat de voornaam/achternaam is veranderd
            $_SESSION['naam'] = trim("$voornaam $tussenvoegsel $achternaam");
            $naam = $_SESSION['naam'];
        } else {
            if ($conn->errno == 1062) { // Dubbele email constraint
                $error_msg = " Dit e-mailadres is al in gebruik.";
            } else {
                $error_msg = " Er ging iets mis bij het updaten: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// ── Huidige Studentgegevens Ophalen ──────────────────────────────────
$stmt = $conn->prepare("SELECT voornaam, tussenvoegsel, achternaam, email, telefoon, beperking, omschrijving, geboortedatum, status FROM studenten WHERE studentID = ? LIMIT 1");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student niet gevonden.");
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Profiel — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="dash-header">
        <div>
            <h2>👋 <?= htmlspecialchars($naam) ?> <span class="rol-badge badge-student">🚗 Student</span></h2>
            <span>Rijschool Dashboard > Profiel beheren</span>
        </div>
        <a href="../logout.php" class="logout-btn">Uitloggen →</a>
    </div>

    <div class="top-buttons">
        <a href="Studentdashboard.php" class="nav-btn" style="text-decoration:none;color:inherit;">Dashboard</a>
        <a href="kalender.php" class="nav-btn" style="text-decoration:none;color:inherit;">Kalender</a>
        <!-- <a href="beschikbaarheid.php" class="nav-btn" style="text-decoration:none;color:inherit;">Rooster</a> -->
        <div class="nav-btn active">Profiel</div>
        <a href="les_inroosteren.php" class="nav-btn" style="text-decoration:none;color:inherit;">+ Nieuwe les</a>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div style="background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div style="background: #f8d7da; color: #842029; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="les-lijst" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0; border-bottom: 2px solid #f3f4f6; padding-bottom: 10px;"> Persoonlijke Gegevens</h3>
        
        <form action="" method="POST" style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Voornaam *</label>
                    <input type="text" name="voornaam" value="<?= htmlspecialchars($student['voornaam']) ?>" required style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div style="width: 100px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Tussenvoegsel</label>
                    <input type="text" name="tussenvoegsel" value="<?= htmlspecialchars($student['tussenvoegsel'] ?? '') ?>" style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Achternaam *</label>
                    <input type="text" name="achternaam" value="<?= htmlspecialchars($student['achternaam']) ?>" required style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">E-mailadres *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div style="flex: 1; min-width: 250px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Telefoonnummer *</label>
                    <input type="text" name="telefoon" value="<?= htmlspecialchars($student['telefoon']) ?>" required style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Geboortedatum</label>
                    <input type="text" value="<?= date('d-m-Y', strtotime($student['geboortedatum'])) ?>" disabled style="width:100%; padding: 10px; border: 1px solid #eee; background:#fafafa; border-radius: 6px; color:#777;">
                </div>
                <div style="flex: 1; min-width: 250px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Account Status</label>
                    <input type="text" value="<?= ucfirst($student['status']) ?>" disabled style="width:100%; padding: 10px; border: 1px solid #eee; background:#fafafa; border-radius: 6px; color:#777; font-weight: bold;">
                </div>
            </div>

            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Opmerkingen / Medische beperking opmerking</label>
                <textarea name="omschrijving" rows="3" style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; resize: vertical;"><?= htmlspecialchars($student['omschrijving'] ?? '') ?></textarea>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

            <!-- <div>
                <h3 style="margin-top:0;"> Wachtwoord Wijzigen</h3>
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Nieuw Wachtwoord (Laat leeg om niet te wijzigen)</label>
                <input type="password" name="wachtwoord" placeholder="Nieuw wachtwoord" style="width:100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
            </div> -->

            <div style="margin-top: 10px;">
                <button type="submit" style="background: #1b2940; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;">
                     Gegevens Opslaan
                </button>
            </div>

        </form>
    </div>

</div>
</body>
</html>