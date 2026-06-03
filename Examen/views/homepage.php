<?php
/**
 * HOMEPAGE — Anna werkt hier
 * ================================================================
 * Pas alleen dit bestand aan (tekst, knoppen, extra secties).
 * Header met "Inloggen": views/partials/header.php
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage — <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/login.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="page page--with-header">
        <section class="card">
            <div class="card-header">
                <h1>Welkom bij <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Jouw rijschool, op één plek</p>
            </div>
            <div class="card-body">
                <?php if (isLoggedIn()): ?>
                    <p class="success-msg">
                        Je bent ingelogd. Ga naar je dashboard of log uit via de header.
                    </p>
                    <a href="<?= htmlspecialchars(dashboardUrlForRole(), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-link">Naar dashboard</a>
                <?php else: ?>
                    <p class="success-msg">
                        Plan lessen, volg je voortgang en beheer alles vanuit één app.
                        <span class="email">Log in om verder te gaan.</span>
                    </p>
                    <a href="<?= htmlspecialchars(app_url('login.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-link">Naar inloggen</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
