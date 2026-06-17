<?php
/**
 * Gedeelde header + navigatie voor instructeurpagina's.
 * Zet vóór include: $navActief = 'dashboard'|'kalender'|'rooster'|'profiel'|'afwezigheid'|'wagenpark'
 * Optioneel: $paginaLabel (ondertitel onder de naam)
 */
$naam        = $_SESSION['naam'] ?? '';
$navActief   = $navActief ?? 'dashboard';
$paginaLabel = $paginaLabel ?? 'Rijschool Dashboard';

$navItems = [
    ['id' => 'dashboard',   'label' => 'Dashboard',     'href' => 'InstructeurDashboard.php'],
    ['id' => 'kalender',    'label' => 'Kalender',      'href' => 'kalender.php'],
    ['id' => 'studenten',   'label' => 'Mijn Studenten', 'href' => 'overzichtStudent.php'],
    ['id' => 'rooster',     'label' => 'Rooster',       'href' => 'beschikbaarheid.php'],
    ['id' => 'profiel',     'label' => 'Profiel',       'href' => 'Profieli.php'],
    ['id' => 'afwezigheid', 'label' => 'Meld Afwezig',  'href' => 'afwezigheid.php', 'alert' => true],
    ['id' => 'wagenpark',   'label' => 'Wagenpark',     'href' => 'Wagenpark.php'],
];
?>
<div class="dash-header">
    <div>
        <h2>👋 <?= htmlspecialchars($naam) ?> <span class="rol-badge badge-instructeur">🎓 Instructeur</span></h2>
        <span><?= htmlspecialchars($paginaLabel) ?></span>
    </div>
    <a href="../logout.php" class="logout-btn">Uitloggen →</a>
</div>

<div class="top-buttons">
    <?php foreach ($navItems as $item):
        $isActief = ($navActief === $item['id']);
        $klasse   = 'nav-btn' . ($isActief ? ' active' : '') . (!empty($item['alert']) ? ' nav-btn--alert' : '');
    ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="<?= htmlspecialchars($klasse) ?>"
           <?= $isActief ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($item['label']) ?>
        </a>
    <?php endforeach; ?>
</div>
