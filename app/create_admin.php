<?php

// This setup script runs from the terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db.php';

$name = 'Demo Airline Admin';
$email = 'admin@example.test';

$findUser = $pdo->prepare(
    'SELECT user_id, role FROM app_user WHERE email = ?'
);
$findUser->execute([$email]);
$existingUser = $findUser->fetch();

if ($existingUser) {
    if ($existingUser['role'] !== 'Admin') {
        fwrite(STDERR, "This email already belongs to a different role." . PHP_EOL);
        exit(1);
    }

    echo 'Admin account already exists. User ID: '
        . $existingUser['user_id'] . PHP_EOL;
    exit;
}

$password = bin2hex(random_bytes(12));
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertUser = $pdo->prepare(
    'INSERT INTO app_user (user_name, email, password_hash, role)
     VALUES (?, ?, ?, ?)'
);
$insertUser->execute([$name, $email, $passwordHash, 'Admin']);

echo 'Admin account created.' . PHP_EOL;
echo 'User ID: ' . $pdo->lastInsertId() . PHP_EOL;
echo "Email: $email" . PHP_EOL;
echo "Password: $password" . PHP_EOL;