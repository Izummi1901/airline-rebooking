USE airline_rebooking;

-- Add a later DEL → BOM flight with enough seats for all three passengers.
INSERT INTO flight (
    flight_number,
    source_airport_id,
    destination_airport_id,
    departure_date_time,
    arrival_date_time,
    total_seats,
    flight_status
)
SELECT
    'DEMO105',
    src.airport_id,
    dst.airport_id,
    TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00'),
    TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '21:15:00'),
    3,
    'Scheduled'
FROM airport AS src
JOIN airport AS dst ON dst.airport_code = 'BOM'
WHERE src.airport_code = 'DEL'
  AND NOT EXISTS (
      SELECT 1
      FROM flight
      WHERE flight_number = 'DEMO105'
        AND departure_date_time =
            TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00')
  );

SELECT flight_number, departure_date_time, total_seats, flight_status
FROM flight
WHERE flight_number = 'DEMO105'
ORDER BY departure_date_time DESC;