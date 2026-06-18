<?php
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/instructeur_afwezigheid.php';

$conn = getDbConnection();
ensureInstructeurAfwezigheidSchema($conn);

$userID = (int) ($_SESSION['userID'] ?? 0);
$naam   = $_SESSION['naam'] ?? '';

$succesBericht = '';
$foutBericht   = '';

$stmt = $conn->prepare('SELECT afwezigheid, afwezig_van, afwezig_tot FROM instructeurs WHERE instructeurID = ? LIMIT 1');
$stmt->bind_param('i', $userID);
$stmt->execute();
$instr = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$isAfwezig = ($instr['afwezigheid'] ?? 'beschikbaar') === 'niet';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weer_beschikbaar'])) {
    stelInstructeurBeschikbaar($conn, $userID);
    $isAfwezig = false;
    $instr['afwezigheid'] = 'beschikbaar';
    $instr['afwezig_van'] = null;
    $instr['afwezig_tot'] = null;
    $succesBericht = 'Je bent weer als beschikbaar gemeld. Toekomstige lessen in je afwezigheidsperiode zijn hersteld.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verzend_afwezigheid'])) {
    $reden = trim($_POST['reden_afwezigheid'] ?? '');
    $van   = trim($_POST['afwezig_van'] ?? '');
    $tot   = trim($_POST['afwezig_tot'] ?? '');

    if ($reden === '') {
        $foutBericht = 'Vul een reden in.';
    } elseif ($van === '' || $tot === '') {
        $foutBericht = 'Vul een start- en einddatum in.';
    } elseif ($van < date('Y-m-d')) {
        $foutBericht = 'De startdatum kan niet in het verleden liggen.';
    } elseif ($van > $tot) {
        $foutBericht = 'De einddatum moet op of na de startdatum liggen.';
    } else {
        $aantalLessen = telLessenInPeriode($conn, $userID, $van, $tot);
        stelInstructeurAfwezig($conn, $userID, $van, $tot);

        $isAfwezig = true;
        $instr['afwezigheid'] = 'niet';
        $instr['afwezig_van'] = $van;
        $instr['afwezig_tot'] = $tot;

        $periodeTekst = date('d-m-Y', strtotime($van)) . ' t/m ' . date('d-m-Y', strtotime($tot));
        $titel  = 'Afwezigheid: ' . $naam;
        $bericht = "Instructeur $naam heeft zich afwezig gemeld.\n"
            . "Periode: $periodeTekst\n"
            . "Reden: $reden\n"
            . "Geannuleerde lessen in periode: $aantalLessen";

        $stmtMeld = $conn->prepare("INSERT INTO meldingen (titel, bericht, ontvanger_type, ontvanger_id) VALUES (?, ?, 'admin', 0)");
        $stmtMeld->bind_param('ss', $titel, $bericht);
        $stmtMeld->execute();
        $stmtMeld->close();

        $succesBericht = "Je bent afwezig gemeld van $periodeTekst. "
            . ($aantalLessen > 0
                ? "$aantalLessen les(sen) in deze periode zijn geannuleerd."
                : 'Er stonden geen lessen gepland in deze periode.');
    }
}

$vandaag = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afwezigheid melden — <?= htmlspecialchars($naam) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <?php
    $navActief = 'afwezigheid';
    $paginaLabel = 'Afwezigheid melden';
    require_once 'instructeur_nav.php';
    ?>

    <?php if ($succesBericht): ?>
        <div class="succes">✅ <?= htmlspecialchars($succesBericht) ?></div>
    <?php endif; ?>
    <?php if ($foutBericht): ?>
        <div class="fout">⚠️ <?= htmlspecialchars($foutBericht) ?></div>
    <?php endif; ?>

    <?php if ($isAfwezig && !empty($instr['afwezig_van']) && !empty($instr['afwezig_tot'])): ?>
        <div style="background:#fff3cd;border:2px solid #ffc107;padding:20px;margin-bottom:20px;border-radius:8px;">
            <h3 style="margin:0 0 10px 0;color:#856404;">Je bent momenteel afwezig</h3>
            <p style="margin:0;color:#856404;">
                Periode:
                <strong><?= date('d-m-Y', strtotime($instr['afwezig_van'])) ?></strong>
                t/m
                <strong><?= date('d-m-Y', strtotime($instr['afwezig_tot'])) ?></strong>
            </p>
            <p style="margin:10px 0 0;font-size:0.9rem;color:#856404;">
                Alleen lessen binnen deze periode zijn geannuleerd. Lessen daarna blijven staan.
            </p>
            <form method="POST" style="margin-top:16px;" onsubmit="return confirm('Weet je zeker dat je weer beschikbaar bent? Toekomstige geannuleerde lessen worden hersteld.');">
                <button type="submit" name="weer_beschikbaar" class="btn-goedkeur" style="width:auto;padding:10px 20px;">
                    ✅ Ik ben weer beschikbaar
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="annuleer-form" style="max-width:560px;">
        <h3 style="margin-bottom:12px;"><?= $isAfwezig ? 'Afwezigheidsperiode wijzigen' : 'Zelf afmelden' ?></h3>
        <p style="color:#64748b;margin-bottom:20px;font-size:0.95rem;line-height:1.5;">
            Kies de periode waarin je niet kunt lesgeven. Alleen lessen binnen die dagen worden automatisch geannuleerd.
            De rijschoolhouder ontvangt een melding.
        </p>

        <form method="POST">
            <div class="form-group">
                <label for="afwezig_van">Afwezig vanaf <span style="color:#dc3545;">*</span></label>
                <input type="date" name="afwezig_van" id="afwezig_van" required
                       min="<?= $vandaag ?>"
                       value="<?= htmlspecialchars($_POST['afwezig_van'] ?? $instr['afwezig_van'] ?? $vandaag) ?>">
            </div>

            <div class="form-group">
                <label for="afwezig_tot">Afwezig tot en met <span style="color:#dc3545;">*</span></label>
                <input type="date" name="afwezig_tot" id="afwezig_tot" required
                       min="<?= $vandaag ?>"
                       value="<?= htmlspecialchars($_POST['afwezig_tot'] ?? $instr['afwezig_tot'] ?? $vandaag) ?>">
            </div>

            <div class="form-group">
                <label for="reden_afwezigheid">Reden <span style="color:#dc3545;">*</span></label>
                <textarea name="reden_afwezigheid" id="reden_afwezigheid" required
                          placeholder="Bijv. griep, familieomstandigheden..."><?= htmlspecialchars($_POST['reden_afwezigheid'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="verzend_afwezigheid" class="btn-annuleer" style="width:100%;">
                🚫 Meld afwezig
            </button>
        </form>
    </div>

</div>
</body>
</html>
