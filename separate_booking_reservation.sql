-- ============================================================
-- BINALGO — Separate BOOKING and RESERVATION
-- Run once: creates dedicated reservations table and patches bookings
-- ============================================================

-- 1. Drop old reservations table if it was created from previous attempt
DROP TABLE IF EXISTS reservations;

-- 2. Create dedicated RESERVATIONS table (slot/facility reservation WITHOUT payment)
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    destination_id INT NOT NULL,
    facility_id INT NULL,
    facility_name VARCHAR(200) NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NULL,
    visitors INT NOT NULL DEFAULT 1,
    notes TEXT NULL,
    reservation_reference VARCHAR(50) NOT NULL UNIQUE,
    reservation_status ENUM('pending','approved','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_res_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_res_user (user_id),
    INDEX idx_res_destination (destination_id),
    INDEX idx_res_date (reservation_date),
    INDEX idx_res_status (reservation_status),
    INDEX idx_res_reference (reservation_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Patch BOOKINGS table to support full Booking spec (service purchase WITH payment & guide)
-- Add missing columns if not exists
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS activity_id INT NULL AFTER destination_id,
    ADD COLUMN IF NOT EXISTS booking_reference_new VARCHAR(50) NULL AFTER booking_reference,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('gcash','maya','card','cash') NULL AFTER payment_status,
    ADD COLUMN IF NOT EXISTS service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_price,
    ADD COLUMN IF NOT EXISTS full_name VARCHAR(150) NULL AFTER service_fee,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER full_name,
    ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20) NULL AFTER email;

-- Expand status enums to include full Booking spec
ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','paid','completed','cancelled','rejected') NOT NULL DEFAULT 'pending';
ALTER TABLE bookings MODIFY payment_status ENUM('pending','paid','refunded','failed','unpaid') NOT NULL DEFAULT 'pending';

-- Add FK for activity if events table exists (optional)
-- ALTER TABLE bookings ADD CONSTRAINT fk_bookings_activity FOREIGN KEY (activity_id) REFERENCES events(id) ON DELETE SET NULL;

-- 4. Seed a dummy facility name for existing reservations if any (no data to migrate)
