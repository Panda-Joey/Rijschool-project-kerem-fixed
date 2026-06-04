<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$sessionStarted = session_status() !== PHP_SESSION_NONE;

$servername = "mysql";          
$username = "root";             
$password = "password";         
$dbname = "Eend";               

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}

$adminNaam = "Admin";

// Actie: Gebruiker Toevoegen
if(isset($_POST['toevoegen'])){
    $rol           = $_POST['add_rol'] ?? 'student';
    $voornaam      = $_POST['add_voornaam'] ?? '';
    $tussenvoegsel = $_POST['add_tussenvoegsel'] ?? '';
    $achternaam    = $_POST['add_achternaam'] ?? '';
    $email         = $_POST['add_email'] ?? '';
    $wachtwoord    = $_POST['add_wachtwoord'] ?? ''; 
    $telefoon      = $_POST['add_telefoon'] ?? '';
    $omschrijving  = $_POST['add_omschrijving'] ?? ''; // FIX: omschrijving opgevangen

    if ($rol === 'student') {
        $beperking    = isset($_POST['add_beperking']) ? intval($_POST['add_beperking']) : 0;
        $geboortedatum = !empty($_POST['add_geboortedatum']) ? $_POST['add_geboortedatum'] : null; // FIX: geboortedatum opgevangen
        $lespakketID   = !empty($_POST['add_lespakket']) ? intval($_POST['add_lespakket']) : null; // FIX: lespakket opgevangen
        $statusStudent = 'aangemeld'; 

        $statusStudent = 'actief';
        
        // Voeg de student toe inclusief omschrijving en geboortedatum
        $stmt = $conn->prepare("
            INSERT INTO studenten (voornaam, tussenvoegsel, achternaam, email, wachtwoord, telefoon, beperking, omschrijving, geboortedatum, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssisss", $voornaam, $tussenvoegsel, $achternaam, $email, $wachtwoord, $telefoon, $beperking, $omschrijving, $geboortedatum, $statusStudent);
        $stmt->execute();
        
        // FIX: Als er een lespakket is gekozen, koppel deze direct in de tabel 'student_lespakket'
        $nieuwStudentID = $conn->insert_id; // Haalt het net aangemaakte studentID op
        if ($lespakketID && $nieuwStudentID) {
            $stmtPakket = $conn->prepare("INSERT INTO student_lespakket (studentID, idlespakket, overige_uren) VALUES (?, ?, 0)");
            $stmtPakket->bind_param("ii", $nieuwStudentID, $lespakketID);
            $stmtPakket->execute();
            $stmtPakket->close();
        }
        $stmt->close();
    } else {
        // Voeg de instructeur toe inclusief omschrijving
        $stmt = $conn->prepare("
            INSERT INTO instructeurs (voornaam, tussenvoegsel, achternaam, email, wachtwoord, telefoon, omschrijving) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $voornaam, $tussenvoegsel, $achternaam, $email, $wachtwoord, $telefoon, $omschrijving);
        $stmt->execute();
        $stmt->close();
    }
    

}

// Actie: Bewerken
if(isset($_POST['bewerken'])){
    $userID        = $_POST['studentID']; 
    $rol           = $_POST['rol'] ?? 'student'; 
    $voornaam      = $_POST['voornaam'] ?? '';
    $tussenvoegsel = $_POST['tussenvoegsel'] ?? '';
    $achternaam    = $_POST['achternaam'] ?? '';
    $email         = $_POST['email'] ?? '';
    $wachtwoord    = $_POST['wachtwoord'] ?? ''; 
    $telefoon      = $_POST['telefoon'] ?? '';

    if ($rol === 'student') {
        if(!empty($wachtwoord)){
            $stmt = $conn->prepare("UPDATE studenten SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, wachtwoord=?, telefoon=? WHERE studentID=?");
            $stmt->bind_param("ssssssi", $voornaam, $tussenvoegsel, $achternaam, $email, $wachtwoord, $telefoon, $userID);
        } else {
            $stmt = $conn->prepare("UPDATE studenten SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, telefoon=? WHERE studentID=?");
            $stmt->bind_param("sssssi", $voornaam, $tussenvoegsel, $achternaam, $email, $telefoon, $userID);
        }
    } else {
        if(!empty($wachtwoord)){
            $stmt = $conn->prepare("UPDATE instructeurs SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, wachtwoord=?, telefoon=? WHERE instructeurID=?");
            $stmt->bind_param("ssssssi", $voornaam, $tussenvoegsel, $achternaam, $email, $wachtwoord, $telefoon, $userID);
        } else {
            $stmt = $conn->prepare("UPDATE instructeurs SET voornaam=?, tussenvoegsel=?, achternaam=?, email=?, telefoon=? WHERE instructeurID=?");
            $stmt->bind_param("sssssi", $voornaam, $tussenvoegsel, $achternaam, $email, $telefoon, $userID);
        }
    }

    $stmt->execute();
    $stmt->close();
    header("Location: AdminGebruikers.php");
    exit();
}

// Actie: Verwijderen
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $id = intval($_GET['id']); 
        
        $checkStudent = $conn->query("SELECT studentID FROM studenten WHERE studentID = " . $id);
        
        if($checkStudent && $checkStudent->num_rows > 0) {
            $stmtInsKoppel = $conn->prepare("DELETE FROM studenten_has_instructeurs WHERE studentID = ?");
            $stmtInsKoppel->bind_param("i", $id);
            $stmtInsKoppel->execute();
            $stmtInsKoppel->close();

            $stmtPakket = $conn->prepare("DELETE FROM student_lespakket WHERE studentID = ?");
            $stmtPakket->bind_param("i", $id);
            $stmtPakket->execute();
            $stmtPakket->close();

            $stmtLessons = $conn->prepare("DELETE FROM lessen WHERE studentID = ?");
            $stmtLessons->bind_param("i", $id);
            $stmtLessons->execute();
            $stmtLessons->close();

            $stmt = $conn->prepare("DELETE FROM studenten WHERE studentID = ?");
        } else {
            $stmtInsKoppel = $conn->prepare("DELETE FROM studenten_has_instructeurs WHERE instructeurID = ?");
            $stmtInsKoppel->bind_param("i", $id);
            $stmtInsKoppel->execute();
            $stmtInsKoppel->close();

            $stmt = $conn->prepare("DELETE FROM instructeurs WHERE instructeurID = ?");
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: AdminGebruikers.php");
        exit();
    }
}
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

    <header class="topbar">
        <div class="logo-section">
            <h2>👋 <?php echo $adminNaam; ?></h2>
            <span class="badge">Admin</span>
            <p>Rijschool Dashboard</p>
        </div>
        <a href="/login.php?logout=1" class="logout-btn">Uitloggen →</a>
    </header>

    <div class="nav-grid">
        <a href="http://localhost/src/AdminDashboard.php" class="nav-card">Dashboard</a>
        <a href="http://localhost/src/AdminGebruikers.php" class="nav-card active">Gebruikers</a>
        <a href="#" class="nav-card">Rooster</a>
        <a href="#" class="nav-card">Profiel</a>
    </div>

    <br>

    <div class="schema">
         <button type="button" class="btn-add" onclick="openAddModal()">+ Gebruiker Toevoegen</button>

         <form method="GET">
            <label for="status">Kies je gebruiker:</label>
            <select name="status" id="status">
              <option value="">alle studenten</option>
              <option value="actieve-studenten">actieve studenten</option>
              <option value="niewe-studenten">aangemelden studenten</option>
              <option value="instructeurs">instructeurs</option>
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
                      <th>beperkt</th>
                      <th>Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $status = $_GET['status'] ?? '';

            if ($status == 'actieve-studenten') {
                $query = "SELECT s.studentID, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, l.naam AS lespakket 
                          FROM studenten s 
                          LEFT JOIN student_lespakket sl ON s.studentID = sl.studentID 
                          LEFT JOIN lespakket l ON sl.idlespakket = l.idlespakket
                          WHERE s.status = 'actief'";
            } 
            elseif ($status == 'niewe-studenten') {
                $query = "SELECT s.studentID, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, l.naam AS lespakket 
                          FROM studenten s 
                          LEFT JOIN student_lespakket sl ON s.studentID = sl.studentID 
                          LEFT JOIN lespakket l ON sl.idlespakket = l.idlespakket
                          WHERE s.status = 'aangemeld'";
            } 
            elseif ($status == 'instructeurs') {
                $query = "SELECT instructeurID, voornaam, tussenvoegsel, achternaam, email, telefoon FROM instructeurs";
            } else {
                $query = "SELECT s.studentID, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, l.naam AS lespakket 
                          FROM studenten s 
                          LEFT JOIN student_lespakket sl ON s.studentID = sl.studentID 
                          LEFT JOIN lespakket l ON sl.idlespakket = l.idlespakket";
            }

            $result = $conn->query($query);

            if (!$result) {
                echo "<tr><td colspan='7' style='color:red; font-weight:bold;'>Database Fout: " . htmlspecialchars($conn->error) . "</td></tr>";
            } elseif ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {

                    $id = $row['studentID'] ?? $row['instructeurID'] ?? 0;
                    $huidigeRol = isset($row['instructeurID']) ? 'instructeur' : 'student';
                    $volledigeNaam = trim(($row['voornaam'] ?? '') . ' ' . ($row['tussenvoegsel'] ?? '') . ' ' . ($row['achternaam'] ?? ''));
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($volledigeNaam) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($row['telefoon'] ?? '') . "</td>";
                    
                    echo "<td>" . htmlspecialchars($row['lespakket'] ?? '-') . "</td>";
                    echo "<td>" . ($huidigeRol == 'instructeur' ? 'Ja' : ($status == 'niewe-studenten' ? 'Nee' : 'Ja')) . "</td>";
                    echo "<td>" . (isset($row['beperking']) && $row['beperking'] == 1 ? 'Ja' : 'Nee') . "</td>";
                    
                    echo "<td>";
                    echo "  <div class='action-buttons'>";
                    echo "    <button type='button' class='btn-edit' onclick='openEditModal(".json_encode([
                                  'id' => $id,
                                  'rol' => $huidigeRol,
                                  'voornaam' => $row['voornaam'] ?? '',
                                  'tussenvoegsel' => $row['tussenvoegsel'] ?? '',
                                  'achternaam' => $row['achternaam'] ?? '',
                                  'email' => $row['email'] ?? '',
                                  'telefoon' => $row['telefoon'] ?? ''
                              ]).")'>Bewerken</button>";
                    echo "    <a href='AdminGebruikers.php?action=delete&id=" . $id . "' class='btn-delete' onclick='return confirm(\"Weet je zeker dat je dit wilt verwijderen?\");'>Verwijderen</a>";
                    echo "  </div>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>Geen resultaten gevonden.</td></tr>";
            }
            ?>
            </tbody>
         </table>
    </div>
</div>

<div id="addModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAddModal()">&times;</span>
    <h3>Nieuwe Gebruiker Aanmaken</h3>
    <hr>
    
    <form method="POST" action="AdminGebruikers.php" class="modal-form">
        <div class="modal-form-group">
            <label for="add_rol">Type Gebruiker:</label>
            <select name="add_rol" id="add_rol" onchange="toggleAddFields()" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
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
                <select name="add_lespakket" id="add_lespakket" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="">-- Geen Pakket --</option>
                    <?php
                    $pakketResult = $conn->query("SELECT idlespakket, naam FROM lespakket");
                    if($pakketResult && $pakketResult->num_rows > 0) {
                        while($pakket = $pakketResult->fetch_assoc()) {
                            echo "<option value='".$pakket['idlespakket']."'>".htmlspecialchars($pakket['naam'])."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="modal-form-group">
                <label for="add_beperking">Medische Beperking?</label>
                <select name="add_beperking" id="add_beperking" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="0">Nee (Geen)</option>
                    <option value="1">Ja (Medische indicatie)</option>
                </select>
            </div>
        <div class="modal-form-group">
            <label>Omschrijving:</label>
            <input type="text" name="add_omschrijving" placeholder="omschrijving van de beperking" >
        </div>

        </div>

        <button type="submit" name="toevoegen" class="btn-submit">Gebruiker Opslaan</button>
    </form>
  </div>
</div>

<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditModal()">&times;</span>
    <h3>Gebruiker Bewerken</h3>
    <hr>
    
    <form method="POST" action="AdminGebruikers.php" class="modal-form">
        <input type="hidden" name="studentID" id="edit_studentID">
        <input type="hidden" name="rol" id="edit_rol">

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
    document.getElementById('addModal').classList.add('show');
    toggleAddFields();
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
}

function toggleAddFields() {
    var rol = document.getElementById('add_rol').value;
    var extraFields = document.getElementById('student_extra_fields');
    if (rol === 'instructeur') {
        extraFields.style.display = 'none';
    } else {
        extraFields.style.display = 'block';
    }
}

function openEditModal(data) {
    document.getElementById('edit_studentID').value = data.id;
    document.getElementById('edit_rol').value = data.rol; 
    document.getElementById('edit_voornaam').value = data.voornaam;
    document.getElementById('edit_tussenvoegsel').value = data.tussenvoegsel || '';
    document.getElementById('edit_achternaam').value = data.achternaam;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_telefoon').value = data.telefoon || '';

    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

window.onclick = function(event) {
    var addModal = document.getElementById('addModal');
    var editModal = document.getElementById('editModal');
    if (event.target == addModal) {
        addModal.classList.remove('show');
    }
    if (event.target == editModal) {
        editModal.classList.remove('show');
    }
}
</script>

</body>
</html>