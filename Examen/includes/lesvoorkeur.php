<?php

function ensureLesvoorkeurSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $r = $conn->query("SHOW COLUMNS FROM studenten LIKE 'transmissie'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE studenten ADD COLUMN transmissie ENUM('schakel','automaat') NOT NULL DEFAULT 'schakel' AFTER status");
    }

    $r = $conn->query("SHOW COLUMNS FROM instructeurs LIKE 'transmissie'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE instructeurs ADD COLUMN transmissie ENUM('schakel','automaat','beide') NOT NULL DEFAULT 'schakel' AFTER rol");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS instructeur_auto (
            instructeurID INT(11) NOT NULL,
            autoID INT(11) NOT NULL,
            PRIMARY KEY (instructeurID),
            CONSTRAINT fk_instr_auto_instr FOREIGN KEY (instructeurID) REFERENCES instructeurs (instructeurID),
            CONSTRAINT fk_instr_auto_auto FOREIGN KEY (autoID) REFERENCES Autos (autoID)
        ) ENGINE=InnoDB
    ");
}

function lesvoorkeurLabel(string $voorkeur): string
{
    return match ($voorkeur) {
        'automaat' => 'Automaat',
        'beide' => 'Beide',
        default => 'Schakel',
    };
}

/** Autos.transmissie: 0 = automaat, 1 = handgeschakeld */
function autoMatchesLesvoorkeur(string $voorkeur, int $autoTransmissie): bool
{
    return match ($voorkeur) {
        'schakel' => $autoTransmissie === 1,
        'automaat' => $autoTransmissie === 0,
        'beide' => true,
        default => false,
    };
}

function studentMatchesInstructeur(string $studentVoorkeur, string $instrVoorkeur): bool
{
    if ($instrVoorkeur === 'beide') {
        return in_array($studentVoorkeur, ['schakel', 'automaat'], true);
    }

    return $studentVoorkeur === $instrVoorkeur;
}

function syncInstructeurAuto(mysqli $conn, int $instructeurID, string $transmissie): void
{
    if (!in_array($transmissie, ['schakel', 'automaat', 'beide'], true)) {
        return;
    }

    $sql = "SELECT autoID FROM Autos WHERE beschikbaar = 1";
    if ($transmissie === 'schakel') {
        $sql .= ' AND transmissie = 1';
    } elseif ($transmissie === 'automaat') {
        $sql .= ' AND transmissie = 0';
    }
    $sql .= ' ORDER BY autoID LIMIT 1';

    $r = $conn->query($sql);
    if (!$r || $r->num_rows === 0) {
        $conn->query("DELETE FROM instructeur_auto WHERE instructeurID = $instructeurID");
        return;
    }

    $autoID = (int) $r->fetch_assoc()['autoID'];
    $stmt = $conn->prepare('
        INSERT INTO instructeur_auto (instructeurID, autoID) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE autoID = VALUES(autoID)
    ');
    $stmt->bind_param('ii', $instructeurID, $autoID);
    $stmt->execute();
    $stmt->close();
}
