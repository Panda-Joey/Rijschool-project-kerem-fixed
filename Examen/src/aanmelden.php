<?php
require_once dirname(__DIR__) . '/includes/ensure-app.php';

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

$pakketten = [];
$pakketten_result = $conn->query("SELECT idlespakket, naam, uren FROM lespakket ORDER BY naam");
while ($row = $pakketten_result->fetch_assoc()) {
    $pakketten[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam      = trim($_POST['voornaam'] ?? '');
    $tussenvoegsel = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam    = trim($_POST['achternaam'] ?? '');
    $telefoon      = trim($_POST['telefoon'] ?? '');
    $geboortedatum = $_POST['geboortedatum'] ?? '';
    $email         = trim($_POST['email'] ?? '');
    $wachtwoord    = password_hash($_POST['wachtwoord'] ?? '', PASSWORD_DEFAULT);
    $lespakketID   = (int) ($_POST['lespakketID'] ?? 0);
    $beperking     = isset($_POST['beperking']) ? (int) $_POST['beperking'] : 0;
    $transmissie   = trim($_POST['transmissie'] ?? 'schakel');
    if (!in_array($transmissie, ['schakel', 'automaat'], true)) {
        $transmissie = 'schakel';
    }
    
    $omschrijving  = ($beperking === 1 && !empty(trim($_POST['omschrijving'] ?? '')))
        ? trim($_POST['omschrijving'])
        : null;

    $check = $conn->prepare("SELECT studentID FROM studenten WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = 'Dit e-mailadres is al geregistreerd.';
        $messageType = 'error';
    } else {
        require_once dirname(__DIR__) . '/includes/lesvoorkeur.php';
        ensureLesvoorkeurSchema($conn);

        $sql = "INSERT INTO studenten
            (voornaam, tussenvoegsel, achternaam, email, wachtwoord, telefoon, beperking, omschrijving, geboortedatum, status, transmissie)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssisss",
            $voornaam,
            $tussenvoegsel,
            $achternaam,
            $email,
            $wachtwoord,
            $telefoon,
            $beperking,
            $omschrijving,
            $geboortedatum,
            $transmissie
        );

        if ($stmt->execute()) {
            $studentID = $conn->insert_id;

            $sql2 = "INSERT INTO student_lespakket (studentID, idlespakket, overige_uren)
                SELECT ?, idlespakket, uren FROM lespakket WHERE idlespakket = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ii", $studentID, $lespakketID);
            $stmt2->execute();
            $stmt2->close();

            $message = 'Je aanmelding is ontvangen. De rijschoolhouder moet je account eerst activeren.';
            $messageType = 'success';
        } else {
            $message = 'Er ging iets fout bij het opslaan.';
            $messageType = 'error';
        }
        $stmt->close();
    }
    $check->close();
}

$active = 'aanmelden';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aanmelden — <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/login.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include dirname(__DIR__) . '/views/partials/header.php'; ?>

    <main class="page page--with-header aanmelden">
        <div class="card">
            <div class="card-header">
                <h1>Aanmelden</h1>
                <p>Word leerling bij <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="card-body">
                <?php if ($message !== ''): ?>
                    <div class="<?= $messageType === 'success' ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($messageType !== 'success'): ?>
                <form method="post" action="<?= htmlspecialchars(src_url('aanmelden.php'), ENT_QUOTES, 'UTF-8') ?>">

                    <section class="form-section">
                        <p class="form-section__title">Persoonlijke gegevens</p>

                        <div class="field">
                            <label for="voornaam">Voornaam</label>
                            <input type="text" id="voornaam" name="voornaam" required
                                value="<?= htmlspecialchars($_POST['voornaam'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="field">
                            <label for="tussenvoegsel">Tussenvoegsel <span class="form-note">(optioneel)</span></label>
                            <input type="text" id="tussenvoegsel" name="tussenvoegsel"
                                value="<?= htmlspecialchars($_POST['tussenvoegsel'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="field">
                            <label for="achternaam">Achternaam</label>
                            <input type="text" id="achternaam" name="achternaam" required
                                value="<?= htmlspecialchars($_POST['achternaam'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="field">
                            <label for="telefoon">Telefoonnummer</label>
                            <input type="tel" id="telefoon" name="telefoon" required
                                value="<?= htmlspecialchars($_POST['telefoon'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="field">
                            <label for="geboortedatum">Geboortedatum</label>
                            <input type="date" id="geboortedatum" name="geboortedatum" required
                                value="<?= htmlspecialchars($_POST['geboortedatum'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </section>

                    <section class="form-section">
                        <p class="form-section__title">Type auto (Transmissie)</p>
                        <p class="form-note">In wat voor soort auto wil je lestoepassingen volgen?</p>

                        <div class="radio-group">
                            <label>
                                <input type="radio" name="transmissie" value="schakel"
                                    <?= ($_POST['transmissie'] ?? 'schakel') === 'schakel' ? 'checked' : '' ?>>
                                Handgeschakeld
                            </label>
                            <label>
                                <input type="radio" name="transmissie" value="automaat"
                                    <?= ($_POST['transmissie'] ?? '') === 'automaat' ? 'checked' : '' ?>>
                                Automaat
                            </label>
                        </div>
                    </section>

                    <section class="form-section">
                        <p class="form-section__title">Beperking</p>
                        <p class="form-note">Heb je een beperking waar de instructeur rekening mee moet houden?</p>

                        <div class="radio-group">
                            <label>
                                <input type="radio" name="beperking" value="0"
                                    <?= ($_POST['beperking'] ?? '0') === '0' ? 'checked' : '' ?>
                                    onchange="toggleBeperking(false)">
                                Nee
                            </label>
                            <label>
                                <input type="radio" name="beperking" value="1"
                                    <?= ($_POST['beperking'] ?? '') === '1' ? 'checked' : '' ?>
                                    onchange="toggleBeperking(true)">
                                Ja
                            </label>
                        </div>

                        <div id="beperking_details" class="<?= ($_POST['beperking'] ?? '') === '1' ? 'is-visible' : '' ?>">
                            <div class="field">
                                <label for="omschrijving">Toelichting</label>
                                <textarea name="omschrijving" id="omschrijving"
                                    placeholder="Bijvoorbeeld: dyslexie, faalangst..."><?= htmlspecialchars($_POST['omschrijving'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <p class="form-section__title">Account &amp; pakket</p>

                        <div class="field">
                            <label for="email">E-mailadres</label>
                            <input type="email" id="email" name="email" required autocomplete="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="field">
                            <label for="wachtwoord">Wachtwoord</label>
                            <input type="password" id="wachtwoord" name="wachtwoord" required autocomplete="new-password">
                        </div>

                        <div class="field">
                            <label for="lespakketID">Lespakket</label>
                            <select name="lespakketID" id="lespakketID" required>
                                <option value="">— Selecteer een pakket —</option>
                                <?php foreach ($pakketten as $pakket): ?>
                                    <option value="<?= (int) $pakket['idlespakket'] ?>"
                                        <?= (string) ($_POST['lespakketID'] ?? '') === (string) $pakket['idlespakket'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pakket['naam'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= (int) $pakket['uren'] ?> uur)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </section>

                    <button type="submit" class="btn btn-primary">Aanmelden</button>
                </form>
                <?php endif; ?>

                <a href="<?= htmlspecialchars(src_url('homepage.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-link">
                    Terug naar homepage
                </a>
                <a href="<?= htmlspecialchars(login_url(), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-link">
                    Al een account? Inloggen
                </a>
            </div>
        </div>
    </main>

    <script>
        function toggleBeperking(isJa) {
            const detailsDiv = document.getElementById('beperking_details');
            const omschrijvingInput = document.getElementById('omschrijving');

            if (isJa) {
                detailsDiv.classList.add('is-visible');
                omschrijvingInput.required = true;
            } else {
                detailsDiv.classList.remove('is-visible');
                omschrijvingInput.required = false;
                omschrijvingInput.value = '';
            }
        }

        if (document.querySelector('input[name="beperking"][value="1"]:checked')) {
            toggleBeperking(true);
        }
    </script>
</body>
</html>