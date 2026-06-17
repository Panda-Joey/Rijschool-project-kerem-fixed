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

$stmtMededelingen = $conn->prepare("
    SELECT titel, bericht, datum_gemaakt 
    FROM meldingen 
    WHERE ontvanger_type = ? OR ontvanger_type = 'admin'
    ORDER BY datum_gemaakt DESC 
    LIMIT 5
");
$stmtMededelingen->bind_param("s", $doelgroepRol);
$stmtMededelingen->execute();
$resMededelingen = $stmtMededelingen->get_result();

$mededelingen = [];
while ($row = $resMededelingen->fetch_assoc()) {
    $mededelingen[] = $row;
}
$stmtMededelingen->close();

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

<div class="mededelingen-container" style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border-radius: 8px; overflow: hidden; margin-bottom: 25px; border: 1px solid #1e293b;">
    
    <div style="background-color: #1e293b; color: #ffffff; padding: 15px 20px; display: flex; align-items: center; gap: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ffffff;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <h3 style="margin: 0; font-size: 16px; font-weight: bold; letter-spacing: 0.5px; font-family: sans-serif;">Actuele Mededelingen</h3>
    </div>

    <div style="background-color: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 15px;">
        <?php if (empty($mededelingen)): ?>
            <p style="color: #6b7280; font-style: italic; margin: 0; font-family: sans-serif;">Er zijn momenteel geen actuele mededelingen.</p>
        <?php else: ?>
            <?php foreach ($mededelingen as $item): 
                // Datum mooi formatteren naar NL stijl (bijv: 12 mei 2026)
                $datumMaanden = [1=>"januari", 2=>"februari", 3=>"maart", 4=>"april", 5=>"mei", 6=>"juni", 7=>"juli", 8=>"augustus", 9=>"september", 10=>"oktober", 11=>"november", 12=>"december"];
                $time = strtotime($item['datum_gemaakt']);
                $dag = date('j', $time);
                $maandNummer = intval(date('n', $time));
                $jaarNummer = date('Y', $time);
                $geformateerdeDatum = $dag . " " . $datumMaanden[$maandNummer] . " " . $jaarNummer;
            ?>
                <div style="background-color: #f0f7ff; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 0 4px 4px 0; font-family: sans-serif;">
                    <span style="color: #93c5fd; font-size: 12px; display: block; margin-bottom: 5px; font-weight: 500;">
                        <?= $geformateerdeDatum ?>
                    </span>
                    <strong style="color: #1e3a8a; font-size: 15px; display: block; margin-bottom: 2px;">
                        <?= htmlspecialchars($item['titel']) ?>
                    </strong>
                    <p style="margin: 0; color: #1e293b; font-size: 14px; line-height: 1.5; white-space: pre-line;">
                        <?= htmlspecialchars($item['bericht']) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<div class="announcement-card">
    <h2>Mededeling Plaatsen</h2>

    <form method="POST">
        <div class="form-group">
            <label for="doelgroep">Doelgroep</label>
            <select id="doelgroep" name="doelgroep">
                <option value="student">Alle studenten</option>
                <option value="instructeur">Alle instructeurs</option>
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
