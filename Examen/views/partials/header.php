<?php
/** @var string $active */
$active = $active ?? '';
?>
<header class="site-header" role="banner">
    <div class="site-header__inner">
        <div class="site-header__brand">
            <span class="site-header__title"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <nav class="site-header__nav" aria-label="Hoofdnavigatie">
            <a class="nav-btn<?= $active === 'home' ? ' nav-btn--active' : '' ?>" href="<?= htmlspecialchars(src_url('homepage.php'), ENT_QUOTES, 'UTF-8') ?>">Homepage</a>
            <?php if (isLoggedIn()): ?>
                <a class="nav-btn" href="<?= htmlspecialchars(logout_url(), ENT_QUOTES, 'UTF-8') ?>">Uitloggen</a>
            <?php else: ?>
                <a class="nav-btn<?= $active === 'login' ? ' nav-btn--active' : '' ?>" href="<?= htmlspecialchars(login_url(), ENT_QUOTES, 'UTF-8') ?>">Inloggen</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
