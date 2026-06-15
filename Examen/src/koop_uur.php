<?php
require_once '../includes/session.php';

if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'student') {
    header("Location: /login.php");
    exit;
}

$conn = new mysqli("mysql", "root", "password", "Eend");

$userID = (int)$_SESSION['userID'];
$aantal = (int)($_GET['aantal'] ?? 0);

/* prijzen */
$prijzen = [
    1 => 60,
    3 => 175,
    5 => 290
];

if (!isset($prijzen[$aantal])) {
    die("Ongeldig aantal uren");
}

$prijs = $prijzen[$aantal];

$stmt = $conn->prepare("
    UPDATE student_lespakket
    SET overige_uren = IFNULL(overige_uren,0) + ?,
        bedrag = IFNULL(bedrag,0) + ?
    WHERE studentID = ?
");

$stmt->bind_param("idi", $aantal, $prijs, $userID);
$stmt->execute();
$stmt->close();

$stmt2 = $conn->prepare("
    INSERT INTO bijkopen (naam, uren, prijs, studentID)
    VALUES (?, ?, ?, ?)
");

$naam = "Losse lesuren aankoop";

$stmt2->bind_param("sidi", $naam, $aantal, $prijs, $userID);
$stmt2->execute();
$stmt2->close();

$conn->close();

header("Location: studentDashboard.php?success=uren_toegevoegd");
exit;