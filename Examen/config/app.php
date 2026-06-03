<?php

const APP_NAME = 'Rijschool';

/** Sessie verloopt na 15 minuten zonder activiteit */
const SESSION_LIFETIME_SECONDS = 15 * 60;

const ROLES = [
    'leerling'    => 'Leerling',
    'instructeur' => 'Instructeur',
    'eigenaar'    => 'Eigenaar',
];

// Demo-accounts — later uit database; rol hoort bij het account, niet bij een knop
const DEMO_PASSWORD_HASH = '$2y$10$0ABjfOmLaElKfi6ZMGUdFe71uGEc7iRe4zXa9ovSZ2uItbONXRTYm';

const DEMO_USERS = [
    'leerling@rijschool.nl'    => ['role' => 'leerling'],
    'instructeur@rijschool.nl' => ['role' => 'instructeur'],
    'eigenaar@rijschool.nl'    => ['role' => 'eigenaar'],
];

// Zet op false vóór livegang
const ENABLE_TEST_LOGIN = true;
