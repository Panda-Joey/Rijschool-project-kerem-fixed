<?php
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$instrID = intval($_SESSION['userID']);
$succes  = "";
$fout    = "";

$dagNamen = ['Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'];

// ── Verwerk opslaan ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gekozenDagen = $_POST['dagen'] ?? [];

    // Maximaal 3 dagen
    if (count($gekozenDagen) > 3) {
        $fout = "Je kunt maximaal 3 dagen selecteren.";
    } elseif (count($gekozenDagen) === 0) {
        $fout = "Selecteer minimaal 1 dag.";
    } else {
        // Verwijder alle huidige beschikbaarheid van deze instructeur
        $conn->query("DELETE FROM beschikbaarheid WHERE instructeurID = $instrID");

        foreach ($gekozenDagen as $dag) {
            $dag       = $conn->real_escape_string($dag);
            $begin     = $conn->real_escape_string($_POST['begin'][$dag] ?? '08:00');
            $eind      = $conn->real_escape_string($_POST['eind'][$dag]  ?? '18:00');
            $maxLessen = min(6, max(1, intval($_POST['max'][$dag] ?? 6)));

            // Valideer: begin voor eind
            if ($begin >= $eind) {
                $fout = "Begintijd moet voor eindtijd liggen ($dag).";
                break;
            }

            // Valideer: genoeg 2-uurs slots voor maxLessen
            $beginMin = intval(substr($begin,0,2))*60 + intval(substr($begin,3,2));
            $eindMin  = intval(substr($eind,0,2))*60  + intval(substr($eind,3,2));
            // Elke les duurt 2 uur, slots per 30 min — maar max gelijktijdige lessen = tijdspan/2
            $maxSlots = floor(($eindMin - $beginMin) / 120);
            if ($maxLessen > $maxSlots) {
                $fout = "Op $dag passen maximaal $maxSlots lessen (elk 2 uur) in het tijdvak {$begin}-{$eind}.";
                break;
            }

            $conn->query("
                INSERT INTO beschikbaarheid (instructeurID, dagNaam, beginTijd, eindTijd, maxLessen)
                VALUES ($instrID, '$dag', '$begin', '$eind', $maxLessen)
            ");
        }

        if (!$fout) $succes = "Beschikbaarheid opgeslagen!";
    }
}

// ── Haal huidige beschikbaarheid op ─────────────────────────
$res = $conn->query("SELECT * FROM beschikbaarheid WHERE instructeurID = $instrID ORDER BY FIELD(dagNaam,'Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag')");
$huidig = [];
while ($r = $res->fetch_assoc()) $huidig[$r['dagNaam']] = $r;

// Tijdopties: 07:00 t/m 20:00
function tijdOpties($geselecteerd = '') {
    $opts = '';
    for ($u = 7; $u <= 20; $u++) {
        foreach (['00','30'] as $min) {
            $t = sprintf('%02d:%s', $u, $min);
            $sel = $t === $geselecteerd ? 'selected' : '';
            $opts .= "<option value='$t' $sel>$t</option>";
        }
    }
    return $opts;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beschikbaarheid instellen</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">



    <h1>Beschikbaarheid instellen</h1>

    <div class="top-buttons">
        <a href="<?= htmlspecialchars(srcDashboardPath(), ENT_QUOTES, 'UTF-8') ?>" class="nav-btn">Dashboard</a>
        <a href="kalender.php"     class="nav-btn">Kalender</a>
        <div class="nav-btn active">Rooster</div>
        <a href="Profieli.php"     class="nav-btn">Profiel</a>
    </div>

    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>
    <?php if ($fout): ?>
        <div class="fout">⚠️ <?= $fout ?></div>
    <?php endif; ?>

    

    <div class="beschikbaar-form">
        <div style="margin-bottom:14px;">
            <strong>Kies maximaal 3 dagen waarop je beschikbaar bent:</strong>
        </div>

        <form method="POST" action="beschikbaarheid.php" onsubmit="return valideer()">

            <!-- Dag-kiezer -->
            <div class="dag-grid">
                <?php foreach ($dagNamen as $dag): ?>
                    <div>
                        <input
                            type="checkbox"
                            class="dag-checkbox"
                            name="dagen[]"
                            value="<?= $dag ?>"
                            id="dag_<?= $dag ?>"
                            <?= isset($huidig[$dag]) ? 'checked' : '' ?>
                            onchange="toggleDag('<?= $dag ?>', this.checked)"
                        >
                        <label class="dag-label" for="dag_<?= $dag ?>"><?= $dag ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="dag-teller">
                <span id="dagTeller"><?= count($huidig) ?></span> / 3 dagen geselecteerd
            </div>

            <!-- Instellingen per dag -->
            <?php foreach ($dagNamen as $dag):
                $h    = $huidig[$dag] ?? null;
                $vis  = $h ? 'zichtbaar' : '';
                $beg  = $h ? substr($h['beginTijd'],0,5) : '08:00';
                $ein  = $h ? substr($h['eindTijd'],0,5)  : '17:00';
                $max  = $h ? $h['maxLessen'] : 6;
            ?>
            <div class="dag-instellingen <?= $vis ?>" id="inst_<?= $dag ?>">
                <h4>⚙️ <?= $dag ?></h4>

                <div class="tijd-rij">
                    <div class="form-group">
                        <label>Begintijd</label>
                        <select name="begin[<?= $dag ?>]" id="begin_<?= $dag ?>" onchange="herbereken('<?= $dag ?>')">
                            <?= tijdOpties($beg) ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Eindtijd</label>
                        <select name="eind[<?= $dag ?>]" id="eind_<?= $dag ?>" onchange="herbereken('<?= $dag ?>')">
                            <?= tijdOpties($ein) ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Max. lessen per dag (max 6):</label>
                    <div class="max-lessen-rij" id="maxRij_<?= $dag ?>">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <button
                                type="button"
                                class="max-btn <?= $i == $max ? 'actief' : '' ?>"
                                onclick="setMax('<?= $dag ?>', <?= $i ?>)"
                                id="maxBtn_<?= $dag ?>_<?= $i ?>"
                            ><?= $i ?></button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="max[<?= $dag ?>]" id="maxVal_<?= $dag ?>" value="<?= $max ?>">
                    <div style="font-size:10px;color:#888;margin-top:5px;" id="maxInfo_<?= $dag ?>"></div>
                </div>

                <!-- Preview tijdslots -->
                <div style="margin-top:10px;">
                    <div style="font-size:11px;color:#555;margin-bottom:5px;">Beschikbare tijdslots:</div>
                    <div class="slot-grid" id="slots_<?= $dag ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="btn-row" style="margin-top:20px;">
                <a href="<?= htmlspecialchars(srcDashboardPath(), ENT_QUOTES, 'UTF-8') ?>" class="btn-terug">← Terug</a>
                <button type="submit" class="btn-opslaan">💾 Opslaan</button>
            </div>

        </form>
    </div>

    <!-- Overzicht huidige beschikbaarheid met slots -->
    <?php if (!empty($huidig)): ?>
    <div class="overzicht">
        <h3>📅 Jouw huidige beschikbaarheid</h3>
        <?php foreach ($huidig as $dag => $info):
            // Haal bezette tijden op voor de komende week op deze dag
            $dagNr = array_search($dag, ['','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag']);
            $bezetRes = $conn->query("
                SELECT lestijd FROM lessen
                WHERE instructeurID = $instrID
                AND DAYOFWEEK(lesDatum) = " . (($dagNr % 7) + 1) . "
                AND lesDatum >= CURDATE()
                AND vervallen = 0
                ORDER BY lestijd ASC
            ");
            $bezet = [];
            while ($br = $bezetRes->fetch_assoc()) $bezet[] = substr($br['lestijd'],0,5);

            // Genereer alle :00/:30 slots
            $slots = [];
            $bMin  = intval(substr($info['beginTijd'],0,2))*60 + intval(substr($info['beginTijd'],3,2));
            $eMin  = intval(substr($info['eindTijd'],0,2))*60  + intval(substr($info['eindTijd'],3,2));
            for ($m = $bMin; $m + 120 <= $eMin; $m += 30) {
                $slots[] = sprintf('%02d:%02d', intdiv($m,60), $m%60);
            }
        ?>
        <div class="overzicht-rij">
            <div class="dag-naam"><?= $dag ?></div>
            <div style="font-size:11px;color:#555;">
                <?= substr($info['beginTijd'],0,5) ?> – <?= substr($info['eindTijd'],0,5) ?>
                &nbsp;·&nbsp; max <?= $info['maxLessen'] ?> lessen
            </div>
            <div class="slot-grid">
                <?php foreach ($slots as $slot):
                    $isBezet = in_array($slot, $bezet);
                ?>
                    <div class="slot <?= $isBezet ? 'bezet' : 'vrij' ?>">
                        <?= $slot ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
// Huidige beginTijden voor validatie
const dagBeginTijden = {};

function toggleDag(dag, aan) {
    const inst = document.getElementById('inst_' + dag);
    if (aan) {
        // Check: max 3 dagen
        const aantalActief = document.querySelectorAll('.dag-checkbox:checked').length;
        if (aantalActief > 3) {
            document.getElementById('dag_' + dag).checked = false;
            alert('Je kunt maximaal 3 dagen selecteren.');
            return;
        }
        inst.classList.add('zichtbaar');
        herbereken(dag);
    } else {
        inst.classList.remove('zichtbaar');
    }
    updateTeller();
}

function updateTeller() {
    const n = document.querySelectorAll('.dag-checkbox:checked').length;
    document.getElementById('dagTeller').textContent = n;
}

function setMax(dag, n) {
    document.getElementById('maxVal_' + dag).value = n;
    for (let i = 1; i <= 6; i++) {
        const btn = document.getElementById('maxBtn_' + dag + '_' + i);
        btn.classList.toggle('actief', i === n);
    }
}

function herbereken(dag) {
    const begin = document.getElementById('begin_' + dag).value;
    const eind  = document.getElementById('eind_'  + dag).value;

    const bMin = tijdNaarMin(begin);
    const eMin = tijdNaarMin(eind);
    // Elke les duurt 2 uur
    const LES_DUUR = 120;

    const infoEl = document.getElementById('maxInfo_' + dag);
    const slotsEl = document.getElementById('slots_' + dag);

    if (eMin <= bMin) {
        infoEl.textContent = '⚠️ Eindtijd moet na begintijd liggen.';
        infoEl.style.color = '#dc3545';
        slotsEl.innerHTML  = '';
        return;
    }

    // Max lessen = aantal niet-overlappende 2u blokken
    const maxMogelijk = Math.min(6, Math.floor((eMin - bMin) / LES_DUUR));
    infoEl.textContent = `In dit tijdvak passen maximaal ${maxMogelijk} lessen van 2 uur.`;
    infoEl.style.color = '#555';

    // Begrens max-knoppen
    for (let i = 1; i <= 6; i++) {
        const btn = document.getElementById('maxBtn_' + dag + '_' + i);
        btn.disabled = i > maxMogelijk;
        btn.style.opacity = i > maxMogelijk ? '.3' : '1';
    }

    // Huidige max eventueel verlagen
    const huidigMax = parseInt(document.getElementById('maxVal_' + dag).value);
    if (huidigMax > maxMogelijk) setMax(dag, maxMogelijk);

    // Slots preview: stap 30 min, slot moet 2u voor eind vallen
    slotsEl.innerHTML = '';
    for (let m = bMin; m + LES_DUUR <= eMin; m += 30) {
        const t   = minNaarTijd(m);
        const div = document.createElement('div');
        div.className   = 'slot';
        div.textContent = t + '–' + minNaarTijd(m + LES_DUUR);
        slotsEl.appendChild(div);
    }
}

function tijdNaarMin(t) {
    const [u, m] = t.split(':').map(Number);
    return u * 60 + m;
}

function minNaarTijd(m) {
    return String(Math.floor(m/60)).padStart(2,'0') + ':' + String(m%60).padStart(2,'0');
}

function valideer() {
    const n = document.querySelectorAll('.dag-checkbox:checked').length;
    if (n === 0) { alert('Selecteer minimaal 1 dag.'); return false; }
    if (n > 3)   { alert('Maximaal 3 dagen.'); return false; }
    return true;
}

// Init: bereken slots voor reeds geselecteerde dagen
document.querySelectorAll('.dag-checkbox:checked').forEach(cb => {
    herbereken(cb.value);
});
</script>
</body>
</html>