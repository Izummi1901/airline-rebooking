USE airline_rebooking;

DROP PROCEDURE IF EXISTS rebook_passenger;

DELIMITER $$

CREATE PROCEDURE rebook_passenger(
    IN p_booking_id INT,
    IN p_new_flight_id INT,
    IN p_seat_number VARCHAR(6),
    IN p_user_id INT
)
BEGIN
    DECLARE v_passenger_id INT DEFAULT NULL;
    DECLARE v_original_flight_id INT;
    DECLARE v_travel_class VARCHAR(20);
    DECLARE v_booking_status VARCHAR(20);
    DECLARE v_source_id INT;
    DECLARE v_destination_id INT;
    DECLARE v_original_departure DATETIME;
    DECLARE v_original_status VARCHAR(20);

    DECLARE v_new_source_id INT;
    DECLARE v_new_destination_id INT;
    DECLARE v_new_departure DATETIME;
    DECLARE v_new_status VARCHAR(20);
    DECLARE v_capacity INT DEFAULT NULL;
    DECLARE v_occupied INT;
    DECLARE v_new_booking_id INT;

    -- Any error undoes all changes and goes back to the caller.
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Lock the original booking so two staff members cannot rebook it twice.
    SELECT passenger_id, flight_id, travel_class, booking_status
    INTO v_passenger_id, v_original_flight_id, v_travel_class, v_booking_status
    FROM booking
    WHERE booking_id = p_booking_id
    FOR UPDATE;

    IF v_passenger_id IS NULL OR v_booking_status <> 'Affected' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Select a booking with Affected status.';
    END IF;

    SELECT source_airport_id, destination_airport_id,
           departure_date_time, flight_status
    INTO v_source_id, v_destination_id, v_original_departure, v_original_status
    FROM flight
    WHERE flight_id = v_original_flight_id
    FOR UPDATE;

    IF v_original_status NOT IN ('Cancelled', 'Delayed') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'The original flight is not disrupted.';
    END IF;

    -- Lock the replacement flight before checking its capacity.
    SELECT source_airport_id, destination_airport_id,
           departure_date_time, flight_status, total_seats
    INTO v_new_source_id, v_new_destination_id,
         v_new_departure, v_new_status, v_capacity
    FROM flight
    WHERE flight_id = p_new_flight_id
    FOR UPDATE;

    IF v_capacity IS NULL
       OR v_new_status <> 'Scheduled'
       OR v_new_source_id <> v_source_id
       OR v_new_destination_id <> v_destination_id
       OR v_new_departure <= v_original_departure
       OR v_new_departure <= NOW() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Select a future scheduled flight on the same route.';
    END IF;

    IF p_seat_number IS NULL OR TRIM(p_seat_number) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Enter a seat number.';
    END IF;

    SELECT COUNT(*) INTO v_occupied
    FROM booking
    WHERE flight_id = p_new_flight_id
      AND booking_status IN ('Confirmed', 'Affected');

    IF v_occupied >= v_capacity THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'The replacement flight is full.';
    END IF;

    -- The booking table's unique constraint rejects duplicate active seats.
    INSERT INTO booking (
        passenger_id, flight_id, seat_number, travel_class, booking_status
    )
    VALUES (
        v_passenger_id, p_new_flight_id, UPPER(TRIM(p_seat_number)),
        v_travel_class, 'Confirmed'
    );

    SET v_new_booking_id = LAST_INSERT_ID();

    UPDATE booking
    SET booking_status = 'Rebooked'
    WHERE booking_id = p_booking_id;

    INSERT INTO rebooking (original_booking_id, new_booking_id, user_id)
    VALUES (p_booking_id, v_new_booking_id, p_user_id);

    COMMIT;
END$$

DELIMITER ;