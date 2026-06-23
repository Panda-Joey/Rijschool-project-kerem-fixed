<?php
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/instructeur_afwezigheid.php';

$conn = getDbConnection();
ensureInstructeurAfwezigheidSchema($conn);
$adminNaam = $_SESSION['naam'] ?? 'Admin';
$message = '';
$messageType = '';

$pakketten = [];
$pakketResult = $conn->query("SELECT idlespakket, naam, uren FROM lespakket ORDER BY naam");
while ($row = $pakketResult->fetch_assoc()) {
    $pakketten[] = $row;
}

$instructeurs = [];

$result = $conn->query("
SELECT
    instructeurID,
    CONCAT(voornaam,' ',achternaam,' - ',transmissie) AS naam
FROM instructeurs
ORDER BY voornaam
");

while ($row = $result->fetch_assoc()) {
    $instructeurs[] = $row;
}

if (isset($_POST['toevoegen'])) {
    $transmissie = $_POST['add_transmissie'] ?? 'beide';
    $rol           = $_POST['add_rol'] ?? 'student';
    $voornaam      = trim($_POST['add_voornaam'] ?? '');
    $tussenvoegsel = trim($_POST['add_tussenvoegsel'] ?? '');
    $achternaam    = trim($_POST['add_achternaam'] ?? '');
    $email         = trim($_POST['add_email'] ?? '');
    $wachtwoord    = $_POST['add_wachtwoord'] ?? '';
    $telefoon      = trim($_POST['add_telefoon'] ?? '');
    $omschrijving  = trim($_POST['add_omschrijving'] ?? '') ?: null;

    $fouten = [];
    if ($voornaam === '') {
        $fouten[] = 'Voornaam is verplicht.';
    }
    if ($achternaam === '') {
        $fouten[] = 'Achternaam is verplicht.';
    }
    if ($email === '') {
        $fouten[] = 'E-mail is verplicht.';
    }
    if ($wachtwoord === '') {
        $fouten[] = 'Wachtwoord is verplicht.';
    }

    if ($fouten === []) {
        $check = $conn->prepare(
            "SELECT studentID FROM studenten WHERE email = ?
             UNION
             SELECT instructeurID FROM instructeurs WHERE email = ?"
        );
        $check->bind_param('ss', $email, $email);
        $check->execute();
        $bestaat = $check->get_result()->num_rows > 0;
        $check->close();

        if ($bestaat) {
            $message = 'Dit e-mailadres is al in gebruik.';
            $messageType = 'fout';
        } else {
            $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);

            if ($rol === 'student') {
                $beperking     = (int) ($_POST['add_beperking'] ?? 0);
                $geboortedatum = trim($_POST['add_geboortedatum'] ?? '');
                $lespakketID   = (int) ($_POST['add_lespakket'] ?? 0);
                $statusStudent = 'pending';

                if ($geboortedatum === '') {
                    $message = 'Geboortedatum is verplicht voor studenten.';
                    $messageType = 'fout';
                } else {
                    $stmt = $conn->prepare("
                         INSERT INTO studenten
                        (voornaam, tussenvoegsel, achternaam, email, wachtwoord, telefoon, beperking, omschrijving, geboortedatum, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        'ssssssisss',
                        $voornaam,
                        $tussenvoegsel,
                        $achternaam,
                        $email,
                        $hash,
                        $telefoon,
                        $beperking,
                        $omschrijving,
                        $geboortedatum,
                        $statusStudent
                    );
                    $stmt->execute();
                    $nieuwStudentID = (int) $conn->insert_id;
                    $stmt->close();

                    if ($lespakketID > 0 && $nieuwStudentID > 0) {
                        $stmtPakket = $conn->prepare("
                            INSERT INTO student_lespakket (studentID, idlespakket, overige_uren)
                            SELECT ?, idlespakket, uren FROM lespakket WHERE idlespakket = ?
                        ");
                        $stmtPakket->bind_param('ii', $nieuwStudentID, $lespakketID);
                        $stmtPakket->execute();
                        $stmtPakket->close();
                    }

                    $message = 'Gebruiker succesvol toegevoegd.';
                    $messageType = 'ok';
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO instructeurs
                    (voornaam, tussenvoegsel, achternaam, email, wachtwoord, telefoon, omschrijving, transmissie)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'ssssssss',
                    $voornaam,
                    $tussenvoegsel,
                    $achternaam,
                    $email,
                    $hash,
                    $telefoon,
                    $omschrijving,
                    $transmissie
                );
                $stmt->execute();
                $stmt->close();

                $message = 'Instructeur succesvol toegevoegd.';
                $messageType = 'ok';
            }
        }
    } else {
        $message = implode(' ', $fouten);
        $messageType = 'fout';
    }
}

if (isset($_POST['bewerken'])) {
    $rol           = $_POST['rol'] ?? 'student';
    $userID        = (int) ($_POST['studentID'] ?? 0);
    $vasteInstructeur = (int)($_POST['vaste_instructeur'] ?? 0);
    $voornaam      = trim($_POST['voornaam'] ?? '');
    $tussenvoegsel = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam    = trim($_POST['achternaam'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $telefoon      = trim($_POST['telefoon'] ?? '');
    $wachtwoord    = $_POST['wachtwoord'] ?? '';

    $afwezigheid  = $_POST['afwezigheid'] ?? 'beschikbaar';
    $afwezigVan   = trim($_POST['afwezig_van'] ?? '');
    $afwezigTot   = trim($_POST['afwezig_tot'] ?? '');

    if ($userID <= 0) {
        $message = 'Geen geldige gebruiker geselecteerd.';
        $messageType = 'fout';
    } else {
        if ($rol === 'student') {
            $statusStudent = $_POST['student_status'] ?? 'pending';
            if (!in_array($statusStudent, ['pending', 'actief'], true)) {
                $statusStudent = 'pending';
            }

            if ($wachtwoord !== '') {
                $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE studenten
                    SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, wachtwoord=?, telefoon=?, status=?
                    WHERE studentID=?
                ");
                $stmt->bind_param(
                    'sssssssi',
                    $voornaam,
                    $tussenvoegsel,
                    $achternaam,
                    $email,
                    $hash,
                    $telefoon,
                    $statusStudent,
                    $userID
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE studenten
                    SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, telefoon=?, status=?
                    WHERE studentID=?
                ");
                $stmt->bind_param(
                    'ssssssi',
                    $voornaam,
                    $tussenvoegsel,
                    $achternaam,
                    $email,
                    $telefoon,
                    $statusStudent,
                    $userID
                );
            }

            if ($vasteInstructeur > 0) {

            $insert = $conn->prepare("
             INSERT INTO studenten_has_instructeurs
             (studentID,instructeurID)
             VALUES (?,?)
             ");

             $insert->bind_param("ii", $userID, $vasteInstructeur);
            }

            $stmt->execute();
            $stmt->close();

            $delete = $conn->prepare("
              DELETE FROM studenten_has_instructeurs
              WHERE studentID=?
              ");
             $delete->bind_param("i", $userID);
             $delete->execute();
             $delete->close();

            if ($vasteInstructeur > 0) {

             $insert = $conn->prepare("
             INSERT INTO studenten_has_instructeurs
             (studentID,instructeurID)
             VALUES (?,?)
             ");

              $insert->bind_param(
              "ii",
             $userID,
             $vasteInstructeur
            );

    $insert->execute();
    $insert->close();
}

        } else {
            if ($afwezigheid === 'niet' && ($afwezigVan === '' || $afwezigTot === '')) {
                $message = 'Vul een start- en einddatum in voor de afwezigheidsperiode.';
                $messageType = 'fout';
            } elseif ($afwezigheid === 'niet' && $afwezigVan > $afwezigTot) {
                $message = 'De einddatum moet op of na de startdatum liggen.';
                $messageType = 'fout';
            } else {
                $vanDb = $afwezigheid === 'niet' ? $afwezigVan : null;
                $totDb = $afwezigheid === 'niet' ? $afwezigTot : null;

                if ($wachtwoord !== '') {
                    $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("
                        UPDATE instructeurs
                        SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, wachtwoord=?, telefoon=?,
                            afwezigheid=?, afwezig_van=?, afwezig_tot=?
                        WHERE instructeurID=?
                    ");
                    $stmt->bind_param(
                        'sssssssssi',
                        $voornaam,
                        $tussenvoegsel,
                        $achternaam,
                        $email,
                        $hash,
                        $telefoon,
                        $afwezigheid,
                        $vanDb,
                        $totDb,
                        $userID
                    );
                } else {
                    $stmt = $conn->prepare("
                        UPDATE instructeurs
                        SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, telefoon=?,
                            afwezigheid=?, afwezig_van=?, afwezig_tot=?
                        WHERE instructeurID=?
                    ");
                    $stmt->bind_param(
                        'ssssssssi',
                        $voornaam,
                        $tussenvoegsel,
                        $achternaam,
                        $email,
                        $telefoon,
                        $afwezigheid,
                        $vanDb,
                        $totDb,
                        $userID
                    );
                }

                $stmt->execute();
                $stmt->close();

                if ($afwezigheid === 'niet') {
                    syncInstructeurAfwezigheidslessen($conn, $userID, $afwezigVan, $afwezigTot);
                } else {
                    stelInstructeurBeschikbaar($conn, $userID);
                }

                header('Location: AdminGebruikers.php');
                exit();
            }
        }

        header('Location: AdminGebruikers.php');
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $id = (int) $_GET['id'];

    $check = $conn->prepare('SELECT studentID FROM studenten WHERE studentID = ? LIMIT 1');
    $check->bind_param('i', $id);
    $check->execute();
    $isStudent = $check->get_result()->num_rows > 0;
    $check->close();

    if ($isStudent) {
        $stmtInsKoppel = $conn->prepare('DELETE FROM studenten_has_instructeurs WHERE studentID = ?');
        $stmtInsKoppel->bind_param('i', $id);
        $stmtInsKoppel->execute();
        $stmtInsKoppel->close();

        $stmtPakket = $conn->prepare('DELETE FROM student_lespakket WHERE studentID = ?');
        $stmtPakket->bind_param('i', $id);
        $stmtPakket->execute();
        $stmtPakket->close();

        $stmtLessons = $conn->prepare('DELETE FROM lessen WHERE studentID = ?');
        $stmtLessons->bind_param('i', $id);
        $stmtLessons->execute();
        $stmtLessons->close();

        $stmt = $conn->prepare('DELETE FROM studenten WHERE studentID = ?');
    } else {
        $stmtInsKoppel = $conn->prepare('DELETE FROM studenten_has_instructeurs WHERE instructeurID = ?');
        $stmtInsKoppel->bind_param('i', $id);
        $stmtInsKoppel->execute();
        $stmtInsKoppel->close();

        $stmt = $conn->prepare('DELETE FROM instructeurs WHERE instructeurID = ?');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    header('Location: AdminGebruikers.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Beheerderspaneel</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(src_url('css/AD.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>

<div class="container">

    <header class="topbar">
        <div class="logo-section">
            <h2>👋 <?= htmlspecialchars($adminNaam, ENT_QUOTES, 'UTF-8') ?></h2>
            <span class="badge">Admin</span>
            <p>Rijschool Dashboard</p>
        </div>
        <a href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-btn">Uitloggen →</a>
    </header>

    <div class="nav-grid">
        <a href="AdminDashboard.php" class="nav-card">Dashboard</a>
        <a href="AdminGebruikers.php" class="nav-card active">Gebruikers</a>
        <a href="#" class="nav-card">Rooster</a>
        <a href="#" class="nav-card">Profiel</a>
        <a href="AdminWagenpark.php" class="nav-card">Wagenpark</a>
    </div>

    <div class="schema">
        <?php if ($message !== ''): ?>
            <div class="flash flash-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <button type="button" class="btn-add" onclick="openAddModal()">+ Gebruiker Toevoegen</button>

        <form method="GET" class="filter-form">
            <label for="filter_status">Kies je gebruiker:</label>
            <select name="gebruiker_filter" id="filter_status">
                <option value="">alle studenten</option>
                <option value="actieve-studenten" <?= ($_GET['gebruiker_filter'] ?? '') === 'actieve-studenten' ? 'selected' : '' ?>>actieve studenten</option>
                <option value="niewe-studenten" <?= ($_GET['gebruiker_filter'] ?? '') === 'niewe-studenten' ? 'selected' : '' ?>>aangemelden studenten</option>
                <option value="instructeurs" <?= ($_GET['gebruiker_filter'] ?? '') === 'instructeurs' ? 'selected' : '' ?>>instructeurs</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <table border="1">
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Email</th>
                    <th>Telefoon</th>
                    <th>Lespakket</th>
                    <th>actief</th>
                    <th>Vaste instructeur</th>
                    <th>Examen pogingen</th>
                    <th>beperking</th>
                    <th>omschrijving</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $statusFilter = $_GET['gebruiker_filter'] ?? '';

            if ($statusFilter === 'actieve-studenten') {
                $query = "
                        SELECT s.studentID, s.status, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, s.omschrijving, s.poging,
                        l.naam AS lespakket,
                        i.instructeurID,
                        CONCAT(i.voornaam, ' ', i.achternaam, ' - ', i.transmissie) AS vasteInstructeur
                        FROM studenten s
                        LEFT JOIN student_lespakket sl
                        ON s.studentID = sl.studentID

                        LEFT JOIN lespakket l
                        ON sl.idlespakket = l.idlespakket

                        LEFT JOIN studenten_has_instructeurs shi
                        ON s.studentID = shi.studentID
                        LEFT JOIN instructeurs i
                        ON shi.instructeurID = i.instructeurID
                        WHERE s.status = 'actief'";
            } elseif ($statusFilter === 'niewe-studenten') {
                $query = "
                          SELECT s.studentID, s.status, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, s.omschrijving, s.poging,
                          l.naam AS lespakket,
                          i.instructeurID,
                          CONCAT(i.voornaam,' ',i.achternaam) AS vasteInstructeur
                          FROM studenten s
                          LEFT JOIN student_lespakket sl
                          ON s.studentID = sl.studentID

                          LEFT JOIN lespakket l
                          ON sl.idlespakket = l.idlespakket

                           LEFT JOIN studenten_has_instructeurs shi
                          ON s.studentID = shi.studentID
                          LEFT JOIN instructeurs i
                          ON shi.instructeurID = i.instructeurID
                          WHERE s.status = 'pending'";
            } elseif ($statusFilter === 'instructeurs') {
                $query = 'SELECT instructeurID, voornaam, afwezigheid, afwezig_van, afwezig_tot, tussenvoegsel, achternaam, email, telefoon, omschrijving FROM instructeurs';
            } else {
                  $query = "
    SELECT
        s.studentID,
        s.status,
        s.voornaam,
        s.tussenvoegsel,
        s.achternaam,
        s.email,
        s.telefoon,
        s.beperking,
        s.poging,
        s.omschrijving,
        l.naam AS lespakket,
        i.instructeurID,
        CONCAT(i.voornaam, ' ', i.achternaam) AS vasteInstructeur

    FROM studenten s

    LEFT JOIN student_lespakket sl
        ON s.studentID = sl.studentID

    LEFT JOIN lespakket l
        ON sl.idlespakket = l.idlespakket

    LEFT JOIN studenten_has_instructeurs shi
        ON s.studentID = shi.studentID

    LEFT JOIN instructeurs i
        ON shi.instructeurID = i.instructeurID
    ";}

            $result = $conn->query($query);

            if (!$result) {
                echo "<tr><td colspan='7' style='color:red; font-weight:bold;'>Database Fout: " . htmlspecialchars($conn->error) . '</td></tr>';
            } elseif ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $id = $row['studentID'] ?? $row['instructeurID'] ?? 0;
                    $huidigeRol = ($statusFilter === 'instructeurs') ? 'instructeur' : 'student';
                    $volledigeNaam = trim(($row['voornaam'] ?? '') . ' ' . ($row['tussenvoegsel'] ?? '') . ' ' . ($row['achternaam'] ?? ''));

                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($volledigeNaam) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['telefoon'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['lespakket'] ?? '-') . '</td>';
                    

                   if ($huidigeRol === 'instructeur') {
                      $status = $row['afwezigheid'] ?? 'onbekend';

                      $kleur = match ($status) {
                      'beschikbaar' => 'green',
                      'niet' => 'red',
                       default => 'gray'
                     };

                     $statusTekst = ucfirst($status);
                     if ($status === 'niet' && !empty($row['afwezig_van']) && !empty($row['afwezig_tot'])) {
                         $statusTekst .= ' (' . date('d-m-Y', strtotime($row['afwezig_van']))
                             . ' t/m ' . date('d-m-Y', strtotime($row['afwezig_tot'])) . ')';
                     }

                     echo "<td><span style='color:$kleur'>" . htmlspecialchars($statusTekst) . "</span></td>";
                    } else {
                    $dbStatus = $row['status'] ?? 'pending';
                     echo '<td>' . ($dbStatus === 'actief' ? 'Ja' : 'Nee') . '</td>';
                    }
                    
                    echo '<td>' . htmlspecialchars($row['vasteInstructeur'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($row['poging'] ?? '0') . '</td>';
                    echo '<td>' . (isset($row['beperking']) && $row['beperking'] == 1 ? 'Ja' : 'Nee') . '</td>';
                    echo '<td>' . htmlspecialchars($row['omschrijving'] ?? '-') . '</td>';
                    echo '<td>';
                    echo "  <div class='action-buttons'>";
                    echo "    <button type='button' class='btn-edit' onclick='openEditModal(" . json_encode([
                        'id' => $id,
                        'rol' => $huidigeRol,
                        'voornaam' => $row['voornaam'] ?? '',
                        'tussenvoegsel' => $row['tussenvoegsel'] ?? '',
                        'achternaam' => $row['achternaam'] ?? '',
                        'email' => $row['email'] ?? '',
                        'telefoon' => $row['telefoon'] ?? '',
                        'status' => $row['status'] ?? 'pending',
                        'vasteInstructeur' => $row['instructeurID'] ?? '',
                        'afwezigheid' => $row['afwezigheid'] ?? 'beschikbaar',
                        'afwezig_van' => $row['afwezig_van'] ?? '',
                        'afwezig_tot' => $row['afwezig_tot'] ?? '',
                    ]) . ")'>Bewerken</button>";
                    echo "    <a href='AdminGebruikers.php?action=delete&amp;id=" . $id . "' class='btn-delete' onclick='return confirm(\"Weet je zeker dat je dit wilt verwijderen?\");'>Verwijderen</a>";
                    echo '  </div>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo "<tr><td colspan='7'>Geen resultaten gevonden.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="modal" hidden>
    <div class="modal-content">
        <span class="close-btn" onclick="closeAddModal()">&times;</span>
        <h3>Nieuwe Gebruiker Aanmaken</h3>
        <hr>

        <form method="POST" action="AdminGebruikers.php" class="modal-form">
            <div class="modal-form-group">
                <label for="add_rol">Type Gebruiker:</label>
                <select name="add_rol" id="add_rol" onchange="toggleAddFields()" required>
                    <option value="student">Student</option>
                    <option value="instructeur">Instructeur</option>
                </select>
            </div>

            <div class="modal-form-group">
                <label>Voornaam:</label>
                <input type="text" name="add_voornaam" required>
            </div>

            <div class="modal-form-group">
                <label>Tussenvoegsel:</label>
                <input type="text" name="add_tussenvoegsel">
            </div>

            <div class="modal-form-group">
                <label>Achternaam:</label>
                <input type="text" name="add_achternaam" required>
            </div>

            <div class="modal-form-group">
                <label>Email:</label>
                <input type="email" name="add_email" required>
            </div>

            <div class="modal-form-group">
                <label>Telefoon:</label>
                <input type="text" name="add_telefoon">
            </div>

            <div class="modal-form-group" id="add_transmissie_group" style="display:none;">
            <label>Transmissie:</label>
            <select name="add_transmissie">
            <option value="schakel">Schakel</option>
            <option value="automaat">Automaat</option>
            <option value="beide">Beide</option>
            </select>
            </div>

            <div class="modal-form-group">
                <label>Wachtwoord:</label>
                <input type="password" name="add_wachtwoord" required>
            </div>

            <div id="student_extra_fields">
                <div class="modal-form-group">
                    <label>Geboortedatum:</label>
                    <input type="date" name="add_geboortedatum">
                </div>

                <div class="modal-form-group">
                    <label for="add_lespakket">Kies Lespakket:</label>
                    <select name="add_lespakket" id="add_lespakket">
                        <option value="">-- Geen Pakket --</option>
                        <?php foreach ($pakketten as $pakket): ?>
                            <option value="<?= (int) $pakket['idlespakket'] ?>">
                                <?= htmlspecialchars($pakket['naam'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="add_beperking">Medische Beperking?</label>
                    <select name="add_beperking" id="add_beperking">
                        <option value="0">Nee (Geen)</option>
                        <option value="1">Ja (Medische indicatie)</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label>Omschrijving:</label>
                    <input type="text" name="add_omschrijving" placeholder="omschrijving van je beperking">
                </div>
            </div>

            <button type="submit" name="toevoegen" class="btn-submit">Gebruiker Opslaan</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal" hidden>
    <div class="modal-content">
        <span class="close-btn" onclick="closeEditModal()">&times;</span>
        <h3>Gebruiker Bewerken</h3>
        <hr>

        <form method="POST" action="AdminGebruikers.php" class="modal-form">
            <input type="hidden" name="studentID" id="edit_studentID">
            <input type="hidden" name="rol" id="edit_rol">

            <div class="modal-form-group" id="edit_status_group">
                <label for="edit_status">Actief:</label>
                <select name="student_status" id="edit_status">
                    <option value="actief">Ja — actief</option>
                    <option value="pending">Nee — niet actief (aangemeld)</option>
                </select>
            </div>

            <div class="modal-form-group" id="edit_afwezigheid_group" style="display:none;">
             <label for="edit_afwezigheid">Beschikbaarheid:</label>
             <select name="afwezigheid" id="edit_afwezigheid" onchange="toggleAfwezigPeriode()">
             <option value="beschikbaar">Beschikbaar</option>
             <option value="niet">Niet beschikbaar (periode)</option>
              </select>
            </div>

            <div class="modal-form-group" id="edit_afwezig_periode_group" style="display:none;">
                <label for="edit_afwezig_van">Afwezig vanaf:</label>
                <input type="date" name="afwezig_van" id="edit_afwezig_van">
                <label for="edit_afwezig_tot" style="margin-top:0.5rem;display:block;">Afwezig tot en met:</label>
                <input type="date" name="afwezig_tot" id="edit_afwezig_tot">
                <p style="font-size:0.85rem;color:#64748b;margin-top:0.5rem;">
                    Alleen lessen binnen deze periode worden geannuleerd. Lessen daarna blijven staan.
                </p>
            </div>

            <div class="modal-form-group">
                <label>Voornaam:</label>
                <input type="text" name="voornaam" id="edit_voornaam" required>
            </div>

            <div class="modal-form-group">
                <label>Tussenvoegsel:</label>
                <input type="text" name="tussenvoegsel" id="edit_tussenvoegsel">
            </div>

            <div class="modal-form-group">
                <label>Achternaam:</label>
                <input type="text" name="achternaam" id="edit_achternaam" required>
            </div>

            <div class="modal-form-group">
                <label>Email:</label>
                <input type="email" name="email" id="edit_email" required>
            </div>

            <div class="modal-form-group">
                <label>Telefoon:</label>
                <input type="text" name="telefoon" id="edit_telefoon">
            </div>

            <div class="modal-form-group"  id="edit_vaste_instructeur_group">
                <label>Vaste instructeur</label>

                <select name="vaste_instructeur" id="edit_vaste_instructeur">

                <option value="">Geen</option>

                <?php foreach($instructeurs as $ins): ?>

                <option value="<?= $ins['instructeurID'] ?>">
                <?= htmlspecialchars($ins['naam' ])?>
                </option>

                <?php endforeach; ?>

                </select>

                </div>

            <div class="modal-form-group">
                <label>Wachtwoord (leeg laten om te behouden):</label>
                <input type="password" name="wachtwoord" placeholder="Nieuw wachtwoord...">
            </div>

            <button type="submit" name="bewerken" class="btn-submit">Wijzigingen Opslaan</button>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    var modal = document.getElementById('addModal');
    modal.hidden = false;
    modal.classList.add('show');
    toggleAddFields();
}

function closeAddModal() {
    var modal = document.getElementById('addModal');
    modal.classList.remove('show');
    modal.hidden = true;
}

function toggleAddFields() {
    var rol = document.getElementById('add_rol').value;

    var extraFields = document.getElementById('student_extra_fields');
    var transmissie = document.getElementById('add_transmissie_group');

    if (rol === 'instructeur') {
        extraFields.style.display = 'none';
        transmissie.style.display = 'block';
    } else {
        extraFields.style.display = 'block';
        transmissie.style.display = 'none';
    }
}

function toggleAfwezigPeriode() {
    var isAfwezig = document.getElementById('edit_afwezigheid').value === 'niet';
    document.getElementById('edit_afwezig_periode_group').style.display = isAfwezig ? 'block' : 'none';
}

function openEditModal(data) {
    document.getElementById('edit_studentID').value = data.id;
    document.getElementById('edit_rol').value = data.rol;
    document.getElementById('edit_voornaam').value = data.voornaam;
    document.getElementById('edit_tussenvoegsel').value = data.tussenvoegsel || '';
    document.getElementById('edit_achternaam').value = data.achternaam;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_telefoon').value = data.telefoon || '';

    if (document.getElementById('edit_vaste_instructeur')) {
    document.getElementById('edit_vaste_instructeur').value =
        data.vasteInstructeur || "";
   }

        if (data.rol === 'instructeur') {
        document.getElementById('edit_afwezigheid_group').style.display = 'block';
        document.getElementById('edit_status_group').style.display = 'none';
        document.getElementById('edit_vaste_instructeur_group').style.display = 'none';
        document.getElementById('edit_afwezigheid').value = data.afwezigheid || 'beschikbaar';

        document.getElementById('edit_afwezigheid').value =
            data.afwezigheid || 'beschikbaar';
        document.getElementById('edit_afwezig_van').value = data.afwezig_van || '';
        document.getElementById('edit_afwezig_tot').value = data.afwezig_tot || '';
        toggleAfwezigPeriode();
    } else {
        document.getElementById('edit_status_group').style.display = 'block';
        document.getElementById('edit_afwezigheid_group').style.display = 'none';
        document.getElementById('edit_vaste_instructeur_group').style.display = 'block';
    }

    var statusGroup = document.getElementById('edit_status_group');
    var statusSelect = document.getElementById('edit_status');
    if (data.rol === 'student') {
        statusGroup.style.display = 'block';
        statusSelect.disabled = false;
        statusSelect.value = data.status === 'actief' ? 'actief' : 'pending';
    } else {
        statusGroup.style.display = 'none';
        statusSelect.disabled = true;
    }

    var editModal = document.getElementById('editModal');
    editModal.hidden = false;
    editModal.classList.add('show');
}

function closeEditModal() {
    var editModal = document.getElementById('editModal');
    editModal.classList.remove('show');
    editModal.hidden = true;
}

window.onclick = function(event) {
    var addModal = document.getElementById('addModal');
    var editModal = document.getElementById('editModal');
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
};
</script>

</body>
</html>