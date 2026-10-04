-- ============================================================
-- BINALGO Tourism — Clear All Demo Data
-- Keeps: schema, admin user (id=1), destination structure
-- Removes: all bookings, payments, feedback, logs, demo users,
--          events, schedules, notifications, reviews
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Transactional / log data (safe to full truncate)
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE user_message_settings;

-- Financial
TRUNCATE TABLE payments;
TRUNCATE TABLE guide_payouts;

-- Booking-related
TRUNCATE TABLE feedback;
TRUNCATE TABLE destination_reviews;
TRUNCATE TABLE bookings;

-- Events & schedules
TRUNCATE TABLE schedules;
TRUNCATE TABLE events;

-- Guide / destination relations
TRUNCATE TABLE destination_guides;
TRUNCATE TABLE destination_seasons;

-- User-related
TRUNCATE TABLE id_verifications;
TRUNCATE TABLE tourist_profiles;
TRUNCATE TABLE guide_profiles;

-- Bookmarks
TRUNCATE TABLE dest_bookmarks;
TRUNCATE TABLE event_bookmarks;
TRUNCATE TABLE general_feedback;

-- Demo users (keep id=1 = admin)
DELETE FROM users WHERE id != 1;

-- Reset destinations to clean slate (no bookings, no guides)
UPDATE destinations SET
    booking_enabled = 0,
    guide_required = 0,
    featured = 0,
    status = 'active',
    updated_at = NOW();

SET FOREIGN_KEY_CHECKS = 1;
