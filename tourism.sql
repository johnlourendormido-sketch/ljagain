-- ============================================================
-- Tour Guide Management System - Database Schema
-- Database: tourism_db
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

DROP DATABASE IF EXISTS tourism_db;

CREATE DATABASE IF NOT EXISTS tourism_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tourism_db;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff','guide','tourist') NOT NULL DEFAULT 'tourist',
    name VARCHAR(150) NOT NULL,
    gender ENUM('male','female') NOT NULL DEFAULT 'male',
    age INT NOT NULL DEFAULT 18,
    phone VARCHAR(20) NOT NULL DEFAULT '',
    avatar VARCHAR(500) NULL,
    status ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
    last_login_ip VARCHAR(45) NULL,
    last_active_ip VARCHAR(45) NULL,
    last_login_at DATETIME NULL,
    login_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status),
    INDEX idx_users_email (email),
    INDEX idx_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TOURIST PROFILES TABLE
-- ============================================================
CREATE TABLE tourist_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    emergency_contact VARCHAR(150) NOT NULL,
    emergency_contact_number VARCHAR(20) NOT NULL,
    disability ENUM('none','physical','visual','hearing','other') NOT NULL DEFAULT 'none',
    disability_details TEXT NULL,
    CONSTRAINT fk_tourist_profiles_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. GUIDE PROFILES TABLE
-- ============================================================
CREATE TABLE guide_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    years_of_experience INT NOT NULL DEFAULT 0,
    languages TEXT NOT NULL,
    specializations TEXT NOT NULL,
    availability_status ENUM('available','on_tour','off_duty','on_leave','suspended') NOT NULL DEFAULT 'available',
    bio TEXT NULL,
    CONSTRAINT fk_guide_profiles_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_guide_availability (availability_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ID VERIFICATIONS TABLE
-- ============================================================
CREATE TABLE id_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    id_type ENUM('passport','drivers_license','national_id','voters_id','senior_citizen','other') NOT NULL,
    id_file_path VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_id_verifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_id_verifications_verifier FOREIGN KEY (verified_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_id_verifications_status (status),
    INDEX idx_id_verifications_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. DESTINATIONS TABLE
-- ============================================================
CREATE TABLE destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    difficulty ENUM('easy','moderate','difficult','extreme') NOT NULL DEFAULT 'easy',
    capacity_limit INT NOT NULL DEFAULT 0,
    recommended_age_min INT NOT NULL DEFAULT 1,
    recommended_age_max INT NOT NULL DEFAULT 100,
    accessibility_info TEXT NULL,
    image VARCHAR(500) NULL,
    gallery_images TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    booking_enabled TINYINT(1) NOT NULL DEFAULT 1,
    guide_required TINYINT(1) NOT NULL DEFAULT 1,
    entrance_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    package_price DECIMAL(10,2) NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'other',
    contact_phone VARCHAR(20) NULL,
    contact_email VARCHAR(100) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    operating_hours_open TIME NULL,
    operating_hours_close TIME NULL,
    max_guests_per_booking INT NOT NULL DEFAULT 10,
    available_booking_days VARCHAR(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
    booking_cutoff_hours INT NOT NULL DEFAULT 2,
    advance_booking_days INT NOT NULL DEFAULT 1,
    rules_regulations TEXT NULL,
    facilities TEXT NULL,
    cancellation_policy TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_destinations_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_destinations_status (status),
    INDEX idx_destinations_difficulty (difficulty),
    INDEX idx_destinations_location (location),
    INDEX idx_destinations_category (category),
    INDEX idx_destinations_featured (featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. DESTINATION SEASONS TABLE
-- ============================================================
CREATE TABLE destination_seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    season_type ENUM('peak','off_peak') NOT NULL,
    months VARCHAR(255) NOT NULL,
    description TEXT NULL,
    CONSTRAINT fk_destination_seasons_dest FOREIGN KEY (destination_id)
        REFERENCES destinations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_destination_seasons_type (season_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. DESTINATION GUIDES TABLE
-- ============================================================
CREATE TABLE destination_guides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    guide_id INT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dg_destination FOREIGN KEY (destination_id)
        REFERENCES destinations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dg_guide FOREIGN KEY (guide_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_dg_pair (destination_id, guide_id),
    INDEX idx_dg_destination (destination_id),
    INDEX idx_dg_guide (guide_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. DESTINATION REVIEWS TABLE
-- ============================================================
CREATE TABLE destination_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL,
    review TEXT NULL,
    images TEXT NULL,
    video VARCHAR(500) NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dr_destination FOREIGN KEY (destination_id)
        REFERENCES destinations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dr_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_dr_destination (destination_id),
    INDEX idx_dr_user (user_id),
    INDEX idx_dr_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. EVENTS TABLE
-- ============================================================
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    destination_id INT NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'tourism_event',
    event_image VARCHAR(500) NULL,
    event_location VARCHAR(255) NULL,
    event_start_date DATE NULL,
    event_end_date DATE NULL,
    event_start_time TIME NULL,
    event_end_time TIME NULL,
    organizer VARCHAR(200) NULL,
    contact_info VARCHAR(255) NULL,
    max_participants INT NOT NULL DEFAULT 0,
    min_participants INT NOT NULL DEFAULT 1,
    min_age INT NOT NULL DEFAULT 1,
    max_age INT NULL,
    health_restrictions TEXT NULL,
    accessibility_info TEXT NULL,
    requires_guide TINYINT(1) NOT NULL DEFAULT 1,
    duration_hours DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','active','published','cancelled','completed') NOT NULL DEFAULT 'draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_destination FOREIGN KEY (destination_id)
        REFERENCES destinations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_events_status (status),
    INDEX idx_events_destination (destination_id),
    INDEX idx_events_price (price),
    INDEX idx_events_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. SCHEDULES TABLE
-- ============================================================
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    guide_id INT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    available_spots INT NOT NULL DEFAULT 0,
    status ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedules_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedules_guide FOREIGN KEY (guide_id)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_schedules_status (status),
    INDEX idx_schedules_dates (start_date, end_date),
    INDEX idx_schedules_guide (guide_id),
    INDEX idx_schedules_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. BOOKINGS TABLE
-- ============================================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tourist_id INT NOT NULL,
    schedule_id INT NULL,
    destination_id INT NULL,
    activity_id INT NULL,
    guide_id INT NULL,
    booking_reference VARCHAR(50) NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    visit_date DATE NULL,
    visit_time TIME NULL,
    num_participants INT NOT NULL DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    full_name VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    contact_number VARCHAR(20) NULL,
    status ENUM('pending','confirmed','paid','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
    payment_status ENUM('pending','paid','refunded','failed','unpaid') NOT NULL DEFAULT 'pending',
    payment_method ENUM('gcash','maya','card','cash') NULL,
    special_requests TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_tourist FOREIGN KEY (tourist_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_schedule FOREIGN KEY (schedule_id)
        REFERENCES schedules(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_destination FOREIGN KEY (destination_id)
        REFERENCES destinations(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_guide FOREIGN KEY (guide_id)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_bookings_tourist (tourist_id),
    INDEX idx_bookings_schedule (schedule_id),
    INDEX idx_bookings_status (status),
    INDEX idx_bookings_date (booking_date),
    INDEX idx_bookings_reference (booking_reference),
    INDEX idx_bookings_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11b. RESERVATIONS TABLE (separate from BOOKINGS — slot/facility hold, no payment)
-- ============================================================
CREATE TABLE IF NOT EXISTS reservations (
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

-- ============================================================
-- 12. PAYMENTS TABLE
-- ============================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    tourist_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('card','gcash','maya','cash') NOT NULL DEFAULT 'card',
    card_last_four VARCHAR(4) NULL,
    card_brand VARCHAR(20) NULL,
    transaction_id VARCHAR(255) NULL,
    reference_number VARCHAR(100) NULL,
    payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payments_tourist FOREIGN KEY (tourist_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_payments_booking (booking_id),
    INDEX idx_payments_tourist (tourist_id),
    INDEX idx_payments_status (payment_status),
    INDEX idx_payments_reference (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. FEEDBACK TABLE
-- ============================================================
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    tourist_id INT NOT NULL,
    guide_id INT NOT NULL,
    schedule_id INT NOT NULL,
    guide_rating INT NOT NULL,
    communication_rating INT NOT NULL,
    safety_rating INT NOT NULL,
    organization_rating INT NOT NULL,
    overall_rating INT NOT NULL,
    comment TEXT NULL,
    suggestions TEXT NULL,
    complaints TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_tourist FOREIGN KEY (tourist_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_guide FOREIGN KEY (guide_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_schedule FOREIGN KEY (schedule_id)
        REFERENCES schedules(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_feedback_tourist (tourist_id),
    INDEX idx_feedback_guide (guide_id),
    INDEX idx_feedback_booking (booking_id),
    INDEX idx_feedback_overall (overall_rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. GUIDE PAYOUTS TABLE
-- ============================================================
CREATE TABLE guide_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guide_id INT NOT NULL,
    booking_id INT NOT NULL,
    payment_id INT NULL,
    tour_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    net_earning DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payout_status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    paid_at DATETIME NULL,
    payout_reference VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gp_guide FOREIGN KEY (guide_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_gp_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_gp_payment FOREIGN KEY (payment_id)
        REFERENCES payments(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_gp_approved_by FOREIGN KEY (approved_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_gp_guide (guide_id),
    INDEX idx_gp_status (payout_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. MESSAGES TABLE
-- ============================================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    reply_to_message_id INT NULL,
    file_url VARCHAR(500) NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_receiver (receiver_id),
    INDEX idx_messages_read (is_read),
    INDEX idx_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. CONVERSATIONS TABLE
-- ============================================================
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    last_message TEXT NULL,
    last_activity DATETIME NULL,
    deleted_by_user1 TINYINT(1) NOT NULL DEFAULT 0,
    deleted_by_user2 TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conv_user1 FOREIGN KEY (user1_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_conv_user2 FOREIGN KEY (user2_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_conv_pair (user1_id, user2_id),
    INDEX idx_conv_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    link VARCHAR(500) NULL,
    priority ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
    scheduled_at DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'delivered',
    audience VARCHAR(100) NULL,
    recipient_count INT NOT NULL DEFAULT 1,
    batch_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. ACTIVITY LOGS TABLE
-- ============================================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_activity_logs_user (user_id),
    INDEX idx_activity_logs_action (action),
    INDEX idx_activity_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. CALLS TABLE
-- ============================================================
CREATE TABLE calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caller_id INT NOT NULL,
    receiver_id INT NOT NULL,
    call_type ENUM('voice','video') NOT NULL DEFAULT 'voice',
    status ENUM('ongoing','completed','missed','cancelled') NOT NULL DEFAULT 'completed',
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    duration INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_calls_caller FOREIGN KEY (caller_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_calls_receiver FOREIGN KEY (receiver_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_calls_caller (caller_id),
    INDEX idx_calls_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. USER MESSAGE SETTINGS TABLE
-- ============================================================
CREATE TABLE user_message_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    show_read_receipts TINYINT(1) NOT NULL DEFAULT 1,
    show_online_status TINYINT(1) NOT NULL DEFAULT 1,
    message_notifications TINYINT(1) NOT NULL DEFAULT 1,
    sound_notifications TINYINT(1) NOT NULL DEFAULT 1,
    message_preview TINYINT(1) NOT NULL DEFAULT 1,
    who_can_message ENUM('everyone','contacts','nobody') NOT NULL DEFAULT 'everyone',
    blocked_users JSON NULL,
    CONSTRAINT fk_ums_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================
INSERT INTO users (username, email, password, role, name, gender, age, phone, status)
VALUES (
    'admin',
    'admin@tourism.com',
    '$2y$10$uidVaAQcO4TpiDopOaUEY.Tyih/kEwdnq0WRUapacLWbySw5l7iW2',
    'admin',
    'System Administrator',
    'male',
    30,
    '+1234567890',
    'active'
);
