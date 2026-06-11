<?php
require_once '../includes/session.php';

if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'student') {
    header("Location: /login.php");
    exit;
}

$conn = new mysqli("mysql", "root", "password", "Eend");

<<<<<<< Updated upstream
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
=======
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userID = (int)$_SESSION['userID'];
$aantal = (int)($_GET['aantal'] ?? 0);

if (!in_array($aantal, [1, 3, 5])) {
    die("Ongeldig aantal uren");
}

$stmt = $conn->prepare("
    UPDATE student_lespakket
    SET overige_uren = overige_uren + ?
    WHERE studentID = ?
");

$stmt->bind_param("ii", $aantal, $userID);

if (!$stmt->execute()) {
    die("Fout bij opslaan van uren");
}

$stmt->close();
$conn->close();

header("Location: StudentDashboard.php");
>>>>>>> Stashed changes
exit;