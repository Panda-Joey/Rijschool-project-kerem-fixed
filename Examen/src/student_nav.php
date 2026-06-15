<?php
/**
 * Gedeelde header + navigatie voor studentpagina's.
 * Zet vóór include: $navActief = 'dashboard'|'kalender'|'profiel'|'les_inroosteren'
 */
$naam        = $_SESSION['naam'] ?? '';
$navActief   = $navActief ?? 'dashboard';
$paginaLabel = $paginaLabel ?? 'Rijschool Dashboard';

$navItems = [
    ['id' => 'dashboard',       'label' => 'Dashboard',    'href' => 'StudentDashboard.php'],
    ['id' => 'kalender',        'label' => 'Kalender',     'href' => 'kalender.php'],
    ['id' => 'profiel',         'label' => 'Profiel',      'href' => 'Profiels.php'],
    ['id' => 'les_inroosteren', 'label' => '+ Nieuwe les', 'href' => 'les_inroosteren.php'],
];
?>
<div class="dash-header">
    <div>
        <h2>👋 <?= htmlspecialchars($naam) ?> <span class="rol-badge badge-student">🚗 Student</span></h2>
        <span><?= htmlspecialchars($paginaLabel) ?></span>
    </div>
    <a href="../logout.php" class="logout-btn">Uitloggen →</a>
</div>

<div class="top-buttons">
    <?php foreach ($navItems as $item):
        $isActief = ($navActief === $item['id']);
        $klasse   = 'nav-btn' . ($isActief ? ' active' : '');
    ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="<?= htmlspecialchars($klasse) ?>"
           <?= $isActief ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($item['label']) ?>
        </a>
    <?php endforeach; ?>
</div>
