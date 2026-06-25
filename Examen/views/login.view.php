<?php
/**
 * Login-scherm — pas hier het uiterlijk aan.
 * Logica & accounts: src/login.php, config/app.php
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen — <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/login.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php $active = 'login'; include __DIR__ . '/partials/header.php'; ?>

    <main class="page page--with-header">
        <div class="card">
            <div class="card-header">
                <h1>Inloggen</h1>
                <p>Welkom bij <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="card-body">
                    <div class="error-slot" aria-live="polite">
                        <div class="error<?= $error === '' ? ' error--hidden' : '' ?>">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <form method="post" action="<?= htmlspecialchars(login_url(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="field">
                            <label for="email">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required
                                autocomplete="email"
                            >
                        </div>
                        <div class="field">
                            <label for="password">Wachtwoord</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">Inloggen</button>
                    </form>

                    <a href="<?= htmlspecialchars(src_url('homepage.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-link">Terug naar homepage</a>

                    <?php if (ENABLE_TEST_LOGIN): ?>
                        <div class="test-box">
                            <p class="test-box__title">Test — direct inloggen</p>
                            <div class="test-box__buttons">
                                <?php
                                $testRoles = [
                                    'leerling'    => 'Log in als Leerling',
                                    'instructeur' => 'Log in als Instructeur',
                                    'eigenaar'    => 'Log in als Eigenaar',
                                ];
                                foreach ($testRoles as $role => $label):
                                    $testEmail = null;
                                    foreach (DEMO_USERS as $email => $user) {
                                        if (($user['role'] ?? '') === $role) {
                                            $testEmail = $email;
                                            break;
                                        }
                                    }
                                    if ($testEmail === null) {
                                        continue;
                                    }
                                ?>
                                <div class="test-user">
                                    <a href="<?= htmlspecialchars(login_url() . '?test=' . $role, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-test"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                                    <div class="test-user__creds">
                                        <span><strong>Email:</strong> <code><?= htmlspecialchars($testEmail, ENT_QUOTES, 'UTF-8') ?></code></span>
                                        <span><strong>Wachtwoord:</strong> <code><?= htmlspecialchars(DEMO_PASSWORD, ENT_QUOTES, 'UTF-8') ?></code></span>
                                        <button type="button" class="copy-btn" data-copy="<?= htmlspecialchars($testEmail, ENT_QUOTES, 'UTF-8') ?>">Kopieer mail</button>
                                        <button type="button" class="copy-btn" data-copy="<?= htmlspecialchars(DEMO_PASSWORD, ENT_QUOTES, 'UTF-8') ?>">Kopieer wachtwoord</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if (ENABLE_TEST_LOGIN): ?>
        <script>
            document.addEventListener('click', async (e) => {
                const btn = e.target && e.target.closest && e.target.closest('.copy-btn');
                if (!btn) return;
                const text = btn.getAttribute('data-copy') || '';
                try {
                    await navigator.clipboard.writeText(text);
                    const old = btn.textContent;
                    btn.textContent = 'Gekopieerd';
                    setTimeout(() => (btn.textContent = old), 900);
                } catch (_) {
                    // clipboard kan geblokkeerd zijn; dan doet de knop niets
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
