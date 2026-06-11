<?php

require_once dirname(__DIR__) . '/includes/database.php';

$conn = getDbConnection();
$adminNaam = $_SESSION['naam'] ?? 'Admin';
$message = '';
$messageType = '';


$adminNaam = $_SESSION['naam'] ?? 'Admin';

$sql = "SELECT COUNT(*) AS totaal FROM studenten WHERE status = 'actief'";
$result = $conn->query($sql);

$aantalActieveStudenten = 0;

if ($result && $row = $result->fetch_assoc()) {
    $aantalActieveStudenten = $row['totaal'];
}

$sql = "SELECT COUNT(*) AS totaal FROM lessen";
$result = $conn->query($sql);

$aantalLessen = 0;

if ($result && $row = $result->fetch_assoc()) {
    $aantalLessen = $row['totaal'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $titel = $_POST['titel'];
    $bericht = $_POST['bericht'];
    $doelgroep = $_POST['doelgroep'];

    $stmt = $conn->prepare("
        INSERT INTO meldingen (titel, bericht, ontvanger_type, datum_gemaakt)
        VALUES (?, ?, ?, NOW())
    ");

    $stmt->bind_param("sss", $titel, $bericht, $doelgroep);
    $stmt->execute();

}

?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Beheerderspaneel</title>
    <link rel="stylesheet" href="css/AD.css">
</head>
<body>

<div class="container">

    <header class="topbar">
        <div class="logo-section">
            <h2>👋 <?= htmlspecialchars($adminNaam, ENT_QUOTES, 'UTF-8') ?></h2>
            <span class="badge">Admin</span>
            <p>Rijschool Dashboard</p>
        </div>
        <a href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-btn">Uitloggen →</a>
    </header>

    <div class="nav-grid">
        <a href="AdminDashboard.php" class="nav-card active">Dashboard</a>
        <a href="AdminGebruikers.php" class="nav-card">Gebruikers</a>
        <a href="#" class="nav-card">Rooster</a>
        <a href="#" class="nav-card">Profiel</a>
        <a href="AdminWagenpark.php" class="nav-card">Wagenpark</a>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stats-card">
        <h3>Actieve studenten</h3>
        <p><?= $aantalActieveStudenten ?></p>
    </div>
        <div class="stats-card">
        <h3>alle lessen</h3>
        <p><?= $aantalLessen ?></p>
    </div>
</div>


<div class="announcement-card">
    <h2>Mededeling Plaatsen</h2>

    <form method="POST">
        <div class="form-group">
            <label for="doelgroep">Doelgroep</label>
            <select id="doelgroep" name="doelgroep">
                <option value="alle_studenten">Alle studenten</option>
                <option value="alle_instructeurs">Alle instructeurs</option>
                <option value="iedereen">Iedereen</option>
            </select>
        </div>

        <div class="form-group">
            <label for="titel">Titel</label>
            <input type="text" id="titel" name="titel" required>
        </div>

        <div class="form-group">
            <label for="bericht">Bericht</label>
            <textarea id="bericht" name="bericht" rows="6" required></textarea>
        </div>

        <button type="submit" class="btn-primary">
            Mededeling Plaatsen
        </button>
    </form>
</div>
</body>
</html>
