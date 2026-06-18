<?php
/* ============================================================
   beschikbaarheid.php
   Alleen toegankelijk voor instructeurs.
   Hiermee stelt een instructeur in:
     - Op welke dagen (max 3) hij beschikbaar is
     - Begin- en eindtijd, ALLEEN uit vaste 2-uurs-grenzen:
       08:00, 10:00, 12:00, 14:00, 16:00, 18:00, 20:00
     - Hoeveel lessen hij per dag wil geven (max 6)
   Elke les duurt precies 2 uur — geen halve uren.
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

/* --- Vaste tijdgrenzen: alleen hele uren, telkens 2 uur uit elkaar.
       Hieruit kiest de instructeur zijn begin- en eindtijd.
       Voorbeeld geldige combinatie: begin=08:00, eind=14:00 → 3 lessen mogelijk. --- */
$vasteTijden = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];

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
        /* Verzamel eerst alle nieuwe rijen, valideer ze allemaal,
           en sla pas op als alles klopt (voorkomt halve updates bij een fout) */
        $nieuweRijen = [];

        foreach ($gekozenDagen as $dag) {

            /* Dagnaam moet een geldige, bekende dag zijn (whitelist tegen manipulatie) */
            if (!in_array($dag, $dagNamen, true)) {
                $fout = "Ongeldige dag ontvangen.";
                break;
            }

            $begin     = $_POST['begin'][$dag] ?? '08:00';
            $eind      = $_POST['eind'][$dag]  ?? '18:00';
            $maxLessen = intval($_POST['max'][$dag] ?? 6);

            /* Begin- en eindtijd moeten allebei uit de vaste lijst komen
               (whitelist tegen manipulatie van halve uren via de browser) */
            if (!in_array($begin, $vasteTijden, true) || !in_array($eind, $vasteTijden, true)) {
                $fout = "Ongeldig tijdstip op $dag. Kies alleen uit de vaste tijden.";
                break;
            }

            /* Begintijd moet voor eindtijd liggen */
            if ($begin >= $eind) {
                $fout = "Begintijd moet voor eindtijd liggen op $dag.";
                break;
            }

            /* Bereken hoeveel volledige 2-uurs lessen passen in het tijdvak.
               Omdat begin/eind altijd op een 2-uurs grens staan, is dit
               altijd een geheel getal — geen halve lessen mogelijk. */
            $beginMin = intval(substr($begin, 0, 2)) * 60;
            $eindMin  = intval(substr($eind,  0, 2)) * 60;
            $maxSlots = intdiv($eindMin - $beginMin, 120);

            /* Max lessen moet tussen 1 en het werkelijk aantal passende slots liggen,
               en mag nooit boven de absolute limiet van 6 komen */
            $maxLessen = max(1, min(6, $maxSlots, $maxLessen));

            if ($maxLessen > $maxSlots) {
                $fout = "Op $dag passen maximaal $maxSlots lessen van 2 uur in het tijdvak $begin–$eind.";
                break;
            }

            $nieuweRijen[] = [
                'dag'       => $dag,
                'begin'     => $begin,
                'eind'      => $eind,
                'maxLessen' => $maxLessen,
            ];
        }

        /* Alleen opslaan als ALLE dagen geldig waren */
        if (!$fout) {
            /* Verwijder eerst alle oude beschikbaarheid van deze instructeur */
            $conn->query("DELETE FROM beschikbaarheid WHERE instructeurID = $instrID");

            foreach ($nieuweRijen as $rij) {
                $dagEsc   = $conn->real_escape_string($rij['dag']);
                $beginEsc = $conn->real_escape_string($rij['begin']);
                $eindEsc  = $conn->real_escape_string($rij['eind']);

                $conn->query("
                    INSERT INTO beschikbaarheid (instructeurID, dagNaam, beginTijd, eindTijd, maxLessen)
                    VALUES ($instrID, '$dagEsc', '$beginEsc:00', '$eindEsc:00', {$rij['maxLessen']})
                ");
            }

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
   HULPFUNCTIE: vasteTijdOpties()
   Genereert <option> tags voor ALLEEN de vaste 2-uurs-grenzen:
   08:00, 10:00, 12:00, 14:00, 16:00, 18:00, 20:00.
   Geen halve uren — dit is bewust beperkt.
   ============================================================ */
function vasteTijdOpties(array $vasteTijden, string $geselecteerd = ''): string
{
    $opties = '';
    foreach ($vasteTijden as $tijd) {
        $select  = ($tijd === $geselecteerd) ? 'selected' : '';
        $opties .= "<option value='$tijd' $select>$tijd</option>";
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

    <?php require_once 'nav.php'; ?>

    <!-- ── FEEDBACK BERICHTEN ─────────────────────────────────── -->
    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>

    <?php if ($fout): ?>
        <div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div>
    <?php endif; ?>


    <!-- ── BESCHIKBAARHEID FORMULIER ──────────────────────────── -->
    <div class="beschikbaar-form">

        <p style="margin-bottom:14px;">
            <strong>Kies maximaal 3 dagen waarop je beschikbaar bent.</strong><br>
            <span style="font-size:11px;color:#666;">
                Elke les duurt precies 2 uur, in vaste blokken: 08:00–10:00, 10:00–12:00, 12:00–14:00, 14:00–16:00, 16:00–18:00, 18:00–20:00.
                Kies hieronder tussen welke tijden je beschikbaar bent.
            </span>
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
                /* Begin/eindtijd als hele uren tonen (geen seconden, geen halve uren) */
                $beg = $h ? substr($h['beginTijd'], 0, 5) : '08:00';
                $ein = $h ? substr($h['eindTijd'],  0, 5) : '18:00';
                $max = $h ? $h['maxLessen'] : 3;
            ?>
            <div class="dag-instellingen <?= $h ? 'zichtbaar' : '' ?>" id="inst_<?= $dag ?>">

                <h4>⚙️ <?= $dag ?></h4>

                <!-- Begin- en eindtijd: ALLEEN vaste 2-uurs-grenzen, geen halve uren -->
                <div class="tijd-rij">
                    <div class="form-group">
                        <label>Begintijd</label>
                        <select name="begin[<?= $dag ?>]" id="begin_<?= $dag ?>" onchange="herbereken('<?= $dag ?>')">
                            <?= vasteTijdOpties($vasteTijden, $beg) ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Eindtijd</label>
                        <select name="eind[<?= $dag ?>]" id="eind_<?= $dag ?>" onchange="herbereken('<?= $dag ?>')">
                            <?= vasteTijdOpties($vasteTijden, $ein) ?>
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

                <!-- Live preview van de vaste 2-uurs blokken -->
                <div>
                    <div style="font-size:11px;color:#555;margin-bottom:5px;">Lesblokken (elk blok = 2 uur, vast):</div>
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
            /* Genereer de vaste 2-uurs blokken voor deze dag (stap = 2 uur, niet 30 min) */
            $slots    = [];
            $beginMin = intval(substr($info['beginTijd'], 0, 2)) * 60;
            $eindMin  = intval(substr($info['eindTijd'],  0, 2)) * 60;
            for ($m = $beginMin; $m + 120 <= $eindMin; $m += 120) {
                $slots[] = sprintf('%02d:00', intdiv($m, 60));
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

            <!-- Lesblok badges: groen = vrij, rood = al bezet -->
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
   Let op: alleen hele uren (geen halve uren) en stap van 2 uur
   per lesblok, in lijn met de vaste tijden in de PHP-dropdowns.
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
        herbereken(dag); // toon direct de lesblok-preview
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
 * herbereken — Herberekent hoeveel vaste 2-uurs lesblokken passen
 * en toont de lesblok-preview. Wordt aangeroepen als begin- of
 * eindtijd verandert (beide zijn altijd hele uren uit de vaste lijst).
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

    /* Bereken hoeveel volledige 2-uurs lesblokken passen.
       Omdat begin/eind altijd op een 2-uurs grens staan (08, 10, 12...),
       is dit altijd een geheel getal, nooit een half blok. */
    const maxMogelijk = Math.min(6, Math.floor((eMin - bMin) / LES_DUUR));
    infoEl.textContent = `Maximaal ${maxMogelijk} lesblokken van 2 uur passen hier in.`;
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

    /* Toon lesblok-preview: stap = 2 uur (vaste blokken, geen 30-minuten-stappen) */
    slotsEl.innerHTML = '';
    for (let m = bMin; m + LES_DUUR <= eMin; m += LES_DUUR) {
        const div       = document.createElement('div');
        div.className   = 'slot';
        div.textContent = minNaarTijd(m) + '–' + minNaarTijd(m + LES_DUUR);
        slotsEl.appendChild(div);
    }
}

/**
 * tijdNaarMin — Zet "08:00" om naar 480 (minuten). Werkt alleen
 * correct voor hele uren, wat hier altijd het geval is.
 */
function tijdNaarMin(t) {
    const [u, m] = t.split(':').map(Number);
    return u * 60 + m;
}

/**
 * minNaarTijd — Zet 480 om naar "08:00".
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

/* Bij laden: bereken de lesblokken voor al eerder geselecteerde dagen */
document.querySelectorAll('.dag-checkbox:checked').forEach(cb => herbereken(cb.value));
</script>
</body>
</html>