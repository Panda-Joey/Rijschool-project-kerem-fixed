<?php
session_start();
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);



$lesID  = isset($_GET['lesID']) ? intval($_GET['lesID']) : 0;
$succes = "";
$fout   = "";

// ── Haal huidige les op ──────────────────────────────────────
$lesResult = $conn->query("
    SELECT lessen.*, instructeurs.voornaam, instructeurs.achternaam
    FROM lessen
    JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
    WHERE lesID = $lesID
");
if (!$lesResult || $lesResult->num_rows === 0) die("Les niet gevonden.");
$les = $lesResult->fetch_assoc();

// ── Haal alle instructeurs op ────────────────────────────────
$instrResult  = $conn->query("SELECT * FROM instructeurs ORDER BY voornaam");
$instructeurs = [];
while ($row = $instrResult->fetch_assoc()) $instructeurs[] = $row;

// ── Verwerk formulier ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nieuweDatum = $conn->real_escape_string($_POST['lesDatum']);
    $nieuweTijd  = $conn->real_escape_string($_POST['lestijd']);
    $nieuweInstr = intval($_POST['instructeurID']);
    $reden       = trim($conn->real_escape_string($_POST['reden'] ?? ''));

    if ($reden === '') {
        $fout = "Vul een reden in voor het verzetten van de les.";
    } else {
        $conflictResult = $conn->query("
            SELECT lesID FROM lessen
            WHERE instructeurID = $nieuweInstr
            AND lesDatum = '$nieuweDatum'
            AND lestijd  = '$nieuweTijd'
            AND lesID   != $lesID
            AND vervallen = 0
        ");

        if ($conflictResult->num_rows > 0) {
            $fout = "Deze instructeur heeft al een les op $nieuweDatum om $nieuweTijd.";
        } else {
            if ($conn->query("
                UPDATE lessen
                SET lesDatum='$nieuweDatum', lestijd='$nieuweTijd',
                    instructeurID=$nieuweInstr, redenWijzig='$reden'
                WHERE lesID=$lesID
            ")) {
                $succes = "Les succesvol bijgewerkt!";
                $r2 = $conn->query("
                    SELECT lessen.*, instructeurs.voornaam, instructeurs.achternaam
                    FROM lessen
                    JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
                    WHERE lesID=$lesID
                ");
                $les = $r2->fetch_assoc();
            } else {
                $fout = "Er ging iets mis bij het opslaan.";
            }
        }
    }
}

// ── Gekozen datum (POST > GET > huidige les) ─────────────────
$gekozenDatum   = $_POST['lesDatum'] ?? ($_GET['datum'] ?? $les['lesDatum']);
$gekozenInstrID = intval($_POST['instructeurID'] ?? ($_GET['instr'] ?? $les['instructeurID']));

// Dag van de week van de gekozen datum
$dagNamen = ['','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'];
$dagNr    = date('N', strtotime($gekozenDatum)); // 1=Ma … 7=Zo
$dagNaam  = $dagNamen[$dagNr];

// ── Beschikbaarheid + bezette tijden per instructeur ─────────
// Resultaat: array[ instrID ] = [ 'beschikbaar'=>[slots], 'bezet'=>[tijden] ]
$instrData = [];
foreach ($instructeurs as $instr) {
    $iID = $instr['instructeurID'];

    // Beschikbaarheid op die dag
    $bRes = $conn->query("
        SELECT beginTijd, eindTijd, maxLessen
        FROM beschikbaarheid
        WHERE instructeurID = $iID AND dagNaam = '$dagNaam'
        LIMIT 1
    ");
    $bRow = $bRes && $bRes->num_rows > 0 ? $bRes->fetch_assoc() : null;

    // Bezette tijden
    $bezRes = $conn->query("
        SELECT lestijd FROM lessen
        WHERE instructeurID = $iID
        AND lesDatum = '$gekozenDatum'
        AND lesID != $lesID
        AND vervallen = 0
    ");
    $bezet = [];
    while ($r = $bezRes->fetch_assoc()) $bezet[] = substr($r['lestijd'], 0, 5);

    // Genereer slots van 2 uur (elke les duurt 2u, stap = 30min voor flexibiliteit)
    // Een slot is alleen geldig als de instructeur 2u na starttijd nog vrij is
    $slots = [];
    if ($bRow) {
        $bMin = intval(substr($bRow['beginTijd'],0,2))*60 + intval(substr($bRow['beginTijd'],3,2));
        $eMin = intval(substr($bRow['eindTijd'],0,2))*60  + intval(substr($bRow['eindTijd'],3,2));
        // Stap per 30 minuten, maar slot eindigt 2u later — moet binnen eindtijd vallen
        for ($m = $bMin; $m + 120 <= $eMin; $m += 30) {
            $slots[] = sprintf('%02d:%02d', intdiv($m,60), $m%60);
        }

        // Begrenzen op maxLessen (bezette tijden tellen mee)
        $aantalBezet    = count($bezet);
        $maxBeschikbaar = $bRow['maxLessen'];
        $nogVrij        = $maxBeschikbaar - $aantalBezet;
    }

    $instrData[$iID] = [
        'beschikbaar' => $bRow ? true : false,
        'slots'       => $slots,
        'bezet'       => $bezet,
        'begin'       => $bRow ? substr($bRow['beginTijd'],0,5) : null,
        'eind'        => $bRow ? substr($bRow['eindTijd'],0,5)  : null,
        'maxLessen'   => $bRow ? $bRow['maxLessen'] : 0,
        'nogVrij'     => isset($nogVrij) ? $nogVrij : 0
    ];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Les wijzigen</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <h1>Les Wijzigen</h1>

    <div class="top-buttons">
        <a href="dashboard.php" class="nav-btn">Dashboard</a>
        <a href="kalender.php"     class="nav-btn">Kalender</a>
        <div class="nav-btn">Rooster</div>
        <div class="nav-btn">Profiel</div>
    </div>

    <div class="wijzig-form">

        <!-- Huidige les info -->
        <div class="les-info">
            <p><strong>Les #<?= $lesID ?></strong></p>
            <p>Datum: <?= date('d-m-Y', strtotime($les['lesDatum'])) ?></p>
            <p>Tijd: <?= substr($les['lestijd'],0,5) ?></p>
            <p>Instructeur: <?= htmlspecialchars($les['voornaam'] . ' ' . $les['achternaam']) ?></p>
            <p>Doel: <?= htmlspecialchars($les['doel']) ?></p>
            <?php if (!empty($les['redenWijzig'])): ?>
                <div class="reden-huidig">
                    📝 Laatste reden: <em><?= htmlspecialchars($les['redenWijzig']) ?></em>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($succes): ?>
            <div class="succes">✅ <?= $succes ?> <a href="kalender.php">Terug naar kalender</a></div>
        <?php endif; ?>
        <?php if ($fout): ?>
            <div class="fout">⚠️ <?= $fout ?></div>
        <?php endif; ?>

        <form method="POST" action="wijzig.php?lesID=<?= $lesID ?>" onsubmit="return valideerForm()">

            <!-- Datum -->
            <div class="form-group">
                <label>Nieuwe datum</label>
                <input
                    type="date"
                    name="lesDatum"
                    id="lesDatum"
                    value="<?= htmlspecialchars($gekozenDatum) ?>"
                    min="2026-05-01"
                    max="2026-12-31"
                    onchange="herlaadbeschikbaarheid()"
                    required
                >
            </div>

            <!-- Instructeur -->
            <div class="form-group">
                <label>Instructeur</label>
                <select name="instructeurID" id="instructeurID" onchange="updateTijdSlots()" required>
                    <?php foreach ($instructeurs as $instr):
                        $iID  = $instr['instructeurID'];
                        $data = $instrData[$iID];
                        $status = !$data['beschikbaar'] ? 'geen' : ($data['nogVrij'] <= 0 ? 'vol' : 'vrij');
                        $statusTxt = [
                            'geen' => '— niet beschikbaar',
                            'vol'  => '— vol',
                            'vrij' => '✓ beschikbaar'
                        ][$status];
                    ?>
                        <option
                            value="<?= $iID ?>"
                            data-status="<?= $status ?>"
                            <?= $iID == $gekozenInstrID ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($instr['voornaam'] . ' ' . $instr['achternaam']) ?>
                            <?= $statusTxt ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <!-- Beschikbaarheid info onder dropdown -->
                <div id="instrInfo"></div>
            </div>

            <!-- Tijdslot dropdown -->
            <div class="form-group">
                <label>Tijdstip <span style="font-size:10px;color:#888;">(alleen :00 en :30 slots)</span></label>
                <div class="tijd-select-wrap">
                    <select name="lestijd" id="lestijd" required>
                        <!-- Gevuld via JS -->
                    </select>
                </div>
                <div class="legenda">
                    <div class="legenda-item"><div class="dot dot-vrij"></div> Vrij</div>
                    <div class="legenda-item"><div class="dot dot-bezet"></div> Al bezet</div>
                    <div class="legenda-item"><div class="dot dot-geen"></div> Buiten beschikbaarheid</div>
                </div>
                <div id="tijd-waarschuwing" style="font-size:11px;color:#c00;margin-top:4px;"></div>
            </div>

            <!-- Reden — verplicht -->
            <div class="form-group">
                <label>Reden voor verzetten <span style="color:#dc3545;">*</span></label>
                <textarea
                    name="reden"
                    id="reden"
                    placeholder="Geef een reden op..."
                    maxlength="300"
                    oninput="updateCounter()"
                ><?= htmlspecialchars($_POST['reden'] ?? '') ?></textarea>
                <div class="reden-counter"><span id="redenTeller">0</span> / 300</div>
                <div class="verplicht-melding" id="reden-fout">⚠️ Een reden is verplicht.</div>
            </div>

            <div class="btn-row">
                <a href="kalender.php" class="btn-terug">← Terug</a>
                <button type="submit" class="btn-opslaan">💾 Opslaan</button>
            </div>

        </form>
    </div>
</div>

<script>
// Alle instructeurdata vanuit PHP
const instrData   = <?= json_encode($instrData) ?>;
const gekozenDag  = '<?= $dagNaam ?>';
const huidigeTijd = '<?= substr($les['lestijd'],0,5) ?>';

function updateTijdSlots() {
    const instrID  = document.getElementById('instructeurID').value;
    const data     = instrData[instrID];
    const select   = document.getElementById('lestijd');
    const infoDiv  = document.getElementById('instrInfo');
    const waarsch  = document.getElementById('tijd-waarschuwing');

    select.innerHTML = '';
    waarsch.textContent = '';

    if (!data.beschikbaar) {
        // Geen beschikbaarheid → toon melding, geen slots
        select.classList.remove('vrij','bezet');
        infoDiv.innerHTML = `<div class="beschikbaar-info geen">
            ⚠️ Deze instructeur heeft geen beschikbaarheid ingesteld voor <strong>${gekozenDag}</strong>.
            Kies een andere dag of instructeur.
        </div>`;
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '— Geen beschikbare tijden —';
        select.appendChild(opt);
        return;
    }

    if (data.nogVrij <= 0) {
        infoDiv.innerHTML = `<div class="beschikbaar-info vol">
            🚫 Vol — deze instructeur heeft al ${data.maxLessen} lessen op deze dag.
        </div>`;
    } else {
        infoDiv.innerHTML = `<div class="beschikbaar-info">
            ✅ Beschikbaar <strong>${data.begin} – ${data.eind}</strong>
            &nbsp;·&nbsp; Max ${data.maxLessen} lessen
            &nbsp;·&nbsp; Nog <strong>${data.nogVrij}</strong> plek(ken) vrij
        </div>`;
    }

    // Vul tijdslots
    data.slots.forEach(slot => {
        const isBezet = data.bezet.includes(slot);
        const opt = document.createElement('option');
        opt.value       = slot;
        opt.textContent = isBezet ? `${slot}  🚫 bezet` : `${slot}  ✓`;
        opt.disabled    = isBezet;
        opt.style.color = isBezet ? '#aaa' : '#000';
        if (slot === huidigeTijd) opt.selected = true;
        select.appendChild(opt);
    });

    // Kleur select op basis van selectie
    select.addEventListener('change', () => {
        select.classList.remove('vrij','bezet');
        const val = select.value;
        if (data.bezet.includes(val)) {
            select.classList.add('bezet');
            waarsch.textContent = '⚠️ Dit tijdstip is al bezet!';
        } else {
            select.classList.add('vrij');
            waarsch.textContent = '';
        }
    });
}

function herlaadbeschikbaarheid() {
    const datum   = document.getElementById('lesDatum').value;
    const instrID = document.getElementById('instructeurID').value;
    window.location.href = `wijzig.php?lesID=<?= $lesID ?>&datum=${datum}&instr=${instrID}`;
}

function updateCounter() {
    const ta = document.getElementById('reden');
    document.getElementById('redenTeller').textContent = ta.value.length;
    if (ta.value.trim()) {
        ta.classList.remove('leeg');
        document.getElementById('reden-fout').style.display = 'none';
    }
}

function valideerForm() {
    const reden = document.getElementById('reden');
    if (!reden.value.trim()) {
        reden.classList.add('leeg');
        document.getElementById('reden-fout').style.display = 'block';
        reden.focus();
        return false;
    }
    const tijd = document.getElementById('lestijd').value;
    if (!tijd) {
        document.getElementById('tijd-waarschuwing').textContent = '⚠️ Kies een tijdstip.';
        return false;
    }
    return true;
}

// Init
updateTijdSlots();
updateCounter();
</script>
</body>
</ht