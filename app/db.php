<?php

$host = '127.0.0.1';
$port = 3306;
$database = 'airline_rebooking';
$username = 'root';
$password = require __DIR__ . '/db.local.php';

$dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the Apache error log.');
}

// Show the result only when this file is opened directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $flightCount = (int) $pdo->query('SELECT COUNT(*) FROM flight')->fetchColumn();
    echo "Database connected. Flights found: $flightCount";
}