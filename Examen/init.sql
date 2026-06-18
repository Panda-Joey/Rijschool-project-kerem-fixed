-- Database-init voor Docker (schema: Stap1, data: Stap3 + testaccounts)

-- === Schema (Stap1) ===
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP SCHEMA IF EXISTS `Eend`;
CREATE SCHEMA IF NOT EXISTS `Eend`;
USE `Eend`;

DROP TABLE IF EXISTS `Autos`;
CREATE TABLE `Autos` (
  `autoID` INT(11) NOT NULL,
  `merk` VARCHAR(50) NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `kenteken` VARCHAR(15) NOT NULL,
  `transmissie` TINYINT(4) NOT NULL,
  `brandstof` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=benzine, 1=elektrisch',
  `beschikbaar` TINYINT(1) NOT NULL DEFAULT 1,
  `statusReden` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`autoID`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `instructeurs`;
CREATE TABLE `instructeurs` (
  `instructeurID` INT(11) NOT NULL,
  `voornaam` VARCHAR(50) NOT NULL,
  `tussenvoegsel` VARCHAR(30) NULL DEFAULT NULL,
  `achternaam` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `wachtwoord` VARCHAR(255) NOT NULL,
  `telefoon` VARCHAR(20) NOT NULL,
  `omschrijving` VARCHAR(255) NULL DEFAULT NULL,
  `rol` ENUM('admin', 'instructeur') NOT NULL DEFAULT 'instructeur',
  `transmissie` ENUM('schakel', 'automaat', 'beide') NOT NULL DEFAULT 'schakel',
  `afwezigheid` ENUM('beschikbaar', 'niet') NOT NULL DEFAULT 'beschikbaar',
  `afwezig_van` DATE NULL DEFAULT NULL,
  `afwezig_tot` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`instructeurID`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `instructeur_auto`;
CREATE TABLE `instructeur_auto` (
  `instructeurID` INT(11) NOT NULL,
  `autoID` INT(11) NOT NULL,
  PRIMARY KEY (`instructeurID`),
  CONSTRAINT `fk_instr_auto_instr` FOREIGN KEY (`instructeurID`) REFERENCES `instructeurs` (`instructeurID`),
  CONSTRAINT `fk_instr_auto_auto` FOREIGN KEY (`autoID`) REFERENCES `Autos` (`autoID`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `beschikbaarheid`;
CREATE TABLE `beschikbaarheid` (
  `beschikbaarheidID` INT(11) NOT NULL AUTO_INCREMENT,
  `instructeurID` INT(11) NOT NULL,
  `dagNaam` ENUM('Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag') NOT NULL,
  `beginTijd` TIME NOT NULL,
  `eindTijd` TIME NOT NULL,
  `maxLessen` INT(11) NOT NULL DEFAULT 6,
  PRIMARY KEY (`beschikbaarheidID`),
  INDEX `fk_beschikbaar_instr` (`instructeurID`),
  CONSTRAINT `fk_beschikbaar_instr` FOREIGN KEY (`instructeurID`) REFERENCES `instructeurs` (`instructeurID`)
) ENGINE=InnoDB AUTO_INCREMENT=4;

DROP TABLE IF EXISTS `studenten`;
CREATE TABLE `studenten` (
  `studentID` INT(11) NOT NULL AUTO_INCREMENT,
  `voornaam` VARCHAR(50) NOT NULL,
  `tussenvoegsel` VARCHAR(30) NULL DEFAULT NULL,
  `achternaam` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `wachtwoord` VARCHAR(255) NOT NULL,
  `telefoon` VARCHAR(20) NOT NULL,
  `beperking` TINYINT(4) NOT NULL,
  `omschrijving` VARCHAR(255) NULL DEFAULT NULL,
  `geboortedatum` DATE NOT NULL,
  `status` ENUM('pending', 'actief', 'geslaagd') NOT NULL,
  `transmissie` ENUM('schakel', 'automaat') NOT NULL DEFAULT 'schakel',
  `poging` INT(11) NULL DEFAULT NULL,
  `geslaagd` TINYINT(1) NULL DEFAULT NULL,
  PRIMARY KEY (`studentID`),
  UNIQUE INDEX `email_UNIQUE` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3;

DROP TABLE IF EXISTS `lessen`;
CREATE TABLE `lessen` (
  `lesID` INT(11) NOT NULL AUTO_INCREMENT,
  `lesDatum` DATE NOT NULL,
  `lestijd` TIME NOT NULL,
  `ophaalLocatie` VARCHAR(100) NOT NULL,
  `doel` VARCHAR(255) NOT NULL,
  `onderwerpen` VARCHAR(255) NOT NULL,
  `studentID` INT(11) NOT NULL,
  `instructeurID` INT(11) NOT NULL,
  `autoID` INT(11) NOT NULL,
  `vervallen` TINYINT(1) NOT NULL DEFAULT 0,
  `goedgekeurd` TINYINT(1) NULL DEFAULT NULL,
  `goedgekeurd_op` TIMESTAMP NULL DEFAULT NULL,
  `redenWijzig` VARCHAR(300) NULL DEFAULT NULL,
  `redenVervalt` VARCHAR(300) NULL DEFAULT NULL,
  PRIMARY KEY (`lesID`),
  INDEX `fk_lessen_studenten` (`studentID`),
  INDEX `fk_lessen_instructeurs` (`instructeurID`),
  INDEX `fk_lessen_autos` (`autoID`),
  CONSTRAINT `fk_lessen_autos` FOREIGN KEY (`autoID`) REFERENCES `Autos` (`autoID`),
  CONSTRAINT `fk_lessen_instructeurs` FOREIGN KEY (`instructeurID`) REFERENCES `instructeurs` (`instructeurID`),
  CONSTRAINT `fk_lessen_studenten` FOREIGN KEY (`studentID`) REFERENCES `studenten` (`studentID`)
) ENGINE=InnoDB AUTO_INCREMENT=6;

DROP TABLE IF EXISTS `lespakket`;
CREATE TABLE `lespakket` (
  `idlespakket` INT NOT NULL AUTO_INCREMENT,
  `naam` VARCHAR(255) NOT NULL,
  `uren` INT(20) NOT NULL,
  `prijs` DECIMAL(6,2) NOT NULL,
  PRIMARY KEY (`idlespakket`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `student_lespakket`;
CREATE TABLE `student_lespakket` (
  `studentID` INT(11) NULL,
  `idlespakket` INT NULL,
  `overige_uren` INT NULL,
  `bedrag` DECIMAL(6,2) NULL DEFAULT NULL,
  INDEX `fk_studenten_has_lespakket_lespakket1_idx` (`idlespakket`),
  INDEX `fk_studenten_has_lespakket_studenten1_idx` (`studentID`),
  CONSTRAINT `fk_studenten_has_lespakket_studenten1` FOREIGN KEY (`studentID`) REFERENCES `studenten` (`studentID`),
  CONSTRAINT `fk_studenten_has_lespakket_lespakket1` FOREIGN KEY (`idlespakket`) REFERENCES `lespakket` (`idlespakket`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `studenten_has_instructeurs`;
CREATE TABLE `studenten_has_instructeurs` (
  `studentID` INT(11) NOT NULL,
  `instructeurID` INT(11) NOT NULL,
  PRIMARY KEY (`studentID`, `instructeurID`),
  INDEX `fk_studenten_has_instructeurs_instructeurs1_idx` (`instructeurID`),
  INDEX `fk_studenten_has_instructeurs_studenten1_idx` (`studentID`),
  CONSTRAINT `fk_studenten_has_instructeurs_studenten1` FOREIGN KEY (`studentID`) REFERENCES `studenten` (`studentID`),
  CONSTRAINT `fk_studenten_has_instructeurs_instructeurs1` FOREIGN KEY (`instructeurID`) REFERENCES `instructeurs` (`instructeurID`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `meldingen`;
CREATE TABLE `meldingen` (
  `meldingID` INT(11) NOT NULL AUTO_INCREMENT,
  `titel` VARCHAR(100) NOT NULL,
  `bericht` VARCHAR(500) NOT NULL,
  `ontvanger_type` ENUM('iedereen', 'alle_studenten', 'alle_instructeurs', 'student', 'instructeur', 'admin') NOT NULL,
  `ontvanger_id` INT(11) NULL DEFAULT NULL,
  `datum_gemaakt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`meldingID`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `bijkopen`;
CREATE TABLE `bijkopen` (
  `idbijkopen` INT(11) NOT NULL AUTO_INCREMENT,
  `naam` VARCHAR(255) NOT NULL,
  `uren` INT(11) NOT NULL,
  `prijs` DECIMAL(6,2) NOT NULL,
  `studentID` INT(11) NOT NULL,
  PRIMARY KEY (`idbijkopen`),
  INDEX `fk_bijkopen_studenten1_idx` (`studentID`),
  CONSTRAINT `fk_bijkopen_studenten1` FOREIGN KEY (`studentID`) REFERENCES `studenten` (`studentID`)
) ENGINE=InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- === Seed (Stap3) ===
USE `Eend`;

INSERT INTO `Autos` (`autoID`, `merk`, `type`, `kenteken`, `transmissie`, `brandstof`, `beschikbaar`, `statusReden`) VALUES
(1, 'Tesla', 'Model 3', 'K-987-ZZ', 0, 1, 1, NULL),
(2, 'Volkswagen', 'ID.3', 'X-456-BB', 0, 1, 1, NULL),
(3, 'Ford', 'Fiesta', 'G-123-AA', 1, 0, 1, NULL);

INSERT INTO `instructeurs` (`instructeurID`, `voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`, `telefoon`, `omschrijving`, `rol`, `transmissie`) VALUES
(10, 'Henk', NULL, 'De Vries', 'henk@rijschooleend.nl', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0612345678', 'Ervaren instructeur voor schakelauto\'s.', 'instructeur', 'schakel'),
(11, 'Anja', 'van', 'Dijk', 'anja@rijschooleend.nl', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0623456789', 'Specialist in faalangst en automaatrijden.', 'instructeur', 'automaat'),
(12, 'Mark', NULL, 'Bakker', 'mark@rijschooleend.nl', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0634567890', 'Eigenaar en hoofd administratie.', 'admin', 'beide');

INSERT INTO `instructeur_auto` (`instructeurID`, `autoID`) VALUES
(10, 3),
(11, 2);

INSERT INTO `beschikbaarheid` (`instructeurID`, `dagNaam`, `beginTijd`, `eindTijd`, `maxLessen`) VALUES
(10, 'Maandag', '08:00:00', '17:00:00', 6),
(10, 'Dinsdag', '08:00:00', '17:00:00', 6),
(11, 'Woensdag', '09:00:00', '16:00:00', 5),
(11, 'Donderdag', '09:00:00', '21:00:00', 8),
(12, 'Vrijdag', '08:00:00', '12:00:00', 3);

INSERT INTO `studenten` (`studentID`, `voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`, `telefoon`, `beperking`, `omschrijving`, `geboortedatum`, `status`, `transmissie`) VALUES
(1, 'Daan', NULL, 'Jansen', 'daan.jansen@example.com', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0645678901', 0, NULL, '2006-05-14', 'actief', 'schakel'),
(2, 'Lisa', 'de', 'Jong', 'lisa.dejong@example.com', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0656789012', 0, 'Wil graag snel opgaan', '2005-11-23', 'actief', 'automaat'),
(3, 'Bram', 'van der', 'Meer', 'bram.vdmeer@example.com', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0667890123', 1, 'Heeft lichte ADHD', '2007-01-08', 'pending', 'automaat'),
(4, 'Emma', NULL, 'Smit', 'emma.smit@example.com', '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW', '0678901234', 0, 'Heeft al elders rijlessen gehad', '2004-03-30', 'geslaagd', 'schakel');

INSERT INTO `lespakket` (`naam`, `uren`, `prijs`) VALUES
('Instapprofiel', 20, 1150.00),
('Gemiddeld Pakket', 35, 1950.00),
('Top Pakket (Inclusief TTT)', 45, 2500.00);

INSERT INTO `student_lespakket` (`studentID`, `idlespakket`, `overige_uren`) VALUES
(1, 2, 15),
(2, 1, 4),
(3, 3, 45);

INSERT INTO `studenten_has_instructeurs` (`studentID`, `instructeurID`) VALUES
(1, 10),
(2, 11),
(3, 11);

INSERT INTO `meldingen` (`titel`, `bericht`, `ontvanger_type`, `ontvanger_id`) VALUES
('Welkom!', 'Welkom op het rijschoolportaal. Bekijk je lessen en plan nieuwe in.', 'student', NULL),
('Team update', 'Controleer regelmatig je rooster en beschikbaarheid.', 'instructeur', NULL),
('Algemene mededeling', 'De rijschool is gesloten op feestdagen.', 'iedereen', NULL);

INSERT INTO `lessen` (`lesDatum`, `lestijd`, `ophaalLocatie`, `doel`, `onderwerpen`, `studentID`, `instructeurID`, `autoID`, `vervallen`, `redenWijzig`, `redenVervalt`) VALUES
('2026-06-10', '09:00:00', 'Station Utrecht Centraal', 'Koppeling beheersen', 'Wegrijden, opschakelen, stoppen', 1, 10, 1, 0, NULL, NULL),
('2026-06-11', '10:30:00', 'Janskerkhof 12, Utrecht', 'Bijzondere verrichtingen', 'Fileparkeren, hellingproef', 2, 11, 2, 0, NULL, NULL),
('2026-06-12', '14:00:00', 'Station Utrecht Centraal', 'Snelweg rijden', 'Invoegen, ritsen, inhalen', 1, 10, 1, 0, NULL, NULL),
('2026-06-02', '11:00:00', 'Thuisadres student', 'Intake les', 'Rijvaardigheid inschatten', 3, 11, 2, 1, NULL, 'Student was ziek gemeld');

-- Testaccounts uit src/login.php (wachtwoord: 123456)
UPDATE `instructeurs` SET
  `email` = 'piet@test.nl',
  `voornaam` = 'Piet',
  `achternaam` = 'Pietersen',
  `wachtwoord` = '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW'
WHERE `instructeurID` = 10;

UPDATE `studenten` SET
  `email` = 'jan@test.nl',
  `voornaam` = 'Jan',
  `achternaam` = 'Jansen',
  `status` = 'actief',
  `wachtwoord` = '$2y$10$oTCSp7GRKlyBeS2Ptn69iOEIlwfShKpBs5HwXHrxmmjbmAC2xo3lW'
WHERE `studentID` = 1;
