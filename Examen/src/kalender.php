<?php


$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['userID']) || !isset($_SESSION['rol'])) { 
    header("Location: /login.php"); 
    exit; 
}

$rol    = $_SESSION['rol'];
$userID = intval($_SESSION['userID']);
$naam   = $_SESSION['naam'];

$maanden = [
    5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Augustus",
    9=>"September", 10=>"Oktober", 11=>"November", 12=>"December"
];

$maand       = isset($_GET['maand']) ? intval($_GET['maand']) : intval(date('m'));
$jaar        = 2026;
$vandaag     = date('Y-m-d');
$eersteDag   = date('N', strtotime("$jaar-$maand-01"));
$aantalDagen = date('t', strtotime("$jaar-$maand-01"));

// ── Haal lessen op gefilterd op rol (Veilig met Prepared Statements) ──
if ($rol === 'instructeur') {
    $stmt = $conn->prepare("
        SELECT lessen.*,
               instructeurs.voornaam AS iVoornaam,
               instructeurs.achternaam AS iAchternaam,
               studenten.voornaam  AS sVoornaam,
               studenten.achternaam AS sAchternaam
        FROM lessen
        JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
        JOIN studenten    ON lessen.studentID     = studenten.studentID
        WHERE lessen.instructeurID = ?
        AND MONTH(lesDatum) = ?
        AND YEAR(lesDatum)  = ?
        ORDER BY lestijd ASC
    ");
} else {
    // student
    $stmt = $conn->prepare("
        SELECT lessen.*,
               instructeurs.voornaam AS iVoornaam,
               instructeurs.achternaam AS iAchternaam,
               studenten.voornaam  AS sVoornaam,
               studenten.achternaam AS sAchternaam
        FROM lessen
        JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
        JOIN studenten    ON lessen.studentID     = studenten.studentID
        WHERE lessen.studentID = ?
        AND MONTH(lesDatum) = ?
        AND YEAR(lesDatum)  = ?
        ORDER BY lestijd ASC
    ");
}

$stmt->bind_param("iii", $userID, $maand, $jaar);
$stmt->execute();
$result = $stmt->get_result();

$lessen       = [];
$dagVervallen = [];

// Groepeer lessen per dag
while ($row = $result->fetch_assoc()) {
    $dag = date('j', strtotime($row['lesDatum']));
    $lessen[$dag][] = $row;
}
$stmt->close();

// Markeer dag als vervallen als ALLE lessen op die dag vervallen zijn
foreach ($lessen as $dag => $dagLessen) {
    $aantalVervallen    = count(array_filter($dagLessen, fn($l) => $l['vervallen'] == 1));
    $dagVervallen[$dag] = ($aantalVervallen === count($dagLessen));
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kalender</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <!-- Zelfde headerstijl als dashboard -->
    <div class="dash-header">
        <div>
            <h2>📅 Kalender</h2>
            <span><?= htmlspecialchars($naam) ?> —
                <span class="rol-badge <?= $rol === 'instructeur' ? 'badge-instructeur' : 'badge-student' ?>">
                    <?= $rol === 'instructeur' ? '🎓 Instructeur' : '🚗 Student' ?>
                </span>
            </span>
        </div>
        <a href="../logout.php" class="logout-btn">Uitloggen →</a>
    </div>

    <!-- Navigatie -->
   <div class="top-buttons">
        <?php if ($rol === 'instructeur'): ?>
            <a href="Instructeurdashboard.php" class="nav-btn">Dashboard</a>
        <?php else: ?>
            <a href="Studentdashboard.php" class="nav-btn">Dashboard</a>
        <?php endif; ?>
        
        <div class="nav-btn active">Kalender</div>
        
        <?php if ($rol === 'instructeur'): ?>
            <a href="beschikbaarheid.php" class="nav-btn">Rooster</a>
        <?php endif; ?>

        <?php if ($rol === 'instructeur'): ?>
            <a href="Profieli.php" class="nav-btn">Profiel</a>
        <?php else: ?>
            <a href="Profiels.php" class="nav-btn">Profiel</a>
        <?php endif; ?>
        
        <?php if ($rol === 'instructeur'): ?>
            <a href="les_inroosteren.php" class="nav-btn">+ Les inplannen</a>
        <?php else: ?>
            <a href="les_inroosteren.php" class="nav-btn" style="background:#1b2940;color:white;">+ Nieuwe les</a>
        <?php endif; ?>
    </div>

    <!-- Kalender -->
    <div class="calendar">

        <!-- Maandnavigatie header -->
        <div class="calendar-header">
            <a href="?maand=<?= ($maand > 5) ? $maand - 1 : 5 ?>">❮</a>
            <h2><?= ($maanden[$maand] ?? $maand) . " " . $jaar ?></h2>
            <a href="?maand=<?= ($maand < 12) ? $maand + 1 : 12 ?>">❯</a>
        </div>

        <!-- Dagnamen rij -->
        <div class="days-header">
            <div>Ma</div><div>Di</div><div>Wo</div><div>Do</div>
            <div>Vr</div><div>Za</div><div>Zo</div>
        </div>

        <!-- Kalender grid met dagcellen -->
        <div class="calendar-grid">
            <?php
            // Lege cellen vóór de eerste dag van de maand
            for ($i = 1; $i < $eersteDag; $i++) {
                echo "<div class='day empty'></div>";
            }

            // Dagcellen met eventuele lessen
            for ($dag = 1; $dag <= $aantalDagen; $dag++) {
                $isVervallenDag = isset($dagVervallen[$dag]) && $dagVervallen[$dag];
                $isVandaag      = date('Y-m-d', strtotime("$jaar-$maand-$dag")) === $vandaag;
                $extraClass     = $isVervallenDag ? " vervallen-dag" : ($isVandaag ? " dag-vandaag" : "");

                echo "<div class='day$extraClass'>";
                echo "<div class='date'>" . ($isVandaag ? "<span class='vandaag-dot'></span>" : "") . "$dag</div>";

                // Toon VERVALT label als alle lessen op die dag vervallen zijn
                if ($isVervallenDag) {
                    echo "<span class='vervallen-label'>VERVALT</span>";
                }

                // Toon lesblokjes per dag
                if (isset($lessen[$dag])) {
                    foreach ($lessen[$dag] as $les) {
                        $vervallenClass = $les['vervallen'] ? ' vervallen' : '';
                        // Naam in lesblokje: instructeur of student afhankelijk van rol
                        $lesNaam = $rol === 'instructeur'
                            ? "{$les['sVoornaam']} {$les['sAchternaam']}"
                            : "{$les['iVoornaam']} {$les['iAchternaam']}";
                        echo "
                        <div class='lesson$vervallenClass'
                            onclick=\"openModal(
                                '{$les['lesDatum']}',
                                '" . substr($les['lestijd'],0,5) . "',
                                '{$les['doel']}',
                                '{$les['iVoornaam']} {$les['iAchternaam']}',
                                '{$les['sVoornaam']} {$les['sAchternaam']}',
                                '{$les['onderwerpen']}',
                                '{$les['ophaalLocatie']}',
                                {$les['lesID']}
                            )\">
                            " . substr($les['lestijd'],0,5) . "<br>
                            $lesNaam
                        </div>";
                    }
                }
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- Aankomende lessen: dashboard leskaart stijl -->
    <div class="upcoming">
        <div class="upcoming-header">
            <h3>📋 Aankomende Lessen — <?= $maanden[$maand] ?? $maand ?></h3>
            <?php if ($rol === 'instructeur'): ?>
                <a href="les_inroosteren.php" class="btn-nieuw-les">+ Les inplannen</a>
            <?php else: ?>
                <a href="les_inroosteren.php" class="btn-nieuw-les">+ Nieuwe les</a>
            <?php endif; ?>
        </div>

        <?php
        $heeftLessen = false;
        $dagLabels   = ['','Ma','Di','Wo','Do','Vr','Za','Zo'];

        foreach ($lessen as $dagNr => $dagLessen):
            foreach ($dagLessen as $les):
                if ($les['vervallen']) continue;
                $heeftLessen = true;
                $isVerleden  = $les['lesDatum'] < $vandaag;
                $isVandaag2  = $les['lesDatum'] === $vandaag;
                $kaartClass  = $isVandaag2 ? ' vandaag' : ($isVerleden ? ' verleden' : '');
                $dagNrLes    = date('N', strtotime($les['lesDatum']));
        ?>
        <!-- Leskaart zelfde stijl als dashboard -->
        <div class="les-kaart<?= $kaartClass ?>">

            <!-- Datum blok -->
            <div class="les-datum-blok">
                <div style="font-size:9px;text-transform:uppercase;"><?= $dagLabels[$dagNrLes] ?></div>
                <div class="dag"><?= date('d', strtotime($les['lesDatum'])) ?></div>
                <div class="maandnaam"><?= date('M', strtotime($les['lesDatum'])) ?></div>
            </div>

            <!-- Lesinformatie -->
            <div class="les-info">
                <span class="tijd-badge">⏰ <?= substr($les['lestijd'],0,5) ?></span>
                <?php if ($isVandaag2): ?>
                    <span class="tijd-badge" style="background:#f59e0b;">VANDAAG</span>
                <?php endif; ?>
                <h4><?= htmlspecialchars($les['doel']) ?></h4>
                <p>📍 <?= htmlspecialchars($les['ophaalLocatie']) ?></p>
                <p>📝 <?= htmlspecialchars($les['onderwerpen']) ?></p>
                <?php if ($rol === 'instructeur'): ?>
                    <p>👤 Student: <strong><?= htmlspecialchars($les['sVoornaam'] . ' ' . $les['sAchternaam']) ?></strong></p>
                <?php else: ?>
                    <p>🎓 Instructeur: <strong><?= htmlspecialchars($les['iVoornaam'] . ' ' . $les['iAchternaam']) ?></strong></p>
                <?php endif; ?>
            </div>

            <!-- Actieknoppen (alleen voor niet-verleden lessen) -->
            <?php if (!$isVerleden): ?>
            <div class="les-acties">
                <a href="wijzig.php?lesID=<?= $les['lesID'] ?>" class="edit">Wijzig</a>
                <a href="annuleer.php?lesID=<?= $les['lesID'] ?>&maand=<?= $maand ?>" class="cancel">Annuleer</a>
            </div>
            <?php endif; ?>

        </div>
        <?php
            endforeach;
        endforeach;

        if (!$heeftLessen) {
            echo "<div class='geen-lessen'>📭 Geen aankomende lessen in " . ($maanden[$maand] ?? $maand) . ".</div>";
        }
        ?>
    </div>

</div><!-- /container -->

<!-- Modal: lesdetails popup -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 style="margin-bottom:14px;font-size:16px;color:#1b2940;">📋 Lesinformatie</h2>
        <p><strong>Datum:</strong>       <span id="mDatum"></span></p>
        <p><strong>Tijd:</strong>        <span id="mTijd"></span></p>
        <p><strong>Doel:</strong>        <span id="mDoel"></span></p>
        <p><strong>Instructeur:</strong> <span id="mInstructeur"></span></p>
        <p><strong>Student:</strong>     <span id="mStudent"></span></p>
        <p><strong>Onderwerpen:</strong> <span id="mOnderwerpen"></span></p>
        <p><strong>Ophaallocatie:</strong><span id="mLocatie"></span></p>
        <div style="margin-top:16px;display:flex;gap:10px;">
            <a id="mWijzig"   href="#" class="edit"   style="flex:1;text-align:center;padding:10px;text-decoration:none;">Wijzig</a>
            <a id="mAnnuleer" href="#" class="cancel" style="flex:1;text-align:center;padding:10px;text-decoration:none;">Annuleer</a>
        </div>
    </div>
</div>

<script>
/**
 * openModal - Vult de modal met lesdata en toont hem.
 * Wordt aangeroepen via onclick op een lesblokje in de kalender.
 */
function openModal(datum, tijd, doel, instructeur, student, onderwerpen, locatie, lesID) {
    document.getElementById('mDatum').textContent       = datum;
    document.getElementById('mTijd').textContent        = tijd;
    document.getElementById('mDoel').textContent        = doel;
    document.getElementById('mInstructeur').textContent = instructeur;
    document.getElementById('mStudent').textContent     = student;
    document.getElementById('mOnderwerpen').textContent = onderwerpen;
    document.getElementById('mLocatie').textContent     = locatie;
    document.getElementById('mWijzig').href             = 'wijzig.php?lesID=' + lesID;
    document.getElementById('mAnnuleer').href           = 'annuleer.php?lesID=' + lesID + '&maand=<?= $maand ?>';
    document.getElementById('modal').style.display      = 'block';
}

/**
 * closeModal - Verbergt de modal.
 */
function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

// Sluit modal bij klik buiten de inhoud
window.onclick = function(e) {
    if (e.target === document.getElementById('modal')) closeModal();
};
</script>
</body>
</html>