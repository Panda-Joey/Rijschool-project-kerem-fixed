<?php

if (!isset($_SESSION['userID']) || ($_SESSION['rol'] ?? '') !== 'instructeur') {
    header('Location: ' . login_url());
    exit;
}

$instructeurID = (int) $_SESSION['userID'];
$naam          = $_SESSION['naam'] ?? '';

require_once dirname(__DIR__) . '/includes/database.php';
$conn = getDbConnection();?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Studenten — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <?php
    $navActief = 'studenten';
    $paginaLabel = 'Mijn Studenten';
    require_once 'instructeur_nav.php';
    ?>

    <section class="studenten-overzicht">
        <h2>Mijn Studenten Overzicht</h2>

        <div class="studenten-tabel-wrap">
            <table class="studenten-tabel">
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

                $baseQuery = "SELECT s.studentID, s.status, s.voornaam, s.tussenvoegsel, s.achternaam, s.email, s.telefoon, s.beperking, l.naam AS lespakket
                              FROM studenten s
                              INNER JOIN studenten_has_instructeurs shi ON s.studentID = shi.studentID
                              LEFT JOIN student_lespakket sl ON s.studentID = sl.studentID
                              LEFT JOIN lespakket l ON sl.idlespakket = l.idlespakket
                              WHERE shi.instructeurID = ?";

                if ($statusFilter === 'actieve-studenten') {
                    $query = $baseQuery . " AND s.status = 'actief'";
                } else {
                    $query = $baseQuery;
                }

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
    </section>

</div>

</body>
</html>
