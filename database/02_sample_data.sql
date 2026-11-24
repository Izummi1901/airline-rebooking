USE airline_rebooking;

START TRANSACTION;

-- Airports used in the demo.
INSERT INTO airport (airport_name, city, airport_code)
VALUES
    ('Indira Gandhi International Airport', 'Delhi', 'DEL'),
    ('Chhatrapati Shivaji Maharaj International Airport', 'Mumbai', 'BOM'),
    ('Kempegowda International Airport', 'Bengaluru', 'BLR');

-- Look up airport IDs instead of assuming they start at 1.
SELECT airport_id INTO @del FROM airport WHERE airport_code = 'DEL';
SELECT airport_id INTO @bom FROM airport WHERE airport_code = 'BOM';
SELECT airport_id INTO @blr FROM airport WHERE airport_code = 'BLR';

-- Schedule the demo for tomorrow, based on the database server's date.
SET @travel_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY);

INSERT INTO flight (
    flight_number, source_airport_id, destination_airport_id,
    departure_date_time, arrival_date_time, total_seats, flight_status
)
VALUES
    ('DEMO101', @del, @bom, TIMESTAMP(@travel_date, '09:00:00'),
     TIMESTAMP(@travel_date, '11:15:00'), 4, 'Cancelled'),
    ('DEMO102', @del, @bom, TIMESTAMP(@travel_date, '13:00:00'),
     TIMESTAMP(@travel_date, '15:15:00'), 3, 'Scheduled'),
    ('DEMO103', @del, @bom, TIMESTAMP(@travel_date, '17:00:00'),
     TIMESTAMP(@travel_date, '19:15:00'), 1, 'Scheduled'),
    ('DEMO104', @del, @blr, TIMESTAMP(@travel_date, '14:00:00'),
     TIMESTAMP(@travel_date, '16:45:00'), 4, 'Scheduled');

INSERT INTO passenger (passenger_name, email, phone_number, passport_number)
VALUES
    ('Aarav Sharma', 'aarav@example.test', '9000000001', 'DEMO-P001'),
    ('Meera Jain', 'meera@example.test', '9000000002', 'DEMO-P002'),
    ('Rohan Verma', 'rohan@example.test', '9000000003', 'DEMO-P003'),
    ('Ananya Kapoor', 'ananya@example.test', '9000000004', 'DEMO-P004');

-- Match each demo passenger to their flight using JOIN.
INSERT INTO booking (passenger_id, flight_id, seat_number, travel_class, booking_status)
SELECT p.passenger_id, f.flight_id, d.seat_number, 'Economy', d.booking_status
FROM (
    SELECT 'aarav@example.test' AS email, 'DEMO101' AS flight_number,
           '1A' AS seat_number, 'Affected' AS booking_status
    UNION ALL
    SELECT 'meera@example.test', 'DEMO101', '1B', 'Affected'
    UNION ALL
    SELECT 'rohan@example.test', 'DEMO102', '1A', 'Confirmed'
    UNION ALL
    SELECT 'ananya@example.test', 'DEMO103', '1A', 'Confirmed'
) AS d
JOIN passenger AS p ON p.email = d.email
JOIN flight AS f ON f.flight_number = d.flight_number
WHERE f.departure_date_time >= @travel_date
  AND f.departure_date_time < DATE_ADD(@travel_date, INTERVAL 1 DAY);

COMMIT;

-- Verify the imported data.
SELECT flight_number, flight_status, total_seats
FROM flight
ORDER BY departure_date_time;

SELECT booking_status, COUNT(*) AS total_bookings
FROM booking
GROUP BY booking_status;