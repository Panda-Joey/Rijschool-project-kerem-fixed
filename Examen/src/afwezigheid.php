<?php
if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'instructeur') {
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

// Afhandeling van het formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verzend_afwezigheid'])) {
    $reden = trim($_POST['reden_afwezigheid']);
    $titel = "Aanvraag afwezigheid: " . $naam;
    $bericht = "Instructeur " . $naam . " (ID: " . $userID . ") heeft zich afwezig gemeld.\nReden: " . $reden;
    
    if (!empty($reden)) {
        $stmtMeld = $conn->prepare("INSERT INTO meldingen (titel, bericht, ontvanger_type, ontvanger_id) VALUES (?, ?, 'admin', 0)");
        $stmtMeld->bind_param("ss", $titel, $bericht);
        $stmtMeld->execute();
        $stmtMeld->close();
        
        $succesBericht = "Je afwezigheidsmelding is succesvol doorgegeven aan de rijschoolhouder.";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afwezigheid Melden — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <?php
    $navActief = 'afwezigheid';
    $paginaLabel = 'Afwezigheid doorgeven';
    require_once 'instructeur_nav.php';
    ?>

    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
        <?php if (isset($succesBericht)): ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold;">
                 <?= $succesBericht ?>
            </div>
            <a href="instructeurDashboard.php" class="nav-btn" style="display:inline-block; text-decoration:none; text-align:center;">Naar Dashboard</a>
        <?php else: ?>
            <p style="color: #4b5563; margin-bottom: 20px;">
                Vul het onderstaande formulier in om je afwezigheid of ziekte door te geven. 
                De rijschoolhouder zal je aanvraag bekijken en je status aanpassen.
            </p>

            <form method="POST" action="">
                <div style="margin-bottom: 15px;">
                    <label for="reden_afwezigheid" style="display:block; margin-bottom:8px; font-weight:bold; font-size:14px; color:#374151;">
                        Reden / Toelichting (bijv. Griep, Familieomstandigheden):
                    </label>
                    <textarea name="reden_afwezigheid" id="reden_afwezigheid" required 
                              style="width:100%; height:120px; padding:12px; border-radius:6px; border:1px solid #d1d5db; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>
                </div>
                
                <button type="submit" name="verzend_afwezigheid" 
                        style="background:#dc2626; color:white; border:none; padding:12px 20px; border-radius:6px; cursor:pointer; font-weight:bold; width:100%; font-size:16px;">
                    Meld afwezig bij rijschoolhouder
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>
</body>
</html>