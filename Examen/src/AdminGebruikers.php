<?php
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


$adminNaam = $_SESSION['naam'] ?? 'Admin';

$pakketten = [];
$pakketResult = $conn->query("SELECT idlespakket, naam, uren FROM lespakket ORDER BY naam");
while ($row = $pakketResult->fetch_assoc()) {
    $pakketten[] = $row;
}

if (isset($_POST['toevoegen']) || isset($_POST['bewerken'])) {
    $voornaam      = trim($_POST['voornaam'] ?? '');
    $tussenvoegsel = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam    = trim($_POST['achternaam'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $wachtwoord    = $_POST['wachtwoord'] ?? '';
    $telefoon      = trim($_POST['telefoon'] ?? '');
    $beperking     = (int) ($_POST['beperking'] ?? 0);
    $omschrijving  = trim($_POST['omschrijving'] ?? '') ?: null;
    $geboortedatum = $_POST['geboortedatum'] ?? date('Y-m-d');
    $status        = $_POST['status'] ?? 'pending';
    $lespakketID   = (int) ($_POST['lespakketID'] ?? 0);

    if (isset($_POST['toevoegen'])) {
        $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO studenten (
                voornaam, tussenvoegsel, achternaam, email, wachtwoord,
                telefoon, beperking, omschrijving, geboortedatum, status
            ) VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "ssssssisss",
            $voornaam,
            $tussenvoegsel,
            $achternaam,
            $email,
            $hash,
            $telefoon,
            $beperking,
            $omschrijving,
            $geboortedatum,
            $status
        );
        $stmt->execute();
        $studentID = (int) $conn->insert_id;
        $stmt->close();

        if ($lespakketID > 0 && $studentID > 0) {
            $stmt2 = $conn->prepare("
                INSERT INTO student_lespakket (studentID, idlespakket, overige_uren)
                SELECT ?, idlespakket, uren FROM lespakket WHERE idlespakket = ?
            ");
            $stmt2->bind_param("ii", $studentID, $lespakketID);
            $stmt2->execute();
            $stmt2->close();
        }
    }

    if (isset($_POST['bewerken'])) {
        $studentID = (int) ($_POST['studentID'] ?? 0);

        if ($wachtwoord !== '') {
            $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE studenten SET
                    voornaam=?, tussenvoegsel=?, achternaam=?, email=?, wachtwoord=?,
                    telefoon=?, beperking=?, omschrijving=?, geboortedatum=?, status=?
                WHERE studentID=?
            ");
            $stmt->bind_param(
                "ssssssisssi",
                $voornaam,
                $tussenvoegsel,
                $achternaam,
                $email,
                $hash,
                $telefoon,
                $beperking,
                $omschrijving,
                $geboortedatum,
                $status,
                $studentID
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE studenten SET
                    voornaam=?, tussenvoegsel=?, achternaam=?, email=?,
                    telefoon=?, beperking=?, omschrijving=?, geboortedatum=?, status=?
                WHERE studentID=?
            ");
            $stmt->bind_param(
                "sssssisssi",
                $voornaam,
                $tussenvoegsel,
                $achternaam,
                $email,
                $telefoon,
                $beperking,
                $omschrijving,
                $geboortedatum,
                $status,
                $studentID
            );
        }
        $stmt->execute();
        $stmt->close();

        if ($lespakketID > 0) {
            $conn->query("DELETE FROM student_lespakket WHERE studentID = $studentID");
            $stmt2 = $conn->prepare("
                INSERT INTO student_lespakket (studentID, idlespakket, overige_uren)
                SELECT ?, idlespakket, uren FROM lespakket WHERE idlespakket = ?
            ");
            $stmt2->bind_param("ii", $studentID, $lespakketID);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

if (isset($_GET['verwijderen'])) {
    $studentID = (int) $_GET['verwijderen'];
    $conn->query("DELETE FROM student_lespakket WHERE studentID = $studentID");
    $stmt = $conn->prepare("DELETE FROM studenten WHERE studentID=?");
    $stmt->bind_param("i", $studentID);
    $stmt->execute();
    $stmt->close();
}

$result = $conn->query("
    SELECT s.*, lp.naam AS lesPakketNaam, lp.idlespakket
    FROM studenten s
    LEFT JOIN student_lespakket sl ON s.studentID = sl.studentID
    LEFT JOIN lespakket lp ON sl.idlespakket = lp.idlespakket
    ORDER BY s.achternaam, s.voornaam
");
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Beheerderspaneel</title>
    <link rel="stylesheet" href="css/AD.css">
</head>
<body>

<div class="container">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="logo-section">
            <h2>👋 <?php echo $adminNaam; ?></h2>
            <span class="badge">Admin</span>
            <p>Rijschool Dashboard</p>
        </div>

        <a href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-btn">
            Uitloggen →
        </a>
    </header>

    <!-- NAVIGATIE -->
    <div class="nav-grid">
        <a href="AdminDashboard.php" class="nav-card">
            Dashboard
        </a>

        <a href="AdminGebruikers.php" class="nav-card active">
            Gebruikers
        </a>

        <a href="#" class="nav-card">
            Rooster
        </a>

        <a href="#" class="nav-card">
            Profiel
        </a>

        <a href="AdminWagenpark.php" class="nav-card">
            Wagenpark
        </a>
    </div>

    <!-- TOEVOEGEN BUTTON -->

    <button class="add-btn" onclick="openAddForm()">
        + Student toevoegen
    </button>

    <!-- FORM -->

    <div class="form-box" id="formBox">

        <form method="POST">

            <input type="hidden" name="studentID" id="studentID">

            <input
            type="text"
            name="voornaam"
            id="voornaam"
            placeholder="Voornaam"
            >

            <input
            type="text"
            name="tussenvoegsel"
            id="tussenvoegsel"
            placeholder="Tussenvoegsel"
            >

            <input
            type="text"
            name="achternaam"
            id="achternaam"
            placeholder="Achternaam"
            >

            <input
            type="email"
            name="email"
            id="email"
            placeholder="Email"
            >

            <input
            type="password"
            name="wachtwoord"
            id="wachtwoord"
            placeholder="Wachtwoord"
            >

            <input
            type="text"
            name="telefoon"
            id="telefoon"
            placeholder="Telefoon"
            >

            <input
            type="text"
            name="beperking"
            id="beperking"
            placeholder="Beperking"
            >

            <textarea
            name="omschrijving"
            id="omschrijving"
            placeholder="Omschrijving"
            ></textarea>

            <label>Geboortedatum</label>

            <input
            type="date"
            name="geboortedatum"
            id="geboortedatum"
            >

            <label>Status</label>
            <select name="status" id="status">
                <option value="pending">Pending</option>
                <option value="actief">Actief</option>
                <option value="geslaagd">Geslaagd</option>
            </select>

            <label>Lespakket</label>
            <select name="lespakketID" id="lespakketID">
                <option value="">— Geen pakket —</option>
                <?php foreach ($pakketten as $pakket): ?>
                    <option value="<?= (int) $pakket['idlespakket'] ?>">
                        <?= htmlspecialchars($pakket['naam'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= (int) $pakket['uren'] ?> uur)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="toevoegen" id="addBtn">
                Opslaan
            </button>

            <button type="submit" name="bewerken" id="editBtn">
                Bewerken
            </button>

        </form>

    </div>

    <!-- TABEL -->

    <table class="student-table">

        <tr>
            <th>Naam</th>
            <th>Email</th>
            <th>Telefoon</th>
            <th>Lespakket</th>
            <th>Acties</th>
        </tr>

        <?php while ($student = $result->fetch_assoc()):
            $studentJson = htmlspecialchars(json_encode([
                'studentID'     => (int) $student['studentID'],
                'voornaam'      => $student['voornaam'],
                'tussenvoegsel' => $student['tussenvoegsel'] ?? '',
                'achternaam'    => $student['achternaam'],
                'email'         => $student['email'],
                'telefoon'      => $student['telefoon'],
                'beperking'     => (int) $student['beperking'],
                'omschrijving'  => $student['omschrijving'] ?? '',
                'geboortedatum' => $student['geboortedatum'],
                'status'        => $student['status'],
                'lespakketID'   => (int) ($student['idlespakket'] ?? 0),
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
        ?>

        <tr>

            <td>
                <?= htmlspecialchars(trim($student['voornaam'] . ' ' . ($student['tussenvoegsel'] ?? '') . ' ' . $student['achternaam'])) ?>
            </td>

            <td>
                <?= htmlspecialchars($student['email']) ?>
            </td>

            <td>
                <?= htmlspecialchars($student['telefoon']) ?>
            </td>

            <td>
                <?= htmlspecialchars($student['lesPakketNaam'] ?? '—') ?>
            </td>

            <td>

                <button
                type="button"
                class="action-btn edit-btn"
                data-student="<?= $studentJson ?>"
                onclick="editStudentFromBtn(this)">
                    Bewerken
                </button>

                <a
                class="action-btn delete-btn"
                href="?verwijderen=<?= $student['studentID']; ?>">
                    Verwijderen
                </a>

            </td>

        </tr>

        <?php endwhile; ?>

    </table>

</div>

<script>

function openAddForm(){

    document.getElementById("formBox")
    .style.display = "block";

    document.getElementById("addBtn")
    .style.display = "inline-block";

    document.getElementById("editBtn")
    .style.display = "none";

    clearForm();
}

function editStudentFromBtn(button){
    editStudent(JSON.parse(button.getAttribute('data-student')));
}

function editStudent(student){

    document.getElementById("formBox")
    .style.display = "block";

    document.getElementById("studentID").value = student.studentID;

    document.getElementById("voornaam").value = student.voornaam;

    document.getElementById("tussenvoegsel").value = student.tussenvoegsel;

    document.getElementById("achternaam").value = student.achternaam;

    document.getElementById("email").value = student.email;

    document.getElementById("wachtwoord").value = "";

    document.getElementById("telefoon").value = student.telefoon;

    document.getElementById("beperking").value = student.beperking;

    document.getElementById("omschrijving").value = student.omschrijving;

    document.getElementById("geboortedatum").value = student.geboortedatum;

    document.getElementById("status").value = student.status;

    document.getElementById("lespakketID").value = student.lespakketID || "";

    document.getElementById("addBtn")
    .style.display = "none";

    document.getElementById("editBtn")
    .style.display = "inline-block";
}

function clearForm(){

    document.getElementById("studentID").value = "";

    document.getElementById("voornaam").value = "";

    document.getElementById("tussenvoegsel").value = "";

    document.getElementById("achternaam").value = "";

    document.getElementById("email").value = "";

    document.getElementById("wachtwoord").value = "";

    document.getElementById("telefoon").value = "";

    document.getElementById("beperking").value = "";

    document.getElementById("omschrijving").value = "";

    document.getElementById("geboortedatum").value = "";

    document.getElementById("status").value = "pending";

    document.getElementById("lespakketID").value = "";
}

</script>

</body>
</html>

