<?php
require_once '../includes/session.php';

if (!isset($_SESSION['userID']) || $_SESSION['rol'] !== 'student') {
    header("Location: /login.php");
    exit;
}

$conn = new mysqli("mysql", "root", "password", "Eend");

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
exit;