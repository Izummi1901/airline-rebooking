<?php
session_start();
$_SESSION['logout_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_out'])) {
    $token = $_POST['logout_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['logout_token'], $token)) {
        http_response_code(403);
        exit('Reload the dashboard and try again.');
    }

    $_SESSION = [];
    session_regenerate_id(true);

    header('Location: login.php', true, 303);
    exit;
}
require_once __DIR__ . '/db.php';

// Query 1 from 03_queries.sql, with the affected booking count added.
$sql = "
    SELECT
        f.flight_number,
        src.airport_code AS source_airport,
        dst.airport_code AS destination_airport,
        f.departure_date_time,
        f.flight_status,
        f.total_seats,
        COUNT(b.booking_id) AS occupied_seats,
        f.total_seats - COUNT(b.booking_id) AS available_seats,
        SUM(CASE WHEN b.booking_status = 'Affected' THEN 1 ELSE 0 END)
            AS affected_bookings
    FROM flight AS f
    JOIN airport AS src ON src.airport_id = f.source_airport_id
    JOIN airport AS dst ON dst.airport_id = f.destination_airport_id
    LEFT JOIN booking AS b
        ON b.flight_id = f.flight_id
        AND b.booking_status IN ('Confirmed', 'Affected')
    GROUP BY
        f.flight_id, f.flight_number, src.airport_code, dst.airport_code,
        f.departure_date_time, f.flight_status, f.total_seats
    ORDER BY f.departure_date_time
";

$flights = $pdo->query($sql)->fetchAll();

$disruptedFlights = 0;
$affectedBookings = 0;

foreach ($flights as $flight) {
    if (in_array($flight['flight_status'], ['Cancelled', 'Delayed'], true)) {
        $disruptedFlights++;
        $affectedBookings += (int) $flight['affected_bookings'];
    }
}

// Escape database values before displaying them in HTML.
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
    <title>Airline Rebooking Dashboard</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f2f5fa;
            color: #15233a;
            font-family: system-ui, sans-serif;
        }

        header {
            padding: 44px 24px 56px;
            background: linear-gradient(115deg, #101e37, #244d8c);
            color: white;
        }

        .container { max-width: 1080px; margin: auto; }
        h1 { margin: 0; font-size: clamp(28px, 5vw, 44px); }
        header p { margin: 10px 0 0; color: #ceddf6; }
        main { padding: 0 24px 40px; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin: -24px 0 24px;
        }

        .stat, .panel {
            background: white;
            border: 1px solid #e1e8f2;
            border-radius: 14px;
            box-shadow: 0 6px 20px #15233a0d;
        }

        .stat { padding: 18px; }
        .stat span { color: #51617b; }
        .stat strong { display: block; margin-top: 4px; font-size: 30px; }

        .panel h2 { margin: 0; padding: 20px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }

        th, td {
            padding: 14px 20px;
            border-top: 1px solid #e8edf5;
            text-align: left;
        }

        th { background: #f7f9fc; color: #43536b; font-size: 14px; }
        tbody tr:hover { background: #f7faff; }

        .status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            background: #edf0f5;
            font-size: 13px;
            font-weight: 600;
        }

        .scheduled { background: #e4f6ed; color: #135d37; }
        .cancelled { background: #fde8e8; color: #9d2630; }
        .delayed { background: #fff1d7; color: #805000; }
    .dashboard-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.dashboard-nav a {
    padding: 7px 12px;
    border: 1px solid #ffffff66;
    border-radius: 8px;
    color: white;
    text-decoration: none;
}

.dashboard-nav a:hover,
.dashboard-nav a:focus-visible {
    background: #ffffff26;
    text-decoration: underline;
}
.sign-out-form { margin-bottom: 12px; }

.sign-out-form button {
    padding: 7px 12px;
    border: 1px solid #ffffff66;
    border-radius: 8px;
    background: transparent;
    color: white;
    font: inherit;
    cursor: pointer;
}
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="dashboard-nav" aria-label="Dashboard navigation">
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="affected.php">Affected passengers</a>
        <a href="history.php">Rebooking history</a>

        <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <a href="disruption.php">Record disruption</a>
        <?php endif; ?>
    <?php else: ?>
        <a href="login.php">Sign in</a>
    <?php endif; ?>
</nav>
            <?php if (isset($_SESSION['user_id'])): ?>
    <form method="post" action="index.php" class="sign-out-form">
        <input type="hidden" name="logout_token"
               value="<?= e($_SESSION['logout_token']) ?>">
        <button type="submit" name="sign_out" value="1">Sign out</button>
    </form>
<?php endif; ?>
            <h1>Airline Rebooking Dashboard</h1>
            <p>Flight schedules, disruptions and seat availability</p>
        </div>
    </header>

    <main class="container">
        <section class="stats" aria-label="Flight overview">
            <div class="stat">
                <span>Total flights</span>
                <strong><?= count($flights) ?></strong>
            </div>
            <div class="stat">
                <span>Disrupted flights</span>
                <strong><?= $disruptedFlights ?></strong>
            </div>
            <div class="stat">
                <span>Affected bookings</span>
                <strong><?= $affectedBookings ?></strong>
            </div>
        </section>

        <section class="panel">
            <h2>Flight schedule</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Flight</th>
                            <th scope="col">Route</th>
                            <th scope="col">Departure</th>
                            <th scope="col">Status</th>
                            <th scope="col">Occupied</th>
                            <th scope="col">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($flights === []): ?>
                            <tr><td colspan="6">No flights found.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($flights as $flight): ?>
                            <tr>
                                <td><strong><?= e($flight['flight_number']) ?></strong></td>
                                <td>
                                    <?= e($flight['source_airport']) ?> →
                                    <?= e($flight['destination_airport']) ?>
                                </td>
                                <td>
                                    <?= e(date('d M Y, H:i', strtotime($flight['departure_date_time']))) ?>
                                </td>
                                <td>
                                    <span class="status <?= e(strtolower($flight['flight_status'])) ?>">
                                        <?= e($flight['flight_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= (int) $flight['occupied_seats'] ?> /
                                    <?= (int) $flight['total_seats'] ?>
                                </td>
                                <td>
                                    <?php if (in_array($flight['flight_status'], ['Scheduled', 'Delayed'], true)): ?>
                                        <?= (int) $flight['available_seats'] ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>