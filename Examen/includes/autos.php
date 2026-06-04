<?php

function ensureAutosAvailabilityColumns(mysqli $conn): void
{
    $check = $conn->query("SHOW COLUMNS FROM Autos LIKE 'beschikbaar'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE Autos ADD COLUMN beschikbaar TINYINT(1) NOT NULL DEFAULT 1");
        $conn->query("ALTER TABLE Autos ADD COLUMN statusReden VARCHAR(255) NULL DEFAULT NULL");
    }

    $brandstof = $conn->query("SHOW COLUMNS FROM Autos LIKE 'brandstof'");
    if ($brandstof && $brandstof->num_rows === 0) {
        $conn->query("ALTER TABLE Autos ADD COLUMN brandstof TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=benzine, 1=elektrisch'");
    }
}

function transmissieLabel(int $transmissie): string
{
    return $transmissie ? 'Handgeschakeld' : 'Automaat';
}

function brandstofLabel(int $brandstof): string
{
    return $brandstof ? 'Elektrisch' : 'Benzine';
}

function autoLabel(array $auto): string
{
    return $auto['merk'] . ' ' . $auto['type']
        . ' (' . $auto['kenteken'] . ')'
        . ' · ' . brandstofLabel((int) ($auto['brandstof'] ?? 0))
        . ' · ' . transmissieLabel((int) ($auto['transmissie'] ?? 0));
}

function fetchAllAutos(mysqli $conn): array
{
    ensureAutosAvailabilityColumns($conn);

    $autos = [];
    $result = $conn->query("SELECT * FROM Autos ORDER BY merk, type");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $autos[] = $row;
        }
    }

    return $autos;
}

function nextAutoId(mysqli $conn): int
{
    $row = $conn->query("SELECT COALESCE(MAX(autoID), 0) + 1 AS volgende FROM Autos")->fetch_assoc();

    return (int) ($row['volgende'] ?? 1);
}

function kentekenBestaat(mysqli $conn, string $kenteken, int $excludeId = 0): bool
{
    $stmt = $conn->prepare("SELECT autoID FROM Autos WHERE kenteken = ? AND autoID <> ? LIMIT 1");
    $stmt->bind_param("si", $kenteken, $excludeId);
    $stmt->execute();
    $bestaat = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $bestaat;
}

/** @return string|null Foutmelding of null bij succes */
function insertAuto(mysqli $conn, string $merk, string $type, string $kenteken, int $transmissie, int $brandstof): ?string
{
    ensureAutosAvailabilityColumns($conn);

    $merk = trim($merk);
    $type = trim($type);
    $kenteken = trim($kenteken);

    if ($merk === '' || $type === '' || $kenteken === '') {
        return 'Vul merk, type en kenteken in.';
    }
    if (strlen($kenteken) > 15) {
        return 'Kenteken is te lang (max. 15 tekens).';
    }
    if ($transmissie !== 0 && $transmissie !== 1) {
        return 'Kies een geldige transmissie.';
    }
    if ($brandstof !== 0 && $brandstof !== 1) {
        return 'Kies benzine of elektrisch.';
    }
    if (kentekenBestaat($conn, $kenteken)) {
        return 'Dit kenteken bestaat al.';
    }

    $autoID = nextAutoId($conn);
    $beschikbaar = 1;
    $stmt = $conn->prepare(
        "INSERT INTO Autos (autoID, merk, type, kenteken, transmissie, brandstof, beschikbaar, statusReden)
         VALUES (?, ?, ?, ?, ?, ?, ?, NULL)"
    );
    $stmt->bind_param("isssiii", $autoID, $merk, $type, $kenteken, $transmissie, $brandstof, $beschikbaar);

    if (!$stmt->execute()) {
        $stmt->close();
        return 'Auto toevoegen mislukt.';
    }
    $stmt->close();

    return null;
}

/** @return string|null Foutmelding of null bij succes */
function deleteAuto(mysqli $conn, int $autoID): ?string
{
    if ($autoID <= 0) {
        return 'Ongeldige auto.';
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM lessen WHERE autoID = ?");
    $stmt->bind_param("i", $autoID);
    $stmt->execute();
    $aantal = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($aantal > 0) {
        return "Deze auto kan niet worden verwijderd: er zijn $aantal les(sen) gekoppeld. Zet de auto op niet beschikbaar.";
    }

    $stmt = $conn->prepare("DELETE FROM Autos WHERE autoID = ?");
    $stmt->bind_param("i", $autoID);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        return 'Auto verwijderen mislukt of auto bestaat niet.';
    }
    $stmt->close();

    return null;
}
