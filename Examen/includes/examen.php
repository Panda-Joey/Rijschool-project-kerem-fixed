<?php

function ensureExamenSchema(mysqli $conn): void
{
    $check = $conn->query("SHOW TABLES LIKE 'examens'");
    if ($check && $check->num_rows > 0) {
        return;
    }

    $r = $conn->query("SHOW COLUMNS FROM studenten LIKE 'poging'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE studenten ADD COLUMN poging INT(11) NULL DEFAULT NULL AFTER status");
    }

    $r = $conn->query("SHOW COLUMNS FROM studenten LIKE 'geslaagd'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE studenten ADD COLUMN geslaagd TINYINT(1) NULL DEFAULT NULL AFTER poging");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS examens (
            examID INT(11) NOT NULL AUTO_INCREMENT,
            studentID INT(11) NOT NULL,
            instructeurID INT(11) NOT NULL,
            examDatum DATE NOT NULL,
            examTijd TIME NOT NULL,
            locatie VARCHAR(100) NOT NULL,
            opmerking VARCHAR(300) NULL DEFAULT NULL,
            poging INT(11) NOT NULL DEFAULT 1,
            uitslag ENUM('wachten', 'geslaagd', 'gezakt') NOT NULL DEFAULT 'wachten',
            PRIMARY KEY (examID),
            INDEX fk_examens_student (studentID),
            INDEX fk_examens_instructeur (instructeurID),
            CONSTRAINT fk_examens_student FOREIGN KEY (studentID) REFERENCES studenten (studentID),
            CONSTRAINT fk_examens_instructeur FOREIGN KEY (instructeurID) REFERENCES instructeurs (instructeurID)
        ) ENGINE=InnoDB
    ");
}
