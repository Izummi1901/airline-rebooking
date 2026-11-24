<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Admin access required. Sign in with the Admin account.');
}

require_once __DIR__ . '/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$_SESSION['disruption_token'] ??= bin2hex(random_bytes(32));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['disruption_token'], $token)) {
        http_response_code(403);
        exit('Please reload the page and try again.');
    }

    $flightId = filter_input(INPUT_POST, 'flight_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $type = $_POST['type'] ?? '';
    $reasonInput = $_POST['reason'] ?? '';
    $reason = is_string($reasonInput) ? trim($reasonInput) : '';
    $delay = filter_input(INPUT_POST, 'delay', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ]);

    if (!$flightId || !in_array($type, ['Delay', 'Cancellation'], true)
        || $reason === '' || strlen($reason) > 255) {
        $error = 'Select a flight and disruption type, and enter a short reason.';
    } elseif ($type === 'Delay' && !$delay) {
        $error = 'Enter a positive number of minutes for the delay.';
    } else {
        try {
            $record = $pdo->prepare('CALL record_disruption(?, ?, ?, ?, ?)');
            $record->execute([
                $flightId,
                $_SESSION['user_id'],
                $type,
                $reason,
                $type === 'Delay' ? $delay : null,
            ]);
            $record->closeCursor();

            $_SESSION['disruption_saved'] = true;
            header('Location: disruption.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? '') === '45000') {
                $error = $exception->errorInfo[2] ?? 'The disruption could not be recorded.';
            } else {
                error_log($exception->getMessage());
                $error = 'Could not record the disruption. Please try again.';
            }
        }
    }
}

$saved = $_SESSION['disruption_saved'] ?? false;
unset($_SESSION['disruption_saved']);

$flights = $pdo->query("
    SELECT flight_id, flight_number, departure_date_time, flight_status
    FROM flight
    WHERE flight_status IN ('Scheduled', 'Delayed')
    ORDER BY departure_date_time
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Record Disruption | Airline Rebooking</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f2f5fa;
            color: #15233a;
            font-family: system-ui, sans-serif;
        }
        header {
            padding: 28px 24px;
            background: linear-gradient(115deg, #101e37, #244d8c);
            color: white;
        }
        header a { color: #d7e6ff; margin-right: 16px; }
        h1 { margin: 10px 0 0; }
        .container { max-width: 760px; margin: auto; }
        main { padding: 24px; }
        form {
            padding: 24px;
            background: white;
            border: 1px solid #e1e8f2;
            border-radius: 14px;
        }
        label { display: block; margin: 0 0 6px; font-weight: 600; }
        select, input, textarea, button {
            padding: 10px;
            border-radius: 8px;
            font: inherit;
        }
        select, input, textarea {
            width: 100%;
            margin-bottom: 16px;
            border: 1px solid #aab7ca;
        }
        button {
            border: 0;
            background: #244d8c;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }
        .message { padding: 12px; border-radius: 8px; }
        .success { background: #e4f6ed; color: #135d37; }
        .error { background: #fde8e8; color: #9d2630; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php">Flight dashboard</a>
            <a href="affected.php">Affected passengers</a>
            <h1>Record Disruption</h1>
        </div>
    </header>

    <main class="container">
        <?php if ($saved): ?>
            <p class="message success" role="status">
                Disruption recorded. Confirmed bookings on this flight were marked affected.
            </p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="message error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($flights === []): ?>
            <p>No scheduled or delayed flights found.</p>
        <?php else: ?>
            <form method="post" action="disruption.php">
                <input type="hidden" name="token"
                       value="<?= e($_SESSION['disruption_token']) ?>">

                <label for="flight_id">Flight</label>
                <select id="flight_id" name="flight_id" required>
                    <option value="">Select a flight</option>
                    <?php foreach ($flights as $flight): ?>
                        <option value="<?= (int) $flight['flight_id'] ?>">
                            <?= e($flight['flight_number']) ?> ·
                            <?= e($flight['departure_date_time']) ?> ·
                            <?= e($flight['flight_status']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="type">Disruption type</label>
                <select id="type" name="type" required>
                    <option value="">Select a type</option>
                    <option value="Delay">Delay</option>
                    <option value="Cancellation">Cancellation</option>
                </select>

                <label for="reason">Reason</label>
                <textarea id="reason" name="reason" rows="3" maxlength="255" required></textarea>

                <label for="delay">Expected delay in minutes (for Delay only)</label>
                <input id="delay" name="delay" type="number" min="1" step="1">

                <button type="submit">Save disruption</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>