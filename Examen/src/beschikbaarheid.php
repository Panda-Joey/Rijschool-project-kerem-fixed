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

require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/lesvoorkeur.php';

$conn = getDbConnection();
ensureLesvoorkeurSchema($conn);

if (!isset($_SESSION['userID']) || ($_SESSION['rol'] ?? '') !== 'instructeur') {
    header('Location: login.php');
    exit;
}

$instrID = intval($_SESSION['userID']);
$naam    = $_SESSION['naam'] ?? '';
$succes  = '';
$fout    = '';

$r = $conn->query("SELECT transmissie FROM instructeurs WHERE instructeurID = $instrID");
$instrTransmissie = ($r && $rij = $r->fetch_assoc()) ? $rij['transmissie'] : 'schakel';

$dagNamen = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

$vasteTijden = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['transmissie'])) {
        $nieuwTransmissie = $_POST['transmissie'];
        if (in_array($nieuwTransmissie, ['schakel', 'automaat', 'beide'], true)) {
            $stmt = $conn->prepare('UPDATE instructeurs SET transmissie = ? WHERE instructeurID = ?');
            $stmt->bind_param('si', $nieuwTransmissie, $instrID);
            $stmt->execute();
            $stmt->close();
            syncInstructeurAuto($conn, $instrID, $nieuwTransmissie);
            $instrTransmissie = $nieuwTransmissie;
        }
    }

    $gekozenDagen = $_POST['dagen'] ?? [];

    if (count($gekozenDagen) > 3) {
        $fout = 'Je kunt maximaal 3 dagen selecteren.';
    } elseif (count($gekozenDagen) === 0) {
        $fout = 'Selecteer minimaal 1 dag.';
    } else {
        $nieuweRijen = [];

        foreach ($gekozenDagen as $dag) {
            if (!in_array($dag, $dagNamen, true)) {
                $fout = 'Ongeldige dag ontvangen.';
                break;
            }

            $begin     = $_POST['begin'][$dag] ?? '08:00';
            $eind      = $_POST['eind'][$dag]  ?? '18:00';
            $maxLessen = intval($_POST['max'][$dag] ?? 6);

            if (!in_array($begin, $vasteTijden, true) || !in_array($eind, $vasteTijden, true)) {
                $fout = "Ongeldig tijdstip op $dag. Kies alleen uit de vaste tijden.";
                break;
            }

            if ($begin >= $eind) {
                $fout = "Begintijd moet voor eindtijd liggen ($dag).";
                break;
            }

            $beginMin = intval(substr($begin, 0, 2)) * 60;
            $eindMin  = intval(substr($eind,  0, 2)) * 60;
            $maxSlots = intdiv($eindMin - $beginMin, 120);
            $maxLessen = max(1, min(6, $maxSlots, $maxLessen));

            if ($maxLessen > $maxSlots) {
                $fout = "Op $dag passen maximaal $maxSlots lessen (elk 2 uur) in het tijdvak {$begin}-{$eind}.";
                break;
            }

            $nieuweRijen[] = [
                'dag'       => $dag,
                'begin'     => $begin,
                'eind'      => $eind,
                'maxLessen' => $maxLessen,
            ];
        }

        if (!$fout) {
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

            $succes = 'Beschikbaarheid opgeslagen!';
        }
    }
}

$res = $conn->query("SELECT * FROM beschikbaarheid WHERE instructeurID = $instrID ORDER BY FIELD(dagNaam,'Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag')");
$huidig = [];
while ($r = $res->fetch_assoc()) {
    $huidig[$r['dagNaam']] = $r;
}

$bezetteTijdenPerDag = [];
foreach ($huidig as $dag => $info) {
    $dagNr    = array_search($dag, ['', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag']);
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
<title>Beschikbaarheid instellen</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <?php
    $navActief = 'rooster';
    $paginaLabel = 'Rooster';
    require_once 'instructeur_nav.php';
    ?>

    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>
    <?php if ($fout): ?>
        <div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div>
    <?php endif; ?>

    <div class="beschikbaar-form">
        <p style="margin-bottom:14px;">
            <strong>Kies maximaal 3 dagen waarop je beschikbaar bent.</strong><br>
            <span style="font-size:11px;color:#666;">
                Elke les duurt precies 2 uur, in vaste blokken: 08:00–10:00, 10:00–12:00, 12:00–14:00, 14:00–16:00, 16:00–18:00, 18:00–20:00.
                Kies hieronder tussen welke tijden je beschikbaar bent.
            </span>
        </p>

        <form method="POST" action="beschikbaarheid.php" onsubmit="return valideer()">

            <div class="form-group" style="margin-bottom:20px;">
                <label>Welk type auto geef jij les in?</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
                    <?php foreach (['schakel', 'automaat', 'beide'] as $t): ?>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:10px 16px;border:2px solid <?= $instrTransmissie === $t ? '#1b2940' : '#ccc' ?>;background:<?= $instrTransmissie === $t ? '#1b2940' : 'white' ?>;color:<?= $instrTransmissie === $t ? 'white' : '#333' ?>;font-size:12px;font-weight:bold;">
                        <input type="radio" name="transmissie" value="<?= $t ?>" <?= $instrTransmissie === $t ? 'checked' : '' ?> style="display:none;">
                        <?= lesvoorkeurLabel($t) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:11px;color:#666;margin-top:6px;">Je gekoppelde lesauto wordt automatisch gekozen op basis van dit type.</p>
            </div>

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

            <?php foreach ($dagNamen as $dag):
                $h   = $huidig[$dag] ?? null;
                $vis = $h ? 'zichtbaar' : '';
                $beg = $h ? substr($h['beginTijd'], 0, 5) : '08:00';
                $ein = $h ? substr($h['eindTijd'],  0, 5) : '18:00';
                $max = $h ? $h['maxLessen'] : 3;
            ?>
            <div class="dag-instellingen <?= $vis ?>" id="inst_<?= $dag ?>">
                <h4>⚙️ <?= $dag ?></h4>

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

                <div>
                    <div style="font-size:11px;color:#555;margin-bottom:5px;">Lesblokken (elk blok = 2 uur, vast):</div>
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

    <?php if (!empty($huidig)): ?>
    <div class="overzicht">
        <h3>📅 Jouw huidige beschikbaarheid</h3>
        <?php foreach ($huidig as $dag => $info):
            $slots    = [];
            $beginMin = intval(substr($info['beginTijd'], 0, 2)) * 60;
            $eindMin  = intval(substr($info['eindTijd'],  0, 2)) * 60;
            for ($m = $beginMin; $m + 120 <= $eindMin; $m += 120) {
                $slots[] = sprintf('%02d:00', intdiv($m, 60));
            }
            $bezet = $bezetteTijdenPerDag[$dag] ?? [];
        ?>
        <div class="overzicht-rij">
            <div class="dag-naam"><?= $dag ?></div>
            <div style="font-size:11px;color:#555;">
                <?= substr($info['beginTijd'], 0, 5) ?> – <?= substr($info['eindTijd'], 0, 5) ?>
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
const LES_DUUR = 120;

function toggleDag(dag, aan) {
    const inst = document.getElementById('inst_' + dag);
    if (aan) {
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

    const infoEl = document.getElementById('maxInfo_' + dag);
    const slotsEl = document.getElementById('slots_' + dag);

    if (eMin <= bMin) {
        infoEl.textContent = '⚠️ Eindtijd moet na begintijd liggen.';
        infoEl.style.color = '#dc3545';
        slotsEl.innerHTML  = '';
        return;
    }

    const maxMogelijk = Math.min(6, Math.floor((eMin - bMin) / LES_DUUR));
    infoEl.textContent = `Maximaal ${maxMogelijk} lesblokken van 2 uur passen hier in.`;
    infoEl.style.color = '#555';

    for (let i = 1; i <= 6; i++) {
        const btn = document.getElementById('maxBtn_' + dag + '_' + i);
        btn.disabled = i > maxMogelijk;
        btn.style.opacity = i > maxMogelijk ? '.3' : '1';
    }

    const huidigMax = parseInt(document.getElementById('maxVal_' + dag).value);
    if (huidigMax > maxMogelijk) setMax(dag, maxMogelijk);

    slotsEl.innerHTML = '';
    for (let m = bMin; m + LES_DUUR <= eMin; m += LES_DUUR) {
        const div = document.createElement('div');
        div.className = 'slot';
        div.textContent = minNaarTijd(m) + '–' + minNaarTijd(m + LES_DUUR);
        slotsEl.appendChild(div);
    }
}

function tijdNaarMin(t) {
    const [u, m] = t.split(':').map(Number);
    return u * 60 + m;
}

function minNaarTijd(m) {
    return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
}

function valideer() {
    const n = document.querySelectorAll('.dag-checkbox:checked').length;
    if (n === 0) { alert('Selecteer minimaal 1 dag.'); return false; }
    if (n > 3)   { alert('Maximaal 3 dagen.'); return false; }
    return true;
}

document.querySelectorAll('.dag-checkbox:checked').forEach(cb => herbereken(cb.value));
</script>
</body>
</html>
