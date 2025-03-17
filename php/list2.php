<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Adatbázis kapcsolat
$conn = new mysqli('localhost', 'root', '', 'szolgaltatasiadatlap');
if ($conn->connect_error) {
    die('Adatbázis kapcsolat sikertelen: ' . $conn->connect_error);
}

// Ügyfelek lekérése
$result = $conn->query("SELECT u.*, c.adoszam, c.cegjegyzek_szam, c.bankszamla_szam FROM ugyfelek u LEFT JOIN cegek c ON u.ceg_id = c.ceg_id");
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ügyfelek listája</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome ikonok -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            border-radius: 12px;
        }

        .table-hover tbody tr:hover {
            background-color: #f1f3f5;
        }
    </style>
</head>

<body>
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

    <div class="container mt-5">
        <h2 class="text-center mb-4">Ügyfelek listája</h2>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Név</th>
                                <th>Email</th>
                                <th>Telefon</th>
                                <th>Cég adószám</th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['szerzodo_neve']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= htmlspecialchars($row['telefon']) ?></td>
                                        <td><?= htmlspecialchars($row['adoszam']) ?: 'N/A' ?></td>
                                        <td><a href="view.php?id=<?= $row['ugyfel_id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download"></i> Megtekintés
                                            </a></td>
                                        <td><a href="edit.php?id=<?= $row['ugyfel_id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download"></i> Szerkesztés
                                            </a></td>
                                        <td>
                                            <a href="download_ini.php?id=<?= $row['ugyfel_id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download"></i> INI Letöltés
                                            </a>
                                        </td>



                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Nincsenek ügyfelek.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>