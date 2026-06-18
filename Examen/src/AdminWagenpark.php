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

$adminNaam = $_SESSION['naam'] ?? 'Admin';
$melding   = '';
$fout      = '';

ensureAutosAvailabilityColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['auto_toevoegen'])) {
        $err = insertAuto(
            $conn,
            $_POST['merk'] ?? '',
            $_POST['type'] ?? '',
            $_POST['kenteken'] ?? '',
            (int) ($_POST['transmissie'] ?? -1),
            (int) ($_POST['brandstof'] ?? -1)
        );
        if ($err) {
            $fout = $err;
        } else {
            $melding = 'Lesauto toegevoegd aan het wagenpark.';
        }
    } elseif (isset($_POST['auto_verwijderen'])) {
        $err = deleteAuto($conn, (int) ($_POST['autoID'] ?? 0));
        if ($err) {
            $fout = $err;
        } else {
            $melding = 'Lesauto verwijderd uit het wagenpark.';
        }
    } elseif (isset($_POST['status_wijzigen'])) {
        $autoID      = (int) ($_POST['autoID'] ?? 0);
        $beschikbaar = (int) ($_POST['beschikbaar'] ?? 1) ? 1 : 0;
        $statusReden = trim($_POST['statusReden'] ?? '');

        if ($autoID <= 0) {
            $fout = 'Ongeldige auto.';
        } elseif (!$beschikbaar && $statusReden === '') {
            $fout = 'Geef een reden op waarom de auto niet beschikbaar is.';
        } else {
            $reden = $beschikbaar ? null : $statusReden;
            $stmt = $conn->prepare("UPDATE Autos SET beschikbaar = ?, statusReden = ? WHERE autoID = ?");
            $stmt->bind_param("isi", $beschikbaar, $reden, $autoID);
            if ($stmt->execute()) {
                $melding = $beschikbaar ? 'Auto staat weer op beschikbaar.' : 'Auto gemarkeerd als niet beschikbaar.';
            } else {
                $fout = 'Opslaan mislukt.';
            }
            $stmt->close();
        }
    }
}

$autos = fetchAllAutos($conn);
$beschikbaarCount = count(array_filter($autos, fn($a) => (int) ($a['beschikbaar'] ?? 1) === 1));
$elektrischCount = count(array_filter($autos, fn($a) => (int) ($a['brandstof'] ?? 0) === 1));
$benzineCount = count($autos) - $elektrischCount;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wagenpark — Admin</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(src_url('css/AD.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>

<div class="container">

    <header class="topbar">
        <div class="logo-section">
            <h2>👋 <?php echo htmlspecialchars($adminNaam, ENT_QUOTES, 'UTF-8'); ?></h2>
            <span class="badge">Admin</span>
            <p>Wagenpark beheer</p>
        </div>
        <a href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-btn">Uitloggen →</a>
    </header>

    <div class="nav-grid">
        <a href="AdminDashboard.php" class="nav-card">Dashboard</a>
        <a href="AdminGebruikers.php" class="nav-card">Gebruikers</a>
        <a href="#" class="nav-card">Rooster</a>
        <a href="#" class="nav-card">Profiel</a>
        <a href="AdminWagenpark.php" class="nav-card active">Wagenpark</a>
    </div>

    <?php if ($melding): ?>
        <p class="flash flash-ok"><?= htmlspecialchars($melding) ?></p>
    <?php endif; ?>
    <?php if ($fout): ?>
        <p class="flash flash-fout"><?= htmlspecialchars($fout) ?></p>
    <?php endif; ?>

    <div class="wagen-stats">
        <span><strong><?= $elektrischCount ?></strong> elektrisch</span>
        <span><strong><?= $benzineCount ?></strong> benzine</span>
        <span><strong><?= $beschikbaarCount ?></strong> beschikbaar</span>
        <span><strong><?= count($autos) - $beschikbaarCount ?></strong> niet beschikbaar</span>
        <span><strong><?= count($autos) ?></strong> totaal</span>
    </div>

    <section class="wagen-add-panel">
        <h3>Nieuwe lesauto toevoegen</h3>
        <form method="post" class="wagen-add-form">
            <label>
                Merk
                <input type="text" name="merk" maxlength="50" required placeholder="bijv. Volkswagen">
            </label>
            <label>
                Type
                <input type="text" name="type" maxlength="100" required placeholder="bijv. Golf 8">
            </label>
            <label>
                Kenteken
                <input type="text" name="kenteken" maxlength="15" required placeholder="bijv. G-123-AA">
            </label>
            <label>
                Brandstof
                <select name="brandstof" required>
                    <option value="">— Kies —</option>
                    <option value="1">Elektrisch</option>
                    <option value="0">Benzine</option>
                </select>
            </label>
            <label>
                Transmissie
                <select name="transmissie" required>
                    <option value="">— Kies —</option>
                    <option value="1">Handgeschakeld</option>
                    <option value="0">Automaat</option>
                </select>
            </label>
            <button type="submit" name="auto_toevoegen" class="add-btn">+ Auto toevoegen</button>
        </form>
    </section>

    <table class="student-table wagen-table">
        <thead>
            <tr>
                <th>Kenteken</th>
                <th>Auto</th>
                <th>Brandstof</th>
                <th>Transmissie</th>
                <th>Status</th>
                <th>Reden</th>
                <th>Beschikbaarheid</th>
                <th>Verwijderen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($autos)): ?>
            <tr>
                <td colspan="8">Nog geen lesauto's. Voeg er een toe met het formulier hierboven.</td>
            </tr>
            <?php endif; ?>
            <?php foreach ($autos as $auto):
                $isBeschikbaar = (int) ($auto['beschikbaar'] ?? 1) === 1;
            ?>
            <tr class="<?= $isBeschikbaar ? 'rij-beschikbaar' : 'rij-niet-beschikbaar' ?>">
                <td><strong><?= htmlspecialchars($auto['kenteken']) ?></strong></td>
                <td><?= htmlspecialchars($auto['merk'] . ' ' . $auto['type']) ?></td>
                <td><?= htmlspecialchars(brandstofLabel((int) ($auto['brandstof'] ?? 0))) ?></td>
                <td><?= htmlspecialchars(transmissieLabel((int) $auto['transmissie'])) ?></td>
                <td>
                    <span class="status-badge <?= $isBeschikbaar ? 'status-ok' : 'status-niet' ?>">
                        <?= $isBeschikbaar ? 'Beschikbaar' : 'Niet beschikbaar' ?>
                    </span>
                </td>
                <td><?= $isBeschikbaar ? '—' : htmlspecialchars($auto['statusReden'] ?? '') ?></td>
                <td>
                    <form method="post" class="status-form">
                        <input type="hidden" name="autoID" value="<?= (int) $auto['autoID'] ?>">
                        <?php if ($isBeschikbaar): ?>
                            <input type="hidden" name="beschikbaar" value="0">
                            <input type="text" name="statusReden" placeholder="Reden (bv. APK, schade)" required maxlength="255">
                            <button type="submit" name="status_wijzigen" class="action-btn delete-btn">Niet beschikbaar</button>
                        <?php else: ?>
                            <input type="hidden" name="beschikbaar" value="1">
                            <input type="hidden" name="statusReden" value="">
                            <button type="submit" name="status_wijzigen" class="action-btn edit-btn">Beschikbaar maken</button>
                        <?php endif; ?>
                    </form>
                </td>
                <td>
                    <form method="post" class="verwijder-form"
                          onsubmit="return confirm('Weet je zeker dat je deze auto wilt verwijderen?');">
                        <input type="hidden" name="autoID" value="<?= (int) $auto['autoID'] ?>">
                        <button type="submit" name="auto_verwijderen" class="action-btn delete-btn">Verwijderen</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

</body>
</html>
