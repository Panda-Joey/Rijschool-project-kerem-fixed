<?php
/* ============================================================
   beschikbaarheid.php
   Alleen toegankelijk voor instructeurs.
   Hiermee stelt een instructeur in:
     - Op welke dagen (max 3) hij beschikbaar is
     - Hoe laat (begintijd en eindtijd)
     - Hoeveel lessen hij per dag wil geven (max 6)
   Elke les duurt 2 uur.
   ============================================================ */

session_start();

/* --- Toegangscontrole: alleen instructeurs mogen hier komen --- */
if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'instructeur') {
    header("Location: login.php");
    exit;
}

/* --- Database verbinding --- */
$conn = new mysqli("mysql", "root", "password", "Eend");
if ($conn->connect_error) die("Verbinding mislukt: " . $conn->connect_error);

/* --- Sessievariabelen --- */
$instrID  = $_SESSION['userID'];
$naam     = $_SESSION['naam'];

/* Haal huidige transmissie van de instructeur op */
$r = $conn->query("SELECT transmissie FROM instructeurs WHERE instructeurID = $instrID");
$instrTransmissie = ($r && $rij = $r->fetch_assoc()) ? $rij['transmissie'] : 'schakel';

/* --- Mogelijke dagen van de week --- */
$dagNamen = ['Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'];

/* --- Feedback variabelen --- */
$succes = "";
$fout   = "";


/* ============================================================
   FORMULIER VERWERKEN
   Wordt uitgevoerd als de instructeur op "Opslaan" klikt.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Sla transmissie van de instructeur op als die gewijzigd is */
    if (isset($_POST['transmissie'])) {
        $nieuwTransmissie = $conn->real_escape_string($_POST['transmissie']);
        $conn->query("UPDATE instructeurs SET transmissie = '$nieuwTransmissie' WHERE instructeurID = $instrID");
        $instrTransmissie = $nieuwTransmissie;
    }

    $gekozenDagen = $_POST['dagen'] ?? [];

    /* Valideer: minimaal 1, maximaal 3 dagen */
    if (count($gekozenDagen) === 0) {
        $fout = "Selecteer minimaal 1 dag.";

    } elseif (count($gekozenDagen) > 3) {
        $fout = "Je kunt maximaal 3 dagen selecteren.";

    } else {
        /* Verwijder eerst alle oude beschikbaarheid van deze instructeur */
        $conn->query("DELETE FROM beschikbaarheid WHERE instructeurID = $instrID");

        /* Sla elke gekozen dag op */
        foreach ($gekozenDagen as $dag) {

            $dag       = $conn->real_escape_string($dag);
            $begin     = $conn->real_escape_string($_POST['begin'][$dag] ?? '08:00');
            $eind      = $conn->real_escape_string($_POST['eind'][$dag]  ?? '18:00');
            $maxLessen = min(6, max(1, intval($_POST['max'][$dag] ?? 6)));

            /* Begintijd moet voor eindtijd liggen */
            if ($begin >= $eind) {
                $fout = "Begintijd moet voor eindtijd liggen op $dag.";
                break;
            }

            /* Bereken hoeveel 2-uurs lessen passen in het tijdvak */
            $beginMin = intval(substr($begin, 0, 2)) * 60 + intval(substr($begin, 3, 2));
            $eindMin  = intval(substr($eind,  0, 2)) * 60 + intval(substr($eind,  3, 2));
            $maxSlots = floor(($eindMin - $beginMin) / 120);

            if ($maxLessen > $maxSlots) {
                $fout = "Op $dag passen maximaal $maxSlots lessen van 2 uur in het tijdvak $begin–$eind.";
                break;
            }

            /* Alles klopt: sla op in de database */
            $conn->query("
                INSERT INTO beschikbaarheid (instructeurID, dagNaam, beginTijd, eindTijd, maxLessen)
                VALUES ($instrID, '$dag', '$begin', '$eind', $maxLessen)
            ");
        }

        if (!$fout) {
            $succes = "Beschikbaarheid opgeslagen!";
        }
    }
}


/* ============================================================
   DATA OPHALEN
   Haal de huidige beschikbaarheid van deze instructeur op.
   ============================================================ */

/* Haal beschikbaarheid op gesorteerd op dag van de week */
$res    = $conn->query("
    SELECT * FROM beschikbaarheid
    WHERE instructeurID = $instrID
    ORDER BY FIELD(dagNaam, 'Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag')
");
$huidig = [];
while ($rij = $res->fetch_assoc()) {
    $huidig[$rij['dagNaam']] = $rij;
}

/* Haal bezette tijden op per dag (voor de overzichtsweergave onderaan) */
$bezetteTijdenPerDag = [];
foreach ($huidig as $dag => $info) {
    /* Bereken het MySQL dagnummer: Maandag=2, Dinsdag=3, ... Zondag=1 */
    $dagNr    = array_search($dag, ['','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag']);
    $mysqlDag = ($dagNr % 7) + 1;

    $bezRes = $conn->query("
        SELECT lestijd FROM lessen
        WHERE instructeurID = $instrID
          AND DAYOFWEEK(lesDatum) = $mysqlDag
          AND lesDatum >= CURDATE()
          AND vervallen = 0
        ORDER BY lestijd ASC
    ");

    $bezet = [];
    while ($b = $bezRes->fetch_assoc()) {
        $bezet[] = substr($b['lestijd'], 0, 5);
    }
    $bezetteTijdenPerDag[$dag] = $bezet;
}


/* ============================================================
   HULPFUNCTIE: tijdOpties()
   Genereert <option> tags van 07:00 t/m 20:30 in stappen van 30 min.
   $geselecteerd = de waarde die 'selected' moet zijn.
   ============================================================ */
function tijdOpties(string $geselecteerd = ''): string
{
    $opties = '';
    for ($uur = 7; $uur <= 20; $uur++) {
        foreach (['00', '30'] as $minuten) {
            $tijd    = sprintf('%02d:%s', $uur, $minuten);
            $select  = ($tijd === $geselecteerd) ? 'selected' : '';
            $opties .= "<option value='$tijd' $select>$tijd</option>";
        }
    }
    return $opties;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooster instellen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <!-- ── HEADER ─────────────────────────────────────────────── -->
    <div class="dash-header">
        <div>
            <h2>📅 Rooster instellen</h2>
            <span>
                <?= htmlspecialchars($naam) ?> —
                <span class="rol-badge badge-instructeur">🎓 Instructeur</span>
            </span>
        </div>
        <a href="logout.php" class="logout-btn">Uitloggen →</a>
    </div>

    <!-- ── NAVIGATIE ──────────────────────────────────────────── -->
    <div class="top-buttons">
        <a href="dashboard.php"       class="nav-btn">Dashboard</a>
        <a href="index.php"           class="nav-btn">Kalender</a>
        <div                          class="nav-btn active">Rooster</div>
        <a href="les_inroosteren.php" class="nav-btn">+ Les inplannen</a>
    </div>

    <!-- ── FEEDBACK BERICHTEN ─────────────────────────────────── -->
    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>

    <?php if ($fout): ?>
        <div class="fout">⚠️ <?= $fout ?></div>
    <?php endif; ?>


    <!-- ── BESCHIKBAARHEID FORMULIER ──────────────────────────── -->
    <div class="beschikbaar-form">

        <p style="margin-bottom:14px;">
            <strong>Kies maximaal 3 dagen waarop je beschikbaar bent.</strong><br>
            <span style="font-size:11px;color:#666;">Elke les duurt 2 uur. Stel per dag je begin- en eindtijd in.</span>
        </p>

        <form method="POST" action="beschikbaarheid.php" onsubmit="return valideer()">

            <!-- Transmissie instelling: bepaalt welke studenten en auto's gekoppeld worden -->
            <div class="form-group" style="margin-bottom:20px;">
                <label>🚗 Welk type auto geef jij les in?</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
                    <?php foreach (['schakel', 'automaat', 'beide'] as $t): ?>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:10px 16px;border:2px solid <?= $instrTransmissie === $t ? '#1b2940' : '#ccc' ?>;background:<?= $instrTransmissie === $t ? '#1b2940' : 'white' ?>;color:<?= $instrTransmissie === $t ? 'white' : '#333' ?>;font-size:12px;font-weight:bold;">
                        <input type="radio" name="transmissie" value="<?= $t ?>" <?= $instrTransmissie === $t ? 'checked' : '' ?> style="display:none;">
                        <?= ucfirst($t) ?>
                        <?php if ($t === 'schakel'): ?>⚙️<?php elseif ($t === 'automaat'): ?>🔄<?php else: ?>🚗<?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:10px;color:#888;margin-top:6px;">
                    Schakel = alleen schakelstudenten · Automaat = alleen automaatstudenten · Beide = alle studenten
                </div>
            </div>

            <!-- Dag-kiezer: klikbare blokken per dag van de week -->
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

            <!-- Teller: toont hoeveel dagen geselecteerd zijn -->
            <div class="dag-teller">
                <span id="dagTeller"><?= count($huidig) ?></span> / 3 dagen geselecteerd
            </div>

            <!-- Instellingen per dag (verschijnt als dag geselecteerd is) -->
            <?php foreach ($dagNamen as $dag):
                $h   = $huidig[$dag] ?? null;
                $beg = $h ? substr($h['beginTijd'], 0, 5) : '08:00';
                $ein = $h ? substr($h['eindTijd'],  0, 5) : '17:00';
                $max = $h ? $h['maxLessen'] : 3;
            ?>
            <div class="dag-instellingen <?= $h ? 'zichtbaar' : '' ?>" id="inst_<?= $dag ?>">

                <h4>⚙️ <?= $dag ?></h4>

                <!-- Begin- en eindtijd selects naast elkaar -->
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

                <!-- Max lessen: klikbare nummerknoppen 1 t/m 6 -->
                <div class="form-group">
                    <label>Max. lessen per dag:</label>
                    <div class="max-lessen-rij">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <button
                                type="button"
                                class="max-btn <?= $i == $max ? 'actief' : '' ?>"
                                id="maxBtn_<?= $dag ?>_<?= $i ?>"
                                onclick="setMax('<?= $dag ?>', <?= $i ?>)"
                            ><?= $i ?></button>
                        <?php endfor; ?>
                    </div>
                    <!-- Hidden input stuurt de gekozen waarde mee -->
                    <input type="hidden" name="max[<?= $dag ?>]" id="maxVal_<?= $dag ?>" value="<?= $max ?>">
                    <div id="maxInfo_<?= $dag ?>" style="font-size:10px;color:#888;margin-top:5px;"></div>
                </div>

                <!-- Live preview van de beschikbare tijdslots -->
                <div>
                    <div style="font-size:11px;color:#555;margin-bottom:5px;">Tijdslots (elke les = 2 uur):</div>
                    <div class="slot-grid" id="slots_<?= $dag ?>"></div>
                </div>

            </div>
            <?php endforeach; ?>

            <!-- Knoppen -->
            <div class="btn-row" style="margin-top:20px;">
                <a href="dashboard.php" class="btn-terug">← Terug</a>
                <button type="submit" class="btn-opslaan">💾 Opslaan</button>
            </div>

        </form>
    </div>


    <!-- ── OVERZICHT HUIDIGE BESCHIKBAARHEID ──────────────────── -->
    <?php if (!empty($huidig)): ?>
    <div class="overzicht">
        <h3>📋 Jouw huidige beschikbaarheid</h3>

        <?php foreach ($huidig as $dag => $info):
            /* Genereer alle 2-uurs slots voor deze dag */
            $slots    = [];
            $beginMin = intval(substr($info['beginTijd'], 0, 2)) * 60 + intval(substr($info['beginTijd'], 3, 2));
            $eindMin  = intval(substr($info['eindTijd'],  0, 2)) * 60 + intval(substr($info['eindTijd'],  3, 2));
            for ($m = $beginMin; $m + 120 <= $eindMin; $m += 30) {
                $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            }
            $bezet = $bezetteTijdenPerDag[$dag] ?? [];
        ?>
        <div class="overzicht-rij">

            <!-- Dagnaam -->
            <div class="dag-naam"><?= $dag ?></div>

            <!-- Tijdvak en max lessen -->
            <div style="font-size:11px;color:#555;">
                <?= substr($info['beginTijd'], 0, 5) ?> – <?= substr($info['eindTijd'], 0, 5) ?>
                &nbsp;·&nbsp; max <?= $info['maxLessen'] ?> lessen
            </div>

            <!-- Tijdslot badges: groen = vrij, rood = al bezet -->
            <div class="slot-grid">
                <?php foreach ($slots as $slot): ?>
                    <div class="slot <?= in_array($slot, $bezet) ? 'bezet' : 'vrij' ?>">
                        <?= $slot ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /container -->


<script>
/* ============================================================
   JAVASCRIPT — beschikbaarheid.php
   ============================================================ */

const LES_DUUR = 120; // elke les duurt 2 uur (in minuten)

/**
 * toggleDag — Toont of verbergt het instellingenpaneel van een dag.
 * Wordt aangeroepen als een dag-checkbox aan/uit gezet wordt.
 */
function toggleDag(dag, aan) {
    if (aan) {
        /* Maximaal 3 dagen mogen geselecteerd zijn */
        const aantalActief = document.querySelectorAll('.dag-checkbox:checked').length;
        if (aantalActief > 3) {
            document.getElementById('dag_' + dag).checked = false;
            alert('Je kunt maximaal 3 dagen selecteren.');
            return;
        }
        document.getElementById('inst_' + dag).classList.add('zichtbaar');
        herbereken(dag); // toon direct de tijdslot-preview
    } else {
        document.getElementById('inst_' + dag).classList.remove('zichtbaar');
    }
    updateTeller();
}

/**
 * updateTeller — Werkt de "X / 3 dagen geselecteerd" tekst bij.
 */
function updateTeller() {
    const n = document.querySelectorAll('.dag-checkbox:checked').length;
    document.getElementById('dagTeller').textContent = n;
}

/**
 * setMax — Markeert de gekozen max-lessen knop en slaat de waarde op.
 */
function setMax(dag, n) {
    document.getElementById('maxVal_' + dag).value = n;
    for (let i = 1; i <= 6; i++) {
        document.getElementById('maxBtn_' + dag + '_' + i)
                .classList.toggle('actief', i === n);
    }
}

/**
 * herbereken — Herberekent hoeveel lessen passen en toont de tijdslot-preview.
 * Wordt aangeroepen als begin- of eindtijd verandert.
 */
function herbereken(dag) {
    const begin  = document.getElementById('begin_' + dag).value;
    const eind   = document.getElementById('eind_'  + dag).value;
    const bMin   = tijdNaarMin(begin);
    const eMin   = tijdNaarMin(eind);
    const infoEl = document.getElementById('maxInfo_' + dag);
    const slotsEl= document.getElementById('slots_'   + dag);

    /* Eindtijd moet na begintijd liggen */
    if (eMin <= bMin) {
        infoEl.textContent = '⚠️ Eindtijd moet na begintijd liggen.';
        infoEl.style.color = '#dc3545';
        slotsEl.innerHTML  = '';
        return;
    }

    /* Bereken hoeveel niet-overlappende 2-uurs lessen passen */
    const maxMogelijk = Math.min(6, Math.floor((eMin - bMin) / LES_DUUR));
    infoEl.textContent = `Maximaal ${maxMogelijk} lessen van 2 uur passen hier in.`;
    infoEl.style.color = '#555';

    /* Grijsmakers: knoppen die meer lessen aangeven dan mogelijk zijn */
    for (let i = 1; i <= 6; i++) {
        const btn = document.getElementById('maxBtn_' + dag + '_' + i);
        btn.disabled      = i > maxMogelijk;
        btn.style.opacity = i > maxMogelijk ? '.3' : '1';
    }

    /* Verlaag de huidige max als die niet meer past */
    const huidigMax = parseInt(document.getElementById('maxVal_' + dag).value);
    if (huidigMax > maxMogelijk) setMax(dag, maxMogelijk);

    /* Toon tijdslot-preview: stap 30 min, elk slot duurt 2 uur */
    slotsEl.innerHTML = '';
    for (let m = bMin; m + LES_DUUR <= eMin; m += 30) {
        const div       = document.createElement('div');
        div.className   = 'slot';
        div.textContent = minNaarTijd(m) + '–' + minNaarTijd(m + LES_DUUR);
        slotsEl.appendChild(div);
    }
}

/**
 * tijdNaarMin — Zet "08:30" om naar 510 (minuten).
 */
function tijdNaarMin(t) {
    const [u, m] = t.split(':').map(Number);
    return u * 60 + m;
}

/**
 * minNaarTijd — Zet 510 om naar "08:30".
 */
function minNaarTijd(m) {
    return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
}

/**
 * valideer — Controleert het formulier vóór het versturen.
 */
function valideer() {
    const n = document.querySelectorAll('.dag-checkbox:checked').length;
    if (n === 0) { alert('Selecteer minimaal 1 dag.'); return false; }
    if (n > 3)   { alert('Maximaal 3 dagen.');         return false; }
    return true;
}

/* Bij laden: bereken de slots voor al eerder geselecteerde dagen */
document.querySelectorAll('.dag-checkbox:checked').forEach(cb => herbereken(cb.value));
</script>
</body>
</html>