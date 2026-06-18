<?php
if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'instructeur') {
    header("Location: /login.php");
    exit;
}

$servername = "mysql";
$username = "root";
$password = "password";
$dbname = "Eend";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$colCheck = $conn->query("SHOW COLUMNS FROM lessen LIKE 'goedgekeurd'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE lessen ADD COLUMN goedgekeurd TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE lessen ADD COLUMN goedgekeurd_op DATETIME NULL DEFAULT NULL");
}

$instructeurID = (int) $_SESSION['userID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['goedkeur_lesID'])) {
    $lesID = (int) $_POST['goedkeur_lesID'];
    $studentID = (int) $_POST['goedkeur_studentID'];

    $check = $conn->query("SELECT goedgekeurd FROM lessen WHERE lesID = $lesID AND instructeurID = $instructeurID AND vervallen = 0");
    $rij = $check ? $check->fetch_assoc() : null;

    if ($rij && (int) $rij['goedgekeurd'] !== 1) {
        $conn->query("UPDATE lessen SET goedgekeurd = 1, goedgekeurd_op = NOW() WHERE lesID = $lesID");
        $conn->query("UPDATE student_lespakket SET overige_uren = GREATEST(0, overige_uren - 2) WHERE studentID = $studentID");
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['afkeur_lesID'])) {
    $lesID = (int) $_POST['afkeur_lesID'];
    $studentID = (int) $_POST['afkeur_studentID'];

    $check = $conn->query("SELECT goedgekeurd FROM lessen WHERE lesID = $lesID AND instructeurID = $instructeurID AND vervallen = 0");
    $rij = $check ? $check->fetch_assoc() : null;

    if ($rij && (int) $rij['goedgekeurd'] === 1) {
        $conn->query("UPDATE lessen SET goedgekeurd = 0, goedgekeurd_op = NULL WHERE lesID = $lesID");
        $conn->query("UPDATE student_lespakket SET overige_uren = overige_uren + 2 WHERE studentID = $studentID");
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opmerking_lesID'])) {

    $lesID = (int) $_POST['opmerking_lesID'];
    $opmerking = $conn->real_escape_string(trim($_POST['opmerking']));

    $stmt = $conn->prepare("
        UPDATE lessen
        SET opmerkingen = ?
        WHERE lesID = ?
        AND instructeurID = ?
    ");

    $stmt->bind_param("sii", $opmerking, $lesID, $instructeurID);
    $stmt->execute();
    $stmt->close();

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$userID = intval($_SESSION['userID']);
$naam = $_SESSION['naam'];
$maand = isset($_GET['maand']) ? intval($_GET['maand']) : intval(date('m'));
$jaar = 2026;

$maanden = [
    5 => "Mei",
    6 => "Juni",
    7 => "Juli",
    8 => "Augustus",
    9 => "September",
    10 => "Oktober",
    11 => "November",
    12 => "December"
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
$vandaag = date('Y-m-d');
$volgendeLes = null;
foreach ($lessen as $les) {
    if ($les['lesDatum'] >= $vandaag) {
        $volgendeLes = $les;
        break;
    }
}

$doelgroepRol = 'instructeur';

$stmtMededelingen = $conn->prepare("
    SELECT titel, bericht, datum_gemaakt 
    FROM meldingen 
    WHERE (ontvanger_type = ? AND (ontvanger_id = ? OR ontvanger_id IS NULL))
       OR ontvanger_type = 'iedereen'
       OR ontvanger_type = 'alle_instructeurs'
    ORDER BY datum_gemaakt DESC 
    LIMIT 5
");
// We binden de rol ('instructeur') én het specifieke ID van deze ingelogde gebruiker
$stmtMededelingen->bind_param("si", $doelgroepRol, $userID);
$stmtMededelingen->execute();
$resMededelingen = $stmtMededelingen->get_result();


$mededelingen = [];
while ($row = $resMededelingen->fetch_assoc()) {
    $mededelingen[] = $row;
}
$stmtMededelingen->close();


// ── Stats ────────────────────────────────────────────────────────────
$totaalLessen = count($lessen);
$totaalUren = $totaalLessen; // Elke les = 1 uur
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

        <?php
        $navActief = 'dashboard';
        $paginaLabel = 'Rijschool Dashboard';
        require_once 'instructeur_nav.php';
        ?>

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

        <div class="mededelingen-container"
            style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border-radius: 8px; overflow: hidden; margin-bottom: 25px; border: 1px solid #1e293b;">

            <div
                style="background-color: #1e293b; color: #ffffff; padding: 15px 20px; display: flex; align-items: center; gap: 10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="color: #ffffff;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3
                    style="margin: 0; font-size: 16px; font-weight: bold; letter-spacing: 0.5px; font-family: sans-serif;">
                    Actuele Mededelingen</h3>
            </div>

            <div
                style="background-color: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 15px; max-height: 320px; overflow-y: auto;">
                <?php if (empty($mededelingen)): ?>
                    <p style="color: #6b7280; font-style: italic; margin: 0; font-family: sans-serif;">Er zijn momenteel
                        geen actuele mededelingen.</p>
                <?php else: ?>
                    <?php foreach ($mededelingen as $item):
                        // Datum mooi formatteren naar NL stijl (bijv: 12 mei 2026)
                        $datumMaanden = [1 => "januari", 2 => "februari", 3 => "maart", 4 => "april", 5 => "mei", 6 => "juni", 7 => "juli", 8 => "augustus", 9 => "september", 10 => "oktober", 11 => "november", 12 => "december"];
                        $time = strtotime($item['datum_gemaakt']);
                        $dag = date('j', $time);
                        $maandNummer = intval(date('n', $time));
                        $jaarNummer = date('Y', $time);
                        $geformateerdeDatum = $dag . " " . $datumMaanden[$maandNummer] . " " . $jaarNummer;
                        ?>
                        <div
                            style="background-color: #f0f7ff; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 0 4px 4px 0; font-family: sans-serif;">
                            <span
                                style="color: #93c5fd; font-size: 12px; display: block; margin-bottom: 5px; font-weight: 500;">
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

        <div class="volgende-les">
            <?php if ($volgendeLes): ?>
                <div>
                    <h3>VOLGENDE LES</h3>
                    <div class="groot">
                        <?= date('d M Y', strtotime($volgendeLes['lesDatum'])) ?> om
                        <?= substr($volgendeLes['lestijd'], 0, 5) ?>
                    </div>
                    <div class="detail">
                        📍 <?= htmlspecialchars($volgendeLes['ophaalLocatie']) ?> &nbsp;·&nbsp; 🎯
                        <?= htmlspecialchars($volgendeLes['doel']) ?>
                    </div>
                </div>
                <div style="font-size:13px;opacity:.85;">
                    👤 <?= htmlspecialchars($volgendeLes['sVoornaam'] . ' ' . $volgendeLes['sAchternaam']) ?>
                    <?php if ($volgendeLes['sBeperking']): ?>
                        <span class="tag-beperking">⚠️ Beperking</span>
                    <?php endif; ?>
                    <br>
                    🚗 <?= htmlspecialchars($volgendeLes['merk'] . ' ' . $volgendeLes['type']) ?>
                    (<?= htmlspecialchars($volgendeLes['kenteken']) ?>)
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
                    $isVandaag = $les['lesDatum'] === $vandaag;
                    $isVerleden = $les['lesDatum'] < $vandaag;
                    $kaartClass = $isVandaag ? ' vandaag' : ($isVerleden ? ' verleden' : '');
                    $dagNamen = ['', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
                    $dagNr = date('N', strtotime($les['lesDatum']));
                    ?>
                    <div class="les-kaart<?= $kaartClass ?>">
                        <div class="les-datum-blok">
                            <div style="font-size:9px;text-transform:uppercase;"><?= $dagNamen[$dagNr] ?></div>
                            <div class="dag"><?= date('d', strtotime($les['lesDatum'])) ?></div>
                            <div class="maandnaam"><?= date('M', strtotime($les['lesDatum'])) ?></div>
                        </div>

                        <div class="les-info">
                            <span class="tijd-badge">⏰ <?= substr($les['lestijd'], 0, 5) ?></span>
                            <?php if ($isVandaag): ?><span class="tijd-badge"
                                    style="background:#f59e0b;">VANDAAG</span><?php endif; ?>
                            <h4><?= htmlspecialchars($les['doel']) ?></h4>
                            <p>📍 Ophalen: <strong><?= htmlspecialchars($les['ophaalLocatie']) ?></strong></p>
                            <p>📝 <?= htmlspecialchars($les['onderwerpen']) ?></p>

                            <?php if (!empty($les['opmerkingen'])): ?>
                                <p style="margin-top:8px;">
                                    <strong>📝 Opmerking instructeur:</strong><br>
                                    <?= nl2br(htmlspecialchars($les['opmerkingen'])) ?>
                                </p>
                            <?php endif; ?>

                            <p>👤 Student:
                                <strong><?= htmlspecialchars($les['sVoornaam'] . ' ' . $les['sAchternaam']) ?></strong>
                                <?php if ($les['sBeperking']): ?><span class="tag-beperking">⚠️ Beperking</span><?php endif; ?>
                            </p>
                            <?php if (!empty($les['sTelefoon'])): ?>
                                <p>📞 <?= htmlspecialchars($les['sTelefoon']) ?></p><?php endif; ?>
                        </div>

                        <div class="les-extra">
                            <p><strong>🚗 Auto</strong></p>
                            <p><?= htmlspecialchars($les['merk'] . ' ' . $les['type']) ?></p>
                            <p>🔑 <?= htmlspecialchars($les['kenteken']) ?></p>
                            <?php if (!empty($les['redenWijzig'])): ?>
                                <p style="margin-top:6px;padding-top:6px;border-top:1px solid #ddd;">
                                    <strong>📝 Gewijzigd:</strong><br><em
                                        style="color:#888;"><?= htmlspecialchars($les['redenWijzig']) ?></em>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="les-acties">
                            <?php if (!$isVerleden): ?>
                                <a href="wijzig.php?lesID=<?= $les['lesID'] ?>" class="edit"
                                    style="text-decoration:none;display:inline-block;text-align:center;">
                                    Wijzig
                                </a>
                                <a href="annuleer.php?lesID=<?= $les['lesID'] ?>&maand=<?= $maand ?>" class="cancel"
                                    style="text-decoration:none;display:inline-block;text-align:center;">
                                    Annuleer
                                </a>
                            <?php else: ?>
                                <form method="POST" style="margin-bottom:10px;">
                                    <input type="hidden" name="opmerking_lesID" value="<?= (int) $les['lesID'] ?>">
                                    <textarea name="opmerking" rows="4" style="width:100%;margin-bottom:5px;"
                                        placeholder="Opmerkingen over deze les..."><?= htmlspecialchars($les['opmerkingen'] ?? '') ?></textarea>
                                    <button type="submit" class="edit">
                                        💾 Opslaan
                                    </button>
                                </form>
                                <?php if (empty($les['goedgekeurd'])): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="goedkeur_lesID" value="<?= (int) $les['lesID'] ?>">
                                        <input type="hidden" name="goedkeur_studentID" value="<?= (int) $les['studentID'] ?>">
                                        <button type="submit" class="btn-goedkeur">
                                            ✅ Goedkeuren<br>
                                            <small>-2 lesuren</small>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge-goedgekeurd">
                                        ✅ Goedgekeurd
                                    </span>
                                    <form method="POST" style="margin-top:8px;">
                                        <input type="hidden" name="afkeur_lesID" value="<?= (int) $les['lesID'] ?>">
                                        <input type="hidden" name="afkeur_studentID" value="<?= (int) $les['studentID'] ?>">
                                        <button type="submit" class="btn-afkeur" onclick="return confirm('Goedkeuring intrekken?');">
                                            ↩ Afkeuren<br>
                                            <small>+2 lesuren</small>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>