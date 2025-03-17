<?php
session_start();

// Ellenőrizzük, hogy be van-e jelentkezve
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Hibakezelés bekapcsolása (fejlesztés közben)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adatbázis kapcsolat
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "szolgaltatasiadatlap";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    $_SESSION['error'] = "Adatbázis kapcsolódási hiba: " . $e->getMessage();
    header("Location: list.php");
    exit();
}

// Ügyfél ID ellenőrzése
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Érvénytelen ügyfél azonosító";
    header("Location: list.php");
    exit();
}

$ugyfel_id = (int)$_GET['id'];

try {
    // Tranzakció kezdete
    $conn->beginTransaction();

    // 1. Cég adatok törlése (ha van)
    $stmt = $conn->prepare("DELETE FROM cegek WHERE ceg_id = (SELECT ceg_id FROM ugyfelek WHERE ugyfel_id = ?)");
    $stmt->execute([$ugyfel_id]);

    // 2. Értesítendők törlése
    $stmt = $conn->prepare("
        DELETE FROM ertesitendo_szemelyek 
        WHERE objektum_id IN (
            SELECT objektum_id FROM vedett_objektumok WHERE ugyfel_id = ?
        )
    ");
    $stmt->execute([$ugyfel_id]);

    // 3. Védett objektumok törlése
    $stmt = $conn->prepare("DELETE FROM vedett_objektumok WHERE ugyfel_id = ?");
    $stmt->execute([$ugyfel_id]);

    // 4. Számlázási adatok törlése
    $stmt = $conn->prepare("DELETE FROM szamlazasi_adatok WHERE ugyfel_id = ?");
    $stmt->execute([$ugyfel_id]);

    // 5. Fizetési adatok törlése
    $stmt = $conn->prepare("DELETE FROM fizetesek WHERE ugyfel_id = ?");
    $stmt->execute([$ugyfel_id]);

    // 6. Jelszavak törlése
    $stmt = $conn->prepare("DELETE FROM jelszavak WHERE ugyfel_id = ?");
    $stmt->execute([$ugyfel_id]);

    // 7. Ügyfél törlése
    $stmt = $conn->prepare("DELETE FROM ugyfelek WHERE ugyfel_id = ?");
    $stmt->execute([$ugyfel_id]);

    // Tranzakció véglegesítése
    $conn->commit();

    $_SESSION['success'] = "Ügyfél sikeresen törölve!";
} catch (PDOException $e) {
    // Hiba esetén rollback
    $conn->rollBack();
    $_SESSION['error'] = "Hiba történt a törlés során: " . $e->getMessage();
} finally {
    $conn = null;
    header("Location: list.php");
    exit();
}
