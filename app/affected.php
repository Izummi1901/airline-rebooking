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

$_SESSION['rebook_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['rebook_token'] ?? '';
    $bookingId = filter_input(
        INPUT_POST, 'booking_id', FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $flightId = filter_input(
        INPUT_POST, 'flight_id', FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $seatInput = $_POST['seat_number'] ?? '';
    $seat = is_string($seatInput) ? strtoupper(trim($seatInput)) : '';

    if (!is_string($token) || !hash_equals($_SESSION['rebook_token'], $token)) {
        http_response_code(403);
        exit('Reload the page and try again.');
    }

    if (!$bookingId || !$flightId || !preg_match('/^[A-Z0-9]{1,6}$/', $seat)) {
        $_SESSION['rebook_notice'] = [
            'error', 'Select a replacement flight and enter a valid seat number.'
        ];
    } else {
        try {
            $rebook = $pdo->prepare('CALL rebook_passenger(?, ?, ?, ?)');
            $rebook->execute([$bookingId, $flightId, $seat, $_SESSION['user_id']]);
            $rebook->closeCursor();

            $_SESSION['rebook_notice'] = [
                'success', "Rebooking confirmed. New seat: $seat."
            ];
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                $message = 'That seat is already taken, or the booking was already processed.';
            } elseif (($error->errorInfo[0] ?? '') === '45000') {
                $message = $error->errorInfo[2] ?? 'The rebooking could not be completed.';
            } else {
                error_log($error->getMessage());
                $message = 'Rebooking failed. Please try again.';
            }

            $_SESSION['rebook_notice'] = ['error', $message];
        }
    }

    // Refresh the list through GET so reloading does not submit the form again.
    header('Location: affected.php', true, 303);
    exit;
}

$notice = $_SESSION['rebook_notice'] ?? null;
unset($_SESSION['rebook_notice']);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Fetch affected bookings and their original flight IDs.
$affectedBookings = $pdo->query("
    SELECT
        b.booking_id,
        b.seat_number,
        p.passenger_name,
        f.flight_id,
        f.flight_number
    FROM booking AS b
    JOIN passenger AS p ON p.passenger_id = b.passenger_id
    JOIN flight AS f ON f.flight_id = b.flight_id
    WHERE b.booking_status = 'Affected'
      AND f.flight_status IN ('Cancelled', 'Delayed')
    ORDER BY f.departure_date_time, p.passenger_name
")->fetchAll();

// Find later, scheduled flights on the same route with free seats.
$findAlternatives = $pdo->prepare("
    SELECT
        alt.flight_id,
        alt.flight_number,
        alt.departure_date_time,
        alt.total_seats - COUNT(b.booking_id) AS available_seats
    FROM flight AS original
    JOIN flight AS alt
        ON alt.source_airport_id = original.source_airport_id
        AND alt.destination_airport_id = original.destination_airport_id
        AND alt.departure_date_time > original.departure_date_time
    LEFT JOIN booking AS b
        ON b.flight_id = alt.flight_id
        AND b.booking_status IN ('Confirmed', 'Affected')
    WHERE original.flight_id = :original_flight_id
      AND alt.flight_status = 'Scheduled'
      AND alt.departure_date_time > NOW()
    GROUP BY
        alt.flight_id, alt.flight_number, alt.departure_date_time, alt.total_seats
    HAVING alt.total_seats - COUNT(b.booking_id) > 0
    ORDER BY alt.departure_date_time
");

// Passengers from the same flight share the same list of alternatives.
$alternativesByFlight = [];

foreach ($affectedBookings as $booking) {
    $flightId = (int) $booking['flight_id'];

    if (!isset($alternativesByFlight[$flightId])) {
        $findAlternatives->execute(['original_flight_id' => $flightId]);
        $alternativesByFlight[$flightId] = $findAlternatives->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Affected Passengers</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f2f5fa;
            color: #15233a;
            font-family: system-ui, sans-serif;
        }
        header {
            background: linear-gradient(115deg, #101e37, #244d8c);
            color: white;
            padding: 32px 24px;
        }
        .container { max-width: 1040px; margin: auto; }
        header a { color: #d7e6ff; }
        h1 { margin: 10px 0 0; font-size: clamp(26px, 4vw, 38px); }
        main { padding: 24px; }
        .card {
            margin-bottom: 16px;
            padding: 20px;
            background: white;
            border: 1px solid #e1e8f2;
            border-radius: 14px;
        }
        h2 { margin: 0; font-size: 20px; }
        h3 { margin: 20px 0 8px; font-size: 16px; }
        .details { margin: 6px 0 0; color: #51617b; }
        ul { margin: 0; padding: 0; list-style: none; }
        li {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 16px;
            padding: 12px 0;
            border-top: 1px solid #e8edf5;
        }
        .seats {
            padding: 4px 10px;
            border-radius: 20px;
            background: #e4f6ed;
            color: #135d37;
            font-size: 13px;
            font-weight: 600;
        }
        .rebook-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.rebook-form input {
    width: 90px;
    padding: 7px;
    border: 1px solid #aab7ca;
    border-radius: 6px;
}

.rebook-form button {
    padding: 8px 14px;
    border: 0;
    border-radius: 6px;
    background: #244d8c;
    color: white;
    font-weight: 600;
    cursor: pointer;
}

.notice { padding: 12px 16px; border-radius: 8px; }
.notice.success { background: #e4f6ed; color: #135d37; }
.notice.error { background: #fde8e8; color: #9d2630; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php">← Flight dashboard</a>
            <h1>Affected passengers</h1>
        </div>
    </header>

    <main class="container">
        <?php if ($notice !== null): ?>
    <p class="notice <?= e($notice[0]) ?>" role="status">
        <?= e($notice[1]) ?>
    </p>
<?php endif; ?>
        <?php if ($affectedBookings === []): ?>
            <p class="card">No affected bookings found.</p>
        <?php endif; ?>

        <?php foreach ($affectedBookings as $booking): ?>
            <?php $alternatives = $alternativesByFlight[(int) $booking['flight_id']]; ?>

            <article class="card">
                <h2><?= e($booking['passenger_name']) ?></h2>
                <p class="details">
                    Booking #<?= (int) $booking['booking_id'] ?> ·
                    Flight <?= e($booking['flight_number']) ?> ·
                    Seat <?= e($booking['seat_number']) ?>
                </p>

                <h3>Replacement flights</h3>

                <?php if ($alternatives === []): ?>
                    <p class="details">No scheduled flights with free seats on this route.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($alternatives as $flight): ?>
                            <li>
                                <strong><?= e($flight['flight_number']) ?></strong>
                                <span>
                                    <?= e(date('d M Y, H:i', strtotime($flight['departure_date_time']))) ?>
                                </span>
                                <?php $available = (int) $flight['available_seats']; ?>
<span class="seats">
    <?= $available ?> <?= $available === 1 ? 'seat' : 'seats' ?> available
</span>

<form class="rebook-form" method="post" action="affected.php">
    <input type="hidden" name="rebook_token"
           value="<?= e($_SESSION['rebook_token']) ?>">
    <input type="hidden" name="booking_id"
           value="<?= (int) $booking['booking_id'] ?>">
    <input type="hidden" name="flight_id"
           value="<?= (int) $flight['flight_id'] ?>">

    <label>
        New seat
        <input name="seat_number" placeholder="e.g. 1C"
               maxlength="6" pattern="[A-Za-z0-9]{1,6}" required>
    </label>
    <button type="submit">Rebook</button>
</form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>