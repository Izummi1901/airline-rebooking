<?php
session_start();

require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: affected.php');
    exit;
}

$_SESSION['login_token'] ??= bin2hex(random_bytes(32));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['login_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['login_token'], $token)) {
        $error = 'Please reload the page and try again.';
    } else {
        $findUser = $pdo->prepare(
            'SELECT user_id, user_name, password_hash, role
             FROM app_user
             WHERE email = ?'
        );
        $findUser->execute([$email]);
        $user = $findUser->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['role'] = $user['role'];
            unset($_SESSION['login_token']);

            header('Location: affected.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Sign In | Airline Rebooking</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: linear-gradient(135deg, #101e37, #244d8c);
            color: #15233a;
            font-family: system-ui, sans-serif;
        }

        main {
            width: min(100%, 420px);
            padding: 32px;
            background: white;
            border-radius: 16px;
        }

        h1 { margin: 0 0 6px; }
        p { margin: 0 0 20px; color: #51617b; }
        label { display: block; margin: 16px 0 6px; font-weight: 600; }

        input, button {
            width: 100%;
            padding: 11px 12px;
            border-radius: 8px;
            font: inherit;
        }

        input { border: 1px solid #aab7ca; }

        button {
            margin-top: 20px;
            border: 0;
            background: #244d8c;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        .error {
            padding: 10px 12px;
            border-radius: 8px;
            background: #fde8e8;
            color: #9d2630;
        }
    </style>
</head>
<body>
    <main>
        <h1>Staff sign in</h1>
        <p>Airline Rebooking System</p>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="login_token"
                value="<?= htmlspecialchars($_SESSION['login_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required>

            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>