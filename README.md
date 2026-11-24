# Airline Disruption & Passenger Rebooking System

A PHP and MySQL application for airline operations teams to manage flight delays, cancellations, and passenger rebooking. An administrator records a disruption, and authorized staff can move affected passengers to a later flight on the same route.

## Features

- **Flight dashboard** shows schedules, flight status, occupied seats, and seat availability.
- **Role-based access** gives Admins permission to record disruptions. Admins and Airline Staff can rebook passengers and view rebooking history.
- **Disruption management** records the reason for a delay or cancellation and marks confirmed bookings on that flight as affected.
- **Replacement flight search** finds later scheduled flights on the same route with available seats.
- **Rebooking history** links the original and replacement bookings and records who processed the change.
- **SQL flight search** filters scheduled flights by source, destination, and travel date.

## Database Design

| SQL file | What it demonstrates |
|---|---|
| `01_schema.sql` | Relational schema, primary keys, foreign keys, and unique constraints for active seat assignments |
| `03_queries.sql` | Joins, aggregation, route search, and availability checks |
| `04_rebooking.sql` | A transaction that creates the replacement booking, updates the original booking, and records rebooking history |
| `06_record_disruption.sql` | A stored procedure with a cursor that marks confirmed bookings as affected |
| `08_seat_availability.sql` | A stored function that calculates remaining capacity |
| `09_booking_validation.sql` | A `BEFORE INSERT` trigger that rejects new active bookings on a full flight |

**Available seats = Total seats − Confirmed bookings − Affected bookings**

All three rebooking changes commit together. If the history insert fails, the transaction rolls back the replacement booking and the original booking status update.

## Run Locally

**Requirements:** PHP 8 with PDO MySQL, MySQL 8, and a web server such as Apache. The project was developed with XAMPP and phpMyAdmin on Windows.

1. Clone the repository into your Apache document root. For a default Windows XAMPP installation, run this from `C:\xampp\htdocs`.

   ```powershell
   git clone https://github.com/Izummi1901/airline-rebooking.git
   cd airline-rebooking
   ```

2. Start Apache and your MySQL server. Import these files from `database/` in this order.

   ```text
   01_schema.sql
   02_sample_data.sql
   04_rebooking.sql
   06_record_disruption.sql
   08_seat_availability.sql
   09_booking_validation.sql
   ```

   `03_queries.sql` contains standalone queries you can run after setup. `05_test_rebooking.sql` is an optional test that changes demo booking data. `07_demo_replacement.sql` adds another demo flight.

3. Create `app/db.local.php` with your local MySQL password. Git ignores this file.

   ```php
   <?php
   return 'YOUR_LOCAL_MYSQL_PASSWORD';
   ```

   Check the host, port, and MySQL username in `app/db.php` against your local setup.

4. From the project root, create the demo accounts.

   ```powershell
   C:\xampp\php\php.exe .\app\create_admin.php
   C:\xampp\php\php.exe .\app\create_staff.php
   ```

   The scripts print the generated passwords when they create the accounts. Use `admin@example.test` or `staff@example.test` on the shared sign-in page.

5. Open the [Flight Dashboard](http://localhost/airline-rebooking/app/index.php).

## Demo Workflow

1. Sign in as **Admin** and record a cancellation for a scheduled flight with a confirmed booking.
2. Open **Affected passengers** and select a later scheduled flight on the same route.
3. Enter a free seat and confirm the rebooking.
4. Check the updated seat availability and open **Rebooking history** to verify the original flight, replacement flight, and staff record.

Use future departure dates for the replacement flights. The application filters out flights that have already departed.

## Verification

- **Route/date search:** Returned the matching scheduled flights in departure order with calculated seat counts.
- **Full-flight booking:** The capacity trigger rejected a new booking on a full flight.
- **Transaction rollback:** A forced failure in the final history insert left the original booking `Affected`, with the booking and history row counts unchanged.

## Project Scope

The application supports airline Admin and Staff workflows. Initial flight and booking records are entered through SQL. Passenger self-service login and rebooking are future work.

## Project Team

Bhupendra Singh Gour · Vidwan Kapoor · Bhoomi Jain · Izummi Tyagi

MCA Semester 1 · Database Management Systems Lab (`24M15CA112`)  
Jaypee Institute of Information Technology, Noida
