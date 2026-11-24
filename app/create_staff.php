<?php

// Run this setup script from the VS Code terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db.php';

$name = 'Demo Airline Staff';
$email = 'staff@example.test';
$role = 'Airline Staff';

$findUser = $pdo->prepare(
    'SELECT user_id FROM app_user WHERE email = ?'
);
$findUser->execute([$email]);

$existingId = $findUser->fetchColumn();

if ($existingId !== false) {
    echo "Staff account already exists. User ID: $existingId" . PHP_EOL;
    exit;
}

// Generate a random password and save only its hash in MySQL.
$password = bin2hex(random_bytes(12));
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertUser = $pdo->prepare(
    'INSERT INTO app_user (user_name, email, password_hash, role)
     VALUES (?, ?, ?, ?)'
);
$insertUser->execute([$name, $email, $passwordHash, $role]);

echo 'Staff account created.' . PHP_EOL;
echo 'User ID: ' . $pdo->lastInsertId() . PHP_EOL;
echo "Email: $email" . PHP_EOL;
echo "Password: $password" . PHP_EOL;