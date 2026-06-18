<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'instructeur') {
    header("Location: /login.php");
    exit;
}

$instructeurID = (int) ($_SESSION['userID'] ?? 0);
$naam          = $_SESSION['naam'] ?? '';

$instructeurID = $_SESSION['instructeurID'] ?? 0;

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructeur Dashboard — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/AD.css">
</head>
<body>

<div class="container">

    <?php
    $navActief = 'studenten';
    $paginaLabel = 'Mijn Studenten';
    require_once 'instructeur_nav.php';
    ?>


<div class="schema">
    
    <h2>Mijn Studenten Overzicht</h2>

    <!-- <form method="GET" class="filter-form">
        <label for="filter_status">Kies je gebruiker:</label>
        <select name="gebruiker_filter" id="filter_status">
            <option value="">Alle eigen studenten</option>
            <option value="actieve-studenten" <?= ($_GET['gebruiker_filter'] ?? '') === 'actieve-studenten' ? 'selected' : '' ?>>Actieve studenten</option>
            <option value="niewe-studenten" <?= ($_GET['gebruiker_filter'] ?? '') === 'niewe-studenten' ? 'selected' : '' ?>>Aangemelden studenten</option>
        </select>
        <button type="submit">Filter</button>
    </form> -->

    <table border="1">
        <thead>
            <tr>
                <th>Naam</th>
                <th>Email</th>
                <th>Telefoon</th>
                <th>Lespakket</th>
                <th>Actief</th>
                <th>Beperkt</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $statusFilter = $_GET['gebruiker_filter'] ?? '';

        // Basis query die sowieso filtert op de studenten van deze specifieke instructeur
        $baseQuery = "SELECT s.studentID, s.status, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, l.naam AS lespakket
                      FROM studenten s
                      INNER JOIN studenten_has_instructeurs shi ON s.studentID = shi.studentID
                      LEFT JOIN student_lespakket sl ON s.studentID = sl.studentID
                      LEFT JOIN lespakket l ON sl.idlespakket = l.idlespakket
                      WHERE shi.instructeurID = ?";

        if ($statusFilter === 'actieve-studenten') {
            $query = $baseQuery . " AND s.status = 'actief'";
        // } elseif ($statusFilter === 'niewe-studenten') {
        //     $query = $baseQuery . " AND s.status = 'pending'";
        } else {
            $query = $baseQuery;
        }

        // Gebruik Prepared Statements voor de veiligheid en om de instructeurID te binden
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $instructeurID);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            echo "<tr><td colspan='6' style='color:red; font-weight:bold;'>Database Fout: " . htmlspecialchars($conn->error) . '</td></tr>';
        } elseif ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $volledigeNaam = trim(($row['voornaam'] ?? '') . ' ' . ($row['tussenvoegsel'] ?? '') . ' ' . ($row['achternaam'] ?? ''));

                echo '<tr>';
                echo '<td>' . htmlspecialchars($volledigeNaam) . '</td>';
                echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['telefoon'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['lespakket'] ?? '-') . '</td>';

                $dbStatus = $row['status'] ?? 'pending';
                echo '<td>' . ($dbStatus === 'actief' ? 'Ja' : 'Nee') . '</td>';
                
                echo '<td>' . (isset($row['beperking']) && $row['beperking'] == 1 ? 'Ja' : 'Nee') . '</td>';
                echo '</tr>';
            }
        } else {
            echo "<tr><td colspan='6'>Geen studenten aan jou gekoppeld gevonden.</td></tr>";
        }
        ?>
        </tbody>
    </table>
</div>

</body>
</html>