-- Voeg toe na import van team-dump als eigenaar-testlogin nodig is.
-- Wachtwoord: wachtwoord123

USE `Eend`;

INSERT INTO `instructeurs` (
    `instructeurID`, `voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`,
    `telefoon`, `omschrijving`, `transmissie`, `rol`, `afwezigheid`
) VALUES (
    3, 'Admin', NULL, 'Portaal', 'admin@rijschooleend.nl',
    '$2y$10$73PovOrwuoysRFRxkGX/C.R2FzkAb6AEEuWcBNXM3zZeeYmQIyRbC',
    '0600000001', 'Beheerder rijschoolportaal', 'beide', 'admin', 'beschikbaar'
)
ON DUPLICATE KEY UPDATE
    `email` = VALUES(`email`),
    `wachtwoord` = VALUES(`wachtwoord`),
    `rol` = VALUES(`rol`);
