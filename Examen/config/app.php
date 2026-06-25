<?php

const APP_NAME = 'Vierkante Wielen';

/** Sessie verloopt na 15 minuten zonder activiteit */
const SESSION_LIFETIME_SECONDS = 15 * 60;

const ROLES = [
    'leerling'    => 'Leerling',
    'instructeur' => 'Instructeur',
    'eigenaar'    => 'Eigenaar',
];

// Demo-accounts — koppel aan userID in database (zie init.sql / team-dump)
const DEMO_PASSWORD = 'wachtwoord123';
const DEMO_PASSWORD_HASH = '$2y$10$73PovOrwuoysRFRxkGX/C.R2FzkAb6AEEuWcBNXM3zZeeYmQIyRbC';

const DEMO_USERS = [
    'sharanprive67@gmail.com' => [
        'role'   => 'leerling',
        'userID' => 1,
        'naam'   => 'Sharan Zwart',
    ],
    'kerem@rijschooleend.nl' => [
        'role'   => 'instructeur',
        'userID' => 1,
        'naam'   => 'Kerem Blank',
    ],
    'admin@rijschooleend.nl' => [
        'role'   => 'eigenaar',
        'userID' => 3,
        'naam'   => 'Admin Portaal',
    ],
];

// Zet op false vóór livegang
const ENABLE_TEST_LOGIN = true;
