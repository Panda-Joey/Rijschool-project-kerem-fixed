<?php
/* ============================================================
   examen.php
   Instructeur kan hier:
   1. Een examen aanvragen voor een gekoppelde student
   2. De uitslag invullen (geslaagd / gezakt) na het examen
   3. Alle examens zien in een kalenderoverzicht
   
   Bij geslaagd: studenten.status → 'geslaagd', geslaagd = 1
   Bij gezakt:   poging teller blijft staan, nieuwe aanvraag mogelijk
   ============================================================ */

/* --- Toegangscontrole (sessie via includes/app.php) --- */
if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'instructeur') {
    header("Location: login.php");
    exit;
}

require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/examen.php';

$conn = getDbConnection();
ensureExamenSchema($conn);

$instrID = $_SESSION['userID'];
$succes  = "";
$fout    = "";

$maanden = [
    5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Augustus",
    9=>"September", 10=>"Oktober", 11=>"November", 12=>"December"
];
$maand = isset($_GET['maand']) ? intval($_GET['maand']) : intval(date('m'));
$jaar  = 2026;

/* ============================================================
   HULPFUNCTIE: examTijdOpties()
   Genereert <option> tags van 09:00 t/m 17:00 in stappen van
   15 minuten (kwart over, half, kwart voor, heel uur).
   ============================================================ */
function examTijdOpties(string $geselecteerd = '09:00'): string
{
    $opties = '';
    for ($m = 9 * 60; $m <= 17 * 60; $m += 15) {
        $tijd    = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $select  = ($tijd === $geselecteerd) ? 'selected' : '';
        $opties .= "<option value='$tijd' $select>$tijd</option>";
    }
    return $opties;
}


/* ============================================================
   EXAMEN AANVRAAG OPSLAAN
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['actie'] === 'aanvragen') {

    $studentID = intval($_POST['studentID']);
    $datum     = $conn->real_escape_string($_POST['examDatum']);
    $tijd      = $conn->real_escape_string($_POST['examTijd'] ?? '09:00');
    $locatie   = $conn->real_escape_string(trim($_POST['locatie']));
    $opmerking = $conn->real_escape_string(trim($_POST['opmerking'] ?? ''));

    /* Controleer dat de tijd binnen 09:00–17:00 valt (beveiliging tegen manipulatie) */
    $tijdMin = intval(substr($tijd, 0, 2)) * 60 + intval(substr($tijd, 3, 2));
    if ($tijdMin < 9 * 60 || $tijdMin > 17 * 60) {
        $tijd = '09:00'; // val terug op een geldige tijd
    }

    if (!$datum || !$tijd || !$locatie) {
        $fout = "Vul een datum, tijd en locatie in.";
    } else {
        /* Haal huidig pogingsnummer op */
        $r      = $conn->query("SELECT poging, voornaam, achternaam FROM studenten WHERE studentID = $studentID");
        $st     = $r ? $r->fetch_assoc() : null;

        if (!$st) {
            $fout = "Student niet gevonden.";
        } else {
            $nieuwePoging = intval($st['poging'] ?? 0) + 1;
            $stNaam       = $st['voornaam'] . ' ' . $st['achternaam'];
            $eindTijd     = date('H:i', strtotime($tijd . ':00 +1 hour')); // vaste duur van 1 uur

            $tijdDb = $conn->real_escape_string($tijd . ':00');

            /* Verhoog pogingsnummer op student */
            $conn->query("UPDATE studenten SET poging = $nieuwePoging WHERE studentID = $studentID");

            /* Sla examen op in examens tabel */
            $conn->query("
                INSERT INTO examens (studentID, instructeurID, examDatum, examTijd, locatie, opmerking, poging, uitslag)
                VALUES ($studentID, $instrID, '$datum', '$tijdDb', '$locatie', '$opmerking', $nieuwePoging, 'wachten')
            ");

            /* Melding naar student */
            $titel   = $conn->real_escape_string("Examen aangevraagd — Poging $nieuwePoging");
            $bericht = $conn->real_escape_string(
                "Je instructeur heeft een examen aangevraagd. Datum: {$datum}, {$tijd}–{$eindTijd} uur. Locatie: {$locatie}."
                . ($opmerking ? " Opmerking: {$opmerking}" : '')
            );
            $conn->query("INSERT INTO meldingen (titel, bericht, ontvanger_type, ontvanger_id) VALUES ('$titel', '$bericht', 'student', $studentID)");

            $succes = "Examen aangevraagd voor <strong>$stNaam</strong> (poging $nieuwePoging) op <strong>$datum</strong> om <strong>$tijd</strong>.";
        }
    }
}


/* ============================================================
   UITSLAG VERWERKEN (geslaagd / gezakt)
   1 examenaanvraag = 1 poging. Geslaagd of gezakt telt allebei
   als die ene poging — er wordt geen nieuwe poging meer bijgeteld
   hier (dat gebeurt al bij het aanvragen van het examen).
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['actie'] === 'uitslag') {

    $examID    = intval($_POST['examID']);
    $studentID = intval($_POST['studentID']);
    $uitslag   = $_POST['uitslag'] === 'geslaagd' ? 'geslaagd' : 'gezakt';

    /* Alleen toestaan als dit examen ook echt bij deze instructeur hoort */
    $check = $conn->query("SELECT examID FROM examens WHERE examID = $examID AND instructeurID = $instrID");

    if (!$check || $check->num_rows === 0) {
        $fout = "Je hebt geen toegang om dit examen te beoordelen.";
    } else {
        /* Sla uitslag op in examens tabel */
        $conn->query("UPDATE examens SET uitslag = '$uitslag' WHERE examID = $examID");

        if ($uitslag === 'geslaagd') {
            /* Geslaagd: status → geslaagd, geslaagd vlag → TRUE (1) */
            $conn->query("UPDATE studenten SET geslaagd = 1, status = 'geslaagd' WHERE studentID = $studentID");

            $conn->query("INSERT INTO meldingen (titel, bericht, ontvanger_type, ontvanger_id)
                VALUES ('🏆 Gefeliciteerd, je bent geslaagd!', 'Je hebt je rijexamen gehaald. Proficiat!', 'student', $studentID)");

            $succes = "🏆 Student gemarkeerd als <strong>geslaagd</strong>. Status bijgewerkt naar 'geslaagd'.";

        } else {
            /* Gezakt: status blijft 'actief', geslaagd vlag → FALSE (0) */
            $conn->query("UPDATE studenten SET geslaagd = 0, status = 'actief' WHERE studentID = $studentID");

            $conn->query("INSERT INTO meldingen (titel, bericht, ontvanger_type, ontvanger_id)
                VALUES ('Uitslag rijexamen', 'Je bent helaas gezakt voor je rijexamen. Je status blijft actief — je instructeur kan een nieuwe poging aanvragen.', 'student', $studentID)");

            $succes = "Uitslag <strong>gezakt</strong> opgeslagen. Status van de student blijft 'actief'.";
        }
    }
}


/* ============================================================
   DATA OPHALEN — Gekoppelde studenten
   ============================================================ */
$stmt = $conn->prepare("
    SELECT
        s.studentID, s.voornaam, s.tussenvoegsel, s.achternaam,
        s.transmissie, s.beperking, s.poging, s.geslaagd, s.status,
        sl.overige_uren,
        lp.naam AS pakketNaam,
        (SELECT COUNT(*) FROM lessen l  WHERE l.studentID = s.studentID AND l.instructeurID = ? AND l.goedgekeurd = 1 AND l.vervallen = 0) AS goedgekeurdeLeessen,
        (SELECT COUNT(*) FROM lessen l2 WHERE l2.studentID = s.studentID AND l2.instructeurID = ? AND l2.vervallen = 0) AS totaalLessen
    FROM studenten_has_instructeurs shi
    JOIN studenten s ON shi.studentID = s.studentID
    LEFT JOIN student_lespakket sl ON sl.studentID = s.studentID
    LEFT JOIN lespakket lp ON sl.idlespakket = lp.idlespakket
    WHERE shi.instructeurID = ?
    ORDER BY s.achternaam ASC
");
$stmt->bind_param("iii", $instrID, $instrID, $instrID);
$stmt->execute();
$studenten = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


/* ============================================================
   DATA OPHALEN — Open uitslagen (ALLE maanden, niet gefilterd)
   Dit zijn examens die al geweest zijn (datum vandaag of eerder)
   maar nog geen uitslag hebben. Los van welke maand de instructeur
   in de kalender bekijkt, moeten deze altijd zichtbaar zijn.
   ============================================================ */
$stmt = $conn->prepare("
    SELECT e.*, s.voornaam, s.achternaam
    FROM examens e
    JOIN studenten s ON e.studentID = s.studentID
    WHERE e.instructeurID = ?
      AND e.uitslag = 'wachten'
      AND e.examDatum <= CURDATE()
    ORDER BY e.examDatum ASC
");
$stmt->bind_param("i", $instrID);
$stmt->execute();
$openUitslagen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


/* ============================================================
   DATA OPHALEN — Examens deze maand (voor kalender)
   ============================================================ */
$stmt = $conn->prepare("
    SELECT e.*, s.voornaam, s.achternaam
    FROM examens e
    JOIN studenten s ON e.studentID = s.studentID
    WHERE e.instructeurID = ?
      AND MONTH(e.examDatum) = ?
      AND YEAR(e.examDatum)  = ?
    ORDER BY e.examDatum ASC
");
$stmt->bind_param("iii", $instrID, $maand, $jaar);
$stmt->execute();
$examens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Groepeer examens per dag voor kalender */
$examensPerDag = [];
foreach ($examens as $ex) {
    $dag = date('j', strtotime($ex['examDatum']));
    $examensPerDag[$dag][] = $ex;
}

$eersteDag   = date('N', strtotime("$jaar-$maand-01"));
$aantalDagen = date('t', strtotime("$jaar-$maand-01"));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examens</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── TABS ── */
        .tabs { display:flex; gap:0; margin-bottom:16px; border:2px solid #1b2940; }
        .tab {
            flex:1; padding:12px; text-align:center; font-size:12px; font-weight:bold;
            cursor:pointer; background:#f0f0f0; color:#555; border-right:2px solid #1b2940;
        }
        .tab:last-child { border-right:none; }
        .tab.actief { background:#1b2940; color:white; }
        .tab-inhoud { display:none; }
        .tab-inhoud.actief { display:block; }

        /* ── EXAMEN KAART ── */
        .examen-kaart {
            background:white; border:2px solid #d0d8e4;
            border-left:5px solid #1b2940; padding:16px 20px;
            margin-bottom:12px; display:flex; flex-wrap:wrap;
            gap:16px; align-items:flex-start; justify-content:space-between;
        }
        .examen-kaart.geslaagd { border-left-color:#28a745; background:#f8fff8; }
        .examen-kaart.wachten  { border-left-color:#ffc107; background:#fffdf0; }

        .examen-student-info { flex:1; min-width:200px; }
        .examen-student-info h4 { margin:0 0 6px 0; font-size:15px; color:#1b2940; }
        .examen-student-info p  { margin:2px 0; font-size:12px; color:#555; }

        .examen-stats { display:flex; gap:10px; flex-wrap:wrap; }
        .examen-stat  { text-align:center; background:#f5f7fa; border:1px solid #d0d8e4; padding:8px 14px; min-width:70px; }
        .examen-stat .getal { font-size:22px; font-weight:bold; color:#1b2940; line-height:1; }
        .examen-stat .label { font-size:9px; color:#888; margin-top:3px; text-transform:uppercase; }

        /* ── BADGES ── */
        .poging-badge { display:inline-block; padding:2px 8px; font-size:10px; font-weight:bold; border-radius:2px; margin-right:4px; }
        .poging-0   { background:#e0e0e0; color:#555; }
        .poging-1   { background:#dbeafe; color:#1e40af; }
        .poging-2   { background:#fef3c7; color:#92400e; }
        .poging-3up { background:#fee2e2; color:#991b1b; }
        .badge-geslaagd  { background:#d4edda; color:#155724; border:1px solid #28a745; font-size:10px; padding:2px 8px; font-weight:bold; }
        .badge-gezakt    { background:#f8d7da; color:#721c24; border:1px solid #dc3545; font-size:10px; padding:2px 8px; font-weight:bold; }
        .badge-wachten   { background:#fff3cd; color:#856404; border:1px solid #ffc107; font-size:10px; padding:2px 8px; font-weight:bold; }
        .badge-beperking { background:#fef3c7; color:#92400e; border:1px solid #f59e0b; font-size:10px; padding:2px 6px; }

        /* ── AANVRAAG FORMULIER ── */
        .examen-form { background:#f5f7fa; border:1px solid #d0d8e4; border-top:3px solid #1b2940; padding:14px 16px; margin-top:12px; display:none; width:100%; box-sizing:border-box; }
        .examen-form.open { display:block; }
        .examen-form .form-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
        .examen-form .form-group { flex:1; min-width:150px; margin-bottom:0; }

        /* ── UITSLAG KNOPPEN ── */
        .btn-geslaagd { background:#28a745; color:white; border:2px solid #28a745; padding:9px 16px; font-size:12px; font-family:Arial; font-weight:bold; cursor:pointer; }
        .btn-gezakt   { background:#dc3545; color:white; border:2px solid #dc3545; padding:9px 16px; font-size:12px; font-family:Arial; font-weight:bold; cursor:pointer; }
        .btn-examen   { background:#1b2940; color:white; border:2px solid #1b2940; padding:9px 16px; font-size:12px; font-family:Arial; font-weight:bold; cursor:pointer; }
        .btn-examen:hover { background:#243a55; }
        .btn-annuleer-form { background:white; color:#1b2940; border:2px solid #1b2940; padding:9px 16px; font-size:12px; font-family:Arial; cursor:pointer; }

        /* ── KALENDER ── */
        .exam-blok {
            padding:2px 4px; font-size:8px; margin-bottom:2px; cursor:default;
            border-left:3px solid #1b2940; background:#dbeafe; color:#1b2940;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .exam-blok.geslaagd { background:#d4edda; border-left-color:#28a745; color:#155724; }
        .exam-blok.gezakt   { background:#f8d7da; border-left-color:#dc3545; color:#721c24; }
        .exam-blok.wachten  { background:#fff3cd; border-left-color:#ffc107; color:#856404; }

        /* ── OPEN UITSLAGEN SECTIE ── */
        .uitslag-sectie { background:#fff3cd; border:2px solid #ffc107; padding:14px 18px; margin-bottom:16px; }
        .uitslag-sectie h4 { margin:0 0 10px 0; color:#856404; font-size:13px; }

        .geen { background:white; border:2px dashed #ccc; padding:24px; text-align:center; color:#888; font-size:13px; }
    </style>
</head>
<body>
<div class="container">

    <?php
    $navActief = 'examen';
    $paginaLabel = 'Examen aanvragen';
    require_once 'instructeur_nav.php';
    ?>

    <?php if ($succes): ?><div class="succes">✅ <?= $succes ?></div><?php endif; ?>
    <?php if ($fout):   ?><div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div><?php endif; ?>


    <!-- ── TABS ───────────────────────────────────────────────── -->
    <div class="tabs">
        <div class="tab actief" onclick="toonTab('aanvragen')">📋 Examen aanvragen</div>
        <div class="tab"        onclick="toonTab('kalender')">📅 Kalender</div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         TAB 1: EXAMEN AANVRAGEN + UITSLAG INVULLEN
    ══════════════════════════════════════════════════════════ -->
    <div class="tab-inhoud actief" id="tab-aanvragen">

        <!-- Open uitslagen: examens die al geweest zijn maar nog geen uitslag hebben -->
        <?php if (!empty($openUitslagen)): ?>
        <div class="uitslag-sectie">
            <h4>⏳ Uitslag invullen — examen(s) al geweest:</h4>
            <?php foreach ($openUitslagen as $ex): ?>
            <div style="background:white;border:1px solid #ffc107;padding:12px 16px;margin-bottom:8px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
                <div>
                    <strong><?= htmlspecialchars($ex['voornaam'] . ' ' . $ex['achternaam']) ?></strong>
                    <span class="badge-wachten" style="margin-left:8px;">Poging <?= $ex['poging'] ?></span><br>
                    <span style="font-size:11px;color:#555;">
                        📅 <?= date('d-m-Y', strtotime($ex['examDatum'])) ?>
                        &nbsp;·&nbsp; ⏰ <?= substr($ex['examTijd'], 0, 5) ?> uur
                        &nbsp;·&nbsp; 📍 <?= htmlspecialchars($ex['locatie']) ?>
                    </span>
                </div>
                <!-- Uitslag knoppen -->
                <div style="display:flex;gap:8px;">
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Bevestig: student is GESLAAGD?')">
                        <input type="hidden" name="actie"     value="uitslag">
                        <input type="hidden" name="examID"    value="<?= $ex['examID'] ?>">
                        <input type="hidden" name="studentID" value="<?= $ex['studentID'] ?>">
                        <input type="hidden" name="uitslag"   value="geslaagd">
                        <button type="submit" class="btn-geslaagd">🏆 Geslaagd</button>
                    </form>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Bevestig: student is GEZAKT?')">
                        <input type="hidden" name="actie"     value="uitslag">
                        <input type="hidden" name="examID"    value="<?= $ex['examID'] ?>">
                        <input type="hidden" name="studentID" value="<?= $ex['studentID'] ?>">
                        <input type="hidden" name="uitslag"   value="gezakt">
                        <button type="submit" class="btn-gezakt">❌ Gezakt</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Studentenlijst voor nieuwe aanvragen -->
        <?php if (empty($studenten)): ?>
            <div class="geen">📭 Geen gekoppelde studenten.</div>
        <?php else: ?>
            <?php foreach ($studenten as $st):
                $volledigeNaam = $st['voornaam'] . ($st['tussenvoegsel'] ? ' '.$st['tussenvoegsel'] : '') . ' ' . $st['achternaam'];
                $poging        = intval($st['poging'] ?? 0);
                $isGeslaagd    = $st['geslaagd'] == 1;
                $pogingKlasse  = $poging === 0 ? 'poging-0' : ($poging === 1 ? 'poging-1' : ($poging === 2 ? 'poging-2' : 'poging-3up'));
            ?>
            <div class="examen-kaart <?= $isGeslaagd ? 'geslaagd' : '' ?>">

                <!-- Student info -->
                <div class="examen-student-info">
                    <h4>
                        <?= htmlspecialchars($volledigeNaam) ?>
                        <?php if ($st['beperking']): ?><span class="badge-beperking">⚠️ Beperking</span><?php endif; ?>
                        <?php if ($isGeslaagd): ?><span class="badge-geslaagd">🏆 Geslaagd</span><?php endif; ?>
                    </h4>
                    <p>🚗 <?= ucfirst($st['transmissie']) ?> &nbsp;·&nbsp; 📦 <?= htmlspecialchars($st['pakketNaam'] ?? '—') ?></p>
                    <p>
                        <span class="poging-badge <?= $pogingKlasse ?>">
                            <?= $poging === 0 ? 'Nog geen poging' : "Poging $poging" ?>
                        </span>
                        <?php if ($poging >= 3): ?><span style="font-size:10px;color:#991b1b;">⚠️ Meerdere pogingen</span><?php endif; ?>
                    </p>
                </div>

                <!-- Statistieken -->
                <div class="examen-stats">
                    <div class="examen-stat">
                        <div class="getal"><?= $st['totaalLessen'] ?></div>
                        <div class="label">Lessen</div>
                    </div>
                    <div class="examen-stat">
                        <div class="getal"><?= $st['goedgekeurdeLeessen'] ?></div>
                        <div class="label">Goedgekeurd</div>
                    </div>
                    <div class="examen-stat">
                        <div class="getal"><?= $st['overige_uren'] ?? '—' ?></div>
                        <div class="label">Uren over</div>
                    </div>
                    <div class="examen-stat">
                        <div class="getal"><?= $poging ?></div>
                        <div class="label">Pogingen</div>
                    </div>
                </div>

                <!-- Examen aanvragen knop (niet als al geslaagd) -->
                <?php if (!$isGeslaagd): ?>
                <div>
                    <button type="button" class="btn-examen" onclick="toggleForm(<?= $st['studentID'] ?>)">
                        📋 Examen aanvragen
                    </button>
                </div>

                <!-- Aanvraagformulier -->
                <div class="examen-form" id="form_<?= $st['studentID'] ?>">
                    <form method="POST" action="examen.php">
                        <input type="hidden" name="actie"     value="aanvragen">
                        <input type="hidden" name="studentID" value="<?= $st['studentID'] ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Examendatum <span style="color:#dc3545;">*</span></label>
                                <input type="date" name="examDatum" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>⏰ Begintijd <span style="color:#dc3545;">*</span></label>
                                <select name="examTijd" required>
                                    <?= examTijdOpties('09:00') ?>
                                </select>
                                <div style="font-size:10px;color:#888;margin-top:3px;">Tussen 09:00–17:00 · examen duurt 1 uur</div>
                            </div>
                            <div class="form-group">
                                <label>📍 Locatie <span style="color:#dc3545;">*</span></label>
                                <input type="text" name="locatie" placeholder="bv. CBR Utrecht Westraven" maxlength="100" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label>📝 Opmerking (optioneel)</label>
                            <textarea name="opmerking" maxlength="300" style="min-height:60px;" placeholder="Extra informatie..."></textarea>
                        </div>
                        <div class="btn-row">
                            <button type="button" class="btn-annuleer-form" onclick="toggleForm(<?= $st['studentID'] ?>)">Annuleer</button>
                            <button type="submit" class="btn-opslaan">✅ Bevestig aanvraag</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         TAB 2: KALENDER MET EXAMENS
    ══════════════════════════════════════════════════════════ -->
    <div class="tab-inhoud" id="tab-kalender">

        <div class="calendar">

            <!-- Maandnavigatie -->
            <div class="calendar-header">
                <a href="?maand=<?= max(5, $maand - 1) ?>">❮</a>
                <h2><?= ($maanden[$maand] ?? $maand) . " $jaar" ?></h2>
                <a href="?maand=<?= min(12, $maand + 1) ?>">❯</a>
            </div>

            <!-- Dagnamen -->
            <div class="days-header">
                <div>Ma</div><div>Di</div><div>Wo</div><div>Do</div>
                <div>Vr</div><div>Za</div><div>Zo</div>
            </div>

            <!-- Kalender grid -->
            <div class="calendar-grid">
                <?php
                /* Lege cellen voor de 1e van de maand */
                for ($i = 1; $i < $eersteDag; $i++):
                ?>
                    <div class="day empty"></div>
                <?php endfor; ?>

                <?php for ($dag = 1; $dag <= $aantalDagen; $dag++):
                    $isVandaag = date('Y-m-d', strtotime("$jaar-$maand-$dag")) === date('Y-m-d');
                ?>
                <div class="day <?= $isVandaag ? 'dag-vandaag' : '' ?>">
                    <div class="date">
                        <?php if ($isVandaag): ?><span class="vandaag-dot"></span><?php endif; ?>
                        <?= $dag ?>
                    </div>

                    <!-- Toon examens op deze dag -->
                    <?php if (isset($examensPerDag[$dag])): ?>
                        <?php foreach ($examensPerDag[$dag] as $ex): ?>
                            <div class="exam-blok <?= $ex['uitslag'] ?>">
                                ⏰<?= substr($ex['examTijd'], 0, 5) ?> 📋 <?= htmlspecialchars($ex['voornaam']) ?>
                                <?php if ($ex['uitslag'] === 'geslaagd'): ?> 🏆
                                <?php elseif ($ex['uitslag'] === 'gezakt'): ?> ❌
                                <?php else: ?> ⏳<?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Legenda -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;font-size:11px;">
            <span><span style="display:inline-block;width:12px;height:12px;background:#dbeafe;border-left:3px solid #1b2940;margin-right:4px;"></span>Gepland</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#fff3cd;border-left:3px solid #ffc107;margin-right:4px;"></span>Wacht op uitslag</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#d4edda;border-left:3px solid #28a745;margin-right:4px;"></span>Geslaagd</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#f8d7da;border-left:3px solid #dc3545;margin-right:4px;"></span>Gezakt</span>
        </div>

        <!-- Lijst van examens deze maand -->
        <?php if (!empty($examens)): ?>
        <div class="upcoming" style="margin-top:16px;">
            <div class="upcoming-header">
                <h3>📋 Examens — <?= $maanden[$maand] ?? $maand ?></h3>
            </div>
            <?php foreach ($examens as $ex):
                $dagNamen = ['','Ma','Di','Wo','Do','Vr','Za','Zo'];
                $dagNr    = date('N', strtotime($ex['examDatum']));
            ?>
            <div class="les-kaart <?= $ex['uitslag'] === 'wachten' && $ex['examDatum'] < date('Y-m-d') ? 'vandaag' : '' ?>">
                <div class="les-datum-blok">
                    <div style="font-size:9px;text-transform:uppercase;"><?= $dagNamen[$dagNr] ?></div>
                    <div class="dag"><?= date('d', strtotime($ex['examDatum'])) ?></div>
                    <div class="maandnaam"><?= date('M', strtotime($ex['examDatum'])) ?></div>
                </div>
                <div class="les-info">
                    <?php if ($ex['uitslag'] === 'geslaagd'): ?>
                        <span class="badge-geslaagd">🏆 Geslaagd</span>
                    <?php elseif ($ex['uitslag'] === 'gezakt'): ?>
                        <span class="badge-gezakt">❌ Gezakt</span>
                    <?php else: ?>
                        <span class="badge-wachten">⏳ Wacht op uitslag</span>
                    <?php endif; ?>
                    <h4><?= htmlspecialchars($ex['voornaam'] . ' ' . $ex['achternaam']) ?></h4>
                    <p>⏰ <?= substr($ex['examTijd'], 0, 5) ?> – <?= date('H:i', strtotime($ex['examTijd'] . ' +1 hour')) ?> uur</p>
                    <p>📍 <?= htmlspecialchars($ex['locatie']) ?></p>
                    <p>Poging <?= $ex['poging'] ?></p>
                    <?php if ($ex['opmerking']): ?><p style="color:#888;font-style:italic;">📝 <?= htmlspecialchars($ex['opmerking']) ?></p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="geen-lessen" style="margin-top:16px;">📭 Geen examens gepland in <?= $maanden[$maand] ?? $maand ?>.</div>
        <?php endif; ?>

    </div>

</div>

<script>
/**
 * toonTab — Wisselt tussen de twee tabs (aanvragen / kalender).
 */
function toonTab(id) {
    document.querySelectorAll('.tab-inhoud').forEach(t => t.classList.remove('actief'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('actief'));
    document.getElementById('tab-' + id).classList.add('actief');
    event.target.classList.add('actief');
}

/**
 * toggleForm — Toont of verbergt het aanvraagformulier per student.
 */
function toggleForm(studentID) {
    document.getElementById('form_' + studentID).classList.toggle('open');
}

/* Open kalender tab automatisch als er een maand in de URL staat */
<?php if (isset($_GET['maand'])): ?>
toonTab('kalender');
document.querySelectorAll('.tab')[1].classList.add('actief');
document.querySelectorAll('.tab')[0].classList.remove('actief');
<?php endif; ?>
</script>
</body>
</html>