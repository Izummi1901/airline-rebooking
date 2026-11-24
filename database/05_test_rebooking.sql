USE airline_rebooking;

-- Find Aarav's original booking on the cancelled demo flight.
SET @old_booking_id = (
    SELECT b.booking_id
    FROM booking AS b
    JOIN passenger AS p ON p.passenger_id = b.passenger_id
    JOIN flight AS f ON f.flight_id = b.flight_id
    WHERE p.email = 'aarav@example.test'
      AND f.flight_number = 'DEMO101'
      AND b.booking_status = 'Affected'
    ORDER BY f.departure_date_time DESC
    LIMIT 1
);

-- Find the replacement flight on the same travel date.
SET @new_flight_id = (
    SELECT replacement.flight_id
    FROM booking AS b
    JOIN flight AS original ON original.flight_id = b.flight_id
    JOIN flight AS replacement
        ON replacement.departure_date_time >= DATE(original.departure_date_time)
       AND replacement.departure_date_time < DATE(original.departure_date_time) + INTERVAL 1 DAY
    WHERE b.booking_id = @old_booking_id
      AND replacement.flight_number = 'DEMO102'
    ORDER BY replacement.departure_date_time
    LIMIT 1
);

SET @staff_id = (
    SELECT user_id
    FROM app_user
    WHERE email = 'staff@example.test'
);

-- Rebook Aarav onto seat 1B.
CALL rebook_passenger(@old_booking_id, @new_flight_id, '1B', @staff_id);

-- Verify the original booking, replacement booking and staff record.
SELECT
    p.passenger_name,
    old_f.flight_number AS original_flight,
    old_b.booking_status AS original_status,
    new_f.flight_number AS replacement_flight,
    new_b.seat_number AS new_seat,
    new_b.booking_status AS new_status,
    u.user_name AS processed_by
FROM rebooking AS r
JOIN booking AS old_b ON old_b.booking_id = r.original_booking_id
JOIN booking AS new_b ON new_b.booking_id = r.new_booking_id
JOIN passenger AS p ON p.passenger_id = old_b.passenger_id
JOIN flight AS old_f ON old_f.flight_id = old_b.flight_id
JOIN flight AS new_f ON new_f.flight_id = new_b.flight_id
JOIN app_user AS u ON u.user_id = r.user_id
WHERE r.original_booking_id = @old_booking_id;