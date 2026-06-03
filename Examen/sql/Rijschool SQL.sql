-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Gegenereerd op: 28 mei 2026 om 07:57
-- Serverversie: 12.0.2-MariaDB-ubu2404
-- PHP-versie: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `Eend`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Autos`
--

CREATE TABLE `Autos` (
  `autoID` int(11) NOT NULL,
  `merk` varchar(50) NOT NULL,
  `type` varchar(100) NOT NULL,
  `kenteken` varchar(15) NOT NULL,
  `transmissie` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Autos`
--

INSERT INTO `Autos` (`autoID`, `merk`, `type`, `kenteken`, `transmissie`) VALUES
(1, 'Volkswagen', 'Golf', 'AB-123-CD', 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `beschikbaarheid`
--

CREATE TABLE `beschikbaarheid` (
  `beschikbaarheidID` int(11) NOT NULL,
  `instructeurID` int(11) NOT NULL,
  `dagNaam` enum('Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag') NOT NULL,
  `beginTijd` time NOT NULL,
  `eindTijd` time NOT NULL,
  `maxLessen` int(11) NOT NULL DEFAULT 6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `beschikbaarheid`
--

INSERT INTO `beschikbaarheid` (`beschikbaarheidID`, `instructeurID`, `dagNaam`, `beginTijd`, `eindTijd`, `maxLessen`) VALUES
(1, 1, 'Maandag', '08:00:00', '18:00:00', 6),
(2, 1, 'Woensdag', '08:00:00', '18:00:00', 6),
(3, 1, 'Vrijdag', '08:00:00', '18:00:00', 6);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `instructeurs`
--

CREATE TABLE `instructeurs` (
  `instructeurID` int(11) NOT NULL,
  `voornaam` varchar(50) NOT NULL,
  `tussenvoegsel` varchar(30) DEFAULT NULL,
  `achternaam` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `wachtwoord` varchar(255) NOT NULL,
  `telefoon` varchar(20) NOT NULL,
  `omschrijving` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `instructeurs`
--

INSERT INTO `instructeurs` (`instructeurID`, `voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`, `telefoon`, `omschrijving`) VALUES
(1, 'Piet', NULL, 'Pietersen', 'piet@test.nl', '123456', '0687654321', 'Ervaren instructeur');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `lessen`
--

CREATE TABLE `lessen` (
  `lesID` int(11) NOT NULL,
  `lesDatum` date NOT NULL,
  `lestijd` time NOT NULL,
  `ophaalLocatie` varchar(100) NOT NULL,
  `doel` varchar(255) NOT NULL,
  `onderwerpen` varchar(255) NOT NULL,
  `studentID` int(11) NOT NULL,
  `instructeurID` int(11) NOT NULL,
  `autoID` int(11) NOT NULL,
  `vervallen` tinyint(1) NOT NULL DEFAULT 0,
  `redenWijzig` varchar(300) DEFAULT NULL,
  `redenVervalt` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `lessen`
--

INSERT INTO `lessen` (`lesID`, `lesDatum`, `lestijd`, `ophaalLocatie`, `doel`, `onderwerpen`, `studentID`, `instructeurID`, `autoID`, `vervallen`, `redenWijzig`, `redenVervalt`) VALUES
(1, '2026-05-14', '10:00:00', 'Rotterdam Centrum', 'Rotondes', 'Rotondes oefenen', 1, 1, 1, 1, NULL, 'ziek'),
(2, '2026-05-16', '17:10:00', 'Delft Station', 'Snelweg', 'Invoegen en uitvoegen', 1, 1, 1, 0, 'kaas', NULL),
(3, '2026-05-20', '09:00:00', 'Den Haag CS', 'Parkeren', 'Fileparkeren', 1, 1, 1, 0, NULL, NULL),
(4, '2026-05-23', '11:00:00', 'Leiden Centrum', 'Voorrang', 'Voorrangsregels', 1, 1, 1, 0, NULL, NULL),
(5, '2026-06-08', '09:00:00', 'stad', 'Snelweg', '', 1, 1, 1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `studenten`
--

CREATE TABLE `studenten` (
  `studentID` int(11) NOT NULL,
  `voornaam` varchar(50) NOT NULL,
  `tussenvoegsel` varchar(30) DEFAULT NULL,
  `achternaam` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `wachtwoord` varchar(255) NOT NULL,
  `telefoon` varchar(20) NOT NULL,
  `beperking` tinyint(4) NOT NULL,
  `omschrijving` varchar(255) DEFAULT NULL,
  `geboortedatum` date NOT NULL,
  `lesUren` int(11) DEFAULT NULL,
  `lesPakket` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `studenten`
--

INSERT INTO `studenten` (`studentID`, `voornaam`, `tussenvoegsel`, `achternaam`, `email`, `wachtwoord`, `telefoon`, `beperking`, `omschrijving`, `geboortedatum`, `lesUren`, `lesPakket`) VALUES
(1, 'Jan', NULL, 'Jansen', 'jan@test.nl', '123456', '0612345678', 0, NULL, '2000-05-10', 20, 'Pakket A');

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `Autos`
--
ALTER TABLE `Autos`
  ADD PRIMARY KEY (`autoID`);

--
-- Indexen voor tabel `beschikbaarheid`
--
ALTER TABLE `beschikbaarheid`
  ADD PRIMARY KEY (`beschikbaarheidID`),
  ADD KEY `fk_beschikbaar_instr` (`instructeurID`);

--
-- Indexen voor tabel `instructeurs`
--
ALTER TABLE `instructeurs`
  ADD PRIMARY KEY (`instructeurID`);

--
-- Indexen voor tabel `lessen`
--
ALTER TABLE `lessen`
  ADD PRIMARY KEY (`lesID`),
  ADD KEY `fk_lessen_studenten` (`studentID`),
  ADD KEY `fk_lessen_instructeurs` (`instructeurID`),
  ADD KEY `fk_lessen_autos` (`autoID`);

--
-- Indexen voor tabel `studenten`
--
ALTER TABLE `studenten`
  ADD PRIMARY KEY (`studentID`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `beschikbaarheid`
--
ALTER TABLE `beschikbaarheid`
  MODIFY `beschikbaarheidID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT voor een tabel `lessen`
--
ALTER TABLE `lessen`
  MODIFY `lesID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `beschikbaarheid`
--
ALTER TABLE `beschikbaarheid`
  ADD CONSTRAINT `fk_beschikbaar_instr` FOREIGN KEY (`instructeurID`) REFERENCES `instructeurs` (`instructeurID`);

--
-- Beperkingen voor tabel `lessen`
--
ALTER TABLE `lessen`
  ADD CONSTRAINT `fk_lessen_autos` FOREIGN KEY (`autoID`) REFERENCES `Autos` (`autoID`),
  ADD CONSTRAINT `fk_lessen_instructeurs` FOREIGN KEY (`instructeurID`) REFERENCES `instructeurs` (`instructeurID`),
  ADD CONSTRAINT `fk_lessen_studenten` FOREIGN KEY (`studentID`) REFERENCES `studenten` (`studentID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION 