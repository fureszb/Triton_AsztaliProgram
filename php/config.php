<?php

// Adatbázis kapcsolat beállításai
define('DB_HOST', 'localhost'); // Adatbázis szerver címe
define('DB_USER', 'root'); // Adatbázis felhasználónév
define('DB_PASS', ''); // Adatbázis jelszó
define('DB_NAME', 'szolgaltatasiadatlap'); // Adatbázis neve

// Kapcsolat létrehozása
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Hibakezelés
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
