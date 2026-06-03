<?php
/**
 * ============================================================
 *  HOMEPAGE — Anna werkt hier
 * ============================================================
 *  Pas alleen de HTML in <main> aan (tekst, knoppen, secties).
 *  Laat de PHP-regels bovenaan en onderaan met rust.
 *
 *  URL: http://localhost:8888/src/homepage.php
 *  Header (logo + Inloggen): views/partials/header.php
 * ============================================================
 */
$active = 'home';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage — <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/login.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        /* Anna: optioneel extra styling voor de homepage */
        main.page.homepage {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        main.page.homepage > .card {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }

        .homepage-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            margin-top: 1.5rem;
        }
        .homepage-tile {
            background: #f8fafc;
            border: 2px solid #000;
            padding: 1rem;
            box-shadow: 4px 4px 0 0 #000;
        }
        .homepage-tile h3 { margin-bottom: 0.35rem; font-size: 1.05rem; }
        .homepage-tile p { font-size: 0.95rem; color: #475569; }
        .homepage-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .homepage-actions .btn-link {
            width: auto;
            min-width: 160px;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/views/partials/header.php'; ?>

    <main class="page page--with-header homepage">
        <!-- ================================================== -->
        <!-- ANNA: pas vanaf hier de inhoud aan                 -->
        <!-- ================================================== -->

        <section class="card">
            <div class="card-header">
                <h1>Welkom bij <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Jouw rijschool, op één plek</p>
            </div>

            <div class="card-body">
                <p class="success-msg">
                    Plan lessen, volg je voortgang en beheer alles vanuit één app.
                </p>

                <div class="homepage-grid">
                    <article class="homepage-tile">
                        <h3>📅 Lessen plannen</h3>
                        <p>Boek en wijzig rijlessen wanneer het jou uitkomt.</p>
                    </article>
                    <article class="homepage-tile">
                        <h3>📊 Voortgang</h3>
                        <p>Bekijk je pakket, geplande uren en aankomende lessen.</p>
                    </article>
                    <article class="homepage-tile">
                        <h3>🎓 Instructeurs</h3>
                        <p>Alles overzichtelijk voor leerling, instructeur en beheerder.</p>
                    </article>
                </div>

                <div class="homepage-actions">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= htmlspecialchars(dashboardUrlForRole(), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-link">
                            Naar dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars(login_url(), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-link">
                            Inloggen
                        </a>
                        <a href="<?= htmlspecialchars(src_url('aanmelden.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-link">
                            Aanmelden
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ANNA: voeg hier extra secties toe (bijv. reviews, FAQ, contact) -->

    </main>
</body>
</html>
