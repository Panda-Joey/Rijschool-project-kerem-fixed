<?php
/* ============================================================
   kalender.php — Kalender
   Toont lessen én examens in een maandoverzicht.
   - Instructeur ziet zijn eigen lessen met studentnamen
   - Student ziet zijn eigen lessen met instructeurnamen
   Rode dag = alle lessen op die dag zijn geannuleerd.
   ============================================================ */

/* --- Toegangscontrole --- */
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

/* --- Database verbinding --- */
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/examen.php';

$conn = getDbConnection();
ensureExamenSchema($conn);

/* --- Sessievariabelen --- */
$rol    = $_SESSION['rol'];
$userID = $_SESSION['userID'];
$naam   = $_SESSION['naam'];

/* --- Maand en jaar bepalen --- */
$maanden = [
    5  => "Mei",       6  => "Juni",     7  => "Juli",
    8  => "Augustus",  9  => "September",10 => "Oktober",
    11 => "November",  12 => "December",
];

$maand       = isset($_GET['maand']) ? intval($_GET['maand']) : intval(date('m'));
$jaar        = 2026;
$vandaag     = date('Y-m-d');
$eersteDag   = date('N', strtotime("$jaar-$maand-01")); // 1=Ma ... 7=Zo
$aantalDagen = date('t', strtotime("$jaar-$maand-01")); // aantal dagen in de maand

/* ============================================================
   LESSEN OPHALEN
   De WHERE-clausule filtert op rol:
   - instructeur: alleen lessen waarbij hij de instructeur is
   - student:     alleen lessen waarbij hij de student is
   ============================================================ */

$filterKolom = ($rol === 'instructeur') ? 'lessen.instructeurID' : 'lessen.studentID';

$result = $conn->query("
    SELECT lessen.*,
           instructeurs.voornaam  AS iVoornaam,
           instructeurs.achternaam AS iAchternaam,
           studenten.voornaam     AS sVoornaam,
           studenten.achternaam   AS sAchternaam
    FROM lessen
    JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
    JOIN studenten    ON lessen.studentID     = studenten.studentID
    WHERE $filterKolom = $userID
      AND MONTH(lesDatum) = $maand
      AND YEAR(lesDatum)  = $jaar
    ORDER BY lesDatum ASC, lestijd ASC
");

/* Groepeer lessen per dagnummer (1–31) */
$lessen = [];
while ($rij = $result->fetch_assoc()) {
    $dag          = date('j', strtotime($rij['lesDatum']));
    $lessen[$dag][] = $rij;
}

/* Bepaal per dag of ALLE lessen vervallen zijn (= dag wordt rood) */
$dagVervallen = [];
foreach ($lessen as $dag => $dagLessen) {
    $aantalVervallen    = count(array_filter($dagLessen, fn($l) => $l['vervallen'] == 1));
    $dagVervallen[$dag] = ($aantalVervallen === count($dagLessen));
}

/* ============================================================
   EXAMENS OPHALEN
   Instructeur ziet examens die hij heeft aangevraagd.
   Student ziet zijn eigen examens.
   ============================================================ */
$examFilterKolom = ($rol === 'instructeur') ? 'e.instructeurID' : 'e.studentID';

$examResult = $conn->query("
    SELECT e.*,
           s.voornaam AS sVoornaam, s.achternaam AS sAchternaam,
           i.voornaam AS iVoornaam, i.achternaam AS iAchternaam
    FROM examens e
    JOIN studenten    s ON e.studentID     = s.studentID
    JOIN instructeurs i ON e.instructeurID = i.instructeurID
    WHERE $examFilterKolom = $userID
      AND MONTH(e.examDatum) = $maand
      AND YEAR(e.examDatum)  = $jaar
    ORDER BY e.examDatum ASC
");

/* Groepeer examens per dag */
$examens = [];
while ($rij = $examResult->fetch_assoc()) {
    $dag = date('j', strtotime($rij['examDatum']));
    $examens[$dag][] = $rij;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Examen blokjes in kalender */
        .lesson.exam-wachten  { background:#fff3cd; border-left-color:#ffc107; color:#856404; }
        .lesson.exam-geslaagd { background:#d4edda; border-left-color:#28a745; color:#155724; }
        .lesson.exam-gezakt   { background:#f8d7da; border-left-color:#dc3545; color:#721c24; }

        /* Legenda */
        .kalender-legenda {
            display:flex; gap:12px; flex-wrap:wrap;
            margin-top:10px; font-size:11px; padding:8px 0;
        }
        .legenda-item { display:flex; align-items:center; gap:5px; }
        .legenda-kleur {
            width:14px; height:14px; flex-shrink:0;
            border-left:3px solid;
        }
    </style>
</head>
<body>
<div class="container">

    <?php
    $navActief = 'kalender';
    $paginaLabel = 'Kalender';
    if ($rol === 'instructeur') {
        require_once 'instructeur_nav.php';
    } else {
        require_once 'student_nav.php';
    }
    ?>

    <!-- Kalender -->
    <div class="calendar">

        <!-- Maandnavigatie: pijl links — maandnaam — pijl rechts -->
        <div class="calendar-header">
            <a href="?maand=<?= max(5, $maand - 1) ?>">❮</a>
            <h2><?= ($maanden[$maand] ?? $maand) . " " . $jaar ?></h2>
            <a href="?maand=<?= min(12, $maand + 1) ?>">❯</a>
        </div>

        <!-- Rij met dagnamen -->
        <div class="days-header">
            <div>Ma</div><div>Di</div><div>Wo</div><div>Do</div>
            <div>Vr</div><div>Za</div><div>Zo</div>
        </div>

        <!-- Dag-cellen -->
        <div class="calendar-grid">
            <?php
            /* Lege cellen vóór de eerste dag van de maand */
            for ($i = 1; $i < $eersteDag; $i++):
            ?>
                <div class="day empty"></div>
            <?php endfor; ?>

            <?php
            /* Eén cel per dag */
            for ($dag = 1; $dag <= $aantalDagen; $dag++):
                $datumString    = "$jaar-$maand-$dag";
                $isVervallenDag = !empty($dagVervallen[$dag]);
                $isVandaag      = date('Y-m-d', strtotime($datumString)) === $vandaag;

                /* CSS klasse op de dag-cel */
                $celKlasse = 'day';
                if ($isVervallenDag) $celKlasse .= ' vervallen-dag';
                elseif ($isVandaag)  $celKlasse .= ' dag-vandaag';
            ?>
            <div class="<?= $celKlasse ?>">

                <!-- Dagnummer (met oranje stip als het vandaag is) -->
                <div class="date">
                    <?php if ($isVandaag): ?>
                        <span class="vandaag-dot"></span>
                    <?php endif; ?>
                    <?= $dag ?>
                </div>

                <!-- VERVALT-label als alle lessen die dag geannuleerd zijn -->
                <?php if ($isVervallenDag): ?>
                    <span class="vervallen-label">VERVALT</span>
                <?php endif; ?>

                <!-- Lesblokjes: klik opent de modal -->
                <?php if (isset($lessen[$dag])): ?>
                    <?php foreach ($lessen[$dag] as $les):
                        $lesNaam = ($rol === 'instructeur')
                            ? $les['sVoornaam'] . ' ' . $les['sAchternaam']
                            : $les['iVoornaam'] . ' ' . $les['iAchternaam'];
                    ?>
                    <div
                        class="lesson <?= $les['vervallen'] ? 'vervallen' : '' ?>"
                        onclick="openModal(
                            '<?= $les['lesDatum'] ?>',
                            '<?= substr($les['lestijd'], 0, 5) ?>',
                            '<?= addslashes($les['doel']) ?>',
                            '<?= addslashes($les['iVoornaam'] . ' ' . $les['iAchternaam']) ?>',
                            '<?= addslashes($les['sVoornaam'] . ' ' . $les['sAchternaam']) ?>',
                            '<?= addslashes($les['onderwerpen']) ?>',
                            '<?= addslashes($les['ophaalLocatie']) ?>',
                            <?= $les['lesID'] ?>
                        )"
                    >
                        <?= substr($les['lestijd'], 0, 5) ?><br>
                        <?= htmlspecialchars($lesNaam) ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Examenblokjes: klik opent de examen-modal -->
                <?php if (isset($examens[$dag])): ?>
                    <?php foreach ($examens[$dag] as $ex):
                        $exNaam  = ($rol === 'instructeur')
                            ? $ex['sVoornaam'] . ' ' . $ex['sAchternaam']
                            : $ex['iVoornaam'] . ' ' . $ex['iAchternaam'];
                        $exKleur = $ex['uitslag'] === 'geslaagd' ? 'exam-geslaagd'
                                 : ($ex['uitslag'] === 'gezakt'  ? 'exam-gezakt' : 'exam-wachten');
                        $exIcon  = $ex['uitslag'] === 'geslaagd' ? '🏆'
                                 : ($ex['uitslag'] === 'gezakt'  ? '❌' : '📋');
                        $exEindTijd = date('H:i', strtotime(substr($ex['examTijd'], 0, 5) . ' +1 hour'));
                    ?>
                    <div
                        class="lesson <?= $exKleur ?>"
                        onclick="openExamModal(
                            '<?= date('d-m-Y', strtotime($ex['examDatum'])) ?>',
                            '<?= substr($ex['examTijd'], 0, 5) ?>',
                            '<?= $exEindTijd ?>',
                            '<?= addslashes($ex['locatie']) ?>',
                            '<?= addslashes($ex['iVoornaam'] . ' ' . $ex['iAchternaam']) ?>',
                            '<?= addslashes($ex['sVoornaam'] . ' ' . $ex['sAchternaam']) ?>',
                            '<?= addslashes($ex['opmerking'] ?? '') ?>',
                            <?= $ex['poging'] ?>,
                            '<?= $ex['uitslag'] ?>'
                        )"
                    >
                        ⏰<?= substr($ex['examTijd'] ?? '09:00:00', 0, 5) ?> <?= $exIcon ?> Examen<br>
                        <?= htmlspecialchars($exNaam) ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
            <?php endfor; ?>
        </div>

    </div><!-- /calendar -->

    <!-- Legenda -->
    <div class="kalender-legenda">
        <div class="legenda-item">
            <div class="legenda-kleur" style="background:#dbeafe;border-color:#1b2940;"></div>
            <span>Les</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-kleur" style="background:#fff3cd;border-color:#ffc107;"></div>
            <span>📋 Examen gepland</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-kleur" style="background:#d4edda;border-color:#28a745;"></div>
            <span>🏆 Geslaagd</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-kleur" style="background:#f8d7da;border-color:#dc3545;"></div>
            <span>❌ Gezakt</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-kleur" style="background:#ffe0e0;border-color:#e00;"></div>
            <span>Vervallen dag</span>
        </div>
    </div>

    <!-- ── AANKOMENDE LESSEN LIJST ────────────────────────────── -->
    <!-- Toont dezelfde lessen als kaartjes onder de kalender    -->
    <div class="upcoming">

        <div class="upcoming-header">
            <h3>📋 Aankomende lessen — <?= $maanden[$maand] ?? $maand ?></h3>
            <a href="les_inroosteren.php" class="btn-nieuw-les">
                <?= $rol === 'instructeur' ? '+ Les inplannen' : '+ Nieuwe les' ?>
            </a>
        </div>

        <?php
        $dagLabels   = ['', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
        $heeftLessen = false;

        foreach ($lessen as $dagLessen):
            foreach ($dagLessen as $les):

                /* Geannuleerde lessen niet tonen in de lijst */
                if ($les['vervallen']) continue;

                $heeftLessen = true;
                $isVerleden  = $les['lesDatum'] < $vandaag;
                $isVandaag2  = $les['lesDatum'] === $vandaag;
                $dagNrLes    = date('N', strtotime($les['lesDatum']));

                /* Kaartkleur: geel = vandaag, grijs = verleden, standaard = blauw */
                $kaartKlasse = 'les-kaart';
                if ($isVandaag2) $kaartKlasse .= ' vandaag';
                elseif ($isVerleden) $kaartKlasse .= ' verleden';
        ?>

        <div class="<?= $kaartKlasse ?>">

            <!-- Datum blok: donkerblauw blokje links -->
            <div class="les-datum-blok">
                <div style="font-size:9px;text-transform:uppercase;"><?= $dagLabels[$dagNrLes] ?></div>
                <div class="dag"><?= date('d', strtotime($les['lesDatum'])) ?></div>
                <div class="maandnaam"><?= date('M', strtotime($les['lesDatum'])) ?></div>
            </div>

            <!-- Lesinformatie -->
            <div class="les-info">
                <span class="tijd-badge">⏰ <?= substr($les['lestijd'], 0, 5) ?></span>
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

            <!-- Wijzig en annuleer knoppen (alleen voor toekomstige lessen) -->
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

        if (!$heeftLessen):
        ?>
            <div class="geen-lessen">
                📭 Geen lessen gepland in <?= $maanden[$maand] ?? $maand ?>.
            </div>
        <?php endif; ?>

    </div><!-- /upcoming -->

</div><!-- /container -->

<!-- ── MODAL: LESDETAILS ───────────────────────────────────────
     Verschijnt als je op een lesblokje in de kalender klikt.
     Gevuld via JavaScript (openModal functie).
──────────────────────────────────────────────────────────────── -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 style="margin-bottom:14px;font-size:16px;color:#1b2940;">📋 Lesinformatie</h2>
        <p><strong>Datum:</strong>        <span id="mDatum"></span></p>
        <p><strong>Tijd:</strong>         <span id="mTijd"></span></p>
        <p><strong>Doel:</strong>         <span id="mDoel"></span></p>
        <p><strong>Instructeur:</strong>  <span id="mInstructeur"></span></p>
        <p><strong>Student:</strong>      <span id="mStudent"></span></p>
        <p><strong>Onderwerpen:</strong>  <span id="mOnderwerpen"></span></p>
        <p><strong>Ophaallocatie:</strong><span id="mLocatie"></span></p>
        <div style="margin-top:16px;display:flex;gap:10px;">
            <a id="mWijzig"   href="#" class="edit"   style="flex:1;text-align:center;padding:10px;text-decoration:none;">Wijzig</a>
            <a id="mAnnuleer" href="#" class="cancel" style="flex:1;text-align:center;padding:10px;text-decoration:none;">Annuleer</a>
        </div>
    </div>
</div>

<!-- ── MODAL: EXAMENINFORMATIE ─────────────────────────────────
     Verschijnt als je op een examenblokje in de kalender klikt.
     Gevuld via JavaScript (openExamModal functie).
──────────────────────────────────────────────────────────────── -->
<div id="examModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeExamModal()">&times;</span>
        <h2 style="margin-bottom:14px;font-size:16px;color:#1b2940;">📋 ExamenInformatie</h2>
        <p><strong>Datum:</strong>        <span id="eDatum"></span></p>
        <p><strong>Tijd:</strong>         <span id="eTijd"></span></p>
        <p><strong>Locatie:</strong> <span id="eLocatie"></span></p>
        <p><strong>Instructeur:</strong>  <span id="eInstructeur"></span></p>
        <p><strong>Student:</strong>      <span id="eStudent"></span></p>
        <p><strong>Poging:</strong>       <span id="ePoging"></span></p>
        <p><strong>Uitslag:</strong>      <span id="eUitslag"></span></p>
        <p id="eOpmerkingRij"><strong>Opmerking:</strong> <span id="eOpmerking"></span></p>
    </div>
</div>

<script>
/* ============================================================
   JAVASCRIPT — index.php
   ============================================================ */

/**
 * openModal — Vult de modal met lesgegevens en maakt hem zichtbaar.
 * Wordt aangeroepen via de onclick op elk lesblokje in de kalender.
 */
function openModal(datum, tijd, doel, instructeur, student, onderwerpen, locatie, lesID) {
    document.getElementById('mDatum').textContent       = datum;
    document.getElementById('mTijd').textContent        = tijd;
    document.getElementById('mDoel').textContent        = doel;
    document.getElementById('mInstructeur').textContent = instructeur;
    document.getElementById('mStudent').textContent     = student;
    document.getElementById('mOnderwerpen').textContent = onderwerpen;
    document.getElementById('mLocatie').textContent     = locatie;

    /* Knoppen linken naar de juiste les */
    document.getElementById('mWijzig').href   = 'wijzig.php?lesID=' + lesID;
    document.getElementById('mAnnuleer').href = 'annuleer.php?lesID=' + lesID + '&maand=<?= $maand ?>';

    document.getElementById('modal').style.display = 'block';
}

/**
 * closeModal — Verbergt de modal.
 */
function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

/**
 * openExamModal — Vult de examen-modal met examengegevens en maakt hem zichtbaar.
 * Wordt aangeroepen via de onclick op elk examenblokje in de kalender.
 */
function openExamModal(datum, beginTijd, eindTijd, locatie, instructeur, student, opmerking, poging, uitslag) {
    document.getElementById('eDatum').textContent       = datum;
    document.getElementById('eTijd').textContent        = beginTijd + ' – ' + eindTijd + ' uur';
    document.getElementById('eLocatie').textContent     = locatie;
    document.getElementById('eInstructeur').textContent = instructeur;
    document.getElementById('eStudent').textContent     = student;
    document.getElementById('ePoging').textContent      = poging;

    /* Uitslag met bijpassend icoon */
    const uitslagTekst = {
        wachten:  '⏳ Wacht op uitslag',
        geslaagd: '🏆 Geslaagd',
        gezakt:   '❌ Gezakt'
    };
    document.getElementById('eUitslag').textContent = uitslagTekst[uitslag] || uitslag;

    /* Opmerking-regel alleen tonen als er een opmerking is */
    const opmerkingRij = document.getElementById('eOpmerkingRij');
    if (opmerking) {
        document.getElementById('eOpmerking').textContent = opmerking;
        opmerkingRij.style.display = 'block';
    } else {
        opmerkingRij.style.display = 'none';
    }

    document.getElementById('examModal').style.display = 'block';
}

/**
 * closeExamModal — Verbergt de examen-modal.
 */
function closeExamModal() {
    document.getElementById('examModal').style.display = 'none';
}

/* Modal sluiten als er buiten de witte kaart geklikt wordt */
window.onclick = function (e) {
    if (e.target === document.getElementById('modal'))     closeModal();
    if (e.target === document.getElementById('examModal')) closeExamModal();
};
</script>
</body>
</html>