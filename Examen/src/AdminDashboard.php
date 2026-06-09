<?php

$sessionStarted = session_status() !== PHP_SESSION_NONE;

$servername = "mysql";          
$username = "root";             
$password = "password";         
$dbname = "Eend";               


$conn = new mysqli($servername, $username, $password, $dbname);

// Controleer de verbinding
if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}

//$adminNaam = $_SESSION['naam'] ?? displayNameFromEmail($_SESSION['user'] ?? 'Admin');
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

        <a href="/logout.php" class="logout-btn">Uitloggen →</a>
    </header>

    <!-- NAVIGATIE -->
    <div class="nav-grid">
        <a href="/src/AdminDashboard.php" class="nav-card active">Dashboard</a>
        <a href="/src/AdminGebruikers.php" class="nav-card">Gebruikers</a>
        <a href="#" class="nav-card">Rooster</a>
        <a href="#" class="nav-card">Profiel</a>
        <a href="AdminWagenpark.php" class="nav-card">Wagenpark</a>
    </div>
</div>


</body>
</html>