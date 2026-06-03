<?php
$servername = "mysql";
$username   = "root";
$password   = "password";
$dbname     = "Eend";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


$message = "";

// Lespakketten ophalen vanuit de database
$pakketten_sql = "SELECT idlespakket, naam, uren FROM lespakket";
$pakketten_result = $conn->query($pakketten_sql);



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $voornaam = $_POST['voornaam'];
    $tussenvoegsel = $_POST['tussenvoegsel'];
    $achternaam = $_POST['achternaam'];
    $telefoon = $_POST['telefoon'];
    $geboortedatum = $_POST['geboortedatum'];
    $email = $_POST['email'];
    $wachtwoord = password_hash($_POST['wachtwoord'], PASSWORD_DEFAULT);
    $lespakketID = $_POST['lespakketID'];
    $beperking = isset($_POST['beperking']) ? (int)$_POST['beperking'] : 0;
    
    // Als er geen beperking is, zetten we de omschrijving op null (voor de database)
    $omschrijving = ($beperking === 1 && !empty(trim($_POST['omschrijving']))) ? trim($_POST['omschrijving']) : null;

    // Controleer of email al bestaat
    $check = $conn->prepare("SELECT studentID FROM studenten WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {

        $message = "
        <div class='error'>
            Dit e-mailadres is al geregistreerd.
        </div>";

    } else {

       
        $sql = "INSERT INTO studenten
        (voornaam, tussenvoegsel, achternaam, email, wachtwoord, telefoon, beperking, omschrijving, geboortedatum, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $conn->prepare($sql);

        
        $stmt->bind_param(
            "ssssssiss",
            $voornaam,
            $tussenvoegsel,
            $achternaam,
            $email,
            $wachtwoord,
            $telefoon,
            $beperking,     
            $omschrijving,  
            $geboortedatum
        );

        if ($stmt->execute()) {

            $studentID = $conn->insert_id;

            $sql2 = "INSERT INTO student_lespakket
            (studentID, idlespakket, overige_uren)
            SELECT ?, idlespakket, uren
            FROM lespakket
            WHERE idlespakket = ?";

            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ii", $studentID, $lespakketID);
            $stmt2->execute();

            $message = "
            <div class='success'>
                Je aanmelding is ontvangen. De rijschoolhouder moet je account eerst activeren.
            </div>";

        } else {

            $message = "
            <div class='error'>
                Er ging iets fout bij het opslaan.
            </div>";
        }
    }
}

?>


<!DOCTYPE html>
<html lang="nl">
<head>
    <style>
        
        #beperking_details {
            display: none;
            margin-top: 10px;
        }
    </style>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aanmelden</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<div class="login-box">
    <h1>🚗 Rijschool</h1>
    <p class="sub">Meld je hier aan om leerling te worden van onze rijschool</p>

    <?php echo $message; ?>

    <form action="aanmelden.php" method="post">
         <div class="form-group">
         <label for="persoonlijke_gegevens" style="display:block; text-align:left; margin: 10px 0 2px 5px; font-size:14px; color:#666;">Persoonlijke gegevens:</label>
        <input type="text" name="voornaam" placeholder="Voornaam" required>
        <input type="text" name="tussenvoegsel" placeholder="Tussenvoegsel (optioneel)">
        <input type="text" name="achternaam" placeholder="Achternaam" required>        
        <input type="tel" name="telefoon" placeholder="Telefoonnummer" required>
        <!-- <input type="text" name="beperking" placeholder="Beperking (Geen beperking vul in geen)" required> -->
         <p>Heb je een beperking?</p>
            

            <label>
                <input type="radio" name="beperking" value="0" onclick="toggleBeperking(false)" checked> Nee
            </label>
            <label>
                <input type="radio" name="beperking" value="1" onclick="toggleBeperking(true)"> Ja
            </label>


            <div id="beperking_details">
                <label for="omschrijving" >Geef eventueel een korte toelichting:</label><br>
                <textarea name="omschrijving" id="omschrijving" rows="4" cols="40" placeholder="Bijvoorbeeld: Dyslexie, faalangst..."></textarea>
            </div>

        <script>
                function toggleBeperking(isJa) {
                    const detailsDiv = document.getElementById('beperking_details');
                    const omschrijvingInput = document.getElementById('omschrijving');
                    
                    if (isJa) {
                        detailsDiv.style.display = 'block';
                        omschrijvingInput.required = true;
                    } else {
                        detailsDiv.style.display = 'none';
                        omschrijvingInput.required = false;
                        omschrijvingInput.value = '';
                    }
                }
                </script>

        <label for="geboortedatum" style="display:block; text-align:left; margin: 10px 0 2px 5px; font-size:14px; color:#666;">Geboortedatum:</label>
        <input type="date" id="geboortedatum" name="geboortedatum" required><br>

         <label for="email" style="display:block; text-align:left; margin: 10px 0 2px 5px; font-size:14px; color:#666;">E-mailadres:</label>
         
        <input type="email" name="email" placeholder="E-mailadres" required>
        <input type="password" name="wachtwoord" placeholder="Wachtwoord" required>
        
        <label for="lespakketID" style="display:block; text-align:left; margin: 10px 0 2px 5px; font-size:14px; color:#666;">Kies je lespakket:</label>
        <select name="lespakketID" id="lespakketID" required style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ccc;">
            <option value="">-- Selecteer een pakket --</option>
            <?php 
            if ($pakketten_result && $pakketten_result->num_rows > 0) {
                while($row = $pakketten_result->fetch_assoc()) {
                    echo "<option value='" . $row['idlespakket'] . "'>" . htmlspecialchars($row['naam']) . " (" . $row['uren'] . " uur)</option>";
                }
            } else {
                echo "<option value=''>Geen pakketten beschikbaar</option>";
            }
            ?>
        </select>
        </div>
        
        <button class="btn-login" type="submit">Aanmelden</button>
    </form>
</div>
</body>
</html>