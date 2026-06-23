<?php

require_once dirname(__DIR__) . '/includes/database.php';

$conn = getDbConnection();
$adminNaam = $_SESSION['naam'] ?? 'Admin';
$message = '';
$messageType = '';

// aantal actieve leerlingen
$sql = "SELECT COUNT(*) AS totaal FROM studenten WHERE status = 'actief'";
$result = $conn->query($sql);

$aantalActieveStudenten = 0;

if ($result && $row = $result->fetch_assoc()) {
    $aantalActieveStudenten = $row['totaal'];
}

//aantal geplande lessen
$sql = "SELECT COUNT(*) AS totaal FROM lessen";
$result = $conn->query($sql);

$aantalLessen = 0;

if ($result && $row = $result->fetch_assoc()) {
    $aantalLessen = $row['totaal'];
}

// meldingen toevoegen
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

// omzet uitrekenen 
$sql = "
SELECT
(
    SELECT COALESCE(SUM(lp.prijs),0)
    FROM student_lespakket slp
    JOIN lespakket lp
        ON slp.idlespakket = lp.idlespakket
)
+
(
    SELECT COALESCE(SUM(prijs),0)
    FROM bijkopen
) AS totaal_omzet
";

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$totaalOmzet = $row['totaal_omzet'];

//slagingspersentage
$sql = "
SELECT ROUND(
    SUM(geslaagd) * 100.0 / SUM(poging),
    2
) AS slagingspercentage
FROM studenten
WHERE poging > 0;
";

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$slagingspercentage = $row['slagingspercentage'];
$stmtMededelingen = $conn->prepare("
    SELECT titel, bericht, datum_gemaakt 
    FROM meldingen 
    WHERE ontvanger_type = 'admin' 
    ORDER BY datum_gemaakt DESC 
    LIMIT 5
");
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
    <link rel="stylesheet" href="<?= htmlspecialchars(src_url('css/AD.css'), ENT_QUOTES, 'UTF-8') ?>">
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
        <a href="AdminWagenpark.php" class="nav-card">Wagenpark</a>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stats-card">
        <h3>omzet</h3>
        <p>€ <?= number_format($totaalOmzet, 2, ',', '.') ?></p>
    </div>
    <div class="stats-card">
        <h3>Actieve studenten</h3>
        <p><?= $aantalActieveStudenten ?></p>
    </div>
        <div class="stats-card">
        <h3>slagingspercentage</h3>
        <p><?= $slagingspercentage ?>%</p>
    </div>
    <div class="stats-card">
        <h3>alle lessen</h3>
        <p><?= $aantalLessen ?></p>
    </div>
</div>


    
<div class="mededelingen-container" style="width: 95%; max-width: 1100px; margin: 40px auto 25px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border-radius: 8px; overflow: hidden; border: 1px solid #1e293b; background: #fff;">

    <div style="background-color: #1e293b; color: #ffffff; padding: 15px 20px; display: flex; align-items: center; gap: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ffffff;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <h3 style="margin: 0; font-size: 16px; font-weight: bold; letter-spacing: 0.5px; font-family: sans-serif;">Actuele Mededelingen</h3>
    </div>

    <div style="background-color: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 15px; max-height: 320px; overflow-y: auto;">
        <?php if (empty($mededelingen)): ?>
            <p style="color: #6b7280; font-style: italic; margin: 0; font-family: sans-serif;">Er zijn momenteel geen actuele mededelingen.</p>
        <?php else: ?>
            <?php foreach ($mededelingen as $item):
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
