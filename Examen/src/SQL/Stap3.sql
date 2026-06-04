USE `Eend`;

-- 1. Dummy data voor `Autos`
-- Let op: autoID heeft geen AUTO_INCREMENT in jouw script, dus deze vullen we handmatig in.
INSERT INTO `Autos` (`autoID`, `merk`, `type`, `kenteken`, `transmissie`, `brandstof`) VALUES
(1, 'Tesla', 'Model 3', 'K-987-ZZ', 0, 1),           -- elektrisch, automaat
(2, 'Volkswagen', 'ID.3', 'X-456-BB', 0, 1),           -- elektrisch, automaat
(3, 'Ford', 'Fiesta', 'G-123-AA', 1, 0);              -- benzine, handgeschakeld

-- 2. Dummy data voor `instructeurs`
-- Let op: instructeurID heeft geen AUTO_INCREMENT in jouw script.
INSERT INTO `instructeurs` (`instructeurID`, `voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`, `telefoon`, `omschrijving`, `rol`) VALUES
(10, 'Henk', NULL, 'De Vries', 'henk@rijschooleend.nl', '$2y$10$abcdefghijklmnopqrstuv', '0612345678', 'Ervaren instructeur voor schakelauto\'s.', 'instructeur'),
(11, 'Anja', 'van', 'Dijk', 'anja@rijschooleend.nl', '$2y$10$abcdefghijklmnopqrstuv', '0623456789', 'Specialist in faalangst en automaatrijden.', 'instructeur'),
(12, 'Mark', NULL, 'Bakker', 'mark@rijschooleend.nl', '$2y$10$abcdefghijklmnopqrstuv', '0634567890', 'Eigenaar en hoofd administratie.', 'admin');

-- 3. Dummy data voor `beschikbaarheid`
-- Heeft wel AUTO_INCREMENT voor beschikbaarheidID
INSERT INTO `beschikbaarheid` (`instructeurID`, `dagNaam`, `beginTijd`, `eindTijd`, `maxLessen`) VALUES
(10, 'Maandag', '08:00:00', '17:00:00', 6),
(10, 'Dinsdag', '08:00:00', '17:00:00', 6),
(11, 'Woensdag', '09:00:00', '16:00:00', 5),
(11, 'Donderdag', '09:00:00', '21:00:00', 8),
(12, 'Vrijdag', '08:00:00', '12:00:00', 3);

-- 4. Dummy data voor `studenten`
-- Heeft AUTO_INCREMENT voor studentID
INSERT INTO `studenten` (`voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`, `telefoon`, `beperking`, `omschrijving`, `geboortedatum`, `status`) VALUES
('Daan', NULL, 'Jansen', 'daan.jansen@example.com', '$2y$10$securepassword123', '0645678901', 0, NULL, '2006-05-14', 'actief'),
('Lisa', 'de', 'Jong', 'lisa.dejong@example.com', '$2y$10$securepassword456', '0656789012', 0, 'Wil graag snel opgaan', '2005-11-23', 'actief'),
('Bram', 'van der', 'Meer', 'bram.vdmeer@example.com', '$2y$10$securepassword789', '0667890123', 1, 'Heeft lichte ADHD', '2007-01-08', 'pending'),
('Emma', NULL, 'Smit', 'emma.smit@example.com', '$2y$10$securepassword012', '0678901234', 0, 'Heeft al elders rijlessen gehad', '2004-03-30', 'geslaagd');

-- 5. Dummy data voor `lespakket`
-- Heeft AUTO_INCREMENT voor idlespakket
INSERT INTO `lespakket` (`naam`, `uren`, `prijs`) VALUES
('Instapprofiel', 20, 1150.00),
('Gemiddeld Pakket', 35, 1950.00),
('Top Pakket (Inclusief TTT)', 45, 2500.00);

-- 6. Dummy data voor `student_lespakket`
-- Koppelt studentID (Gegenereerd: 1, 2, 3) aan idlespakket (Gegenereerd: 1, 2, 3)
INSERT INTO `student_lespakket` (`studentID`, `idlespakket`, `overige_uren`) VALUES
(1, 2, 15), -- Daan heeft het Gemiddelde Pakket, nog 15 uur over
(2, 1, 4),  -- Lisa heeft het Instapprofiel, nog 4 uur over
(3, 3, 45); -- Bram is nieuw (pending) en heeft het Top Pakket nog volledig open staan

-- 7. Dummy data voor `studenten_has_instructeurs`
-- Koppelt de studenten aan vaste instructeurs
INSERT INTO `studenten_has_instructeurs` (`studentID`, `instructeurID`) VALUES
(1, 10), -- Daan lest bij Henk
(2, 11), -- Lisa lest bij Anja
(3, 11); -- Bram lest bij Anja

-- 8. Dummy data voor `lessen`
-- Koppelt studenten, instructeurs en auto's aan elkaar
INSERT INTO `lessen` (`lesDatum`, `lestijd`, `ophaalLocatie`, `doel`, `onderwerpen`, `studentID`, `instructeurID`, `autoID`, `vervallen`, `redenWijzig`, `redenVervalt`) VALUES
('2026-06-10', '09:00:00', 'Station Utrecht Centraal', 'Koppeling beheersen', 'Wegrijden, opschakelen, stoppen', 1, 10, 1, 0, NULL, NULL),
('2026-06-11', '10:30:00', 'Janskerkhof 12, Utrecht', 'Bijzondere verrichtingen', 'Fileparkeren, hellingproef', 2, 11, 2, 0, NULL, NULL),
('2026-06-12', '14:00:00', 'Station Utrecht Centraal', 'Snelweg rijden', 'Invoegen, ritsen, inhalen', 1, 10, 1, 0, NULL, NULL),
('2026-06-02', '11:00:00', 'Thuisadres student', 'Intake les', 'Rijvaardigheid inschatten', 3, 11, 2, 1, NULL, 'Student was ziek gemeld');