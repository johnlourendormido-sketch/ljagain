<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION = ['user_id' => 1, 'role' => 'admin', 'name' => 'Admin', 'email' => 'admin@tourism.com'];
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

// Capture the admin index output
ob_start();
try {
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/helpers.php';
    start_session();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['name'] = 'Admin';
    $_SESSION['email'] = 'admin@tourism.com';

    require_once __DIR__ . '/includes/layout.php';

    $userModel = new User();
    $bookingModel = new Booking();
    $activityLogModel = new ActivityLog();
    $paymentModel = new Payment();

    $admin = current_user();
    echo "current_user OK: " . ($admin['name'] ?? 'null') . PHP_EOL;

    $stats = $userModel->getStats();
    echo "userStats OK" . PHP_EOL;

    $bookingStats = $bookingModel->getStats();
    echo "bookingStats OK" . PHP_EOL;

    $paymentStats = $paymentModel->getStats();
    echo "paymentStats OK" . PHP_EOL;

    $monthlyRevenue = $paymentModel->getMonthlyRevenue(6);
    echo "monthlyRevenue OK" . PHP_EOL;

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        "SELECT b.*, u.name as tourist_name, e.title as event_title, d.name as destination_name, s.start_date
         FROM bookings b
         LEFT JOIN users u ON b.tourist_id = u.id
         LEFT JOIN schedules s ON b.schedule_id = s.id
         LEFT JOIN events e ON s.event_id = e.id
         LEFT JOIN destinations d ON e.destination_id = d.id
         ORDER BY b.created_at DESC LIMIT 10"
    );
    $stmt->execute();
    $recentBookings = $stmt->fetchAll();
    echo "recentBookings OK (" . count($recentBookings) . " rows)" . PHP_EOL;

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}
ob_end_flush();
