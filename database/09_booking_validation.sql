USE airline_rebooking;

DROP TRIGGER IF EXISTS validate_booking_capacity;

DELIMITER $$

CREATE TRIGGER validate_booking_capacity
BEFORE INSERT ON booking
FOR EACH ROW
BEGIN
    DECLARE v_flight_id INT DEFAULT NULL;
    DECLARE v_available_seats INT;

    IF NEW.booking_status IN ('Confirmed', 'Affected') THEN
        -- Serialize new active bookings for the same flight.
        SELECT flight_id INTO v_flight_id
        FROM flight
        WHERE flight_id = NEW.flight_id
        FOR UPDATE;

        IF v_flight_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Flight does not exist.';
        END IF;

        SET v_available_seats = check_seat_availability(v_flight_id);

        IF v_available_seats <= 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Flight is full. Booking cannot be created.';
        END IF;
    END IF;
END$$

DELIMITER ;