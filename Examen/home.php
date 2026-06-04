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
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/homepage.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="page page--with-header" id="homepage-page">
        <div class="content-width">
            <section class="card card--hero">
                <div class="card--hero-content">
                    <h1>Welkom bij
                        <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <p>Jouw rijschool, op één plek</p>
                </div>
            </section>
            <div class="card-row">
                <section class="card">
                    <div class="card-header">
                        <h3>over ons</h3>
                    </div>
                    <div class="card-body">
                        <p>Deze rijschool geeft rijlessen op maat voor mensen met een beperking, met extra aandacht voor
                            veiligheid en persoonlijke begeleiding. Elke leerling leert in zijn eigen tempo wat bij hem
                            of haar past. Zo wordt zelfstandigheid in het verkeer op een veilige manier vergroot.</p>
                        <h4>Eigenaar</h4>
                        <p></p>
                        <h4>Medewerkers (3 instructeurs)</h4>
                        <p>Henk de Vries</p>
                        <p>Anja van Dijk</p>
                        <p>Mark Bakker</p>
                    </div>
                </section>
                <section class="card">
                    <div class="card-header">
                        <h3>ons wagenpark</h3>
                    </div>
                    <div class="card-body">
                        <div class="border-text">
                            <p id="border-text">Volkswagen Golf 8</p>
                            <p id="border-text">Tesla Model 3 - Elektrisch</p>
                            <p id="border-text">Ford Fiesta </p>
                            <p id="border-text">BMW 1 Serie - Elektrisch</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="card-row--tall">
                <section class="card">
                    <div class="card-header">
                        <h3>Doelstelling</h3>
                    </div>
                    <div class="card-body">
                        <div class="border-text">
                            <p id="border-text">Leerlingen veilig leren rijden in het verkeer</p>
                            <p id="border-text">Zelfvertrouwen opbouwen bij beginnende bestuurders</p>
                            <p id="border-text">Kwalitatieve rijlessen aanbieden</p>
                            <p id="border-text">Rijlessen toegankelijk maken voor mensen met een fysieke beperking</p>
                            <p id="border-text">Moderne en veilige lesauto's gebruiken</p>
                            <p id="border-text">Betaalbare lespakketten aanbieden</p>
                        </div>
                    </div>
                </section>
                <section class="card">
                    <div class="card-header">
                        <h3>Routebeschrijving</h3>
                    </div>
                    <div class="card-body">
                        <div class="card-map"></div>
                        <p>Plan je route naar onze locatie in een paar stappen.</p>
                    </div>
                </section>
                <section class="card card--scroll">
                    <div class="card-header">
                        <h3>Algemene voorwaarden</h3>
                    </div>
                    <div class="card-body">
                        <h4>1. Algemeen</h4>
                        <p>Deze rijschool biedt rijlessen aan voor mensen met een beperking. Door gebruik te maken van
                            onze diensten gaat de leerling akkoord met deze voorwaarden.</p>
                        <h4>2. Rijlessen</h4>
                        <p>Rijlessen worden aangepast aan de mogelijkheden van de leerling. De instructeur bepaalt samen
                            met de leerling het lesplan en tempo.</p>
                        <h4>3. Veiligheid en geschiktheid</h4>
                        <p>De leerling is verantwoordelijk voor het doorgeven van relevante medische informatie die
                            invloed kan hebben op het rijden. De rijschool kan een les stoppen als de veiligheid in
                            gevaar komt.</p>
                        <h4>4. Annuleren van lessen</h4>
                        <p>Een rijles moet minimaal 24 uur van tevoren worden geannuleerd. Bij te late annulering kan de
                            les in rekening worden gebracht.</p>
                        <h4>5. Betaling</h4>
                        <p>Lessen worden vooraf of volgens afspraak betaald. Bij te late betaling kunnen lessen worden
                            opgeschort.</p>
                        <h4>6. Aansprakelijkheid</h4>
                        <p>De rijschool is niet aansprakelijk voor schade of letsel, behalve bij opzet of ernstige
                            nalatigheid.</p>
                        <h4>7. Examen</h4>
                        <p>Het praktijkexamen wordt alleen ingepland als de instructeur inschat dat de leerling er klaar
                            voor is.</p>
                        <h4>8. Beëindiging</h4>
                        <p>De rijschool mag de lessen stopzetten als de samenwerking niet veilig of niet werkbaar is.
                        </p>
                        <h4>9. Overmacht</h4>
                        <p>Bij ziekte, weersomstandigheden of andere onverwachte situaties mag een les worden verzet.
                        </p>
                        <h4>Toepasselijk recht</h4>
                        <p>Op deze voorwaarden is Nederlands recht van toepassing.</p>
                    </div>
                </section>
            </div>
            <section class="card">
                <div class="card-header">
                    <h3>Inschrijven voor lessen</h3>
                </div>
                <div class="card-body">
                    <?php if (isLoggedIn()): ?>
                        <p class="success-msg">
                            Je bent ingelogd. Ga naar je dashboard of log uit via de header.
                        </p>
                        <a href="<?= htmlspecialchars(dashboardUrlForRole(), ENT_QUOTES, 'UTF-8') ?>"
                            class="btn btn-primary btn-link">Naar dashboard</a>
                    <?php else: ?>
                        <p class="success-msg">
                            Plan lessen, volg je voortgang en beheer alles vanuit één app.
                            <span class="email">meld aan om verder te gaan.</span>
                        </p>
                        <a href="<?= htmlspecialchars(app_url('login.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="btn btn-primary btn-link">Naar aanmelden</a>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>
</body>

</html>