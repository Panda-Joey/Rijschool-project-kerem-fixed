<?php
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$lesID = isset($_GET['lesID']) ? intval($_GET['lesID']) : 0;
$maand = isset($_GET['maand']) ? intval($_GET['maand']) : 5;
$fout  = "";
$rol   = $_SESSION['rol'] ?? '';

// ── Haal les op voor weergave ────────────────────────────────
$lesResult = $conn->query("
    SELECT lessen.*, instructeurs.voornaam, instructeurs.achternaam
    FROM lessen
    JOIN instructeurs ON lessen.instructeurID = instructeurs.instructeurID
    WHERE lesID = $lesID
");
if (!$lesResult || $lesResult->num_rows === 0) die("Les niet gevonden.");
$les = $lesResult->fetch_assoc();

// ── Verwerk formulier ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reden = trim($conn->real_escape_string($_POST['reden'] ?? ''));

    if ($reden === '') {
        $fout = "Vul een reden in voor het annuleren van de les.";
    } else {
        $conn->query("
            UPDATE lessen
            SET vervallen    = 1,
                redenVervalt = '$reden'
            WHERE lesID = $lesID
        ");
        header("Location: index.php?maand=$maand");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Les annuleren</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <?php
    $navActief = 'kalender';
    $paginaLabel = 'Les annuleren';
    if ($rol === 'instructeur') {
        require_once 'instructeur_nav.php';
    } else {
        require_once 'student_nav.php';
    }
    ?>

    <div class="annuleer-form">

        <!-- Les info -->
        <div class="les-info">
            <h4>⚠️ Je staat op het punt deze les te annuleren</h4>
            <p><strong>Les #<?= $lesID ?></strong></p>
            <p>Datum: <?= date('d-m-Y', strtotime($les['lesDatum'])) ?></p>
            <p>Tijd: <?= substr($les['lestijd'],0,5) ?></p>
            <p>Instructeur: <?= htmlspecialchars($les['voornaam'] . ' ' . $les['achternaam']) ?></p>
            <p>Doel: <?= htmlspecialchars($les['doel']) ?></p>
        </div>

        <div class="waarschuwing-banner">
            🔴 Als alle lessen op <?= date('d-m-Y', strtotime($les['lesDatum'])) ?> worden geannuleerd, wordt de dag rood gemarkeerd in de kalender.
        </div>

        <?php if ($fout): ?>
            <div class="fout">⚠️ <?= $fout ?></div>
        <?php endif; ?>

        <form method="POST" action="annuleer.php?lesID=<?= $lesID ?>&maand=<?= $maand ?>" onsubmit="return valideerForm()">

            <div class="form-group">
                <label>
                    Reden voor annulering <span style="color:#dc3545;">*</span>
                </label>
                <textarea
                    name="reden"
                    id="reden"
                    placeholder="Geef een reden op waarom de les wordt geannuleerd..."
                    maxlength="300"
                    oninput="updateCounter()"
                ><?= htmlspecialchars($_POST['reden'] ?? '') ?></textarea>
                <div class="reden-counter"><span id="redenTeller">0</span> / 300</div>
                <div class="verplicht-melding" id="reden-fout">
                    ⚠️ Een reden is verplicht voordat je kunt annuleren.
                </div>
            </div>

            <div class="btn-row">
                <a href="kalender.php?maand=<?= $maand ?>" class="btn-terug">← Terug</a>
                <button type="submit" class="btn-annuleer">🚫 Annuleer les</button>
            </div>

        </form>
    </div>
</div>

<script>
function updateCounter() {
    const ta = document.getElementById('reden');
    document.getElementById('redenTeller').textContent = ta.value.length;
    if (ta.value.trim() !== '') {
        ta.classList.remove('leeg');
        document.getElementById('reden-fout').style.display = 'none';
    }
}

function valideerForm() {
    const reden = document.getElementById('reden');
    if (reden.value.trim() === '') {
        reden.classList.add('leeg');
        document.getElementById('reden-fout').style.display = 'block';
        reden.focus();
        return false;
    }
    return true;
}

updateCounter();
</script>
</body>
</html>