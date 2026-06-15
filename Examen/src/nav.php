<?php
/* ============================================================
   nav.php — Gedeelde header + navigatie
   Inclusief op elke pagina via: require_once 'nav.php';

   Verwacht dat session_start() al gedaan is en dat
   $_SESSION['userID'], $_SESSION['rol'], $_SESSION['naam']
   beschikbaar zijn.
   ============================================================ */

// Huidige pagina bepalen voor actieve staat
$huidigePagina = basename($_SERVER['PHP_SELF']);

$rol  = $_SESSION['rol']  ?? '';
$naam = $_SESSION['naam'] ?? '';

// Dashboard link verschilt per rol
$dashboardLink = match($rol) {
    'instructeur' => 'InstructeurDashboard.php',
    'student'     => 'StudentDashboard.php',
    default       => 'dashboard.php'
};

// Navigatie-items: [label, href, alleen_voor_rol (null = iedereen)]
$navItems = [
    ['label' => 'Dashboard',     'href' => $dashboardLink,          'rol' => null,          'id' => 'dashboard'],
    ['label' => 'Kalender',      'href' => 'kalender.php',          'rol' => null,          'id' => 'kalender'],
    ['label' => 'Rooster',       'href' => 'beschikbaarheid.php',   'rol' => 'instructeur', 'id' => 'beschikbaarheid'],
    ['label' => '+ Les inplannen','href'=> 'les_inroosteren.php',   'rol' => 'instructeur', 'id' => 'les_inroosteren'],
    ['label' => '+ Nieuwe les',  'href' => 'les_inroosteren.php',   'rol' => 'student',     'id' => 'les_inroosteren'],
    ['label' => '📋 Examen',     'href' => 'examen.php',            'rol' => 'instructeur', 'id' => 'examen'],
];

// Pagina → nav-item id koppeling (voor actieve staat)
$paginaActief = [
    'InstructeurDashboard.php' => 'dashboard',
    'StudentDashboard.php'     => 'dashboard',
    'dashboard.php'            => 'dashboard',
    'kalender.php'             => 'kalender',
    'index.php'                => 'kalender',
    'beschikbaarheid.php'      => 'beschikbaarheid',
    'les_inroosteren.php'      => 'les_inroosteren',
    'examen.php'               => 'examen',
    'wijzig.php'               => 'kalender',
    'annuleer.php'             => 'kalender',
];

$actiefID = $paginaActief[$huidigePagina] ?? '';
?>

<!-- ── HEADER ───────────────────────────────────────────────── -->
<div class="dash-header">
    <div>
        <h2>🚗 Rijschool</h2>
        <span>
            <?= htmlspecialchars($naam) ?> —
            <span class="rol-badge <?= $rol === 'instructeur' ? 'badge-instructeur' : 'badge-student' ?>">
                <?= $rol === 'instructeur' ? '🎓 Instructeur' : '🚗 Student' ?>
            </span>
        </span>
    </div>
    <a href="logout.php" class="logout-btn">Uitloggen →</a>
</div>

<!-- ── NAVIGATIE ────────────────────────────────────────────── -->
<div class="top-buttons">
    <?php foreach ($navItems as $item):
        // Sla over als item alleen voor een bepaalde rol is
        if ($item['rol'] !== null && $item['rol'] !== $rol) continue;

        $isActief   = ($item['id'] === $actiefID);
        $isGoedkeur = ($item['id'] === 'goedkeuren');
        $klasse     = 'nav-btn' . ($isActief ? ' active' : '');
        $stijl      = $isGoedkeur ? 'background:#28a745;color:white;' : '';
    ?>
        <a href="<?= $item['href'] ?>"
           class="<?= $klasse ?>"
           style="text-decoration:none;<?= $stijl ?>">
            <?= $item['label'] ?>
        </a>
    <?php endforeach; ?>
</div>