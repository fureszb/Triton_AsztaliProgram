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

$filter_ceges = isset($_GET['ceges']) ? (int)$_GET['ceges'] : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : null;
$search_term = isset($_GET['search']) ? $_GET['search'] : null; // NINCS real_escape_string!

// Pagination beállítások
$items_per_page = 5;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// SQL lekérdezés összeállítása
$sql = "SELECT SQL_CALC_FOUND_ROWS u.*, c.adoszam 
        FROM ugyfelek u 
        LEFT JOIN cegek c ON u.ceg_id = c.ceg_id 
        WHERE 1=1";

$conditions = [];
$params = [];
$types = '';

// 1. Céges szűrés (NEM használ paramétert)
if ($filter_ceges !== null) {
    $conditions[] = "u.ceg_id " . ($filter_ceges ? "IS NOT NULL" : "IS NULL");
}

// 2. Státusz szűrés
if ($filter_status) {
    $conditions[] = "u.statusz = ?";
    $params[] = $filter_status;
    $types .= 's';
}

// 3. Keresési feltétel (telefon NEM része)
if ($search_term) {
    $conditions[] = "(u.szerzodo_neve LIKE ? OR u.email LIKE ? OR u.ugyfel_kod LIKE ?)";
    $search_term_like = "%$search_term%";
    $params = array_merge($params, [
        $search_term_like,
        $search_term_like,
        $search_term_like
    ]);
    $types .= 'sss';
}

// Feltételek hozzáadása
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Pagination hozzáadása
$sql .= " LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $items_per_page;
$params[] = $offset;

// Hibakeresés (opcionális)
// error_log("SQL: $sql");
// error_log("Params: " . print_r($params, true));
// error_log("Types: $types");

// Prepared statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL hiba: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Összes rekord száma
$total_result = $conn->query("SELECT FOUND_ROWS() AS total");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $items_per_page);
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ügyfelek listája - Triton Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --triton-red: #dc3545;
            --triton-dark: #2c3e50;
            --triton-light: #f8f9fa;
        }

        .gradient-bg {
            background: linear-gradient(135deg, var(--triton-red) 0%, var(--triton-dark) 100%);
        }

        .card-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(240, 240, 240, 0.9);
            /*transform: translateX(5px);
            transition: all 0.3s ease;*/
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--triton-red);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navigációs sáv -->
    <nav class="navbar navbar-expand-lg navbar-dark gradient-bg shadow-lg">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="../images/tritonLogo.webp" alt="Logo" width="40" class="me-2">
                <span class="fw-bold">Triton Security</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="list.php"><i class="fas fa-users me-2"></i>Ügyfelek</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Kijelentkezés</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Fő tartalom -->
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-xxl-10 col-xl-11">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0 text-triton-dark"><i class="fas fa-list-ul me-2"></i>Ügyfelek listája</h2>
                            <a href="../index.html" class="btn btn-danger">
                                <i class="fas fa-plus-circle me-2"></i>Új ügyfél
                            </a>
                        </div>
                    </div>

                    <!-- Card Body rész módosítása -->
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <form method="get" class="mb-4">
                                    <div class="row g-2 align-items-end">
                                        <!-- Keresés mező -->
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" name="search" class="form-control"
                                                    placeholder="Keresés név, email, telefon vagy ügyfélkód szerint"
                                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <!-- Cég típus választó -->
                                        <div class="col-md-2">
                                            <select name="ceges" class="form-select">
                                                <option value="">Mindegy</option>
                                                <option value="1" <?= isset($_GET['ceges']) && $_GET['ceges'] == 1 ? 'selected' : '' ?>>Céges</option>
                                                <option value="0" <?= isset($_GET['ceges']) && $_GET['ceges'] == 0 ? 'selected' : '' ?>>Magán</option>
                                            </select>
                                        </div>

                                        <!-- Státusz választó -->
                                        <div class="col-md-2">
                                            <select name="status" class="form-select">
                                                <option value="">Mindegy</option>
                                                <option value="aktiv" <?= ($_GET['status'] ?? '') === 'aktiv' ? 'selected' : '' ?>>Aktív</option>
                                                <option value="inaktiv" <?= ($_GET['status'] ?? '') === 'inaktiv' ? 'selected' : '' ?>>Inaktív</option>
                                            </select>
                                        </div>

                                        <!-- Szűrés gomb -->
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-danger w-100">
                                                <i class="fas fa-filter me-2"></i>Szűrés
                                            </button>
                                        </div>

                                        <!-- Export gomb -->
                                        <div class="col-md-2 ms-auto">
                                            <a href="export.php?<?= htmlspecialchars($_SERVER['QUERY_STRING']) ?>"
                                                class="btn btn-outline-secondary w-100"
                                                target="_blank">
                                                <i class="fas fa-download me-2"></i>Export
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Táblázat rész változatlanul marad -->

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>Név</th>
                                        <th>Ügyfélkód</th> <!-- Új oszlop -->
                                        <th>Elérhetőség</th>
                                        <th>Cég adatok</th>
                                        <th>Státusz</th>
                                        <th class="text-end">Műveletek</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="bg-white">
                                                <td>
                                                    <div class="avatar">
                                                        <?= strtoupper(substr($row['szerzodo_neve'], 0, 1)) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-500"><?= htmlspecialchars($row['szerzodo_neve']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($row['szig_szam']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($row['ugyfel_kod']): ?>
                                                        <span class="badge bg-primary"><?= htmlspecialchars($row['ugyfel_kod']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">nincs</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <div><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($row['email']) ?></div>
                                                    <div><i class="fas fa-phone me-2"></i><?= htmlspecialchars($row['telefon']) ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($row['adoszam']): ?>
                                                        <span class="badge bg-danger me-1">Céges</span>
                                                        <div class="text-muted small"><?= htmlspecialchars($row['adoszam']) ?></div>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Magánszemély</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge bg-success text-white">
                                                        <i class="fas fa-check-circle me-1"></i>Aktív
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                            type="button"
                                                            data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-h"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item" href="view.php?id=<?= $row['ugyfel_id'] ?>">
                                                                    <i class="fas fa-eye me-2"></i>Megtekintés
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="edit.php?id=<?= $row['ugyfel_id'] ?>">
                                                                    <i class="fas fa-edit me-2"></i>Szerkesztés
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="download_ini.php?id=<?= $row['ugyfel_id'] ?>">
                                                                    <i class="fas fa-download me-2"></i>INI letöltés
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <hr class="dropdown-divider">
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="delete.php?id=<?= $row['ugyfel_id'] ?>" onclick="return confirm('Biztosan törölni szeretné ezt az ügyfelet?')">
                                                                    <i class="fas fa-trash-alt me-2"></i>Törlés
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="fas fa-users-slash fa-3x mb-3"></i><br>
                                                    Nincsenek ügyfelek az adatbázisban
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>


                        <!-- Pagination rész módosítva -->
                        <nav class="d-flex justify-content-between mt-4">
                            <div class="text-muted">
                                Összesen <strong><?= $total_rows ?></strong> ügyfél •
                                Oldal: <strong><?= $current_page ?>/<?= $total_pages ?></strong>
                            </div>

                            <ul class="pagination pagination-sm">
                                <?php
                                $query_params = $_GET;
                                unset($query_params['page']);

                                // Előző gomb
                                if ($current_page > 1) {
                                    $prev_page = $current_page - 1;
                                    echo '<li class="page-item">
                    <a class="page-link" 
                       href="?' . http_build_query($query_params + ['page' => $prev_page]) . '">
                       Előző
                    </a>
                  </li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Előző</span></li>';
                                }

                                // Oldalszámok
                                for ($i = 1; $i <= $total_pages; $i++) {
                                    $active = $i == $current_page ? 'active' : '';
                                    echo '<li class="page-item ' . $active . '">
                    <a class="page-link" 
                       href="?' . http_build_query($query_params + ['page' => $i]) . '">
                       ' . $i . '
                    </a>
                  </li>';
                                }

                                // Következő gomb
                                if ($current_page < $total_pages) {
                                    $next_page = $current_page + 1;
                                    echo '<li class="page-item">
                    <a class="page-link" 
                       href="?' . http_build_query($query_params + ['page' => $next_page]) . '">
                       Következő
                    </a>
                  </li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Következő</span></li>';
                                }
                                ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dinamikus keresés
        document.querySelector('input[type="search"]').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>