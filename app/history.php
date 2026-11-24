<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Airline Staff'], true)) {
    http_response_code(403);
    exit('Staff access required.');
}

require_once __DIR__ . '/db.php';

$history = $pdo->query("
    SELECT
        p.passenger_name,
        old_f.flight_number AS original_flight,
        old_b.seat_number AS original_seat,
        new_f.flight_number AS replacement_flight,
        new_b.seat_number AS new_seat,
        u.user_name AS processed_by,
        r.rebooking_date
    FROM rebooking AS r
    JOIN booking AS old_b ON old_b.booking_id = r.original_booking_id
    JOIN booking AS new_b ON new_b.booking_id = r.new_booking_id
    JOIN passenger AS p ON p.passenger_id = old_b.passenger_id
    JOIN flight AS old_f ON old_f.flight_id = old_b.flight_id
    JOIN flight AS new_f ON new_f.flight_id = new_b.flight_id
    JOIN app_user AS u ON u.user_id = r.user_id
    ORDER BY r.rebooking_date DESC, r.rebooking_id DESC
")->fetchAll();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rebooking History | Airline Rebooking</title>
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
        .container { max-width: 1040px; margin: auto; }
        nav { display: flex; flex-wrap: wrap; gap: 16px; }
        nav a { color: #d7e6ff; }
        h1 { margin: 12px 0 0; }
        main { padding: 24px; }
        .panel {
            overflow-x: auto;
            background: white;
            border: 1px solid #e1e8f2;
            border-radius: 14px;
        }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td {
            padding: 14px 18px;
            border-bottom: 1px solid #e8edf5;
            text-align: left;
        }
        th { background: #f7f9fc; color: #43536b; }
        tbody tr:last-child td { border-bottom: 0; }
        .empty { padding: 16px 18px; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav aria-label="Project navigation">
                <a href="index.php">Flight dashboard</a>
                <a href="affected.php">Affected passengers</a>
            </nav>
            <h1>Rebooking History</h1>
        </div>
    </header>

    <main class="container">
        <div class="panel">
            <?php if ($history === []): ?>
                <p class="empty">No rebookings recorded yet.</p>
            <?php else: ?>
                <table aria-label="Passenger rebookings">
                    <thead>
                        <tr>
                            <th scope="col">Passenger</th>
                            <th scope="col">Original flight / seat</th>
                            <th scope="col">Replacement flight / seat</th>
                            <th scope="col">Processed by</th>
                            <th scope="col">Rebooked at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?= e($row['passenger_name']) ?></td>
                                <td><?= e($row['original_flight']) ?> / <?= e($row['original_seat']) ?></td>
                                <td><?= e($row['replacement_flight']) ?> / <?= e($row['new_seat']) ?></td>
                                <td><?= e($row['processed_by']) ?></td>
                                <td><?= e($row['rebooking_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>