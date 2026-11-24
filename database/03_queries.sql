USE airline_rebooking;

-- 1. Dashboard ke liye flight route, status aur available seats.
SELECT
    f.flight_number,
    src.airport_code AS source_airport,
    dst.airport_code AS destination_airport,
    f.departure_date_time,
    f.flight_status,
    f.total_seats,
    COUNT(b.booking_id) AS occupied_seats,
    f.total_seats - COUNT(b.booking_id) AS available_seats
FROM flight AS f
JOIN airport AS src ON src.airport_id = f.source_airport_id
JOIN airport AS dst ON dst.airport_id = f.destination_airport_id
LEFT JOIN booking AS b
    ON b.flight_id = f.flight_id
    AND b.booking_status IN ('Confirmed', 'Affected')
GROUP BY
    f.flight_id, f.flight_number, src.airport_code, dst.airport_code,
    f.departure_date_time, f.flight_status, f.total_seats
ORDER BY f.departure_date_time;

-- 2. Cancelled ya delayed flights ke affected passengers.
SELECT
    b.booking_id,
    f.flight_number,
    p.passenger_name,
    b.seat_number,
    b.booking_status
FROM booking AS b
JOIN passenger AS p ON p.passenger_id = b.passenger_id
JOIN flight AS f ON f.flight_id = b.flight_id
WHERE b.booking_status = 'Affected'
  AND f.flight_status IN ('Cancelled', 'Delayed')
ORDER BY f.departure_date_time, p.passenger_name;

-- 3. DEMO101 ke liye same route par later flights with free seats.
SELECT
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
WHERE original.flight_number = 'DEMO101'
  AND original.flight_status IN ('Cancelled', 'Delayed')
  AND alt.flight_status = 'Scheduled'
GROUP BY
    alt.flight_id, alt.flight_number, alt.departure_date_time, alt.total_seats
HAVING alt.total_seats - COUNT(b.booking_id) > 0
ORDER BY alt.departure_date_time;

-- 4. Search scheduled flights by source, destination and travel date.
SET @source_code = 'DEL';
SET @destination_code = 'BOM';

-- Use an existing demo flight's date for the first test.
SET @travel_date = (
    SELECT DATE(MAX(departure_date_time))
    FROM flight
    WHERE flight_number = 'DEMO105'
);

SELECT
    f.flight_number,
    src.airport_code AS source_airport,
    dst.airport_code AS destination_airport,
    f.departure_date_time,
    f.arrival_date_time,
    check_seat_availability(f.flight_id) AS available_seats
FROM flight AS f
JOIN airport AS src ON src.airport_id = f.source_airport_id
JOIN airport AS dst ON dst.airport_id = f.destination_airport_id
WHERE src.airport_code = @source_code
  AND dst.airport_code = @destination_code
  AND f.flight_status = 'Scheduled'
  AND f.departure_date_time >= @travel_date
  AND f.departure_date_time < @travel_date + INTERVAL 1 DAY
ORDER BY f.departure_date_time;