<?php
// Adatbázis kapcsolat
session_start();
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "szolgaltatasiadatlap";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Adatbázis kapcsolódási hiba: " . $conn->connect_error);
}

// Ügyfél ID ellenőrzése
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Érvénytelen ügyfél ID.");
}
$ugyfel_id = intval($_GET['id']);

// Ügyfél adatok lekérése
$ugyfel = [];
$stmt = $conn->prepare("SELECT * FROM ugyfelek WHERE ugyfel_id = ?");
$stmt->bind_param("i", $ugyfel_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Ügyfél nem található.");
}
$ugyfel = $result->fetch_assoc();

// Értesítendő személyek lekérése
$ertesitendok = [];
$ertesitendo_stmt = $conn->prepare("SELECT * FROM ertesitendo_szemelyek WHERE objektum_id IN (SELECT objektum_id FROM vedett_objektumok WHERE ugyfel_id = ?)");
$ertesitendo_stmt->bind_param("i", $ugyfel_id);
$ertesitendo_stmt->execute();
$ertesitendok = $ertesitendo_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Cég adatok lekérése (ha van)
$ceg = [];
if (!empty($ugyfel['ceg_id'])) {
    $ceg_stmt = $conn->prepare("SELECT * FROM cegek WHERE ceg_id = ?");
    $ceg_stmt->bind_param("i", $ugyfel['ceg_id']);
    $ceg_stmt->execute();
    $ceg_result = $ceg_stmt->get_result();
    $ceg = $ceg_result->fetch_assoc();
}

// Számlázási adatok lekérése
$szamlazasi = [];
$szamla_stmt = $conn->prepare("SELECT * FROM szamlazasi_adatok WHERE ugyfel_id = ?");
$szamla_stmt->bind_param("i", $ugyfel_id);
$szamla_stmt->execute();
$szamla_result = $szamla_stmt->get_result();
$szamlazasi = $szamla_result->fetch_assoc();

// Fizetési adatok lekérése
$fizetes = [];
$fizetes_stmt = $conn->prepare("SELECT * FROM fizetesek WHERE ugyfel_id = ?");
$fizetes_stmt->bind_param("i", $ugyfel_id);
$fizetes_stmt->execute();
$fizetes_result = $fizetes_stmt->get_result();
$fizetes = $fizetes_result->fetch_assoc();

// Jelszavak lekérése
$jelszo = [];
$jelszo_stmt = $conn->prepare("SELECT * FROM jelszavak WHERE ugyfel_id = ?");
$jelszo_stmt->bind_param("i", $ugyfel_id);
$jelszo_stmt->execute();
$jelszo_result = $jelszo_stmt->get_result();
$jelszo = $jelszo_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ügyfél megtekintése - Triton Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 px-4">
        <div class="container-fluid">
            <a class="navbar-brand text-danger fw-bold d-flex gap-3 align-items-center" href="#">
                <img src="../images/tritonLogo.webp" alt="Triton Logo" class="img-fluid d-block mx-auto logo-filter eltunik" style="max-width: 50px;">
                Triton Security
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="list.php" class="btn btn-link text-dark text-decoration-none nav-link-custom">
                    <i class="fas fa-users me-2"></i> Ügyfelek
                </a>
                <a href="logout.php" class="btn btn-link text-dark text-decoration-none nav-link-custom">
                    <button class="btn btn-danger d-flex align-items-center">
                        <i class="fas fa-sign-out-alt me-2"></i> Kijelentkezés
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
                                    <img src="../images/tritonLogo.webp" alt="Triton Logo" class="img-fluid d-block mx-auto logo-filter eltunik" style="max-height: 130px;">
                                </div>
                            </div>
                            <div class="col-md-8 col-12 order-md-2 order-3 text-center text-md-left">
                                <h3 class="display-5 fw-bold mb-3"><?= htmlspecialchars($ugyfel['szerzodo_neve']) ?></h3>
                                <p class="text-muted" style="color: black !important;">Ügyfél megtekintése</p>
                            </div>
                            <div class="col-auto order-md-3 order-2 mt-3 mt-md-0">
                                <div class="kep">
                                    <img src="../images/tritonLogo.webp" alt="Triton Logo" class="img-fluid d-block mx-auto logo-filter" style="max-height: 130px;">
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Bal oldali oszlop -->
                            <div class="col-lg-6">
                                <!-- Alapadatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-person-gear me-2"></i>Alapadatok</h4>
                                        <div class="mb-3">
                                            <label class="form-label">Szerződő neve</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['szerzodo_neve']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Anyja neve</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['anyja_neve']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Szig. szám</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['szig_szam']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Cím</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['cime']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telefon</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['telefon']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['email']) ?></p>
                                        </div>
                                        <?php if (!empty($ugyfel['ceg_id'])): ?>
                                            <div class="mb-3">
                                                <label class="form-label">Cég ID</label>
                                                <p class="form-control-static"><?= htmlspecialchars($ugyfel['ceg_id']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Cég adatai -->
                                <?php if (!empty($ugyfel['ceg_id'])): ?>
                                    <div class="card mb-4 shadow-sm">
                                        <div class="card-body">
                                            <h4 class="mb-3"><i class="bi bi-building me-2"></i>Cég adatai</h4>
                                            <div class="mb-3">
                                                <label class="form-label">Adószám</label>
                                                <p class="form-control-static"><?= htmlspecialchars($ceg['adoszam']) ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Cégjegyzék szám</label>
                                                <p class="form-control-static"><?= htmlspecialchars($ceg['cegjegyzek_szam']) ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Bankszámla szám</label>
                                                <p class="form-control-static"><?= htmlspecialchars($ceg['bankszamla_szam']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Egyéb adatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-info-circle me-2"></i>Egyéb adatok</h4>
                                        <div class="mb-3">
                                            <label class="form-label">Riasztó központ típusa</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['riasztokozpont_tipusa'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telepítő neve</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['telepito_nev'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telepítő telefonszáma</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['telepito_telefonszam'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kutya</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['kutya'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kapu kulcs</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['kapu_kulcs'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Megjegyzés</label>
                                            <p class="form-control-static"><?= htmlspecialchars($ugyfel['megjegyzes'] ?? '') ?></p>
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
                                        <div class="mb-3">
                                            <label class="form-label">Számlázó név</label>
                                            <p class="form-control-static"><?= htmlspecialchars($szamlazasi['szamlazo_nev'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Számlázó cím</label>
                                            <p class="form-control-static"><?= htmlspecialchars($szamlazasi['szamlazo_cim'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Postázási cím</label>
                                            <p class="form-control-static"><?= htmlspecialchars($szamlazasi['postazasi_cim'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fizetési adatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-credit-card me-2"></i>Fizetési adatok</h4>
                                        <div class="mb-3">
                                            <label class="form-label">Fizetés gyakorisága</label>
                                            <p class="form-control-static"><?= htmlspecialchars($fizetes['fizetes_gyakorisag'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Fizetés módja</label>
                                            <p class="form-control-static"><?= htmlspecialchars($fizetes['fizetes_mod'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Biztonsági adatok -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Biztonsági adatok</h4>
                                        <div class="mb-3">
                                            <label class="form-label">Jelszó</label>
                                            <p class="form-control-static"><?= htmlspecialchars($jelszo['jelszo'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Vendég jelszó</label>
                                            <p class="form-control-static"><?= htmlspecialchars($jelszo['vendeg_jelszo'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Értesítendő személyek -->
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h4 class="mb-3"><i class="bi bi-person-badge me-2"></i>Értesítendő személyek</h4>
                                        <?php foreach ($ertesitendok as $szemely): ?>
                                            <div class="mb-3">
                                                <label class="form-label">Név</label>
                                                <p class="form-control-static"><?= htmlspecialchars($szemely['nev']) ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Telefonszám</label>
                                                <p class="form-control-static"><?= htmlspecialchars($szemely['telefon']) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Belső ügyfélkód</label>
                                            <p type="text" class="form-control-static">
                                                <?= htmlspecialchars($ugyfel['ugyfel_kod'] ?? '') ?></p>

                                            <div class="form-text">Csak belső használatra!</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>