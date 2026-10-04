<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/classes/User.php';
require_once __DIR__ . '/includes/classes/Booking.php';
require_once __DIR__ . '/includes/classes/ActivityLog.php';
require_once __DIR__ . '/includes/classes/Payment.php';
require_once __DIR__ . '/includes/classes/Schedule.php';
require_once __DIR__ . '/includes/classes/Feedback.php';
require_once __DIR__ . '/includes/classes/Event.php';
require_once __DIR__ . '/includes/classes/Destination.php';
$db = Database::getInstance()->getConnection();

echo "=== Check Staff dashboard dependencies ===" . PHP_EOL;
$checks = [
    "booking stats" => "SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) as confirmed_count, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_count FROM bookings",
    "feedback stats" => "SELECT COUNT(*) as total, COALESCE(AVG(rating),0) as avg_rating FROM feedback",
    "user stats" => "SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count FROM users",
    "schedules" => "SELECT * FROM schedules WHERE DATE(start_date) = CURDATE()",
    "pending users" => "SELECT * FROM users WHERE status='pending' AND role='tourist' LIMIT 10",
    "recent feedback" => "SELECT * FROM feedback ORDER BY created_at DESC LIMIT 5",
];

foreach ($checks as $name => $sql) {
    try {
        $db->query($sql);
        echo "  {$name}: OK" . PHP_EOL;
    } catch (Throwable $e) {
        echo "  {$name}: FAIL - " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "=== Check Tourist dashboard dependencies ===" . PHP_EOL;
$tourist_checks = [
    "categories" => "SELECT category, COUNT(*) as cnt FROM destinations WHERE status='active' GROUP BY category ORDER BY category",
    "featured destinations" => "SELECT d.id, d.name, d.location, d.description, d.image, d.entrance_fee, d.difficulty, d.category, d.featured FROM destinations d WHERE d.status='active' ORDER BY d.featured DESC LIMIT 6",
    "events" => "SELECT * FROM events WHERE status='active' LIMIT 5",
    "bookings" => "SELECT b.*, s.start_date FROM bookings b LEFT JOIN schedules s ON b.schedule_id = s.id WHERE b.tourist_id = 1 ORDER BY b.created_at DESC LIMIT 5",
];

foreach ($tourist_checks as $name => $sql) {
    try {
        $db->query($sql);
        echo "  {$name}: OK" . PHP_EOL;
    } catch (Throwable $e) {
        echo "  {$name}: FAIL - " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "=== Check landing page ===" . PHP_EOL;
$landing_checks = [
    "featured dests" => "SELECT d.id, d.name, d.location, d.description, d.image, d.entrance_fee, d.difficulty, d.category, (SELECT COUNT(*) FROM destination_reviews r WHERE r.destination_id = d.id) as review_count, (SELECT COALESCE(AVG(r.rating),0) FROM destination_reviews r WHERE r.destination_id = d.id) as avg_rating FROM destinations d WHERE d.status = 'active' ORDER BY d.featured DESC, d.created_at DESC LIMIT 6",
    "total dests" => "SELECT COUNT(*) FROM destinations WHERE status='active'",
    "total reviews" => "SELECT COUNT(*) FROM destination_reviews",
];

foreach ($landing_checks as $name => $sql) {
    try {
        $db->query($sql);
        echo "  {$name}: OK" . PHP_EOL;
    } catch (Throwable $e) {
        echo "  {$name}: FAIL - " . $e->getMessage() . PHP_EOL;
    }
}
