<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'szolgaltatasiadatlap');
if ($conn->connect_error) die('Adatbázis hiba: ' . $conn->connect_error);

if (isset($_GET['id'])) {
    $ugyfel_id = intval($_GET['id']);

    // Ügyfél fő adatai
    $stmt = $conn->prepare("
        SELECT u.*, c.*, sz.*, j.*, f.* 
        FROM ugyfelek u
        LEFT JOIN cegek c ON u.ceg_id = c.ceg_id
        LEFT JOIN szamlazasi_adatok sz ON u.ugyfel_id = sz.ugyfel_id
        LEFT JOIN jelszavak j ON u.ugyfel_id = j.ugyfel_id
        LEFT JOIN fizetesek f ON u.ugyfel_id = f.ugyfel_id
        WHERE u.ugyfel_id = ?
    ");
    $stmt->bind_param("i", $ugyfel_id);
    $stmt->execute();
    $ugyfel = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Védett objektumok és értesítendők
    $objektumok = [];
    $stmt = $conn->prepare("SELECT * FROM vedett_objektumok WHERE ugyfel_id = ?");
    $stmt->bind_param("i", $ugyfel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ertesitendok = [];
        $stmt2 = $conn->prepare("SELECT * FROM ertesitendo_szemelyek WHERE objektum_id = ?");
        $stmt2->bind_param("i", $row['objektum_id']);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        while ($row2 = $result2->fetch_assoc()) {
            $ertesitendok[] = $row2;
        }
        $row['ertesitendok'] = $ertesitendok;
        $objektumok[] = $row;
    }

    // INI tartalom összeállítása
    $iniContent = "[Ügyfél]\n";
    $iniContent .= "Név = \"" . $ugyfel['szerzodo_neve'] . "\"\n";
    $iniContent .= "Email = \"" . $ugyfel['email'] . "\"\n";
    $iniContent .= "Telefon = \"" . $ugyfel['telefon'] . "\"\n";
    $iniContent .= "Céges szerződés = \"" . ($ugyfel['ceges_szerzodes'] ? 'Igen' : 'Nem') . "\"\n";

    if ($ugyfel['ceges_szerzodes']) {
        $iniContent .= "\n[Cég]\n";
        $iniContent .= "Adószám = \"" . $ugyfel['adoszam'] . "\"\n";
        $iniContent .= "Cégjegyzék szám = \"" . $ugyfel['cegjegyzek_szam'] . "\"\n";
        $iniContent .= "Bankszámla szám = \"" . $ugyfel['bankszamla_szam'] . "\"\n";
    }

    $iniContent .= "\n[Számlázás]\n";
    $iniContent .= "Név = \"" . $ugyfel['szamlazo_nev'] . "\"\n";
    $iniContent .= "Cím = \"" . $ugyfel['szamlazo_cim'] . "\"\n";
    $iniContent .= "Postázási cím = \"" . $ugyfel['postazasi_cim'] . "\"\n";

    $iniContent .= "\n[Jelszavak]\n";
    $iniContent .= "Jelszó = \"" . $ugyfel['jelszo'] . "\"\n";
    $iniContent .= "Vendég jelszó = \"" . $ugyfel['vendeg_jelszo'] . "\"\n";

    $iniContent .= "\n[Fizetés]\n";
    $iniContent .= "Gyakoriság = \"" . $ugyfel['fizetes_gyakorisag'] . "\"\n";
    $iniContent .= "Mód = \"" . $ugyfel['fizetes_mod'] . "\"\n";

    foreach ($objektumok as $index => $obj) {
        $iniContent .= "\n[Objektum_" . ($index + 1) . "]\n";
        $iniContent .= "Név = \"" . $obj['objektum_neve'] . "\"\n";
        $iniContent .= "Cím = \"" . $obj['objektum_cime'] . "\"\n";

        foreach ($obj['ertesitendok'] as $ertIndex => $ert) {
            $iniContent .= "Értesítendő_" . ($ertIndex + 1) . "_Név = \"" . $ert['nev'] . "\"\n";
            $iniContent .= "Értesítendő_" . ($ertIndex + 1) . "_Telefon = \"" . $ert['telefon'] . "\"\n";
        }
    }

    // Fájl letöltés
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="ugyfel_' . $ugyfel_id . '.ini"');
    echo $iniContent;
    exit;
} else {
    die("Érvénytelen kérés.");
}
