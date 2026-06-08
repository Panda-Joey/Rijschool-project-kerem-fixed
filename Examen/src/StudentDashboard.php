<?php
session_start();
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$rol    = $_SESSION['rol'];
$userID = $_SESSION['userID'];
$naam   = $_SESSION['naam'];
$maand  = isset($_GET['maand']) ? intval($_GET['maand']) : intval(date('m'));
$jaar   = 2026;

$maanden = [
    5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Augustus",
    9=>"September", 10=>"Oktober", 11=>"November", 12=>"December"
];

// ── Query op basis van rol ───────────────────────────────────
if ($rol === 'instructeur') {
    $sql = "
        SELECT lessen.*,
               studenten.voornaam  AS sVoornaam,
               studenten.achternaam AS sAchternaam,
               studenten.telefoon  AS sTelefoon,
               studenten.beperking AS sBeperking,
               Autos.merk, Autos.type, Autos.kenteken
        FROM lessen
        JOIN studenten ON lessen.studentID  = studenten.studentID
        JOIN Autos     ON lessen.autoID     = Autos.autoID
        WHERE lessen.instructeurID = $userID
        AND MONTH(lesDatum) = $maand
        AND YEAR(lesDatum)  = $jaar
        AND vervallen = 0
        ORDER BY lesDatum ASC, lestijd ASC
    ";
} else {
    // student
    $sql = "
        SELECT lessen.*,
               instructeurs.voornaam  AS iVoornaam,
               instructeurs.achternaam AS iAchternaam,
               instructeurs.telefoon  AS iTelefoon,
               instructeurs.omschrijving AS iOmschrijving,
               Autos.merk, Autos.type, Autos.kenteken
        FROM lessen
        JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
        JOIN Autos        ON lessen.autoID        = Autos.autoID
        WHERE lessen.studentID = $userID
        AND MONTH(lesDatum) = $maand
        AND YEAR(lesDatum)  = $jaar
        AND vervallen = 0
        ORDER BY lesDatum ASC, lestijd ASC
    ";
}

$result  = $conn->query($sql);
$lessen  = [];
while ($row = $result->fetch_assoc()) $lessen[] = $row;

// ── Volgende les ─────────────────────────────────────────────
$vandaag    = date('Y-m-d');
$volgendeLes = null;
foreach ($lessen as $les) {
    if ($les['lesDatum'] >= $vandaag) { $volgendeLes = $les; break; }
}

// ── Stats ────────────────────────────────────────────────────
$totaalLessen = count($lessen);
$totaalUren   = $totaalLessen * 2; // Elke les duurt 2 uur
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= htmlspecialchars($naam) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h2>
                👋 <?= htmlspecialchars($naam) ?>
                <span class="rol-badge <?= $rol === 'instructeur' ? 'badge-instructeur' : 'badge-student' ?>">
                    <?= $rol === 'instructeur' ? '🎓 Instructeur' : '🚗 Student' ?>
                </span>
            </h2>
            <span>Rijschool Dashboard</span>
        </div>
        <a href="logout.php" class="logout-btn">Uitloggen →</a>
    </div>

    <!-- Nav buttons -->
    <div class="top-buttons">
        <div class="nav-btn active">Dashboard</div>
        <a href="kalender.php" class="nav-btn" style="text-decoration:none;color:inherit;">Kalender</a>
        <a href="beschikbaarheid.php" class="nav-btn" style="text-decoration:none;color:inherit;">Rooster</a>
        <div class="nav-btn">Profiel</div>
        <?php if ($rol === 'student'): ?>
        <a href="les_inroosteren.php" class="nav-btn" style="background:#1b2940;color:white;text-decoration:none;">+ Nieuwe les</a>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="getal"><?= $totaalLessen ?></div>
            <div class="label">Lessen deze maand</div>
        </div>
        <div class="stat-card">
            <div class="getal"><?= $totaalUren ?></div>
            <div class="label">Lesuren gepland</div>
        </div>
        <?php if ($rol === 'student'):
            /* Pakket info via student_lespakket JOIN lespakket */
            $stmtPakket = $conn->prepare("
                SELECT lp.naam AS lesPakket, lp.uren AS lesUren, sl.overige_uren
                FROM student_lespakket sl
                JOIN lespakket lp ON sl.idlespakket = lp.idlespakket
                WHERE sl.studentID = ?
                LIMIT 1
            ");
            $stmtPakket->bind_param("i", $userID);
            $stmtPakket->execute();
            $st = $stmtPakket->get_result()->fetch_assoc();
            $stmtPakket->close();
        ?>
        <div class="stat-card">
            <div class="getal"><?= $st['lesUren'] ?? '—' ?></div>
            <div class="label">Totaal lesuren pakket</div>
        </div>
        <div class="stat-card">
            <div class="getal" style="font-size:16px;"><?= htmlspecialchars($st['lesPakket'] ?? '—') ?></div>
            <div class="label">Lespakket</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Volgende les -->
    <div class="volgende-les">
        <?php if ($volgendeLes): ?>
            <div>
                <h3>VOLGENDE LES</h3>
                <div class="groot">
                    <?= date('d M Y', strtotime($volgendeLes['lesDatum'])) ?>
                    om <?= substr($volgendeLes['lestijd'],0,5) ?>
                </div>
                <div class="detail">
                    📍 <?= htmlspecialchars($volgendeLes['ophaalLocatie']) ?>
                    &nbsp;·&nbsp;
                    🎯 <?= htmlspecialchars($volgendeLes['doel']) ?>
                </div>
            </div>
            <div style="font-size:13px;opacity:.85;">
                <?php if ($rol === 'instructeur'): ?>
                    👤 <?= htmlspecialchars($volgendeLes['sVoornaam'] . ' ' . $volgendeLes['sAchternaam']) ?>
                    <?php if ($volgendeLes['sBeperking']): ?>
                        <span class="tag-beperking">⚠️ Beperking</span>
                    <?php endif; ?>
                <?php else: ?>
                    🎓 <?= htmlspecialchars($volgendeLes['iVoornaam'] . ' ' . $volgendeLes['iAchternaam']) ?>
                <?php endif; ?>
                <br>
                🚗 <?= htmlspecialchars($volgendeLes['merk'] . ' ' . $volgendeLes['type']) ?>
                (<?= htmlspecialchars($volgendeLes['kenteken']) ?>)
            </div>
        <?php else: ?>
            <div class="geen">Geen aankomende lessen deze maand.</div>
        <?php endif; ?>
    </div>

    <!-- Maand navigatie -->
    <div class="maand-nav">
        <a href="?maand=<?= ($maand > 5) ? $maand - 1 : 5 ?>">❮</a>
        <h3><?= $maanden[$maand] ?? $maand ?> <?= $jaar ?></h3>
        <a href="?maand=<?= ($maand < 12) ? $maand + 1 : 12 ?>">❯</a>
    </div>

    <!-- Lessenlijst -->
    <div class="les-lijst">
        <?php if (empty($lessen)): ?>
            <div class="geen-lessen">
                📭 Geen lessen gepland voor <?= $maanden[$maand] ?? $maand ?>.
            </div>
        <?php else: ?>
            <?php foreach ($lessen as $les):
                $isVandaag  = $les['lesDatum'] === $vandaag;
                $isVerleden = $les['lesDatum'] < $vandaag;
                $kaartClass = $isVandaag ? ' vandaag' : ($isVerleden ? ' verleden' : '');
                $dagNamen   = ['','Ma','Di','Wo','Do','Vr','Za','Zo'];
                $dagNr      = date('N', strtotime($les['lesDatum']));
            ?>
            <div class="les-kaart<?= $kaartClass ?>">

                <!-- Datum blok -->
                <div class="les-datum-blok">
                    <div style="font-size:9px;text-transform:uppercase;"><?= $dagNamen[$dagNr] ?></div>
                    <div class="dag"><?= date('d', strtotime($les['lesDatum'])) ?></div>
                    <div class="maandnaam"><?= date('M', strtotime($les['lesDatum'])) ?></div>
                </div>

                <!-- Info -->
                <div class="les-info">
                    <span class="tijd-badge">⏰ <?= substr($les['lestijd'],0,5) ?></span>
                    <?php if ($isVandaag): ?>
                        <span class="tijd-badge" style="background:#f59e0b;">VANDAAG</span>
                    <?php endif; ?>
                    <h4><?= htmlspecialchars($les['doel']) ?></h4>
                    <p>📍 Ophalen: <strong><?= htmlspecialchars($les['ophaalLocatie']) ?></strong></p>
                    <p>📝 <?= htmlspecialchars($les['onderwerpen']) ?></p>

                    <?php if ($rol === 'instructeur'): ?>
                        <p>👤 Student:
                            <strong><?= htmlspecialchars($les['sVoornaam'] . ' ' . $les['sAchternaam']) ?></strong>
                            <?php if ($les['sBeperking']): ?>
                                <span class="tag-beperking">⚠️ Beperking</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($les['sTelefoon'])): ?>
                            <p>📞 <?= htmlspecialchars($les['sTelefoon']) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>🎓 Instructeur:
                            <strong><?= htmlspecialchars($les['iVoornaam'] . ' ' . $les['iAchternaam']) ?></strong>
                        </p>
                        <?php if (!empty($les['iTelefoon'])): ?>
                            <p>📞 <?= htmlspecialchars($les['iTelefoon']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($les['iOmschrijving'])): ?>
                            <p style="color:#888;font-style:italic;"><?= htmlspecialchars($les['iOmschrijving']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Extra: auto info -->
                <div class="les-extra">
                    <p><strong>🚗 Auto</strong></p>
                    <p><?= htmlspecialchars($les['merk'] . ' ' . $les['type']) ?></p>
                    <p>🔑 <?= htmlspecialchars($les['kenteken']) ?></p>
                    <?php if (!empty($les['redenWijzig'])): ?>
                        <p style="margin-top:6px;padding-top:6px;border-top:1px solid #ddd;">
                            <strong>📝 Gewijzigd:</strong><br>
                            <em style="color:#888;"><?= htmlspecialchars($les['redenWijzig']) ?></em>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Acties (alleen voor instructeur) -->
                <?php if ($rol === 'instructeur' && !$isVerleden): ?>
                <div class="les-acties">
                    <a href="wijzig.php?lesID=<?= $les['lesID'] ?>" class="edit" style="text-decoration:none;display:inline-block;text-align:center;">
                        Wijzig
                    </a>
                    <a href="annuleer.php?lesID=<?= $les['lesID'] ?>&maand=<?= $maand ?>" class="cancel" style="text-decoration:none;display:inline-block;text-align:center;">
                        Annuleer
                    </a>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>