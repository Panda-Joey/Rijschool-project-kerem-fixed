<?php

/** Reden in lessen.redenVervalt voor automatisch geannuleerde lessen door afwezigheid. */
const REDEN_INSTR_AFWEZIG = 'Instructeur afwezig (periode)';

function ensureInstructeurAfwezigheidSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $r = $conn->query("SHOW COLUMNS FROM instructeurs LIKE 'afwezig_van'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE instructeurs ADD COLUMN afwezig_van DATE NULL DEFAULT NULL AFTER afwezigheid");
        $conn->query("ALTER TABLE instructeurs ADD COLUMN afwezig_tot DATE NULL DEFAULT NULL AFTER afwezig_van");
    }
}

/** Of de instructeur op een gegeven lesdatum als afwezig geldt. */
function instructeurIsAfwezigOpDatum(array $instructeur, string $datum): bool
{
    if (($instructeur['afwezigheid'] ?? 'beschikbaar') !== 'niet') {
        return false;
    }

    $van = $instructeur['afwezig_van'] ?? null;
    $tot = $instructeur['afwezig_tot'] ?? null;

    if ($van === null || $van === '' || $tot === null || $tot === '') {
        return true;
    }

    return $datum >= $van && $datum <= $tot;
}

/** Herstel toekomstige lessen die automatisch zijn geannuleerd door afwezigheid. */
function herstelAutoGeannuleerdeLessen(mysqli $conn, int $instructeurID): void
{
    $stmt = $conn->prepare("
        UPDATE lessen
        SET vervallen = 0, redenVervalt = NULL
        WHERE instructeurID = ?
          AND vervallen = 1
          AND redenVervalt IN (?, 'Instructeur niet beschikbaar')
          AND (lesDatum > CURDATE() OR (lesDatum = CURDATE() AND lestijd >= CURTIME()))
    ");
    $reden = REDEN_INSTR_AFWEZIG;
    $stmt->bind_param('is', $instructeurID, $reden);
    $stmt->execute();
    $stmt->close();
}

/** Annuleer alleen lessen binnen de afwezigheidsperiode (toekomst + vandaag). */
function annuleerLessenInAfwezigheidsperiode(mysqli $conn, int $instructeurID, string $van, string $tot): void
{
    $reden = REDEN_INSTR_AFWEZIG;

    $stmtLessen = $conn->prepare("
        SELECT lesID, studentID, goedgekeurd
        FROM lessen
        WHERE instructeurID = ?
          AND vervallen = 0
          AND lesDatum BETWEEN ? AND ?
          AND (lesDatum > CURDATE() OR (lesDatum = CURDATE() AND lestijd >= CURTIME()))
    ");
    $stmtLessen->bind_param('iss', $instructeurID, $van, $tot);
    $stmtLessen->execute();
    $teVervallen = $stmtLessen->get_result();

    while ($les = $teVervallen->fetch_assoc()) {
        if ((int) ($les['goedgekeurd'] ?? 0) === 1) {
            $studentID = (int) $les['studentID'];
            $conn->query("UPDATE student_lespakket SET overige_uren = overige_uren + 2 WHERE studentID = $studentID");
        }
    }
    $stmtLessen->close();

    $stmtVerval = $conn->prepare("
        UPDATE lessen
        SET vervallen = 1,
            redenVervalt = ?,
            goedgekeurd = 0,
            goedgekeurd_op = NULL
        WHERE instructeurID = ?
          AND vervallen = 0
          AND lesDatum BETWEEN ? AND ?
          AND (lesDatum > CURDATE() OR (lesDatum = CURDATE() AND lestijd >= CURTIME()))
    ");
    $stmtVerval->bind_param('siss', $reden, $instructeurID, $van, $tot);
    $stmtVerval->execute();
    $stmtVerval->close();
}

/**
 * Na wijziging van afwezigheidsperiode: eerst toekomstige auto-annuleringen terugzetten,
 * daarna opnieuw annuleren binnen de nieuwe periode.
 */
function syncInstructeurAfwezigheidslessen(mysqli $conn, int $instructeurID, string $van, string $tot): void
{
    herstelAutoGeannuleerdeLessen($conn, $instructeurID);
    annuleerLessenInAfwezigheidsperiode($conn, $instructeurID, $van, $tot);
}

function stelInstructeurAfwezig(mysqli $conn, int $instructeurID, string $van, string $tot): void
{
    ensureInstructeurAfwezigheidSchema($conn);

    $stmt = $conn->prepare("
        UPDATE instructeurs
        SET afwezigheid = 'niet', afwezig_van = ?, afwezig_tot = ?
        WHERE instructeurID = ?
    ");
    $stmt->bind_param('ssi', $van, $tot, $instructeurID);
    $stmt->execute();
    $stmt->close();

    syncInstructeurAfwezigheidslessen($conn, $instructeurID, $van, $tot);
}

function stelInstructeurBeschikbaar(mysqli $conn, int $instructeurID): void
{
    ensureInstructeurAfwezigheidSchema($conn);

    $stmt = $conn->prepare("
        UPDATE instructeurs
        SET afwezigheid = 'beschikbaar', afwezig_van = NULL, afwezig_tot = NULL
        WHERE instructeurID = ?
    ");
    $stmt->bind_param('i', $instructeurID);
    $stmt->execute();
    $stmt->close();

    herstelAutoGeannuleerdeLessen($conn, $instructeurID);
}

/** Aantal toekomstige lessen dat in de periode valt (vóór annuleren). */
function telLessenInPeriode(mysqli $conn, int $instructeurID, string $van, string $tot): int
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS n FROM lessen
        WHERE instructeurID = ?
          AND vervallen = 0
          AND lesDatum BETWEEN ? AND ?
          AND (lesDatum > CURDATE() OR (lesDatum = CURDATE() AND lestijd >= CURTIME()))
    ");
    $stmt->bind_param('iss', $instructeurID, $van, $tot);
    $stmt->execute();
    $n = (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();

    return $n;
}
