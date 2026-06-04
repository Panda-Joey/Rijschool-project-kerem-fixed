<?php
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


if (!isset($_SESSION['userID'])) { header("Location: /login.php"); exit; }




$rol    = $_SESSION['rol'];
$userID = $_SESSION['userID'];
$naam   = $_SESSION['naam'];
$succes = "";
$fout   = "";

$dagNamen = ['','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'];

$dashboardLink = ($rol === 'instructeur') ? 'Instructeurdashboard.php' : 'Studentdashboard.php';

// ── Auto's ophalen ─────────────────────────────────────────
$autos = [];
require_once dirname(__DIR__) . '/includes/autos.php';
ensureAutosAvailabilityColumns($conn);
$r = $conn->query("SELECT * FROM Autos ORDER BY beschikbaar DESC, merk, type");
while ($row = $r->fetch_assoc()) {
    $autos[] = $row;
}

// ── Studenten ophalen (instructeur-modus) ──────────────────
$studenten = [];
if ($rol === 'instructeur') {
    $r = $conn->query("SELECT studentID, voornaam, tussenvoegsel, achternaam FROM studenten ORDER BY achternaam");
    while ($row = $r->fetch_assoc()) $studenten[] = $row;
}

// ── Instructeurs ophalen (student-modus) ───────────────────
$instructeurs = [];
if ($rol === 'student') {
    $r = $conn->query("SELECT instructeurID, voornaam, achternaam FROM instructeurs ORDER BY voornaam");
    while ($row = $r->fetch_assoc()) $instructeurs[] = $row;
}

// ── Datum bepalen ─────────────────────────────────────────
$gekozenDatum = $_GET['datum'] ?? $_POST['lesDatum'] ?? date('Y-m-d', strtotime('+1 day'));
$dagNr        = date('N', strtotime($gekozenDatum));
$dagNaam      = $dagNamen[$dagNr];

// ── Bouw slots per instructeur voor de gekozen datum ──────
function bouwInstrData($conn, $instrLijst, $gekozenDatum, $dagNaam) {
    $data = [];
    foreach ($instrLijst as $instr) {
        $iID = $instr['instructeurID'];

        // Beschikbaarheid op die dag ophalen
        $bRes = $conn->query("
            SELECT beginTijd, eindTijd, maxLessen FROM beschikbaarheid
            WHERE instructeurID = $iID AND dagNaam = '$dagNaam' LIMIT 1
        ");
        $bRow = ($bRes && $bRes->num_rows > 0) ? $bRes->fetch_assoc() : null;

        // Bezette tijden ophalen
        $bezet = [];
        $bezRes = $conn->query("
            SELECT lestijd FROM lessen
            WHERE instructeurID = $iID AND lesDatum = '$gekozenDatum' AND vervallen = 0
        ");
        while ($row = $bezRes->fetch_assoc()) $bezet[] = substr($row['lestijd'], 0, 5);

        // Slots genereren: stap 30 min, elk slot duurt 2u
        $slots = [];
        if ($bRow) {
            $bMin = intval(substr($bRow['beginTijd'],0,2))*60 + intval(substr($bRow['beginTijd'],3,2));
            $eMin = intval(substr($bRow['eindTijd'],0,2))*60  + intval(substr($bRow['eindTijd'],3,2));
            for ($m = $bMin; $m + 120 <= $eMin; $m += 30) {
                $slotTijd = sprintf('%02d:%02d', intdiv($m,60), $m%60);
                $overlap  = false;
                foreach ($bezet as $b) {
                    $bM = intval(substr($b,0,2))*60 + intval(substr($b,3,2));
                    if ($bM < $m + 120 && $bM + 120 > $m) { $overlap = true; break; }
                }
                $slots[] = [
                    'tijd'  => $slotTijd,
                    'eind'  => sprintf('%02d:%02d', intdiv($m+120,60), ($m+120)%60),
                    'bezet' => $overlap
                ];
            }
        }

        $data[$iID] = [
            'naam'        => $instr['voornaam'] . ' ' . $instr['achternaam'],
            'beschikbaar' => $bRow ? true : false,
            'begin'       => $bRow ? substr($bRow['beginTijd'],0,5) : null,
            'eind'        => $bRow ? substr($bRow['eindTijd'],0,5)  : null,
            'maxLessen'   => $bRow ? $bRow['maxLessen'] : 0,
            'nogVrij'     => $bRow ? max(0, $bRow['maxLessen'] - count($bezet)) : 0,
            'slots'       => $slots,
        ];
    }
    return $data;
}

// Bouw instrLijst: instructeur ziet alleen zichzelf
$instrLijst = $rol === 'instructeur'
    ? [['instructeurID' => $userID,
        'voornaam'      => explode(' ', $naam)[0],
        'achternaam'    => implode(' ', array_slice(explode(' ', $naam), 1))]]
    : $instructeurs;

$instrData = bouwInstrData($conn, $instrLijst, $gekozenDatum, $dagNaam);

// ── Verwerk formulier ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datum       = $conn->real_escape_string($_POST['lesDatum']);
    $tijd        = $conn->real_escape_string($_POST['lestijd']);
    $instrID     = intval($_POST['instructeurID']);
    $studentID   = $rol === 'instructeur' ? intval($_POST['studentID']) : $userID;
    $autoID      = intval($_POST['autoID']);
    $ophaal      = $conn->real_escape_string(trim($_POST['ophaalLocatie']));
    $doel        = $conn->real_escape_string(trim($_POST['doel']));
    $onderwerpen = $conn->real_escape_string(trim($_POST['onderwerpen'] ?? ''));

    if (!$datum || !$tijd || !$instrID || !$studentID || !$autoID || !$ophaal || !$doel) {
        $fout = "Vul alle verplichte velden in.";
    } else {
        $autoStmt = $conn->prepare("SELECT beschikbaar, statusReden FROM Autos WHERE autoID = ?");
        $autoStmt->bind_param("i", $autoID);
        $autoStmt->execute();
        $autoRow = $autoStmt->get_result()->fetch_assoc();
        $autoStmt->close();
        if (!$autoRow || (int) $autoRow['beschikbaar'] !== 1) {
            $reden = trim($autoRow['statusReden'] ?? '');
            $fout = $reden !== ''
                ? "Deze auto is niet beschikbaar: $reden"
                : "Deze auto is niet beschikbaar.";
        }
    }
    if (!$fout && $datum && $tijd && $instrID && $studentID && $autoID && $ophaal && $doel) {
        $dNr = date('N', strtotime($datum));
        $dNm = $dagNamen[$dNr];

        // Beschikbaarheid check
        $bChk = $conn->query("
            SELECT * FROM beschikbaarheid
            WHERE instructeurID = $instrID AND dagNaam = '$dNm'
        ");
        if (!$bChk || $bChk->num_rows === 0) {
            $fout = "De instructeur is niet beschikbaar op $dNm.";
        } else {
            $bRow = $bChk->fetch_assoc();
            $sMin = intval(substr($tijd,0,2))*60 + intval(substr($tijd,3,2));
            $bMin = intval(substr($bRow['beginTijd'],0,2))*60 + intval(substr($bRow['beginTijd'],3,2));
            $eMin = intval(substr($bRow['eindTijd'],0,2))*60  + intval(substr($bRow['eindTijd'],3,2));

            if ($sMin < $bMin || $sMin + 120 > $eMin) {
                $fout = "Dit tijdstip valt buiten de beschikbaarheid ({$bRow['beginTijd']}–{$bRow['eindTijd']}).";
            } else {
                // Overlap check
                $ovRes = $conn->query("
                    SELECT lestijd FROM lessen
                    WHERE instructeurID = $instrID AND lesDatum = '$datum' AND vervallen = 0
                ");
                $overlap = false;
                while ($ov = $ovRes->fetch_assoc()) {
                    $oMin = intval(substr($ov['lestijd'],0,2))*60 + intval(substr($ov['lestijd'],3,2));
                    if ($oMin < $sMin + 120 && $oMin + 120 > $sMin) { $overlap = true; break; }
                }

                // Max lessen check
                $tel = $conn->query("
                    SELECT COUNT(*) AS n FROM lessen
                    WHERE instructeurID = $instrID AND lesDatum = '$datum' AND vervallen = 0
                ")->fetch_assoc()['n'];

                if ($overlap) {
                    $eindStr = sprintf('%02d:%02d', intdiv($sMin+120,60), ($sMin+120)%60);
                    $fout = "Overlap: instructeur heeft al een les die botst met $tijd–$eindStr.";
                } elseif ($tel >= $bRow['maxLessen']) {
                    $fout = "De instructeur heeft al het maximale aantal lessen op $datum.";
                } else {
                    $conn->query("
                        INSERT INTO lessen
                            (lesDatum, lestijd, ophaalLocatie, doel, onderwerpen, studentID, instructeurID, autoID, vervallen)
                        VALUES
                            ('$datum','$tijd:00','$ophaal','$doel','$onderwerpen',$studentID,$instrID,$autoID,0)
                    ");
                    $door   = $rol === 'instructeur' ? "door instructeur ingepland" : "aangevraagd";
                    $succes = "Les $door op <strong>$datum om $tijd</strong>! <a href='dashboard.php'>Naar dashboard →</a>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $rol === 'instructeur' ? 'Les inplannen' : 'Nieuwe les' ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <!-- Header zelfde stijl als dashboard -->
    <div class="dash-header">
        <div>
            <h2><?= $rol === 'instructeur' ? '📋 Les inplannen voor leerling' : '📅 Nieuwe les aanvragen' ?></h2>
            <span><?= htmlspecialchars($naam) ?></span>
        </div>
        <a href="../logout.php" class="logout-btn">Uitloggen →</a>
    </div>

    <!-- Navigatie: let op correcte sluit-tags -->
    <div class="top-buttons">
        <a href="<?= $dashboardLink ?>" class="nav-btn">Dashboard</a>
        <a href="kalender.php"           class="nav-btn">Kalender</a>
        <a href="beschikbaarheid.php" class="nav-btn">Rooster</a>
        <div class="nav-btn active"><?= $rol === 'instructeur' ? '+ Les inplannen' : '+ Nieuwe les' ?></div>
    </div>

    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>

    <div class="inrooster-wrap">

        <!-- Stappenbalk: 3 stappen voor instructeur, 4 voor student -->
        <div class="stappen">
            <div class="stap actief" id="stap1"><span class="nr">1</span>Datum</div>
            <?php if ($rol === 'student'): ?>
                <div class="stap" id="stap2"><span class="nr">2</span>Instructeur</div>
            <?php endif; ?>
            <div class="stap" id="<?= $rol === 'instructeur' ? 'stap2' : 'stap3' ?>"><span class="nr"><?= $rol === 'instructeur' ? '2' : '3' ?></span>Tijdstip</div>
            <div class="stap" id="<?= $rol === 'instructeur' ? 'stap3' : 'stap4' ?>"><span class="nr"><?= $rol === 'instructeur' ? '3' : '4' ?></span>Details</div>
        </div>

        <!-- Stap 1: Datum kiezen -->
        <div class="datum-wrap">
            <div class="form-group" style="margin-bottom:0;">
                <label>📅 Kies een datum</label>
                <input
                    type="date"
                    id="datumPicker"
                    value="<?= htmlspecialchars($gekozenDatum) ?>"
                    min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                    max="2026-12-31"
                    onchange="laadPagina()"
                    style="width:100%;padding:11px;border:2px solid #1b2940;font-size:14px;font-family:Arial;box-sizing:border-box;"
                >
                <div style="font-size:11px;color:#666;margin-top:6px;">
                    <?= $dagNaam ?> <?= date('d-m-Y', strtotime($gekozenDatum)) ?>
                </div>
            </div>
        </div>

        <?php
        // ── INSTRUCTEUR-MODUS: toon eigen slots direct ──────────
        if ($rol === 'instructeur'):
            $myData = $instrData[$userID] ?? null;
        ?>
        <div style="margin-bottom:12px;">
            <?php if (!$myData || !$myData['beschikbaar']): ?>
                <div class="geen-lessen">
                    ⚠️ Je hebt geen beschikbaarheid op <strong><?= $dagNaam ?></strong>.
                    <a href="beschikbaarheid.php" style="color:#1b2940;font-weight:bold;">Stel je rooster in →</a>
                </div>
            <?php elseif ($myData['nogVrij'] <= 0): ?>
                <div class="fout">🚫 Je rooster op <?= $dagNaam ?> is al vol.</div>
            <?php else: ?>
                <div class="instr-kaart" style="cursor:default;border-color:#1b2940;">
                    <div class="instr-naam">
                        🎓 <?= htmlspecialchars($naam) ?>
                        <span style="font-size:10px;color:#28a745;font-weight:bold;margin-left:8px;">— Jouw beschikbaarheid</span>
                    </div>
                    <div class="instr-meta">
                        ⏰ <?= $myData['begin'] ?> – <?= $myData['eind'] ?>
                        &nbsp;·&nbsp; Max <?= $myData['maxLessen'] ?> lessen
                        &nbsp;·&nbsp; Nog <strong><?= $myData['nogVrij'] ?></strong> plek(ken) vrij
                        &nbsp;·&nbsp; Elke les = 2 uur
                    </div>
                    <!-- Plek-indicator -->
                    <div class="plek-balk">
                        <?php for ($p = 1; $p <= $myData['maxLessen']; $p++): ?>
                            <div class="plek-blok <?= $p <= ($myData['maxLessen'] - $myData['nogVrij']) ? 'bezet' : 'vrij' ?>"></div>
                        <?php endfor; ?>
                    </div>
                    <!-- Tijdslot knoppen -->
                    <div style="font-size:11px;color:#555;margin-bottom:6px;">Kies een tijdstip:</div>
                    <div class="slot-knoppen">
                        <?php foreach ($myData['slots'] as $slot): ?>
                            <button
                                type="button"
                                class="slot-knop <?= $slot['bezet'] ? 'bezet' : '' ?>"
                                <?= $slot['bezet'] ? 'disabled' : '' ?>
                                onclick="kiesTijdslot('<?= $slot['tijd'] ?>', '<?= $slot['eind'] ?>')"
                            ><?= $slot['tijd'] ?>–<?= $slot['eind'] ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // ── STUDENT-MODUS: kies instructeur + slot ──────────────
        elseif ($rol === 'student'):
        ?>
        <div id="instrSectie" style="margin-bottom:12px;">
            <div style="font-size:12px;color:#555;margin-bottom:8px;">
                Beschikbare instructeurs op <strong><?= $dagNaam ?></strong>:
            </div>
            <?php if (empty($instructeurs)): ?>
                <div class="geen-lessen">Geen instructeurs beschikbaar.</div>
            <?php else: ?>
                <div class="instr-kaarten">
                    <?php foreach ($instructeurs as $instr):
                        $iID   = $instr['instructeurID'];
                        $data  = $instrData[$iID] ?? null;
                        if (!$data) continue;
                        $isVol = !$data['beschikbaar'] || $data['nogVrij'] <= 0;
                    ?>
                    <div class="instr-kaart <?= $isVol ? 'vol' : '' ?>"
                         id="instrKaart_<?= $iID ?>">

                        <div class="instr-naam">
                            🎓 <?= htmlspecialchars($data['naam']) ?>
                            <?php if (!$data['beschikbaar']): ?>
                                <span style="color:#999;font-size:10px;"> — niet beschikbaar op <?= $dagNaam ?></span>
                            <?php elseif ($data['nogVrij'] <= 0): ?>
                                <span style="color:#dc3545;font-size:10px;"> — VOL</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($data['beschikbaar'] && !$isVol): ?>
                            <div class="instr-meta">
                                ⏰ <?= $data['begin'] ?> – <?= $data['eind'] ?>
                                &nbsp;·&nbsp; Nog <?= $data['nogVrij'] ?> plek(ken) vrij
                                &nbsp;·&nbsp; Elke les = 2 uur
                            </div>
                            <div class="plek-balk">
                                <?php for ($p = 1; $p <= $data['maxLessen']; $p++): ?>
                                    <div class="plek-blok <?= $p <= ($data['maxLessen'] - $data['nogVrij']) ? 'bezet' : 'vrij' ?>"></div>
                                <?php endfor; ?>
                            </div>
                            <div style="font-size:11px;color:#555;margin-bottom:6px;">Kies een tijdstip:</div>
                            <div class="slot-knoppen" id="slots_<?= $iID ?>">
                                <?php foreach ($data['slots'] as $slot): ?>
                                    <button
                                        type="button"
                                        class="slot-knop <?= $slot['bezet'] ? 'bezet' : '' ?>"
                                        <?= $slot['bezet'] ? 'disabled' : '' ?>
                                        onclick="kiesTijdslotStudent(<?= $iID ?>, '<?= htmlspecialchars($data['naam']) ?>', '<?= $slot['tijd'] ?>', '<?= $slot['eind'] ?>')"
                                    ><?= $slot['tijd'] ?>–<?= $slot['eind'] ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Detailformulier: verborgen tot slot gekozen -->
        <div class="formulier-sectie" id="formulierSectie">

            <!-- Samenvatting bovenin formulier -->
            <div class="keuze-samenvatting">
                <div><strong id="samDatum">—</strong>Datum</div>
                <?php if ($rol === 'student'): ?>
                    <div><strong id="samInstr">—</strong>Instructeur</div>
                <?php endif; ?>
                <div><strong id="samTijd">—</strong>Tijdstip (2 uur)</div>
            </div>

            <form method="POST" action="les_inroosteren.php" onsubmit="return valideerForm()">
                <input type="hidden" name="lesDatum"      id="hiddenDatum" value="<?= htmlspecialchars($gekozenDatum) ?>">
                <input type="hidden" name="lestijd"       id="hiddenTijd">
                <input type="hidden" name="instructeurID" id="hiddenInstr" value="<?= $rol === 'instructeur' ? $userID : '' ?>">

                <!-- Leerling kiezen (alleen instructeur) -->
                <?php if ($rol === 'instructeur'): ?>
                <div class="form-group">
                    <label>👤 Voor welke leerling? <span style="color:#dc3545;">*</span></label>
                    <select name="studentID" required>
                        <option value="">— Kies een leerling —</option>
                        <?php foreach ($studenten as $st):
                            $tv   = $st['tussenvoegsel'] ? $st['tussenvoegsel'] . ' ' : '';
                            $vol  = $st['voornaam'] . ' ' . $tv . $st['achternaam'];
                        ?>
                            <option value="<?= $st['studentID'] ?>"
                                <?= (isset($_POST['studentID']) && $_POST['studentID'] == $st['studentID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vol) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Ophaallocatie -->
                <div class="form-group">
                    <label>📍 Ophaallocatie <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="ophaalLocatie"
                        placeholder="bv. Rotterdam Centrum, Delft Station..."
                        value="<?= htmlspecialchars($_POST['ophaalLocatie'] ?? '') ?>"
                        maxlength="100" required>
                </div>

                <!-- Leerdoel -->
                <div class="form-group">
                    <label>🎯 Leerdoel <span style="color:#dc3545;">*</span></label>
                    <select name="doel" required>
                        <option value="">— Kies een onderwerp —</option>
                        <?php
                        $doelen = ['Rotondes','Snelweg','Parkeren','Voorrang','Stadsverkeer',
                                   'Inhalen','Noodremmen','Spiegels & dode hoek','Theorie in praktijk'];
                        foreach ($doelen as $d)
                            echo "<option value='$d'" . (($_POST['doel'] ?? '') === $d ? ' selected' : '') . ">$d</option>";
                        ?>
                    </select>
                </div>

                <!-- Extra opmerkingen -->
                <div class="form-group">
                    <label>📝 Extra opmerkingen</label>
                    <textarea name="onderwerpen" placeholder="Wat wil je specifiek oefenen?" maxlength="255"
                    ><?= htmlspecialchars($_POST['onderwerpen'] ?? '') ?></textarea>
                </div>

                <!-- Auto -->
                <div class="form-group">
                    <label>🚗 Auto <span style="color:#dc3545;">*</span></label>
                    <select name="autoID" required>
                        <option value="">— Kies een auto —</option>
                        <?php foreach ($autos as $auto):
                            $magKiezen = (int) ($auto['beschikbaar'] ?? 1) === 1;
                            $label = autoLabel($auto);
                            if (!$magKiezen) {
                                $reden = trim($auto['statusReden'] ?? '');
                                $label .= $reden !== '' ? ' — niet beschikbaar: ' . $reden : ' — niet beschikbaar';
                            }
                        ?>
                            <option value="<?= (int) $auto['autoID'] ?>"<?= $magKiezen ? '' : ' disabled' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    $beschikbaarAutos = count(array_filter($autos, fn($a) => (int) ($a['beschikbaar'] ?? 1) === 1));
                    if ($beschikbaarAutos < count($autos)): ?>
                    <p class="form-hint" style="margin-top:0.35rem;font-size:0.9rem;color:#64748b;">
                        <?= $beschikbaarAutos ?> van <?= count($autos) ?> lesauto's zijn nu beschikbaar.
                        Beheer status via <strong>Wagenpark</strong> (admin).
                    </p>
                    <?php endif; ?>
                </div>

                <?php if ($fout): ?>
                    <div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div>
                <?php endif; ?>

                <div class="btn-row">
                    <button type="button" class="btn-terug" onclick="resetFormulier()">← Terug</button>
                    <button type="submit" class="btn-opslaan">
                        <?= $rol === 'instructeur' ? '📋 Les inplannen' : '✅ Les aanvragen' ?>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /inrooster-wrap -->
</div><!-- /container -->

<script>
const rolIsInstructeur = <?= $rol === 'instructeur' ? 'true' : 'false' ?>;
const aantalStappen    = rolIsInstructeur ? 3 : 4;

/**
 * laadPagina — Herlaadt pagina met nieuwe datum na datumwissel.
 */
function laadPagina() {
    const d = document.getElementById('datumPicker').value;
    if (d) window.location.href = 'les_inroosteren.php?datum=' + d;
}

/**
 * kiesTijdslot — Instructeur kiest tijdslot (is altijd zichzelf als instructeur).
 */
function kiesTijdslot(tijd, eind) {
    document.querySelectorAll('.slot-knop').forEach(b => b.classList.remove('actief'));
    event.currentTarget.classList.add('actief');
    toonFormulier(tijd, eind, null);
    setStap(rolIsInstructeur ? 2 : 3);
}

/**
 * kiesTijdslotStudent — Student kiest instructeur + tijdslot tegelijk.
 */
function kiesTijdslotStudent(iID, instrNaam, tijd, eind) {
    // Markeer instructeurskaart
    document.querySelectorAll('.instr-kaart').forEach(k => k.classList.remove('gekozen'));
    document.getElementById('instrKaart_' + iID)?.classList.add('gekozen');

    // Markeer slot-knop
    document.querySelectorAll('.slot-knop').forEach(b => b.classList.remove('actief'));
    event.currentTarget.classList.add('actief');

    document.getElementById('hiddenInstr').value = iID;
    if (document.getElementById('samInstr'))
        document.getElementById('samInstr').textContent = instrNaam;

    toonFormulier(tijd, eind, instrNaam);
    setStap(4);
}

/**
 * toonFormulier — Vult samenvatting + hidden inputs en toont detailformulier.
 */
function toonFormulier(tijd, eind, instrNaam) {
    const datum = document.getElementById('datumPicker').value;
    document.getElementById('samDatum').textContent = datum;
    document.getElementById('samTijd').textContent  = tijd + '–' + eind;
    document.getElementById('hiddenDatum').value    = datum;
    document.getElementById('hiddenTijd').value     = tijd;

    document.getElementById('formulierSectie').classList.add('zichtbaar');
    document.getElementById('formulierSectie').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * resetFormulier — Verbergt formulier en wist selecties.
 */
function resetFormulier() {
    document.getElementById('formulierSectie').classList.remove('zichtbaar');
    document.querySelectorAll('.instr-kaart').forEach(k => k.classList.remove('gekozen'));
    document.querySelectorAll('.slot-knop').forEach(b => b.classList.remove('actief'));
    document.getElementById('hiddenTijd').value = '';
    if (!rolIsInstructeur) document.getElementById('hiddenInstr').value = '';
    setStap(1);
}

/**
 * setStap — Werkt de stappenbalk bij.
 */
function setStap(n) {
    for (let i = 1; i <= aantalStappen; i++) {
        const el = document.getElementById('stap' + i);
        if (!el) continue;
        el.classList.remove('actief', 'klaar');
        if (i < n)  el.classList.add('klaar');
        if (i === n) el.classList.add('actief');
    }
}

/**
 * valideerForm — Controleert verplichte velden voor submit.
 */
function valideerForm() {
    if (!document.getElementById('hiddenTijd').value) {
        alert('Kies eerst een tijdstip.');
        return false;
    }
    if (!rolIsInstructeur && !document.getElementById('hiddenInstr').value) {
        alert('Kies eerst een instructeur.');
        return false;
    }
    return true;
}
</script>
</body>
</html>