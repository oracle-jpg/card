<?php
// db.php
$DB_HOST = 'localhost';
$DB_NAME = 'microfinance';
$DB_USER = 'root';
$DB_PASS = 'passwordko'; // change accordingly
$DSN = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($DSN, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // In production, do not echo errors - log them
    exit('Database connection failed: ' . $e->getMessage());
}
