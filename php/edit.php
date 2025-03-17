<?php
session_start();
session_regenerate_id(true);
error_log("SESSION: " . print_r($_SESSION, true));
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    session_write_close();
    header('HTTP/1.1 403 Forbidden');
    exit('Hozzáférés megtagadva! Admin jogosultság szükséges!');
}
$servername = "127.0.0.1";
$username = "root"; // Helyettesítsd a valós adatokkal
$password = ""; // Helyettesítsd a valós adatokkal
$dbname = "szolgaltatasiadatlap";

$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Adatbázis kapcsolódási hiba: " . $conn->connect_error);
}
$conn->autocommit(false);
$conn->set_charset("utf8mb4");

try {
    $conn->autocommit(false);
    // Ügyfél ID ellenőrzése
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        die("Érvénytelen ügyfél ID.");
    }
    $ugyfel_id = intval($_GET['id']);

    // Adatok lekérése
    // Ügyfél adatai
    $ugyfel = [];
    $stmt = $conn->prepare("SELECT * FROM ugyfelek WHERE ugyfel_id = ?");
    $stmt->bind_param("i", $ugyfel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("Ügyfél nem található.");
    }
    $ugyfel = $result->fetch_assoc();
    $ertesitendok = [];
    $ertesitendo_stmt = $conn->prepare("SELECT * FROM ertesitendo_szemelyek WHERE objektum_id IN (SELECT objektum_id FROM vedett_objektumok WHERE ugyfel_id = ?)");
    $ertesitendo_stmt->bind_param("i", $ugyfel_id);
    $ertesitendo_stmt->execute();
    $ertesitendok = $ertesitendo_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Cég adatai (ha van)
    $ceg = [];
    if (!empty($ugyfel['ceg_id'])) {
        $ceg_stmt = $conn->prepare("SELECT * FROM cegek WHERE ceg_id = ?");
        $ceg_stmt->bind_param("i", $ugyfel['ceg_id']);
        $ceg_stmt->execute();
        $ceg_result = $ceg_stmt->get_result();
        $ceg = $ceg_result->fetch_assoc();
    }

    // Számlázási adatok
    $szamlazasi = [];
    $szamla_stmt = $conn->prepare("SELECT * FROM szamlazasi_adatok WHERE ugyfel_id = ?");
    $szamla_stmt->bind_param("i", $ugyfel_id);
    $szamla_stmt->execute();
    $szamla_result = $szamla_stmt->get_result();
    $szamlazasi = $szamla_result->fetch_assoc();

    // Fizetési adatok
    $fizetes = [];
    $fizetes_stmt = $conn->prepare("SELECT * FROM fizetesek WHERE ugyfel_id = ?");
    $fizetes_stmt->bind_param("i", $ugyfel_id);
    $fizetes_stmt->execute();
    $fizetes_result = $fizetes_stmt->get_result();
    $fizetes = $fizetes_result->fetch_assoc();

    // Jelszavak
    $jelszo = [];
    $jelszo_stmt = $conn->prepare("SELECT * FROM jelszavak WHERE ugyfel_id = ?");
    $jelszo_stmt->bind_param("i", $ugyfel_id);
    $jelszo_stmt->execute();
    $jelszo_result = $jelszo_stmt->get_result();
    $jelszo = $jelszo_result->fetch_assoc();


    // Üzenetek megjelenítése és törlése
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']); // Törlés a session-ből
    }

    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']); // Törlés a session-ből
    }
    // Űrlap feldolgozása
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // 1. Ügyfél frissítése (ceges_szerzodes mező nélkül)
        $ugyfel_kod = !empty($_POST['ugyfel_kod']) ? $conn->real_escape_string($_POST['ugyfel_kod']) : null;

        // Frissítés az ugyfelek táblában
        $stmt = $conn->prepare("
UPDATE ugyfelek 
SET 
    szerzodo_neve = ?, 
    anyja_neve = ?, 
    szig_szam = ?, 
    cime = ?, 
    telefon = ?, 
    email = ?, 
    riasztokozpont_tipusa = ?, 
    telepito_nev = ?, 
    telepito_telefonszam = ?, 
    kutya = ?, 
    kapu_kulcs = ?, 
    megjegyzes = ?,
    ugyfel_kod = ?
WHERE ugyfel_id = ?
");

        if (!$stmt) {
            die("Hiba az SQL előkészítésekor: " . $conn->error);
        }

        // Javított bind_param
        $stmt->bind_param(
            'sssssssssssssi', // 13 string (s) + 1 integer (i)
            $_POST['szerzodo_neve'],  // 1. paraméter
            $_POST['anyja_neve'],     // 2. paraméter
            $_POST['szig_szam'],      // 3. paraméter
            $_POST['cime'],           // 4. paraméter
            $_POST['telefon'],        // 5. paraméter
            $_POST['email'],          // 6. paraméter
            $_POST['riasztokozpont_tipusa'], // 7. paraméter
            $_POST['telepito_nev'],   // 8. paraméter
            $_POST['telepito_telefonszam'], // 9. paraméter
            $_POST['kutya'],          // 10. paraméter
            $_POST['kapu_kulcs'],     // 11. paraméter
            $_POST['megjegyzes'],     // 12. paraméter
            $ugyfel_kod,              // 13. paraméter (ugyfel_kod)
            $ugyfel_id                // 14. paraméter (ugyfel_id)
        );
        if (!$stmt->execute()) {
            die("Hiba az SQL végrehajtásakor: " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            die("Nincs ilyen ügyfél, vagy nem történt változás az adatokban.");
        }

        // 2. Cég adatok kezelése
        $is_ceges = isset($_POST['ceges_szerzodes']) && $_POST['ceges_szerzodes'] === 'igen';

        if ($is_ceges) {
            if (!empty($ugyfel['ceg_id'])) {
                // Frissítés
                $stmt = $conn->prepare("UPDATE cegek SET adoszam=?, cegjegyzek_szam=?, bankszamla_szam=? WHERE ceg_id=?");
                $stmt->bind_param('sssi', $_POST['adoszam'], $_POST['cegjegyzek_szam'], $_POST['bankszamla_szam'], $ugyfel['ceg_id']);
            } else {
                // Beszúrás
                $stmt = $conn->prepare("INSERT INTO cegek (adoszam, cegjegyzek_szam, bankszamla_szam) VALUES (?,?,?)");
                $stmt->bind_param('sss', $_POST['adoszam'], $_POST['cegjegyzek_szam'], $_POST['bankszamla_szam']);
            }

            if (!$stmt->execute()) {
                die("Cég mentési hiba: " . $stmt->error);
            }

            $new_ceg_id = $stmt->insert_id ?? $ugyfel['ceg_id'];
            $conn->query("UPDATE ugyfelek SET ceg_id=$new_ceg_id WHERE ugyfel_id=$ugyfel_id");
            $stmt->close();
        } else {
            if (!empty($ugyfel['ceg_id'])) {
                $conn->query("DELETE FROM cegek WHERE ceg_id={$ugyfel['ceg_id']}");
                $conn->query("UPDATE ugyfelek SET ceg_id=NULL WHERE ugyfel_id=$ugyfel_id");
            }
        }

        // Számlázási adatok frissítése
        $szamla_stmt = $conn->prepare("
        UPDATE szamlazasi_adatok 
        SET 
            szamlazo_nev = ?, 
            szamlazo_cim = ?, 
            postazasi_cim = ? 
        WHERE ugyfel_id = ?
    ");
        $szamla_stmt->bind_param(
            "sssi",
            $_POST['szamlazo_nev'],
            $_POST['szamlazo_cim'],
            $_POST['postazasi_cim'],
            $ugyfel_id
        );
        $szamla_stmt->execute();

        // Fizetési adatok frissítése
        $fizetes_stmt = $conn->prepare("
        UPDATE fizetesek 
        SET 
            fizetes_gyakorisag = ?, 
            fizetes_mod = ? 
        WHERE ugyfel_id = ?
    ");
        $fizetes_stmt->bind_param(
            "ssi",
            $_POST['fizetes_gyakorisag'],
            $_POST['fizetes_mod'],
            $ugyfel_id
        );
        $fizetes_stmt->execute();

        // Jelszavak frissítése (Figyelem: jelszó nyers formában!)
        if (!empty($_POST['jelszo'])) {
            $jelszo_stmt = $conn->prepare("
            UPDATE jelszavak 
            SET 
                jelszo = ?, 
                vendeg_jelszo = ? 
            WHERE ugyfel_id = ?
        ");
            $jelszo_stmt->bind_param(
                "ssi",
                $_POST['jelszo'],
                $_POST['vendeg_jelszo'],
                $ugyfel_id
            );
            $jelszo_stmt->execute();
        }

        // Sikeres mentés üzenet
        echo "<p>Adatok frissítve!</p>";
    }



    // HTML űrlap megjelenítése

    $ugyfel_kod = !empty($_POST['ugyfel_kod']) ? trim($_POST['ugyfel_kod']) : null;

    // Validáció
    if ($ugyfel_kod && !preg_match('/^[A-Z0-9]{3,20}$/', $ugyfel_kod)) {
        $_SESSION['error'] = "Érvénytelen ügyfélkód formátum! Csak nagybetűk és számok (3-20 karakter)";
    }

    // Egyediség ellenőrzés
    if ($ugyfel_kod) {
        $check = $conn->prepare("SELECT ugyfel_id FROM ugyfelek WHERE ugyfel_kod = ? AND ugyfel_id != ?");
        $check->bind_param('si', $ugyfel_kod, $ugyfel_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Az ügyfélkód már létezik!";
        }
    }

    $conn->commit(); // Commit csak sikeres művelet után
    $_SESSION['success'] = "Adatok sikeresen frissítve!";
    session_write_close();
    //header("Location: edit.php?id=" . $ugyfel_id); // Átirányítás a try blokkban
    //exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Hiba történt: " . $e->getMessage();
    session_write_close();
    header("Location: edit.php?id=" . $ugyfel_id); // Átirányítás a catch blokkban
    exit();
} finally {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ügyfél szerkesztése - Triton Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome ikonok -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 px-4">
        <div class="container-fluid">

            <a class="navbar-brand text-danger fw-bold d-flex gap-3 align-items-center" href="#">
                <img src="../images/tritonLogo.webp" alt="Triton Logo"
                    class="img-fluid d-block mx-auto logo-filter eltunik" style="max-width: 50px;">
                Triton Security
            </a>


            <div class="d-flex align-items-center gap-3">
                <a href="list.php" class="btn btn-link text-dark text-decoration-none nav-link-custom">
                    <i class="fas fa-users me-2"></i>
                    Ügyfelek
                </a>

                <a href="logout.php" class="btn btn-link text-dark text-decoration-none nav-link-custom">
                    <button class="btn btn-danger d-flex align-items-center">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Kijelentkezés
                    </button>
                </a>
            </div>
        </div>
    </nav>
    <main class="container card-0 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-12">
                <div class="card shadow-lg card-1">
                    <div class="card-body inner-card">

                        <div class="row justify-content-center mb-5 fejlec flex-md-nowrap align-items-center">
                            <div class="col-auto order-md-1 order-1 mb-4 mb-md-0">
                                <div class="kep">
                                    <img src="../images/tritonLogo.webp" alt="Triton Logo"
                                        class="img-fluid d-block mx-auto logo-filter eltunik" style="max-height: 130px;">
                                </div>
                            </div>

                            <div class="col-md-8 col-12 order-md-2 order-3 text-center text-md-left">
                                <h3 class="display-5 fw-bold mb-3"><?= htmlspecialchars($ugyfel['szerzodo_neve']) ?></h3>
                                <p class="text-muted" style="color: black !important;">Ügyfél szerkesztése</p>
                            </div>

                            <div class="col-auto order-md-3 order-2 mt-3 mt-md-0">
                                <div class="kep">
                                    <img src="../images/tritonLogo.webp" alt="Triton Logo"
                                        class="img-fluid d-block mx-auto logo-filter" style="max-height: 130px;">
                                </div>
                            </div>
                        </div>

                        <form method="post" action="save_client.php" class="row g-4">
                            <input type="hidden" name="ugyfel_id" value="<?= $ugyfel_id ?>">

                            <!-- Bal oldali oszlop -->
                            <div class="col-lg-6">
                                <!-- Alapadatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-person-gear me-2"></i>Alapadatok</h4>
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control modern-input" name="szerzodo_neve"
                                                value="<?= htmlspecialchars($ugyfel['szerzodo_neve']) ?>" required>
                                            <label>Szerződő neve</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control modern-input" name="anyja_neve" value="<?php echo htmlspecialchars($ugyfel['anyja_neve']); ?>" required>
                                            <label>Anyja neve</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="text" name="szig_szam" value="<?php echo htmlspecialchars($ugyfel['szig_szam']); ?>" required>
                                            <label>Szig. szám:</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="text" name="cime" value="<?php echo htmlspecialchars($ugyfel['cime']); ?>" required>
                                            <label>Cím</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="text" name="telefon" value="<?php echo htmlspecialchars($ugyfel['telefon']); ?>" required>
                                            <label>Telefon:</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="email" name="email" value="<?php echo htmlspecialchars($ugyfel['email']); ?>" required>
                                            <label>Email:</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="number" name="ceg_id" value="<?php echo $ugyfel['ceg_id']; ?>">
                                            <label>Cég ID:</label>
                                        </div>
                                        <!-- Checkbox a céges szerződéshez (érték átadás és feltétel) -->
                                        <!-- Checkbox a céges szerződéshez -->
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="ceges_szerzodes" name="ceges_szerzodes" value="igen"
                                                <?= (!empty($ugyfel['ceg_id'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label">Céges szerződés</label>
                                        </div>

                                        <!-- JavaScript a megerősítéshez -->
                                        <script>
                                            document.getElementById('ceges_szerzodes').addEventListener('change', function(e) {
                                                if (!this.checked) {
                                                    const megerosites = confirm("A céges szerződés inaktiválása ELTÁVOLÍTJA a céghez kapcsolódást, de a cég adatai megmaradnak. Folytatja?");

                                                    if (!megerosites) {
                                                        e.preventDefault();
                                                        this.checked = true;
                                                        return false;
                                                    }
                                                }
                                                document.getElementById('ceg_adatok').style.display = this.checked ? 'block' : 'none';
                                            });
                                        </script>

                                        <!-- Céges adatok (mindig a DOM-ban legyen, de CSS-sel rejtve) -->
                                        <div class="card mb-4 shadow-sm" id="ceg_adatok" style="<?= (!empty($ugyfel['ceg_id'])) ? 'display:block;' : 'display:none;' ?>">
                                            <div class="card-body">
                                                <h4 class="mb-3"><i class="bi bi-building me-2"></i>Cég adatai</h4>

                                                <!-- Adószám -->
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control modern-input"
                                                        name="adoszam"
                                                        value="<?= htmlspecialchars($ceg['adoszam'] ?? '') ?>"
                                                        <?= (!empty($ugyfel['ceg_id'])) ? 'required' : '' ?>>
                                                    <label>Adószám</label>
                                                </div>

                                                <!-- Cégjegyzék szám -->
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control modern-input"
                                                        name="cegjegyzek_szam"
                                                        value="<?= htmlspecialchars($ceg['cegjegyzek_szam'] ?? '') ?>"
                                                        <?= (!empty($ugyfel['ceg_id'])) ? 'required' : '' ?>>
                                                    <label>Cégjegyzék szám</label>
                                                </div>

                                                <!-- Bankszámla szám -->
                                                <div class="form-floating">
                                                    <input type="text" class="form-control modern-input"
                                                        name="bankszamla_szam"
                                                        value="<?= htmlspecialchars($ceg['bankszamla_szam'] ?? '') ?>"
                                                        <?= (!empty($ugyfel['ceg_id'])) ? 'required' : '' ?>>
                                                    <label>Bankszámla szám</label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- JavaScript a dinamikus megjelenítéshez -->
                                        <script>
                                            document.getElementById('ceges_szerzodes').addEventListener('change', function() {
                                                const cegAdatok = document.getElementById('ceg_adatok');
                                                cegAdatok.style.display = this.checked ? 'block' : 'none';

                                                // Kötelezővé teszi a mezőket, ha a checkbox aktív
                                                const cegInputok = cegAdatok.querySelectorAll('input');
                                                cegInputok.forEach(input => {
                                                    input.required = this.checked;
                                                    input.disabled = !this.checked; // Opcionális: letiltja a mezőket, ha nem aktív
                                                });
                                            });
                                        </script>


                                        <!-- Egyéb adatok -->
                                        <div class="card mb-4 shadow-sm">
                                            <div class="card-body">
                                                <h4 class="mb-3"><i class="bi bi-info-circle me-2"></i>Egyéb adatok</h4>

                                                <!-- E-mail cím -->
                                                <div class="form-floating mb-3">
                                                    <input type="email" class="form-control modern-input" name="email"
                                                        value="<?= htmlspecialchars($ugyfel['email']) ?>" required>
                                                    <label>E-mail cím</label>
                                                </div>

                                                <!-- Riasztó központ típusa -->
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control modern-input" name="riasztokozpont_tipusa"
                                                        value="<?= htmlspecialchars($ugyfel['riasztokozpont_tipusa'] ?? '') ?>">
                                                    <label>Riasztó központ típusa</label>
                                                </div>

                                                <!-- Telepítő neve -->
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control modern-input" name="telepito_nev"
                                                        value="<?= htmlspecialchars($ugyfel['telepito_nev']  ?? '') ?>">
                                                    <label>Telepítő neve</label>
                                                </div>

                                                <!-- Telepítő telefonszáma -->
                                                <div class="form-floating mb-3">
                                                    <input type="tel" class="form-control modern-input" name="telepito_telefonszam"
                                                        value="<?= htmlspecialchars($ugyfel['telepito_telefonszam'] ?? '') ?>">
                                                    <label>Telepítő telefonszáma</label>
                                                </div>

                                                <!-- Kutya -->
                                                <div class="form-floating mb-3">
                                                    <select class="form-select modern-select" name="kutya" required>
                                                        <option value="" disabled <?= empty($ugyfel['kutya']) ? 'selected' : '' ?>>-- Válasszon --</option>
                                                        <option value="van" <?= ($ugyfel['kutya'] ?? '') == 'van' ? 'selected' : '' ?>>Van</option>
                                                        <option value="nincs" <?= ($ugyfel['kutya'] ?? '') == 'nincs' ? 'selected' : '' ?>>Nincs</option>
                                                    </select>
                                                    <label>Kutya</label>
                                                </div>

                                                <!-- Kapu kulcs -->
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control modern-input" name="kapu_kulcs"
                                                        value="<?= htmlspecialchars($ugyfel['kapu_kulcs'] ?? '') ?>">
                                                    <label>Kapu kulcs</label>
                                                </div>

                                                <!-- Megjegyzés -->
                                                <div class="form-floating">
                                                    <textarea class="form-control modern-input" name="megjegyzes"
                                                        style="height: 100px"><?= htmlspecialchars($ugyfel['megjegyzes'] ?? '') ?></textarea>
                                                    <label>Megjegyzés</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jobb oldali oszlop -->
                            <div class="col-lg-6">
                                <!-- Számlázási adatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-receipt me-2"></i>Számlázási adatok</h4>
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control modern-input" name="szamlazo_nev"
                                                value="<?= htmlspecialchars($szamlazasi['szamlazo_nev'] ?? '') ?>">
                                            <label>Számlázó név</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="text" name="szamlazo_cim" value="<?php echo htmlspecialchars($szamlazasi['szamlazo_cim'] ?? ''); ?>">
                                            <label>Cím:</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input class="form-control modern-input" type="text" name="postazasi_cim" value="<?php echo htmlspecialchars($szamlazasi['postazasi_cim']); ?>"><br>
                                            <label>Postázási cím:</label>
                                        </div>
                                        <!-- Egyéb számlázási mezők... -->
                                    </div>
                                </div>

                                <!-- Fizetési adatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-credit-card me-2"></i>Fizetési adatok</h4>
                                        <div class="form-floating mb-3">
                                            <select class="form-select modern-select" name="fizetes_gyakorisag">
                                                <?php $selected = $fizetes['fizetes_gyakorisag'] ?? 'havi'; ?>
                                                <option value="havi" <?= $selected == 'havi' ? 'selected' : '' ?>>Havi</option>
                                                <option value="negyed_eves" <?= $selected == 'negyed_eves' ? 'selected' : '' ?>>1/4 éves</option>
                                                <option value="fel_eves" <?= $selected == 'fel_eves' ? 'selected' : '' ?>>1/2 éves</option>
                                                <option value="eves" <?= $selected == 'eves' ? 'selected' : '' ?>>Éves</option>
                                            </select>
                                            <label>Fizetés gyakorisága</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select name="fizetes_mod" class="form-select modern-select">
                                                <option value="kp_irodaban" <?php echo ($fizetes['fizetes_mod'] == 'kp_irodaban') ? 'selected' : ''; ?>>Készpénz irodában</option>
                                                <option value="atutalas" <?php echo ($fizetes['fizetes_mod'] == 'atutalas') ? 'selected' : ''; ?>>Átutalás</option>
                                            </select>
                                            <label> Fizetés módja:</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Jelszavak -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Biztonsági adatok</h4>
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control modern-input"
                                                name="jelszo"
                                                value="<?= htmlspecialchars($jelszo['jelszo'] ?? '') ?>">
                                            <label>Jelszó</label>
                                        </div>
                                        <div class="form-floating">
                                            <input type="text" class="form-control modern-input"
                                                name="vendeg_jelszo"
                                                value="<?= htmlspecialchars($jelszo['vendeg_jelszo'] ?? '') ?>">
                                            <label>Vendég jelszó</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-person-badge me-2"></i>Értesítendő személyek</h4>
                                        <?php foreach ($ertesitendok as $szemely): ?>
                                            <div class="ertesitendo-item mb-3">
                                                <!-- Rejtett mező az ID-hoz -->
                                                <input type="hidden" name="ertesitendo_id[]" value="<?= $szemely['ertesitendo_id'] ?>">

                                                <div class="form-floating mb-2">
                                                    <input type="text" class="form-control modern-input"
                                                        name="ertesitendo_neve[]"
                                                        value="<?= htmlspecialchars($szemely['nev']) ?>">
                                                    <label>Név</label>
                                                </div>
                                                <div class="form-floating">
                                                    <input type="tel" class="form-control modern-input"
                                                        name="ertesitendo_telefon[]"
                                                        value="<?= htmlspecialchars($szemely['telefon']) ?>">
                                                    <label>Telefonszám</label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="uj_ertesitendo">
                                            <i class="bi bi-plus-circle me-2"></i>Új értesítendő
                                        </button>
                                    </div>
                                </div>

                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Belső ügyfélkód</label>
                                            <input type="text" class="form-control"
                                                name="ugyfel_kod"
                                                value="<?= htmlspecialchars($ugyfel['ugyfel_kod'] ?? '') ?>"
                                                pattern="[A-Z0-9]{3,20}"
                                                title="Csak nagybetűk és számok (3-20 karakter)">
                                            <div class="form-text">Csak belső használatra!</div>
                                        </div>
                                    </div>
                                </div>

                            </div>


                            <div class="col-12 text-center">
                                <button type="submit" class="btn triton-btn btn-lg px-5">
                                    <i class="bi bi-save me-2"></i>Mentés
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Céges szerződés toggle
        document.getElementById('ceges_szerzodes').addEventListener('change', function() {
            document.getElementById('ceg_adatok').style.display = this.checked ? 'block' : 'none';
        });

        // Új értesítendő hozzáadása
        document.getElementById('uj_ertesitendo').addEventListener('click', function() {
            const newItem = document.createElement('div');
            newItem.className = 'ertesitendo-item mb-3';
            newItem.innerHTML = `
        <div class="form-floating mb-2">
            <input type="text" class="form-control modern-input" name="uj_ertesitendo_neve[]">
            <label>Név</label>
        </div>
        <div class="form-floating">
            <input type="tel" class="form-control modern-input" name="uj_ertesitendo_telefon[]">
            <label>Telefonszám</label>
        </div>
    `;
            this.parentNode.insertBefore(newItem, this);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>