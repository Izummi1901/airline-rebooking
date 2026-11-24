CREATE DATABASE IF NOT EXISTS airline_rebooking
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE airline_rebooking;

CREATE TABLE passenger (
    passenger_id INT AUTO_INCREMENT PRIMARY KEY,
    passenger_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NOT NULL,
    passport_number VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE airport (
    airport_id INT AUTO_INCREMENT PRIMARY KEY,
    airport_name VARCHAR(150) NOT NULL,
    city VARCHAR(80) NOT NULL,
    airport_code CHAR(3) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Stores the admin and airline staff accounts.
CREATE TABLE app_user (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Airline Staff') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE flight (
    flight_id INT AUTO_INCREMENT PRIMARY KEY,
    flight_number VARCHAR(10) NOT NULL,
    source_airport_id INT NOT NULL,
    destination_airport_id INT NOT NULL,
    departure_date_time DATETIME NOT NULL,
    arrival_date_time DATETIME NOT NULL,
    total_seats INT NOT NULL,
    flight_status ENUM('Scheduled', 'Delayed', 'Cancelled', 'Completed')
        NOT NULL DEFAULT 'Scheduled',

    CONSTRAINT uq_flight_schedule
        UNIQUE (flight_number, departure_date_time),

    CONSTRAINT chk_different_airports
        CHECK (source_airport_id <> destination_airport_id),

    CONSTRAINT chk_flight_times
        CHECK (arrival_date_time > departure_date_time),

    CONSTRAINT chk_total_seats
        CHECK (total_seats > 0),

    FOREIGN KEY (source_airport_id) REFERENCES airport (airport_id),
    FOREIGN KEY (destination_airport_id) REFERENCES airport (airport_id)
) ENGINE=InnoDB;

CREATE TABLE booking (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    flight_id INT NOT NULL,
    booking_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    seat_number VARCHAR(6) NOT NULL,
    travel_class ENUM('Economy', 'Business', 'First') NOT NULL,
    booking_status ENUM('Confirmed', 'Affected', 'Cancelled', 'Rebooked')
        NOT NULL DEFAULT 'Confirmed',

    -- Confirmed and affected bookings both reserve their seats.
    active_seat VARCHAR(6) GENERATED ALWAYS AS (
        CASE
            WHEN booking_status IN ('Confirmed', 'Affected') THEN seat_number
            ELSE NULL
        END
    ) STORED,

    CONSTRAINT uq_active_flight_seat
        UNIQUE (flight_id, active_seat),

    FOREIGN KEY (passenger_id) REFERENCES passenger (passenger_id),
    FOREIGN KEY (flight_id) REFERENCES flight (flight_id)
) ENGINE=InnoDB;

CREATE TABLE disruption (
    disruption_id INT AUTO_INCREMENT PRIMARY KEY,
    flight_id INT NOT NULL,
    user_id INT NOT NULL,
    disruption_type ENUM('Delay', 'Cancellation') NOT NULL,
    disruption_reason VARCHAR(255) NOT NULL,
    reported_date_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expected_delay INT COMMENT 'Delay in minutes',

    CONSTRAINT chk_expected_delay
        CHECK (expected_delay >= 0),

    FOREIGN KEY (flight_id) REFERENCES flight (flight_id),
    FOREIGN KEY (user_id) REFERENCES app_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE rebooking (
    rebooking_id INT AUTO_INCREMENT PRIMARY KEY,
    original_booking_id INT NOT NULL UNIQUE,
    new_booking_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    rebooking_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rebooking_status VARCHAR(20) NOT NULL DEFAULT 'Completed',

    CONSTRAINT chk_different_bookings
        CHECK (original_booking_id <> new_booking_id),

    FOREIGN KEY (original_booking_id) REFERENCES booking (booking_id),
    FOREIGN KEY (new_booking_id) REFERENCES booking (booking_id),
    FOREIGN KEY (user_id) REFERENCES app_user (user_id)
) ENGINE=InnoDB;

SHOW TABLES;