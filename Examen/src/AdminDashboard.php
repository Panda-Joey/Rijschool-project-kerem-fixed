<?php
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


$adminNaam = $_SESSION['naam'] ?? 'Admin';

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
            <h2>👋 <?php echo htmlspecialchars($adminNaam, ENT_QUOTES, 'UTF-8'); ?></h2>
            <span class="badge">Admin</span>
            <p>Rijschool Dashboard</p>
        </div>

        <a href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-btn">Uitloggen →</a>
    </header>

    <!-- NAVIGATIE -->
    <div class="nav-grid">
        <a href="AdminDashboard.php" class="nav-card active">Dashboard</a>
        <a href="AdminGebruikers.php" class="nav-card">Gebruikers</a>
        <a href="#" class="nav-card">Rooster</a>
        <a href="#" class="nav-card">Profiel</a>
        <a href="AdminWagenpark.php" class="nav-card">Wagenpark</a>
    </div>


</div>

</body>
</html>
