<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adatbázis kapcsolat
    $conn = new mysqli('localhost', 'root', '', 'szolgaltatasiadatlap');
    $conn->autocommit(false); // Tranzakció kezelése

    try {
        if ($conn->connect_error) throw new Exception('Adatbázis kapcsolat sikertelen: ' . $conn->connect_error);

        // Ügyfél adatok beszúrása
        $stmt = $conn->prepare("
        INSERT INTO ugyfelek (
            szerzodo_neve, anyja_neve, szig_szam, cime, telefon, email, 
            riasztokozpont_tipusa, telepito_nev, telepito_telefonszam, kutya, kapu_kulcs, megjegyzes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->bind_param(
            'ssssssssssss', // 12 paraméter
            $_POST['szerzodo_neve'],
            $_POST['anyja_neve'],
            $_POST['szig_szam'],
            $_POST['cime'],
            $_POST['telefon_szama'],
            $_POST['email'],
            $_POST['riasztokozpont_tipusa'],
            $_POST['telepito-nev'],
            $_POST['telepito-telefonszam'],
            $_POST['kutya'],
            $_POST['kapu_kulcs'],
            $_POST['megjegyzes']
        );
        if (!$stmt->execute()) throw new Exception("Ügyfél mentése sikertelen: " . $stmt->error);
        $ugyfel_id = $stmt->insert_id;
        $stmt->close();

        // Céges szerződés ellenőrzése a HTML űrlap értéke alapján
        if ($_POST['ceges_szerzodes'] === 'igen') {
            // Cég adatok beszúrása
            $stmt = $conn->prepare("
        INSERT INTO cegek (adoszam, cegjegyzek_szam, bankszamla_szam)
        VALUES (?, ?, ?)
    ");
            $stmt->bind_param(
                'sss',
                $_POST['adoszam'],
                $_POST['cegjegyzek_szam'],
                $_POST['bankszamla_szam']
            );
            if (!$stmt->execute()) throw new Exception("Cég mentése sikertelen: " . $stmt->error);
            $ceg_id = $stmt->insert_id;
            $stmt->close();

            // Cég ID frissítése az ügyfélben
            $conn->query("UPDATE ugyfelek SET ceg_id = $ceg_id WHERE ugyfel_id = $ugyfel_id");
        }

        // 3. Védett objektum mentése
        $stmt = $conn->prepare("
            INSERT INTO vedett_objektumok (ugyfel_id, objektum_neve, objektum_cime)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param(
            'iss',
            $ugyfel_id,
            $_POST['vedett_objektum_neve'],
            $_POST['objektum_cime']
        );
        if (!$stmt->execute()) throw new Exception("Objektum mentése sikertelen: " . $stmt->error);
        $objektum_id = $stmt->insert_id;
        $stmt->close();

        // 4. Értesítendő személyek kezelése (több értesítendő)
        if (!empty($_POST['ertesitendo_szemely_neve'])) {
            $stmt = $conn->prepare("
                INSERT INTO ertesitendo_szemelyek (objektum_id, nev, telefon)
                VALUES (?, ?, ?)
            ");
            foreach ($_POST['ertesitendo_szemely_neve'] as $index => $nev) {
                $telefon = $_POST['ertesitendo_szemely_telefon'][$index] ?? '';
                if (!empty($nev) && !empty($telefon)) {
                    $stmt->bind_param('iss', $objektum_id, $nev, $telefon);
                    if (!$stmt->execute()) throw new Exception("Értesítendő mentése sikertelen: " . $stmt->error);
                }
            }
            $stmt->close();
        }

        // 5. Számlázási adatok kezelése
        $sameAsContractor = isset($_POST['sameAsContractor']) && $_POST['sameAsContractor'] === 'on';
        $szamlazo_nev = $sameAsContractor ? $_POST['szerzodo_neve'] : $_POST['szamlara_irando_nev'];
        $szamlazo_cim = $sameAsContractor ? $_POST['cime'] : $_POST['szamlara_irando_cim'];
        $stmt = $conn->prepare("
            INSERT INTO szamlazasi_adatok (ugyfel_id, szamlazo_nev, szamlazo_cim, postazasi_cim)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'isss',
            $ugyfel_id,
            $szamlazo_nev,
            $szamlazo_cim,
            $_POST['postazasi_cim']
        );
        if (!$stmt->execute()) throw new Exception("Számlázási adatok mentése sikertelen: " . $stmt->error);
        $stmt->close();

        // 6. Jelszavak kezelése
        $stmt = $conn->prepare("
            INSERT INTO jelszavak (ugyfel_id, jelszo, vendeg_jelszo)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param(
            'iss',
            $ugyfel_id,
            $_POST['jelszo'],
            $_POST['vendeg_jelszo']
        );
        if (!$stmt->execute()) throw new Exception("Jelszó mentése sikertelen: " . $stmt->error);
        $stmt->close();

        // 7. Fizetési adatok kezelése
        $stmt = $conn->prepare("
            INSERT INTO fizetesek (ugyfel_id, fizetes_gyakorisag, fizetes_mod)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param(
            'iss',
            $ugyfel_id,
            $_POST['fizetes_gyakorisaga'],
            $_POST['fizetes_modja']
        );
        if (!$stmt->execute()) throw new Exception("Fizetési adatok mentése sikertelen: " . $stmt->error);
        $stmt->close();

        $conn->commit();
        echo "Adatok sikeresen mentve! Ügyfél azonosító: $ugyfel_id";
    } catch (Exception $e) {
        $conn->rollback();
        die("Hiba történt: " . $e->getMessage());
    } finally {
        $conn->close();
    }
}
