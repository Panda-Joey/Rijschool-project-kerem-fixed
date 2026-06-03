<?php
session_start();
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


$adminNaam = $_SESSION['naam'] ?? 'Admin';




if(isset($_POST['toevoegen']) || isset($_POST['bewerken'])){

    // Haal alle velden op en voorkom "Undefined key" waarschuwingen
    $voornaam      = $_POST['voornaam'] ?? '';
    $tussenvoegsel = $_POST['tussenvoegsel'] ?? '';
    $achternaam    = $_POST['achternaam'] ?? '';
    $email         = $_POST['email'] ?? '';
    $wachtwoord    = $_POST['wachtwoord'] ?? ''; // Geen hashing meer
    $telefoon      = $_POST['telefoon'] ?? '';
    $beperking     = $_POST['beperking'] ?? 0;
    $omschrijving  = $_POST['omschrijving'] ?? '';
    $geboortedatum = $_POST['geboortedatum'] ?? '';
    $lesPakket     = $_POST['lesPakket'] ?? '';

    // Voorkom de "cannot be null" error voor de datum
    if (empty($geboortedatum)) {
        // Je kunt hier een foutmelding geven of een standaard datum (bijv. vandaag) gebruiken
        $geboortedatum = date('Y-m-d'); 
    }

    if(isset($_POST['toevoegen'])){
        $stmt = $conn->prepare("
            INSERT INTO studenten (
                voornaam, tussenvoegsel, achternaam, email, wachtwoord,
                telefoon, beperking, omschrijving, geboortedatum, lesPakket
            ) VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("ssssssisss", $voornaam, $tussenvoegsel, $achternaam, $email, $wachtwoord, $telefoon, $beperking, $omschrijving, $geboortedatum, $lesPakket);
        $stmt->execute();
    }

    if(isset($_POST['bewerken'])){
        $studentID = $_POST['studentID'];
        $stmt = $conn->prepare("
            UPDATE studenten SET
                voornaam=?, tussenvoegsel=?, achternaam=?, email=?, wachtwoord=?,
                telefoon=?, beperking=?, omschrijving=?, geboortedatum=?, lesPakket=?
            WHERE studentID=?
        ");
        $stmt->bind_param("ssssssisssi", $voornaam, $tussenvoegsel, $achternaam, $email, $wachtwoord, $telefoon, $beperking, $omschrijving, $geboortedatum, $lesPakket, $studentID);
        $stmt->execute();
    }
}

// Verwijderen en Select queries blijven hetzelfde...
if(isset($_GET['verwijderen'])){
    $stmt = $conn->prepare("DELETE FROM studenten WHERE studentID=?");
    $stmt->bind_param("i", $_GET['verwijderen']);
    $stmt->execute();
}
$result = $conn->query("SELECT * FROM studenten");
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

            <input
            type="text"
            name="lesPakket"
            id="lesPakket"
            placeholder="Lespakket"
            >

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

        <?php while($student = $result->fetch_assoc()): ?>

        <tr>

            <td>
                <?= $student['voornaam']; ?>
                <?= $student['tussenvoegsel']; ?>
                <?= $student['achternaam']; ?>
            </td>

            <td>
                <?= $student['email']; ?>
            </td>

            <td>
                <?= $student['telefoon']; ?>
            </td>

            <td>
                <?= $student['lesPakket']; ?>
            </td>

            <td>

                <button
                class="action-btn edit-btn"

                onclick="editStudent(
                '<?= $student['studentID']; ?>',
                '<?= $student['voornaam']; ?>',
                '<?= $student['tussenvoegsel']; ?>',
                '<?= $student['achternaam']; ?>',
                '<?= $student['email']; ?>',
                '<?= $student['wachtwoord']; ?>',
                '<?= $student['telefoon']; ?>',
                '<?= $student['beperking']; ?>',
                '<?= $student['omschrijving']; ?>',
                '<?= $student['geboortedatum']; ?>',
                '<?= $student['lesPakket']; ?>'
                )">
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

function editStudent(
    id,
    voornaam,
    tussenvoegsel,
    achternaam,
    email,
    wachtwoord,
    telefoon,
    beperking,
    omschrijving,
    geboortedatum,
    lesPakket
){

    document.getElementById("formBox")
    .style.display = "block";

    document.getElementById("studentID").value = id;

    document.getElementById("voornaam").value = voornaam;

    document.getElementById("tussenvoegsel").value = tussenvoegsel;

    document.getElementById("achternaam").value = achternaam;

    document.getElementById("email").value = email;

    document.getElementById("wachtwoord").value = wachtwoord;

    document.getElementById("telefoon").value = telefoon;

    document.getElementById("beperking").value = beperking;

    document.getElementById("omschrijving").value = omschrijving;

    document.getElementById("geboortedatum").value = geboortedatum;

    document.getElementById("lesPakket").value = lesPakket;

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

    document.getElementById("lesPakket").value = "";
}

</script>

</body>
</html>

