<?php
/* ============================================================
   examen.php
   Alleen toegankelijk voor instructeurs.
   Instructeur kan hier een examen aanvragen voor een
   gekoppelde student. Het systeem slaat de aanvraag op
   via studenten.poging (pogingsnummer) en stuurt een
   melding naar de student.
   ============================================================ */

/* --- Toegangscontrole (sessie via includes/app.php) --- */
if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'instructeur') {
    header("Location: login.php");
    exit;
}

$conn      = new mysqli("mysql", "root", "password", "Eend");
if ($conn->connect_error) die("Verbinding mislukt: " . $conn->connect_error);

$instrID   = $_SESSION['userID'];
$succes    = "";
$fout      = "";


/* ============================================================
   EXAMEN AANVRAAG VERWERKEN
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studentID'])) {

    $studentID = intval($_POST['studentID']);
    $datum     = $conn->real_escape_string($_POST['examDatum']);
    $locatie   = $conn->real_escape_string(trim($_POST['locatie']));
    $opmerking = $conn->real_escape_string(trim($_POST['opmerking'] ?? ''));

    /* Validatie */
    if (!$datum || !$locatie) {
        $fout = "Vul een datum en locatie in.";
    } else {

        /* Haal huidige pogingsnummer op van de student */
        $r       = $conn->query("SELECT poging, voornaam, achternaam FROM studenten WHERE studentID = $studentID");
        $student = $r ? $r->fetch_assoc() : null;

        if (!$student) {
            $fout = "Student niet gevonden.";
        } else {
            $huidigPoging  = intval($student['poging'] ?? 0);
            $nieuwePoging  = $huidigPoging + 1;
            $studentNaam   = $student['voornaam'] . ' ' . $student['achternaam'];

            /* Verhoog het pogingsnummer */
            $conn->query("UPDATE studenten SET poging = $nieuwePoging WHERE studentID = $studentID");

            /* Stuur een melding naar de student */
            $titel   = $conn->real_escape_string("Examen aangevraagd — Poging $nieuwePoging");
            $bericht = $conn->real_escape_string(
                "Je instructeur heeft een examen voor je aangevraagd. " .
                "Datum: $datum. Locatie: $locatie." .
                ($opmerking ? " Opmerking: $opmerking" : "")
            );
            $conn->query("
                INSERT INTO meldingen (titel, bericht, ontvanger_type, ontvanger_id)
                VALUES ('$titel', '$bericht', 'student', $studentID)
            ");

            $succes = "Examen aangevraagd voor <strong>$studentNaam</strong> (poging $nieuwePoging) op <strong>$datum</strong>.";
        }
    }
}


/* ============================================================
   DATA OPHALEN
   Haal alle gekoppelde studenten op van deze instructeur,
   met hun huidige pogingsnummer en geslaagd-status.
   ============================================================ */
$stmt = $conn->prepare("
    SELECT
        s.studentID,
        s.voornaam,
        s.tussenvoegsel,
        s.achternaam,
        s.transmissie,
        s.beperking,
        s.poging,
        s.geslaagd,
        s.status,
        sl.overige_uren,
        lp.naam AS pakketNaam,
        lp.uren AS pakketUren,
        -- Aantal goedgekeurde lessen
        (SELECT COUNT(*) FROM lessen l
         WHERE l.studentID = s.studentID
           AND l.instructeurID = ?
           AND l.goedgekeurd = 1
           AND l.vervallen = 0) AS goedgekeurdeLeessen,
        -- Totaal gevolgde lessen
        (SELECT COUNT(*) FROM lessen l2
         WHERE l2.studentID = s.studentID
           AND l2.instructeurID = ?
           AND l2.vervallen = 0) AS totaalLessen
    FROM studenten_has_instructeurs shi
    JOIN studenten s        ON shi.studentID    = s.studentID
    LEFT JOIN student_lespakket sl ON sl.studentID = s.studentID
    LEFT JOIN lespakket lp         ON sl.idlespakket = lp.idlespakket
    WHERE shi.instructeurID = ?
    ORDER BY s.achternaam ASC
");
$stmt->bind_param("iii", $instrID, $instrID, $instrID);
$stmt->execute();
$result   = $stmt->get_result();
$studenten = [];
while ($rij = $result->fetch_assoc()) $studenten[] = $rij;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examen aanvragen</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Examenkaart per student */
        .examen-kaart {
            background: white;
            border: 2px solid #d0d8e4;
            border-left: 5px solid #1b2940;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-start;
            justify-content: space-between;
        }

        /* Variant: al geslaagd */
        .examen-kaart.geslaagd {
            border-left-color: #28a745;
            background: #f8fff8;
        }

        /* Student info blok links */
        .examen-student-info { flex: 1; min-width: 200px; }
        .examen-student-info h4 { margin: 0 0 6px 0; font-size: 15px; color: #1b2940; }
        .examen-student-info p  { margin: 2px 0; font-size: 12px; color: #555; }

        /* Stats blok in het midden */
        .examen-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .examen-stat {
            text-align: center;
            background: #f5f7fa;
            border: 1px solid #d0d8e4;
            padding: 8px 14px;
            min-width: 70px;
        }

        .examen-stat .getal {
            font-size: 22px;
            font-weight: bold;
            color: #1b2940;
            line-height: 1;
        }

        .examen-stat .label {
            font-size: 9px;
            color: #888;
            margin-top: 3px;
            text-transform: uppercase;
        }

        /* Poging badges */
        .poging-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 2px;
            margin-right: 4px;
        }
        .poging-0   { background: #e0e0e0; color: #555; }
        .poging-1   { background: #dbeafe; color: #1e40af; }
        .poging-2   { background: #fef3c7; color: #92400e; }
        .poging-3up { background: #fee2e2; color: #991b1b; }

        .badge-geslaagd { background: #d4edda; color: #155724; border: 1px solid #28a745; font-size: 10px; padding: 2px 8px; font-weight: bold; }
        .badge-beperking { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; font-size: 10px; padding: 2px 6px; }

        /* Formulier voor examen aanvragen */
        .examen-form {
            background: #f5f7fa;
            border: 1px solid #d0d8e4;
            border-top: 3px solid #1b2940;
            padding: 14px 16px;
            margin-top: 12px;
            display: none;
            width: 100%;
        }

        .examen-form.open { display: block; }

        .examen-form .form-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .examen-form .form-group {
            flex: 1;
            min-width: 150px;
            margin-bottom: 0;
        }

        /* Knop om formulier te openen/sluiten */
        .btn-examen {
            background: #1b2940;
            color: white;
            border: 2px solid #1b2940;
            padding: 9px 16px;
            font-size: 12px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-examen:hover { background: #243a55; }

        .btn-annuleer-form {
            background: white;
            color: #1b2940;
            border: 2px solid #1b2940;
            padding: 9px 16px;
            font-size: 12px;
            font-family: Arial, sans-serif;
            cursor: pointer;
        }

        /* Lege staat */
        .geen-studenten {
            background: white;
            border: 2px dashed #ccc;
            padding: 30px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="container">

    <?php
    $navActief = 'examen';
    $paginaLabel = 'Examen aanvragen';
    require_once 'instructeur_nav.php';
    ?>

    <!-- ── FEEDBACK ───────────────────────────────────────────── -->
    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>
    <?php if ($fout): ?>
        <div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div>
    <?php endif; ?>


    <!-- ── INTRO ──────────────────────────────────────────────── -->
    <div style="background:white;border:2px solid #1b2940;padding:14px 18px;margin-bottom:16px;font-size:13px;">
        <strong>📋 Examen aanvragen</strong><br>
        <span style="font-size:11px;color:#666;">
            Klik op "Examen aanvragen" bij een student om een examendatum en locatie in te vullen.
            Het pogingsnummer wordt automatisch verhoogd en de student krijgt een melding.
        </span>
    </div>


    <!-- ── STUDENTENLIJST ─────────────────────────────────────── -->
    <?php if (empty($studenten)): ?>
        <div class="geen-studenten">
            📭 Je hebt nog geen gekoppelde studenten.
        </div>

    <?php else: ?>
        <?php foreach ($studenten as $st):
            $volledigeNaam = $st['voornaam']
                . ($st['tussenvoegsel'] ? ' ' . $st['tussenvoegsel'] : '')
                . ' ' . $st['achternaam'];
            $poging        = intval($st['poging'] ?? 0);
            $isGeslaagd    = $st['geslaagd'] == 1;
            $pogingKlasse  = $poging === 0 ? 'poging-0'
                           : ($poging === 1 ? 'poging-1'
                           : ($poging === 2 ? 'poging-2' : 'poging-3up'));
        ?>
        <div class="examen-kaart <?= $isGeslaagd ? 'geslaagd' : '' ?>">

            <!-- Student info -->
            <div class="examen-student-info">
                <h4>
                    <?= htmlspecialchars($volledigeNaam) ?>
                    <?php if ($st['beperking']): ?>
                        <span class="badge-beperking">⚠️ Beperking</span>
                    <?php endif; ?>
                    <?php if ($isGeslaagd): ?>
                        <span class="badge-geslaagd">🏆 Geslaagd</span>
                    <?php endif; ?>
                </h4>
                <p>🚗 <?= ucfirst($st['transmissie']) ?>
                   &nbsp;·&nbsp; 📦 <?= htmlspecialchars($st['pakketNaam'] ?? '—') ?>
                </p>
                <p>
                    <!-- Pogingsnummer badge -->
                    <span class="poging-badge <?= $pogingKlasse ?>">
                        <?= $poging === 0 ? 'Nog geen poging' : "Poging $poging" ?>
                    </span>
                    <?php if ($poging >= 3): ?>
                        <span style="font-size:10px;color:#991b1b;">⚠️ 3 of meer pogingen</span>
                    <?php endif; ?>
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
            </div>

            <!-- Examen knop (niet tonen als al geslaagd) -->
            <?php if (!$isGeslaagd): ?>
            <div>
                <button
                    type="button"
                    class="btn-examen"
                    onclick="toggleForm(<?= $st['studentID'] ?>)"
                >
                    📋 Examen aanvragen
                </button>
            </div>
            <?php endif; ?>

            <!-- Examen formulier (verborgen, verschijnt na klik op knop) -->
            <?php if (!$isGeslaagd): ?>
            <div class="examen-form" id="form_<?= $st['studentID'] ?>">
                <form method="POST" action="examen.php">
                    <input type="hidden" name="studentID" value="<?= $st['studentID'] ?>">

                    <div class="form-row">
                        <!-- Examendatum -->
                        <div class="form-group">
                            <label>📅 Examendatum <span style="color:#dc3545;">*</span></label>
                            <input
                                type="date"
                                name="examDatum"
                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                required
                            >
                        </div>

                        <!-- Locatie -->
                        <div class="form-group">
                            <label>📍 Examenlocatie <span style="color:#dc3545;">*</span></label>
                            <input
                                type="text"
                                name="locatie"
                                placeholder="bv. CBR Utrecht Westraven"
                                maxlength="100"
                                required
                            >
                        </div>
                    </div>

                    <!-- Optionele opmerking -->
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>📝 Opmerking (optioneel)</label>
                        <textarea
                            name="opmerking"
                            placeholder="Extra informatie voor de student..."
                            maxlength="300"
                            style="min-height:60px;"
                        ></textarea>
                    </div>

                    <div class="btn-row">
                        <button type="button" class="btn-annuleer-form" onclick="toggleForm(<?= $st['studentID'] ?>)">
                            Annuleer
                        </button>
                        <button type="submit" class="btn-opslaan">
                            ✅ Bevestig examen aanvraag
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
/**
 * toggleForm — Toont of verbergt het examen-aanvraagformulier
 * voor een specifieke student.
 */
function toggleForm(studentID) {
    const form = document.getElementById('form_' + studentID);
    form.classList.toggle('open');
}
</script>
</body>
</html>