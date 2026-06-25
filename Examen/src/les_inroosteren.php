<?php
/* ============================================================
   les_inroosteren.php
   Toegankelijk voor zowel studenten als instructeurs.

   STUDENT:  kiest een datum → ziet tijdslots van vaste instructeur → vult details in
   INSTRUCTEUR: kiest een datum → ziet zijn eigen tijdslots
             → kiest een tijdslot → kiest een leerling + details

   Elke les duurt 2 uur. Tijdslots gaan in stappen van 30 minuten.
   ============================================================ */

require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/autos.php';
require_once dirname(__DIR__) . '/includes/lesvoorkeur.php';
require_once dirname(__DIR__) . '/includes/instructeur_afwezigheid.php';

$conn = getDbConnection();
ensureInstructeurAfwezigheidSchema($conn);

/* --- Sessievariabelen --- */
$rol    = $_SESSION['rol'];
$userID = $_SESSION['userID'];
$naam   = $_SESSION['naam'];

/* --- Feedback --- */
$succes = "";
$fout   = "";

/* --- Vaste dagvolgorde (index 1=Ma ... 7=Zo, index 0 ongebruikt) --- */
$dagNamen = ['', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

/* --- Leerdoelen voor de dropdown --- */
$doelen = [
    'Rotondes', 'Snelweg', 'Parkeren', 'Voorrang', 'Stadsverkeer',
    'Inhalen', 'Noodremmen', 'Spiegels & dode hoek', 'Theorie in praktijk',
];


/* ============================================================
   DATA OPHALEN
   ============================================================ */

ensureAutosAvailabilityColumns($conn);
ensureLesvoorkeurSchema($conn);

$gekoppeldeInstructeur = null;
$transmissie           = 'schakel';
$transmissieInstr      = 'schakel';
$instrAuto             = null;
$studenten             = [];
$autos                 = [];

if ($rol === 'student') {
    $r = $conn->query("SELECT transmissie FROM studenten WHERE studentID = $userID");
    if ($r && $rij = $r->fetch_assoc()) {
        $transmissie = $rij['transmissie'];
    }

    $r = $conn->query("
        SELECT i.instructeurID, i.voornaam, i.achternaam, i.omschrijving, i.transmissie, i.afwezigheid,
               i.afwezig_van, i.afwezig_tot,
               a.autoID, a.merk, a.type, a.kenteken, a.transmissie AS autoTransmissie,
               a.beschikbaar, a.statusReden
        FROM studenten_has_instructeurs shi
        JOIN instructeurs i ON shi.instructeurID = i.instructeurID
        LEFT JOIN instructeur_auto ia ON i.instructeurID = ia.instructeurID
        LEFT JOIN Autos a ON ia.autoID = a.autoID
        WHERE shi.studentID = $userID
        LIMIT 1
    ");
    if ($r && $r->num_rows > 0) {
        $gekoppeldeInstructeur = $r->fetch_assoc();
    }
}

if ($rol === 'instructeur') {
    $r = $conn->query("SELECT transmissie FROM instructeurs WHERE instructeurID = $userID");
    if ($r && $rij = $r->fetch_assoc()) {
        $transmissieInstr = $rij['transmissie'];
    }
    $transmissie = $transmissieInstr;

    $r = $conn->query("
        SELECT a.* FROM instructeur_auto ia
        JOIN Autos a ON ia.autoID = a.autoID
        WHERE ia.instructeurID = $userID
        LIMIT 1
    ");
    if ($r && $r->num_rows > 0) {
        $instrAuto = $r->fetch_assoc();
    }

    $r = $conn->query("
        SELECT s.studentID, s.voornaam, s.tussenvoegsel, s.achternaam, s.transmissie
        FROM studenten_has_instructeurs shi
        JOIN studenten s ON shi.studentID = s.studentID
        WHERE shi.instructeurID = $userID AND s.status = 'actief'
        ORDER BY s.achternaam
    ");
    while ($rij = $r->fetch_assoc()) {
        $studenten[] = $rij;
    }
}

if ($rol === 'instructeur' && $instrAuto) {
    $autos[] = $instrAuto;
} elseif ($rol === 'instructeur') {
    $r = $conn->query("SELECT * FROM Autos ORDER BY beschikbaar DESC, merk, type");
    while ($rij = $r->fetch_assoc()) {
        $autos[] = $rij;
    }
}

/* Gekozen datum: uit URL (?datum=...) of POST of morgen als standaard */
$gekozenDatum = $_GET['datum'] ?? $_POST['lesDatum'] ?? date('Y-m-d', strtotime('+1 day'));
$dagNr        = date('N', strtotime($gekozenDatum)); // 1=Ma ... 7=Zo
$dagNaam      = $dagNamen[$dagNr];


/* ============================================================
   HULPFUNCTIE: bouwSlotsVoorInstructeur()
   Geeft voor één instructeur alle tijdslots terug op een datum.
   Een slot is 'bezet' als er al een les is die overlapt (2 uur).
   ============================================================ */
function bouwSlotsVoorInstructeur(mysqli $conn, int $iID, string $datum, string $dagNaam): array
{
    /* Stap 1: beschikbaarheid van de instructeur op die dag */
    $res  = $conn->query("
        SELECT beginTijd, eindTijd, maxLessen FROM beschikbaarheid
        WHERE instructeurID = $iID AND dagNaam = '$dagNaam'
        LIMIT 1
    ");
    $bRow = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    /* Geen beschikbaarheid = geen slots */
    if (!$bRow) {
        return ['beschikbaar' => false, 'slots' => [], 'nogVrij' => 0,
                'maxLessen' => 0, 'begin' => null, 'eind' => null];
    }

    /* Stap 2: al geplande (bezette) starttijden op die dag ophalen */
    $bezet  = [];
    $bezRes = $conn->query("
        SELECT lestijd FROM lessen
        WHERE instructeurID = $iID AND lesDatum = '$datum' AND vervallen = 0
    ");
    while ($b = $bezRes->fetch_assoc()) {
        $bezet[] = substr($b['lestijd'], 0, 5);
    }

    /* Stap 3: mogelijke starttijden genereren.
       Stap = 120 min (2 uur), zodat elk lesblok exact aansluit op het
       vorige zonder overlap: 08:00-10:00, 10:00-12:00, 12:00-14:00, ...
       Dit komt overeen met de vaste 2-uurs-blokken in beschikbaarheid.php. */
    $bMin  = intval(substr($bRow['beginTijd'], 0, 2)) * 60 + intval(substr($bRow['beginTijd'], 3, 2));
    $eMin  = intval(substr($bRow['eindTijd'],  0, 2)) * 60 + intval(substr($bRow['eindTijd'],  3, 2));
    $slots = [];

    for ($m = $bMin; $m + 120 <= $eMin; $m += 120) {
        $startTijd = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $eindTijd  = sprintf('%02d:%02d', intdiv($m + 120, 60), ($m + 120) % 60);

        /* Controleer of dit blok overlapt met een bestaande les */
        $overlap = false;
        foreach ($bezet as $b) {
            $bM = intval(substr($b, 0, 2)) * 60 + intval(substr($b, 3, 2));
            /* Overlap: bestaande les start vóór einde blok EN eindigt na start blok */
            if ($bM < $m + 120 && $bM + 120 > $m) {
                $overlap = true;
                break;
            }
        }

        $slots[] = ['tijd' => $startTijd, 'eind' => $eindTijd, 'bezet' => $overlap];
    }

    return [
        'beschikbaar' => true,
        'begin'       => substr($bRow['beginTijd'], 0, 5),
        'eind'        => substr($bRow['eindTijd'],  0, 5),
        'maxLessen'   => $bRow['maxLessen'],
        'nogVrij'     => max(0, $bRow['maxLessen'] - count($bezet)),
        'slots'       => $slots,
    ];
}

/* Bouw de instrData array: slots voor elke relevante instructeur */
$instrData = [];

if ($rol === 'instructeur') {
    $instrData[$userID] = bouwSlotsVoorInstructeur($conn, $userID, $gekozenDatum, $dagNaam);
    $instrData[$userID]['naam']        = $naam;
    $instrData[$userID]['transmissie'] = $transmissieInstr;
} elseif ($gekoppeldeInstructeur) {
    $iID = (int) $gekoppeldeInstructeur['instructeurID'];
    $instrData[$iID] = bouwSlotsVoorInstructeur($conn, $iID, $gekozenDatum, $dagNaam);
    $instrData[$iID]['naam']        = $gekoppeldeInstructeur['voornaam'] . ' ' . $gekoppeldeInstructeur['achternaam'];
    $instrData[$iID]['transmissie'] = $gekoppeldeInstructeur['transmissie'];
    $instrData[$iID]['omschrijving']= $gekoppeldeInstructeur['omschrijving'] ?? '';
    $instrData[$iID]['autoNaam']    = !empty($gekoppeldeInstructeur['autoID'])
        ? $gekoppeldeInstructeur['merk'] . ' ' . $gekoppeldeInstructeur['type'] . ' (' . $gekoppeldeInstructeur['kenteken'] . ')'
        : '';
    $instrData[$iID]['autoID']      = $gekoppeldeInstructeur['autoID'] ?? null;
}


/* ============================================================
   FORMULIER VERWERKEN
   Wordt uitgevoerd als op "Opslaan" geklikt wordt.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Invoer ophalen en schoonmaken */
    $datum       = $conn->real_escape_string($_POST['lesDatum']);
    $tijd        = $conn->real_escape_string($_POST['lestijd']);
    $instrID     = intval($_POST['instructeurID']);
    $studentID   = ($rol === 'instructeur') ? intval($_POST['studentID']) : $userID;
    $autoID      = intval($_POST['autoID']);
    $ophaal      = $conn->real_escape_string(trim($_POST['ophaalLocatie']));
    $doel        = $conn->real_escape_string(trim($_POST['doel']));
    $onderwerpen = $conn->real_escape_string(trim($_POST['onderwerpen'] ?? ''));

    $studentVerplichteVelden = !$datum || !$tijd || !$instrID || !$studentID || !$ophaal;
    $instructeurVerplichteVelden = !$autoID || !$doel;

    if ($studentVerplichteVelden || ($rol === 'instructeur' && $instructeurVerplichteVelden)) {
        $fout = "Vul alle verplichte velden in.";
    } elseif ($rol === 'instructeur' && $instrAuto && $autoID !== (int) $instrAuto['autoID']) {
        $fout = 'Je mag alleen les geven in je vaste lesauto.';
    } elseif ($rol === 'instructeur' && $autoID) {
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

    if (!$fout) {
        $afwRes = $conn->query("SELECT afwezigheid, afwezig_van, afwezig_tot FROM instructeurs WHERE instructeurID = $instrID LIMIT 1");
        if ($afwRes && ($afwRow = $afwRes->fetch_assoc()) && instructeurIsAfwezigOpDatum($afwRow, $datum)) {
            $periode = '';
            if (!empty($afwRow['afwezig_van']) && !empty($afwRow['afwezig_tot'])) {
                $periode = ' (afwezig t/m ' . date('d-m-Y', strtotime($afwRow['afwezig_tot'])) . ')';
            }
            $fout = 'De instructeur is niet beschikbaar op deze datum' . $periode . '.';
        }
    }

    if (!$fout) {
        $dNm = $dagNamen[date('N', strtotime($datum))];

        /* Beschikbaarheid van instructeur op die dag */
        $bRes = $conn->query("
            SELECT * FROM beschikbaarheid
            WHERE instructeurID = $instrID AND dagNaam = '$dNm'
        ");

        if (!$bRes || $bRes->num_rows === 0) {
            $fout = "De instructeur is niet beschikbaar op $dNm.";

        } else {
            $bRow = $bRes->fetch_assoc();
            $sMin = intval(substr($tijd, 0, 2)) * 60 + intval(substr($tijd, 3, 2));
            $bMin = intval(substr($bRow['beginTijd'], 0, 2)) * 60 + intval(substr($bRow['beginTijd'], 3, 2));
            $eMin = intval(substr($bRow['eindTijd'],  0, 2)) * 60 + intval(substr($bRow['eindTijd'],  3, 2));

            /* Tijdstip buiten beschikbaarheidvenster? */
            if ($sMin < $bMin || $sMin + 120 > $eMin) {
                $fout = "Dit tijdstip valt buiten de beschikbaarheid ({$bRow['beginTijd']}–{$bRow['eindTijd']}).";

            } elseif (($sMin - $bMin) % 120 !== 0) {
                /* Beveiliging: het tijdstip moet exact op een 2-uurs-blok-grens
                   vallen (08:00, 10:00, 12:00...), niet op een willekeurig
                   moment ertussenin. Dit voorkomt manipulatie via de browser. */
                $fout = "Ongeldig tijdstip. Lessen starten altijd op een vast 2-uurs blok vanaf {$bRow['beginTijd']}.";

            } else {
                /* Overlapt het gekozen slot met een bestaande les? */
                $ovRes   = $conn->query("
                    SELECT lestijd FROM lessen
                    WHERE instructeurID = $instrID AND lesDatum = '$datum' AND vervallen = 0
                ");
                $overlap = false;
                while ($ov = $ovRes->fetch_assoc()) {
                    $oMin = intval(substr($ov['lestijd'], 0, 2)) * 60 + intval(substr($ov['lestijd'], 3, 2));
                    if ($oMin < $sMin + 120 && $oMin + 120 > $sMin) {
                        $overlap = true;
                        break;
                    }
                }

                /* Maximum aantal lessen al bereikt? */
                $aantalLessen = $conn->query("
                    SELECT COUNT(*) AS n FROM lessen
                    WHERE instructeurID = $instrID AND lesDatum = '$datum' AND vervallen = 0
                ")->fetch_assoc()['n'];

                if ($overlap) {
                    $eindStr = sprintf('%02d:%02d', intdiv($sMin + 120, 60), ($sMin + 120) % 60);
                    $fout    = "Overlap: instructeur heeft al een les die botst met {$tijd}–{$eindStr}.";

                } elseif ($aantalLessen >= $bRow['maxLessen']) {
                    $fout = "De instructeur heeft al het maximale aantal lessen op $datum.";

                } else {
    /* Alles klopt: les aanmaken via een veilige prepared statement */
    $stmt = $conn->prepare("
        INSERT INTO lessen 
            (lesDatum, lestijd, ophaalLocatie, doel, onderwerpen, studentID, instructeurID, autoID, vervallen) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    if ($stmt) {
        $tijdFormatted = $tijd . ':00';
        
            // Kopppelen van de variabelen aan de vraagtekens (?)
            // s = string (tekst/datum), i = integer (getal)
            $stmt->bind_param("sssssiii", $datum, $tijdFormatted, $ophaal, $doel, $onderwerpen, $studentID, $instrID, $autoID);
            
            try {
                $stmt->execute();
                
                $door   = ($rol === 'instructeur') ? "door jou ingepland" : "aangevraagd";
                $succes = "Les $door op <strong>$datum om $tijd</strong>! <a href='" . htmlspecialchars(srcDashboardPath(), ENT_QUOTES, 'UTF-8') . "'>Naar dashboard →</a>";
            } catch (mysqli_sql_exception $e) {
                // Hiermee vang je de Foreign Key crash op en toon je een nette foutmelding aan de gebruiker
                $fout = "Fout bij opslaan: Het studentID ($studentID) of instructeurID ($instrID) bestaat niet in de database. Controleer of je correct bent ingelogd.";
            }
            
            $stmt->close();
        } else {
            $fout = "Databasefout: Kon de database-query niet voorbereiden.";
        }
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
    <title><?= $rol === 'instructeur' ? 'Les inplannen' : 'Nieuwe les aanvragen' ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <?php
    if ($rol === 'instructeur') {
        $navActief = '';
        $paginaLabel = 'Les inplannen';
        require_once 'instructeur_nav.php';
    } else {
        $navActief = 'les_inroosteren';
        $paginaLabel = 'Nieuwe les aanvragen';
        require_once 'student_nav.php';
    }
    ?>

    <!-- ── FEEDBACK ───────────────────────────────────────────── -->
    <?php if ($succes): ?>
        <div class="succes">✅ <?= $succes ?></div>
    <?php endif; ?>

    <div class="inrooster-wrap">

        <!-- ── STAPPENBALK ──────────────────────────────────────
             3 stappen: Datum → Tijdstip → Details
        ──────────────────────────────────────────────────────── -->
        <div class="stappen">
            <div class="stap actief" id="stap1"><span class="nr">1</span>Datum</div>
            <div class="stap" id="stap2"><span class="nr">2</span>Tijdstip</div>
            <div class="stap" id="stap3"><span class="nr">3</span>Details</div>
        </div>


        <!-- ── STAP 1: DATUM KIEZEN ─────────────────────────── -->
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
                    <?= $dagNaam ?>, <?= date('d-m-Y', strtotime($gekozenDatum)) ?>
                </div>
            </div>
        </div>


        <!-- ── STAP 2 (INSTRUCTEUR-MODUS): EIGEN TIJDSLOTS ──── -->
        <?php if ($rol === 'instructeur'):
            $myData = $instrData[$userID];
        ?>
        <div style="margin-bottom:12px;">
            <?php if (!$myData['beschikbaar']): ?>
                <!-- Geen beschikbaarheid ingesteld voor deze dag -->
                <div class="geen-lessen">
                    ⚠️ Je hebt geen beschikbaarheid op <strong><?= $dagNaam ?></strong>.
                    <a href="beschikbaarheid.php" style="color:#1b2940;font-weight:bold;">
                        Stel je rooster in →
                    </a>
                </div>

            <?php elseif ($myData['nogVrij'] <= 0): ?>
                <!-- Dag zit vol -->
                <div class="fout">🚫 Je rooster op <?= $dagNaam ?> is al vol.</div>

            <?php else: ?>
                <!-- Tijdslots weergeven -->
                <div class="instr-kaart" style="cursor:default;border-color:#1b2940;">
                    <div class="instr-naam">
                        🎓 <?= htmlspecialchars($naam) ?>
                        <span style="font-size:10px;color:#28a745;margin-left:8px;">— Jouw beschikbaarheid</span>
                    </div>
                    <div class="instr-meta">
                        ⏰ <?= $myData['begin'] ?> – <?= $myData['eind'] ?>
                        &nbsp;·&nbsp; Nog <?= $myData['nogVrij'] ?> plek(ken) vrij
                        &nbsp;·&nbsp; Elke les = 2 uur
                    </div>

                    <!-- Gekleurde blokjes: groen = vrij, rood = bezet -->
                    <div class="plek-balk">
                        <?php for ($p = 1; $p <= $myData['maxLessen']; $p++): ?>
                            <div class="plek-blok <?= $p <= ($myData['maxLessen'] - $myData['nogVrij']) ? 'bezet' : 'vrij' ?>"></div>
                        <?php endfor; ?>
                    </div>

                    <!-- Klikbare tijdslot knoppen -->
                    <div style="font-size:11px;color:#555;margin:8px 0 4px;">Kies een tijdstip:</div>
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


        <!-- ── STAP 2 (STUDENT): TIJDSLOTS VASTE INSTRUCTEUR ──── -->
        <?php elseif ($rol === 'student'): ?>
        <div style="margin-bottom:12px;">
            <?php if (!$gekoppeldeInstructeur): ?>
                <div class="geen-lessen">Je hebt nog geen vaste instructeur. Neem contact op met de rijschool.</div>
            <?php else:
                if (instructeurIsAfwezigOpDatum($gekoppeldeInstructeur, $gekozenDatum)):
                    $tot = $gekoppeldeInstructeur['afwezig_tot'] ?? '';
                    $periodeTekst = $tot ? ' tot ' . date('d-m-Y', strtotime($tot)) : '';
                ?>
                <div class="geen-lessen">Je instructeur is afwezig op deze datum<?= htmlspecialchars($periodeTekst) ?>. Kies een andere dag of wacht tot de instructeur weer beschikbaar is.</div>
            <?php else:
                $iID  = (int) $gekoppeldeInstructeur['instructeurID'];
                $data = $instrData[$iID] ?? ['beschikbaar' => false, 'slots' => [], 'nogVrij' => 0];
                $vol  = empty($data['beschikbaar']) || ($data['nogVrij'] ?? 0) <= 0;
                $tKleur = $transmissie === 'automaat' ? '#3b82f6' : '#1b2940';
            ?>
                <div style="font-size:12px;color:#555;margin-bottom:8px;">
                    Jouw instructeur op <strong><?= $dagNaam ?></strong>
                    <span style="margin-left:8px;padding:2px 8px;background:<?= $tKleur ?>;color:white;font-size:10px;border-radius:4px;">
                        <?= lesvoorkeurLabel($transmissie) ?>
                    </span>
                </div>
                <div class="instr-kaart <?= $vol ? 'vol' : '' ?>" id="instrKaart_<?= $iID ?>">
                    <div class="instr-naam">
                        👤 <?= htmlspecialchars($data['naam'] ?? '') ?>
                        <?php if (empty($data['beschikbaar'])): ?>
                            <span style="color:#999;font-size:10px;"> – niet beschikbaar op <?= $dagNaam ?></span>
                        <?php elseif (($data['nogVrij'] ?? 0) <= 0): ?>
                            <span style="color:#dc3545;font-size:10px;"> – VOL</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($gekoppeldeInstructeur['omschrijving'])): ?>
                        <div class="instr-omschr"><?= htmlspecialchars($gekoppeldeInstructeur['omschrijving']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($data['autoNaam'])): ?>
                        <div class="instr-meta">🚗 Lesauto: <?= htmlspecialchars($data['autoNaam']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($data['beschikbaar']) && !$vol): ?>
                        <div class="instr-meta">
                            🕐 <?= $data['begin'] ?> – <?= $data['eind'] ?>
                            &nbsp;·&nbsp; Nog <?= $data['nogVrij'] ?> plek(ken) vrij
                        </div>
                        <div class="slot-knoppen" id="slots_<?= $iID ?>">
                            <?php foreach ($data['slots'] as $slot): ?>
                                <button type="button" class="slot-knop <?= $slot['bezet'] ? 'bezet' : '' ?>"
                                    <?= $slot['bezet'] ? 'disabled' : '' ?>
                                    onclick="kiesTijdslotStudent(<?= $iID ?>, '<?= htmlspecialchars($data['naam'] ?? '') ?>', '<?= $slot['tijd'] ?>', '<?= $slot['eind'] ?>')"
                                ><?= $slot['tijd'] ?>-<?= $slot['eind'] ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; endif; ?>
        </div>

        <?php endif; ?>


        <!-- ── STAP 3: DETAILFORMULIER ────────────────────────
             Verborgen totdat een tijdslot gekozen is.
             Wordt zichtbaar via JS na klikken op een slot-knop.
        ──────────────────────────────────────────────────────── -->
        <div class="formulier-sectie" id="formulierSectie">

            <!-- Samenvatting van de gemaakte keuzes -->
            <div class="keuze-samenvatting">
                <div><strong id="samDatum">—</strong>Datum</div>
                <?php if ($rol === 'student'): ?>
                    <div><strong id="samInstr">—</strong>Instructeur</div>
                <?php endif; ?>
                <div><strong id="samTijd">—</strong>Tijdstip (2 uur)</div>
            </div>

            <form method="POST" action="les_inroosteren.php" onsubmit="return valideerForm()">

                <!-- Hidden: datum, tijd en instructeurID worden via JS ingevuld -->
                <input type="hidden" name="lesDatum"      id="hiddenDatum" value="<?= htmlspecialchars($gekozenDatum) ?>">
                <input type="hidden" name="lestijd"       id="hiddenTijd">
                <input type="hidden" name="instructeurID" id="hiddenInstr" value="<?= $rol === 'instructeur' ? $userID : ($gekoppeldeInstructeur['instructeurID'] ?? '') ?>">

                <!-- Voor welke leerling? (alleen zichtbaar voor instructeur) -->
                <?php if ($rol === 'instructeur'): ?>
                <div class="form-group">
                    <label>👤 Voor welke leerling? <span style="color:#dc3545;">*</span></label>
                    <select name="studentID" required>
                        <option value="">— Kies een leerling —</option>
                        <?php foreach ($studenten as $st):
                            if (!studentMatchesInstructeur($st['transmissie'] ?? 'schakel', $transmissieInstr)) {
                                continue;
                            }
                            $tv  = $st['tussenvoegsel'] ? $st['tussenvoegsel'] . ' ' : '';
                            $vol = $st['voornaam'] . ' ' . $tv . $st['achternaam'];
                            $badge = lesvoorkeurLabel($st['transmissie'] ?? 'schakel');
                        ?>
                            <option value="<?= $st['studentID'] ?>"
                                <?= (isset($_POST['studentID']) && $_POST['studentID'] == $st['studentID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vol) ?> (<?= $badge ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Ophaallocatie -->
                <div class="form-group">
                    <label>📍 Ophaallocatie <span style="color:#dc3545;">*</span></label>
                    <input
                        type="text"
                        name="ophaalLocatie"
                        placeholder="bv. Rotterdam Centrum, Delft Station..."
                        value="<?= htmlspecialchars($_POST['ophaalLocatie'] ?? '') ?>"
                        maxlength="100"
                        required
                    >
                </div>

                <!-- Leerdoel: alleen instructeur kiest dit -->
                <?php if ($rol === 'instructeur'): ?>
                <div class="form-group">
                    <label>🎯 Leerdoel <span style="color:#dc3545;">*</span></label>
                    <select name="doel" required>
                        <option value="">— Kies een onderwerp —</option>
                        <?php foreach ($doelen as $d): ?>
                            <option value="<?= $d ?>" <?= (($_POST['doel'] ?? '') === $d) ? 'selected' : '' ?>>
                                <?= $d ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <!-- Student geeft geen leerdoel op: instructeur bepaalt dit -->
                    <input type="hidden" name="doel" value="Nader te bepalen door instructeur">
                <?php endif; ?>

                <!-- Extra opmerkingen (optioneel) -->
                <div class="form-group">
                    <label>📝 Extra opmerkingen</label>
                    <textarea name="onderwerpen" placeholder="Wat wil je specifiek oefenen?" maxlength="255"
                    ><?= htmlspecialchars($_POST['onderwerpen'] ?? '') ?></textarea>
                </div>

                <!-- Auto: instructeur = vaste lesauto, student = auto van instructeur -->
                <?php if ($rol === 'instructeur'): ?>
                    <?php if ($instrAuto): ?>
                    <div class="form-group">
                        <label>🚗 Jouw lesauto</label>
                        <p style="margin:0;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                            <?= htmlspecialchars(autoLabel($instrAuto)) ?>
                        </p>
                        <input type="hidden" name="autoID" value="<?= (int) $instrAuto['autoID'] ?>">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>🚗 Auto <span style="color:#dc3545;">*</span></label>
                        <select name="autoID" required>
                            <option value="">— Kies een auto —</option>
                            <?php foreach ($autos as $auto):
                                $magKiezen = (int) ($auto['beschikbaar'] ?? 1) === 1;
                                $label = autoLabel($auto);
                            ?>
                            <option value="<?= (int) $auto['autoID'] ?>"<?= $magKiezen ? '' : ' disabled' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint" style="margin-top:0.35rem;font-size:0.9rem;color:#64748b;">
                            Stel je lestype in via <strong>Rooster</strong> om een vaste auto te koppelen.
                        </p>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                    $studentAutoID = (int) ($gekoppeldeInstructeur['autoID'] ?? 0);
                    if ($studentAutoID > 0): ?>
                        <input type="hidden" name="autoID" value="<?= $studentAutoID ?>">
                    <?php else: ?>
                        <input type="hidden" name="autoID" value="0">
                    <?php endif; ?>
                <?php endif; ?>

                                <?php if ($fout): ?>
                    <div class="fout">⚠️ <?= htmlspecialchars($fout) ?></div>
                <?php endif; ?>

                <div class="btn-row">
                    <button type="button" class="btn-terug" onclick="resetFormulier()">← Terug</button>
                    <button type="submit" class="btn-opslaan">
                        <?= $rol === 'instructeur' ? '📋 Les inplannen' : '✅ Les inplannen' ?>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /inrooster-wrap -->
</div><!-- /container -->


<script>
/* ============================================================
   JAVASCRIPT — les_inroosteren.php
   ============================================================ */

const rolIsInstructeur = <?= $rol === 'instructeur' ? 'true' : 'false' ?>;
const aantalStappen    = 3;
const defaultInstrId   = <?= (int) ($rol === 'instructeur' ? $userID : ($gekoppeldeInstructeur['instructeurID'] ?? 0)) ?>;

/**
 * laadPagina — Herlaadt de pagina met de nieuw gekozen datum.
 * Zo worden de beschikbare slots opnieuw berekend in PHP.
 */
function laadPagina() {
    const datum = document.getElementById('datumPicker').value;
    if (datum) window.location.href = 'les_inroosteren.php?datum=' + datum;
}

/**
 * kiesTijdslot — Wordt gebruikt door de instructeur.
 * Slaat het gekozen tijdstip op en toont het detailformulier.
 */
function kiesTijdslot(tijd, eind) {
    markeerSlotKnop(tijd);
    toonFormulier(tijd, eind);
    setStap(3);
}

/**
 * kiesTijdslotStudent — Wordt gebruikt door de student.
 * Slaat zowel de instructeur als het tijdstip op en toont het formulier.
 */
function kiesTijdslotStudent(iID, instrNaam, tijd, eind) {
    /* Markeer de instructeurskaart */
    document.querySelectorAll('.instr-kaart').forEach(k => k.classList.remove('gekozen'));
    document.getElementById('instrKaart_' + iID)?.classList.add('gekozen');

    /* Sla instructeurID op en toon naam in samenvatting */
    document.getElementById('hiddenInstr').value = iID;
    const samInstr = document.getElementById('samInstr');
    if (samInstr) samInstr.textContent = instrNaam;

    markeerSlotKnop(tijd);
    toonFormulier(tijd, eind);
    setStap(3);
}

/**
 * markeerSlotKnop — Markeert de geklekte slot-knop als actief.
 */
function markeerSlotKnop(tijd) {
    document.querySelectorAll('.slot-knop').forEach(b => b.classList.remove('actief'));
    document.querySelectorAll('.slot-knop').forEach(b => {
        if (b.textContent.trim().startsWith(tijd)) b.classList.add('actief');
    });
}

/**
 * toonFormulier — Vult de samenvatting en hidden inputs in,
 * maakt het detailformulier zichtbaar en scrollt ernaartoe.
 */
function toonFormulier(tijd, eind) {
    const datum = document.getElementById('datumPicker').value;

    /* Samenvatting bovenin formulier bijwerken */
    document.getElementById('samDatum').textContent = datum;
    document.getElementById('samTijd').textContent  = tijd + '–' + eind;

    /* Hidden inputs vullen zodat het formulier de juiste waarden verstuurt */
    document.getElementById('hiddenDatum').value = datum;
    document.getElementById('hiddenTijd').value  = tijd;

    /* Formulier tonen en scrollen */
    document.getElementById('formulierSectie').classList.add('zichtbaar');
    document.getElementById('formulierSectie').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * resetFormulier — Verbergt het formulier en wist alle selecties.
 * Wordt aangeroepen door de "← Terug" knop.
 */
function resetFormulier() {
    document.getElementById('formulierSectie').classList.remove('zichtbaar');
    document.querySelectorAll('.instr-kaart').forEach(k => k.classList.remove('gekozen'));
    document.querySelectorAll('.slot-knop').forEach(b => b.classList.remove('actief'));
    document.getElementById('hiddenTijd').value = '';
    document.getElementById('hiddenInstr').value = defaultInstrId || '';
    setStap(1);
}

/**
 * setStap — Werkt de stappenbalk bij.
 * Voltooide stappen worden groen, de huidige stap blauw.
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
 * valideerForm — Controleert of tijdstip (en instructeur) gekozen zijn
 * voordat het formulier verstuurd wordt.
 */
function valideerForm() {
    if (!document.getElementById('hiddenTijd').value) {
        alert('Kies eerst een tijdstip.');
        return false;
    }
    return true;
}
</script>
</body>
</html>