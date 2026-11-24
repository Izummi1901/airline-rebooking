USE airline_rebooking;

DROP FUNCTION IF EXISTS check_seat_availability;

DELIMITER $$

CREATE FUNCTION check_seat_availability(p_flight_id INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_available_seats INT DEFAULT NULL;

    SELECT f.total_seats - COUNT(b.booking_id)
    INTO v_available_seats
    FROM flight AS f
    LEFT JOIN booking AS b
        ON b.flight_id = f.flight_id
        AND b.booking_status IN ('Confirmed', 'Affected')
    WHERE f.flight_id = p_flight_id
    GROUP BY f.flight_id, f.total_seats;

    RETURN v_available_seats;
END$$

DELIMITER ;