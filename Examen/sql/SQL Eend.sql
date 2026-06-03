-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema Eend
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema Eend
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `Eend` ;
USE `Eend` ;

-- -----------------------------------------------------
-- Table `Eend`.`Autos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Eend`.`Autos` (
  `autoID` INT(11) NOT NULL,
  `merk` VARCHAR(50) NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `kenteken` VARCHAR(15) NOT NULL,
  `transmissie` TINYINT(4) NOT NULL,
  PRIMARY KEY (`autoID`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Eend`.`instructeurs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Eend`.`instructeurs` (
  `instructeurID` INT(11) NOT NULL,
  `voornaam` VARCHAR(50) NOT NULL,
  `tussenvoegsel` VARCHAR(30) NULL DEFAULT NULL,
  `achternaam` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `wachtwoord` VARCHAR(255) NOT NULL,
  `telefoon` VARCHAR(20) NOT NULL,
  `omschrijving` VARCHAR(255) NULL DEFAULT NULL,
  `rol` ENUM('admin', 'instructeur') NOT NULL DEFAULT 'instructeur',
  PRIMARY KEY (`instructeurID`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Eend`.`beschikbaarheid`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Eend`.`beschikbaarheid` (
  `beschikbaarheidID` INT(11) NOT NULL AUTO_INCREMENT,
  `instructeurID` INT(11) NOT NULL,
  `dagNaam` ENUM('Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag') NOT NULL,
  `beginTijd` TIME NOT NULL,
  `eindTijd` TIME NOT NULL,
  `maxLessen` INT(11) NOT NULL DEFAULT 6,
  PRIMARY KEY (`beschikbaarheidID`),
  INDEX `fk_beschikbaar_instr` (`instructeurID` ASC) VISIBLE,
  CONSTRAINT `fk_beschikbaar_instr`
    FOREIGN KEY (`instructeurID`)
    REFERENCES `Eend`.`instructeurs` (`instructeurID`))
ENGINE = InnoDB
AUTO_INCREMENT = 4;


-- -----------------------------------------------------
-- Table `Eend`.`studenten`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Eend`.`studenten` (
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
  `lesUren` INT(11) NULL DEFAULT NULL,
  `lesPakket` VARCHAR(50) NULL DEFAULT NULL,
  PRIMARY KEY (`studentID`))
ENGINE = InnoDB
AUTO_INCREMENT = 3;


-- -----------------------------------------------------
-- Table `Eend`.`lessen`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Eend`.`lessen` (
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
  `redenWijzig` VARCHAR(300) NULL DEFAULT NULL,
  `redenVervalt` VARCHAR(300) NULL DEFAULT NULL,
  PRIMARY KEY (`lesID`),
  INDEX `fk_lessen_studenten` (`studentID` ASC) VISIBLE,
  INDEX `fk_lessen_instructeurs` (`instructeurID` ASC) VISIBLE,
  INDEX `fk_lessen_autos` (`autoID` ASC) VISIBLE,
  CONSTRAINT `fk_lessen_autos`
    FOREIGN KEY (`autoID`)
    REFERENCES `Eend`.`Autos` (`autoID`),
  CONSTRAINT `fk_lessen_instructeurs`
    FOREIGN KEY (`instructeurID`)
    REFERENCES `Eend`.`instructeurs` (`instructeurID`),
  CONSTRAINT `fk_lessen_studenten`
    FOREIGN KEY (`studentID`)
    REFERENCES `Eend`.`studenten` (`studentID`))
ENGINE = InnoDB
AUTO_INCREMENT = 6;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
