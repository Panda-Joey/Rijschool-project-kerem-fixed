<?php
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
$maand  = isset($_GET['maand']) ? intval($_GET['maand']) : intval(date('m'));
$jaar   = 2026;

$maanden = [
    5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Augustus",
    9=>"September", 10=>"Oktober", 11=>"November", 12=>"December"
];

// ── Instructeur Query (Lessen ophalen) ───────────────────────────────
$stmt = $conn->prepare("
    SELECT lessen.*,
           studenten.voornaam  AS sVoornaam,
           studenten.achternaam AS sAchternaam,
           studenten.telefoon  AS sTelefoon,
           studenten.beperking AS sBeperking,
           Autos.merk, Autos.type, Autos.kenteken
    FROM lessen
    JOIN studenten ON lessen.studentID  = studenten.studentID
    JOIN Autos     ON lessen.autoID     = Autos.autoID
    WHERE lessen.instructeurID = ?
    AND MONTH(lesDatum) = ?
    AND YEAR(lesDatum)  = ?
    AND vervallen = 0
    ORDER BY lesDatum ASC, lestijd ASC
");
$stmt->bind_param("iii", $userID, $maand, $jaar);
$stmt->execute();
$result = $stmt->get_result();
$lessen = [];
while ($row = $result->fetch_assoc()) {
    $lessen[] = $row;
}
$stmt->close();

// ── Volgende les bepalen ─────────────────────────────────────────────
$vandaag     = date('Y-m-d');
$volgendeLes = null;
foreach ($lessen as $les) {
    if ($les['lesDatum'] >= $vandaag) { 
        $volgendeLes = $les; 
        break; 
    }
}

// ── Stats ────────────────────────────────────────────────────────────
$totaalLessen = count($lessen);
$totaalUren   = $totaalLessen; // Elke les = 1 uur
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructeur Dashboard — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="dash-header">
        <div>
            <h2>👋 <?= htmlspecialchars($naam) ?> <span class="rol-badge badge-instructeur">🎓 Instructeur</span></h2>
            <span>Rijschool Dashboard</span>
        </div>
        <a href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-btn">Uitloggen →</a>
    </div>

    <div class="top-buttons">
        <div class="nav-btn active">Dashboard</div>
        <a href="kalender.php" class="nav-btn" style="text-decoration:none;color:inherit;">Kalender</a>
        <a href="beschikbaarheid.php" class="nav-btn" style="text-decoration:none;color:inherit;">Rooster</a>
        <div class="nav-btn">Profiel</div>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="stat-card">
            <div class="getal"><?= $totaalLessen ?></div>
            <div class="label">Lessen deze maand</div>
        </div>
        <div class="stat-card">
            <div class="getal"><?= $totaalUren ?></div>
            <div class="label">Lesuren gepland</div>
        </div>
    </div>

    <div class="volgende-les">
        <?php if ($volgendeLes): ?>
            <div>
                <h3>VOLGENDE LES</h3>
                <div class="groot">
                    <?= date('d M Y', strtotime($volgendeLes['lesDatum'])) ?> om <?= substr($volgendeLes['lestijd'],0,5) ?>
                </div>
                <div class="detail">
                    📍 <?= htmlspecialchars($volgendeLes['ophaalLocatie']) ?> &nbsp;·&nbsp; 🎯 <?= htmlspecialchars($volgendeLes['doel']) ?>
                </div>
            </div>
            <div style="font-size:13px;opacity:.85;">
                👤 <?= htmlspecialchars($volgendeLes['sVoornaam'] . ' ' . $volgendeLes['sAchternaam']) ?>
                <?php if ($volgendeLes['sBeperking']): ?>
                    <span class="tag-beperking">⚠️ Beperking</span>
                <?php endif; ?>
                <br>
                🚗 <?= htmlspecialchars($volgendeLes['merk'] . ' ' . $volgendeLes['type']) ?> (<?= htmlspecialchars($volgendeLes['kenteken']) ?>)
            </div>
        <?php else: ?>
            <div class="geen">Geen aankomende lessen deze maand.</div>
        <?php endif; ?>
    </div>

    <div class="maand-nav">
        <a href="?maand=<?= ($maand > 5) ? $maand - 1 : 5 ?>">❮</a>
        <h3><?= $maanden[$maand] ?? $maand ?> <?= $jaar ?></h3>
        <a href="?maand=<?= ($maand < 12) ? $maand + 1 : 12 ?>">❯</a>
    </div>

    <div class="les-lijst">
        <?php if (empty($lessen)): ?>
            <div class="geen-lessen">📭 Geen lessen gepland voor <?= $maanden[$maand] ?? $maand ?>.</div>
        <?php else: ?>
            <?php foreach ($lessen as $les):
                $isVandaag  = $les['lesDatum'] === $vandaag;
                $isVerleden = $les['lesDatum'] < $vandaag;
                $kaartClass = $isVandaag ? ' vandaag' : ($isVerleden ? ' verleden' : '');
                $dagNamen   = ['','Ma','Di','Wo','Do','Vr','Za','Zo'];
                $dagNr      = date('N', strtotime($les['lesDatum']));
            ?>
            <div class="les-kaart<?= $kaartClass ?>">
                <div class="les-datum-blok">
                    <div style="font-size:9px;text-transform:uppercase;"><?= $dagNamen[$dagNr] ?></div>
                    <div class="dag"><?= date('d', strtotime($les['lesDatum'])) ?></div>
                    <div class="maandnaam"><?= date('M', strtotime($les['lesDatum'])) ?></div>
                </div>

                <div class="les-info">
                    <span class="tijd-badge">⏰ <?= substr($les['lestijd'],0,5) ?></span>
                    <?php if ($isVandaag): ?><span class="tijd-badge" style="background:#f59e0b;">VANDAAG</span><?php endif; ?>
                    <h4><?= htmlspecialchars($les['doel']) ?></h4>
                    <p>📍 Ophalen: <strong><?= htmlspecialchars($les['ophaalLocatie']) ?></strong></p>
                    <p>📝 <?= htmlspecialchars($les['onderwerpen']) ?></p>
                    <p>👤 Student: <strong><?= htmlspecialchars($les['sVoornaam'] . ' ' . $les['sAchternaam']) ?></strong>
                        <?php if ($les['sBeperking']): ?><span class="tag-beperking">⚠️ Beperking</span><?php endif; ?>
                    </p>
                    <?php if (!empty($les['sTelefoon'])): ?><p>📞 <?= htmlspecialchars($les['sTelefoon']) ?></p><?php endif; ?>
                </div>

                <div class="les-extra">
                    <p><strong>🚗 Auto</strong></p>
                    <p><?= htmlspecialchars($les['merk'] . ' ' . $les['type']) ?></p>
                    <p>🔑 <?= htmlspecialchars($les['kenteken']) ?></p>
                    <?php if (!empty($les['redenWijzig'])): ?>
                        <p style="margin-top:6px;padding-top:6px;border-top:1px solid #ddd;">
                            <strong>📝 Gewijzigd:</strong><br><em style="color:#888;"><?= htmlspecialchars($les['redenWijzig']) ?></em>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!$isVerleden): ?>
                <div class="les-acties">
                    <a href="wijzig.php?lesID=<?= $les['lesID'] ?>" class="edit" style="text-decoration:none;display:inline-block;text-align:center;">Wijzig</a>
                    <a href="annuleer.php?lesID=<?= $les['lesID'] ?>&maand=<?= $maand ?>" class="cancel" style="text-decoration:none;display:inline-block;text-align:center;">Annuleer</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>