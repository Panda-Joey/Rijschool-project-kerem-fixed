<?php
require_once dirname(__DIR__) . '/includes/autos.php';

$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$naam  = $_SESSION['naam'] ?? 'Instructeur';
$autos = fetchAllAutos($conn);

$beschikbaar = [];
$nietBeschikbaar = [];
foreach ($autos as $auto) {
    if ((int) ($auto['beschikbaar'] ?? 1) === 1) {
        $beschikbaar[] = $auto;
    } else {
        $nietBeschikbaar[] = $auto;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wagenpark — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <?php
    $navActief = 'wagenpark';
    $paginaLabel = 'Wagenpark';
    require_once 'instructeur_nav.php';
    ?>

    <?php if (!empty($nietBeschikbaar)): ?>
    <section class="wagen-sectie wagen-sectie-waarschuwing">
        <h3>⚠️ Niet beschikbaar (<?= count($nietBeschikbaar) ?>)</h3>
        <div class="wagen-kaarten">
            <?php foreach ($nietBeschikbaar as $auto): ?>
            <div class="wagen-kaart wagen-kaart-niet">
                <div class="wagen-kaart-kenteken"><?= htmlspecialchars($auto['kenteken']) ?></div>
                <h4><?= htmlspecialchars($auto['merk'] . ' ' . $auto['type']) ?></h4>
                <p><?= htmlspecialchars(brandstofLabel((int) ($auto['brandstof'] ?? 0))) ?> · <?= htmlspecialchars(transmissieLabel((int) $auto['transmissie'])) ?></p>
                <?php if (!empty($auto['statusReden'])): ?>
                    <p class="wagen-reden"><strong>Reden:</strong> <?= htmlspecialchars($auto['statusReden']) ?></p>
                <?php endif; ?>
                <span class="wagen-status-label niet">Niet beschikbaar</span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="wagen-sectie">
        <h3>✅ Beschikbaar (<?= count($beschikbaar) ?>)</h3>
        <?php if (empty($beschikbaar)): ?>
            <p class="geen-lessen">Geen auto's beschikbaar op dit moment.</p>
        <?php else: ?>
        <div class="wagen-kaarten">
            <?php foreach ($beschikbaar as $auto): ?>
            <div class="wagen-kaart wagen-kaart-ok">
                <div class="wagen-kaart-kenteken"><?= htmlspecialchars($auto['kenteken']) ?></div>
                <h4><?= htmlspecialchars($auto['merk'] . ' ' . $auto['type']) ?></h4>
                <p><?= htmlspecialchars(brandstofLabel((int) ($auto['brandstof'] ?? 0))) ?> · <?= htmlspecialchars(transmissieLabel((int) $auto['transmissie'])) ?></p>
                <span class="wagen-status-label ok">Beschikbaar</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

</div>
</body>
</html>
