USE airline_rebooking;

DROP PROCEDURE IF EXISTS record_disruption;

DELIMITER $$

CREATE PROCEDURE record_disruption(
    IN p_flight_id INT,
    IN p_user_id INT,
    IN p_type VARCHAR(20),
    IN p_reason VARCHAR(255),
    IN p_expected_delay INT
)
BEGIN
    DECLARE v_flight_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_user_role VARCHAR(20) DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT role INTO v_user_role
    FROM app_user
    WHERE user_id = p_user_id
    FOR SHARE;

    IF v_user_role IS NULL OR v_user_role <> 'Admin' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only an admin can record a disruption.';
    END IF;

    IF p_type IS NULL OR p_type NOT IN ('Delay', 'Cancellation') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Select Delay or Cancellation.';
    END IF;

    IF p_reason IS NULL OR TRIM(p_reason) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Enter the disruption reason.';
    END IF;

    IF p_type = 'Delay' AND (p_expected_delay IS NULL OR p_expected_delay <= 0) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Enter the expected delay in minutes.';
    END IF;

    SELECT flight_status INTO v_flight_status
    FROM flight
    WHERE flight_id = p_flight_id
    FOR UPDATE;

    IF v_flight_status IS NULL OR v_flight_status NOT IN ('Scheduled', 'Delayed') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Select a scheduled or delayed flight.';
    END IF;

    IF p_type = 'Delay' AND v_flight_status = 'Delayed' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This flight is already marked as delayed.';
    END IF;

    UPDATE flight
    SET flight_status = CASE p_type
        WHEN 'Delay' THEN 'Delayed'
        ELSE 'Cancelled'
    END
    WHERE flight_id = p_flight_id;

    INSERT INTO disruption (
        flight_id, user_id, disruption_type, disruption_reason, expected_delay
    )
    VALUES (
        p_flight_id, p_user_id, p_type, TRIM(p_reason),
        CASE WHEN p_type = 'Delay' THEN p_expected_delay ELSE NULL END
    );

    -- Walk through confirmed bookings one at a time.
    BEGIN
        DECLARE v_done BOOLEAN DEFAULT FALSE;
        DECLARE v_booking_id INT;

        DECLARE confirmed_bookings CURSOR FOR
            SELECT booking_id
            FROM booking
            WHERE flight_id = p_flight_id
              AND booking_status = 'Confirmed'
            ORDER BY booking_id;

        DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

        OPEN confirmed_bookings;

        booking_loop: LOOP
            FETCH confirmed_bookings INTO v_booking_id;

            IF v_done THEN
                LEAVE booking_loop;
            END IF;

            UPDATE booking
            SET booking_status = 'Affected'
            WHERE booking_id = v_booking_id
              AND booking_status = 'Confirmed';
        END LOOP;

        CLOSE confirmed_bookings;
    END;

    COMMIT;
END$$

DELIMITER ;