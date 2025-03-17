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

// CSV fejléc beállítások
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ugyfelek.csv"');

// CSV író létrehozása
$output = fopen('php://output', 'w');

// Fejléc sor
fputcsv($output, [
    'Név',
    'Email',
    'Telefon',
    'Személyi igazolvány szám',
    'Cím',
    'Státusz',
    'Cég adószám'
], ';');

// Szűrési paraméterek (ugyanazok mint a listában)
$filter_ceges = (isset($_GET['ceges']) && $_GET['ceges'] !== '') ? (int)$_GET['ceges'] : null;
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : null;
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : null;

// SQL lekérdezés (ugyanaz mint a list.php-ban)
$sql = "SELECT u.*, c.adoszam 
        FROM ugyfelek u 
        LEFT JOIN cegek c ON u.ceg_id = c.ceg_id 
        WHERE 1=1";

$params = [];
$types = '';

if ($filter_ceges !== null) {
    $sql .= " AND u.ceg_id " . ($filter_ceges ? "IS NOT NULL" : "IS NULL");
}

if ($filter_status) {
    $sql .= " AND u.statusz = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($search_term) {
    $sql .= " AND (u.szerzodo_neve LIKE ? OR u.email LIKE ? OR u.telefon LIKE ?)";
    $search_term = "%$search_term%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= str_repeat('s', 3);
}

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Adatok írása a CSV-be
while ($row = $result->fetch_assoc()) {
    $csvRow = [
        $row['szerzodo_neve'],
        $row['email'],
        $row['telefon'],
        $row['szig_szam'],
        $row['cime'],
        $row['statusz'] === 'aktiv' ? 'Aktív' : 'Inaktív',
        $row['adoszam'] ?: 'N/A'
    ];

    fputcsv($output, $csvRow, ';');
}

fclose($output);
exit;
