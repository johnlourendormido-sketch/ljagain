<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

require_once __DIR__ . '/../includes/classes/User.php';
require_once __DIR__ . '/../includes/classes/Booking.php';
require_once __DIR__ . '/../includes/classes/ActivityLog.php';
require_once __DIR__ . '/../includes/classes/Payment.php';

$userModel = new User();
$bookingModel = new Booking();
$activityLogModel = new ActivityLog();
$paymentModel = new Payment();

$admin = current_user();
if (!$admin) {
    session_destroy();
    redirect('/login.php');
}
$stats = $userModel->getStats();
$bookingStats = $bookingModel->getStats();
$recentActivity = $activityLogModel->getRecent(10);
$paymentStats = $paymentModel->getStats();
$monthlyRevenue = $paymentModel->getMonthlyRevenue(6);

$db = Database::getInstance()->getConnection();

// Month-over-month comparison
$prevMonthStart = date('Y-m-01', strtotime('-1 month'));
$prevMonthEnd = date('Y-m-t', strtotime('-1 month'));
$curMonthStart = date('Y-m-01');

$pRevStmt = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM payments WHERE payment_status='paid' AND created_at >= :from AND created_at <= :to");
$pRevStmt->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevRevenue = (float)$pRevStmt->fetchColumn();
$curRevenue = (float)($paymentStats['monthly_revenue'] ?? 0);
$revChange = $prevRevenue > 0 ? round((($curRevenue - $prevRevenue) / $prevRevenue) * 100) : null;

$pBkStmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE created_at >= :from AND created_at <= :to");
$pBkStmt->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevBookings = (int)$pBkStmt->fetchColumn();
$curBookings = (int)($bookingStats['total'] ?? 0);
$bkChange = $prevBookings > 0 ? round((($curBookings - $prevBookings) / $prevBookings) * 100) : null;

$pUsrStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at >= :from AND created_at <= :to");
$pUsrStmt->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevUsers = (int)$pUsrStmt->fetchColumn();
$curUsers = (int)($stats['total_users'] ?? 0);
$userChange = $prevUsers > 0 ? round((($curUsers - $prevUsers) / $prevUsers) * 100) : null;

$pPendV = $db->prepare("SELECT COUNT(*) FROM id_verifications WHERE status='pending' AND created_at >= :from AND created_at <= :to");
$pPendV->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$pPendB = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status='pending' AND created_at >= :from AND created_at <= :to");
$pPendB->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevPending = (int)$pPendV->fetchColumn() + (int)$pPendB->fetchColumn();
$curPending = ($stats['pending_verifications'] ?? 0) + ($bookingStats['pending'] ?? 0);
$pendChange = $prevPending > 0 ? round((($curPending - $prevPending) / $prevPending) * 100) : ($curPending > 0 ? null : 0);

$stmt = $db->prepare(
    "SELECT b.*, u.name as tourist_name, e.title as event_title, d.name as destination_name, s.start_date
     FROM bookings b
     LEFT JOIN users u ON b.tourist_id = u.id
     LEFT JOIN schedules s ON b.schedule_id = s.id
     LEFT JOIN events e ON s.event_id = e.id
     LEFT JOIN destinations d ON e.destination_id = d.id
     ORDER BY b.created_at DESC LIMIT 5"
);
$stmt->execute();
$recentBookings = $stmt->fetchAll();

$stmt = $db->prepare(
    "SELECT iv.*, u.name as user_name, u.email as user_email
     FROM id_verifications iv
     LEFT JOIN users u ON iv.user_id = u.id
     WHERE iv.status = 'pending'
     ORDER BY iv.created_at ASC
     LIMIT 5"
);
$stmt->execute();
$pendingVerifications = $stmt->fetchAll();

$bookingByStatus = $db->query(
    "SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$bookingByMonth = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt
     FROM bookings
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month ASC"
)->fetchAll();

$popularDestinations = $db->query(
    "SELECT d.name, COUNT(b.id) as booking_count
     FROM bookings b
     JOIN schedules s ON b.schedule_id = s.id
     JOIN events e ON s.event_id = e.id
     JOIN destinations d ON e.destination_id = d.id
     WHERE b.status IN ('confirmed','completed')
     GROUP BY d.id, d.name
     ORDER BY booking_count DESC
     LIMIT 5"
)->fetchAll();

$paymentByMethod = $db->query(
    "SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as total
     FROM payments WHERE payment_status = 'paid'
     GROUP BY payment_method"
)->fetchAll();

$revenueByMonth = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total_amount) as revenue
     FROM payments
     WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month ASC"
)->fetchAll();

$feedbackSparkline = $db->query(
    "SELECT DATE(created_at) as day, ROUND(AVG(overall_rating),1) as avg_rating, COUNT(*) as cnt
     FROM feedback
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
)->fetchAll();

$todaySchedules = $db->query(
    "SELECT s.*, e.title as event_title, u.name as guide_name, d.name as destination_name
     FROM schedules s
     LEFT JOIN events e ON s.event_id = e.id
     LEFT JOIN users u ON s.guide_id = u.id
     LEFT JOIN destinations d ON e.destination_id = d.id
     WHERE DATE(s.start_date) = CURDATE()
     ORDER BY s.start_date ASC"
)->fetchAll();

$quickCounts = [
    'bookings' => (int)($bookingStats['total'] ?? 0),
    'pending_bookings' => (int)($bookingStats['pending'] ?? 0),
    'destinations' => (int)$db->query("SELECT COUNT(*) FROM destinations")->fetchColumn(),
    'events' => (int)$db->query("SELECT COUNT(*) FROM events WHERE status != 'cancelled'")->fetchColumn(),
    'users' => (int)($stats['total_users'] ?? 0),
    'guides' => (int)($stats['total_guides'] ?? 0),
];

$dailyBookings = $db->query(
    "SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();
$dailyBookingsPrev = $db->query(
    "SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
)->fetchColumn();
$dailyChange = $dailyBookingsPrev > 0 ? round((($dailyBookings - $dailyBookingsPrev) / $dailyBookingsPrev) * 100) : null;

$dailyRevenue = (float)$db->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM payments WHERE payment_status='paid' AND DATE(created_at) = CURDATE()"
)->fetchColumn();
$dailyRevenuePrev = (float)$db->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM payments WHERE payment_status='paid' AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
)->fetchColumn();
$revDailyChange = $dailyRevenuePrev > 0 ? round((($dailyRevenue - $dailyRevenuePrev) / $dailyRevenuePrev) * 100) : null;

// ═══ Binalbagan-scoped demo fallbacks (display-only when DB is empty) ═══
$demoTopDests = [
    ['name' => 'Binadlan Falls', 'barangay' => 'Brgy Bi-ao', 'booking_count' => 48, 'rating' => 4.9, 'trend' => 'up'],
    ['name' => 'Mount Hermit', 'barangay' => 'Brgy Bi-ao', 'booking_count' => 36, 'rating' => 4.8, 'trend' => 'up'],
    ['name' => 'Poblacion Town Center', 'barangay' => 'Heritage Walk, Poblacion', 'booking_count' => 29, 'rating' => 4.7, 'trend' => 'flat'],
    ['name' => 'Coastal Promenade', 'barangay' => 'Panay Gulf, Brgy Marina', 'booking_count' => 22, 'rating' => 4.6, 'trend' => 'up'],
];
$isDemoDests = empty($popularDestinations);
if ($isDemoDests) $popularDestinations = $demoTopDests;

$demoActivity = [
    ['user_name' => 'LGU Admin', 'action' => 'destination_added', 'details' => 'New destination added: Binadlan Falls', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
    ['user_name' => 'LGU Admin', 'action' => 'booking_confirmed', 'details' => 'Booking confirmed for Carlos Garcia', 'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))],
    ['user_name' => 'Tourism Officer', 'action' => 'event_created', 'details' => 'Event created: Balbagan Festival 2027', 'created_at' => date('Y-m-d H:i:s', strtotime('-7 hours'))],
    ['user_name' => 'LGU Admin', 'action' => 'verification_approved', 'details' => 'ID verification approved for Rosa Mendoza', 'created_at' => date('Y-m-d H:i:s', strtotime('-9 hours'))],
    ['user_name' => 'Tourism Officer', 'action' => 'schedule_published', 'details' => 'Schedule published: Mount Hermit Sunrise Trek', 'created_at' => date('Y-m-d H:i:s', strtotime('-12 hours'))],
];
$isDemoActivity = empty($recentActivity);
if ($isDemoActivity) $recentActivity = $demoActivity;

$todayStr = date('Y-m-d');
$demoSchedules = [
    ['event_title' => 'Binadlan Falls Waterfall Tour', 'guide_name' => 'Jomar Villanueva', 'destination_name' => 'Binadlan Falls, Bi-ao', 'start_date' => $todayStr . ' 08:00:00', 'slot_time' => '8:00 AM – 11:00 AM', 'status' => 'scheduled', 'slots' => '12 slots'],
    ['event_title' => 'Mount Hermit Sunrise Trek', 'guide_name' => 'Brian Locsin', 'destination_name' => 'Mount Hermit, Bi-ao', 'start_date' => $todayStr . ' 05:30:00', 'slot_time' => '5:30 AM – 9:00 AM', 'status' => 'in_progress', 'slots' => '10 slots'],
    ['event_title' => 'Coastal & Mangrove Walk', 'guide_name' => 'Ana Ramos', 'destination_name' => 'Brgy Marina, Panay Gulf', 'start_date' => $todayStr . ' 15:00:00', 'slot_time' => '3:00 PM – 5:00 PM', 'status' => 'scheduled', 'slots' => '15 slots'],
];
$isDemoSchedules = empty($todaySchedules);
if ($isDemoSchedules) $todaySchedules = $demoSchedules;

$demoBookings = [
    ['id' => 101, 'tourist_name' => 'Rosa Mendoza', 'event_title' => 'Binadlan Falls Waterfall Tour', 'destination_name' => 'Binadlan Falls, Bi-ao', 'start_date' => $todayStr, 'status' => 'completed', 'is_demo' => true],
    ['id' => 102, 'tourist_name' => 'Carlos Garcia', 'event_title' => 'Mount Hermit Sunrise Trek', 'destination_name' => 'Mount Hermit, Bi-ao', 'start_date' => date('Y-m-d', strtotime('-1 day')), 'status' => 'confirmed', 'is_demo' => true],
    ['id' => 103, 'tourist_name' => 'Juan Dela Cruz', 'event_title' => 'Balbagan Festival 2027', 'destination_name' => 'Poblacion Town Center', 'start_date' => date('Y-m-d', strtotime('-2 days')), 'status' => 'pending', 'is_demo' => true],
    ['id' => 104, 'tourist_name' => 'Maria Santos', 'event_title' => 'Coastal Cleanup Drive', 'destination_name' => 'Brgy Marina, Panay Gulf', 'start_date' => date('Y-m-d', strtotime('-3 days')), 'status' => 'confirmed', 'is_demo' => true],
    ['id' => 105, 'tourist_name' => 'Jose Ramos', 'event_title' => 'Bi-ao Heritage Walk', 'destination_name' => 'Brgy Santol', 'start_date' => date('Y-m-d', strtotime('-4 days')), 'status' => 'cancelled', 'is_demo' => true],
];
$isDemoBookings = empty($recentBookings);
if ($isDemoBookings) $recentBookings = $demoBookings;

$demoVerifs = [
    ['id' => 'demo-1', 'user_name' => 'Ramon Dela Cruz', 'user_email' => 'ramon.santol@example.com', 'role' => 'Tour Guide Applicant · Brgy Santol', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'is_demo' => true],
    ['id' => 'demo-2', 'user_name' => 'Liza Enriquez', 'user_email' => 'liza.payao@example.com', 'role' => 'Tourist · Brgy Payao', 'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours')), 'is_demo' => true],
];
$isDemoVerifs = empty($pendingVerifications);
if ($isDemoVerifs) $pendingVerifications = $demoVerifs;

// Feedback breakdown (real query, fallback to demo)
$avgFeedback = 0;
$feedbackCount = 0;
if (!empty($feedbackSparkline)) {
    $fbTotalRating = 0;
    foreach ($feedbackSparkline as $fs) { $fbTotalRating += $fs['avg_rating'] * $fs['cnt']; $feedbackCount += $fs['cnt']; }
    $avgFeedback = $feedbackCount > 0 ? round($fbTotalRating / $feedbackCount, 1) : 0;
}
try {
    $fbBreak = $db->query("SELECT overall_rating as r, COUNT(*) as c FROM feedback WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY overall_rating")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) { $fbBreak = []; }
$fbSample = null;
try {
    $fbSample = $db->query("SELECT f.overall_rating, f.comments, u.name FROM feedback f LEFT JOIN users u ON f.tourist_id=u.id ORDER BY f.created_at DESC LIMIT 1")->fetch();
} catch (Exception $e) { $fbSample = null; }
if (empty($fbBreak)) {
    $fbBreak = [5 => 3, 4 => 1, 3 => 1];
    $isDemoFeedback = true;
    if (!$fbSample) $fbSample = ['overall_rating' => 5, 'comments' => 'Binadlan Falls was amazing! Clear water and very friendly guides from Bi-ao.', 'name' => 'Rosa M.'];
    if ($feedbackCount === 0) { $feedbackCount = 5; $avgFeedback = 4.4; }
} else {
    $isDemoFeedback = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_verification' && isset($_POST['verification_id'])) {
        $vid = (int) $_POST['verification_id'];
        $stmt = $db->prepare("UPDATE id_verifications SET status = 'approved', verified_at = NOW(), verified_by = :uid WHERE id = :id");
        $stmt->execute([':id' => $vid, ':uid' => $_SESSION['user_id']]);
        flash_message('success', 'Verification approved.');
        redirect('/admin/index.php');
    }

    if ($action === 'reject_verification' && isset($_POST['verification_id'])) {
        $vid = (int) $_POST['verification_id'];
        $stmt = $db->prepare("UPDATE id_verifications SET status = 'rejected', verified_at = NOW(), verified_by = :uid WHERE id = :id");
        $stmt->execute([':id' => $vid, ':uid' => $_SESSION['user_id']]);
        flash_message('success', 'Verification rejected.');
        redirect('/admin/index.php');
    }
}

render_page('admin', 'index.php', 'Admin Dashboard', function () use (
    $admin, $stats, $bookingStats, $paymentStats, $recentActivity,
    $recentBookings, $pendingVerifications, $monthlyRevenue,
    $bookingByStatus, $bookingByMonth, $popularDestinations,
    $paymentByMethod, $revenueByMonth,
    $revChange, $bkChange, $userChange, $pendChange,
    $feedbackSparkline, $todaySchedules, $quickCounts,
    $dailyChange, $revDailyChange, $dailyBookings, $dailyRevenue,
    $isDemoDests, $isDemoActivity, $isDemoSchedules, $isDemoBookings, $isDemoVerifs,
    $isDemoFeedback, $fbBreak, $fbSample, $avgFeedback, $feedbackCount
) {
?>

<style>
/* ═══ Dashboard Overrides ═══ */
.dash-wrap { max-width: 100%; margin: 0 auto; }

/* ── Greeting Banner ── */
.dash-banner {
    background: linear-gradient(135deg, #0c6e5e 0%, #10b981 50%, #0d9488 100%);
    border-radius: 18px; padding: 28px 32px; margin-bottom: 28px;
    position: relative; overflow: hidden; color: #fff;
    box-shadow: 0 8px 32px rgba(12,110,94,0.25);
}
.dash-banner::before {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='400' height='200' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 180 Q100 120 200 160 T400 140' fill='none' stroke='rgba(255,255,255,0.07)' stroke-width='2'/%3E%3Cpath d='M0 140 Q100 80 200 120 T400 100' fill='none' stroke='rgba(255,255,255,0.05)' stroke-width='1.5'/%3E%3Cpath d='M0 100 Q80 50 180 90 T400 70' fill='none' stroke='rgba(255,255,255,0.04)' stroke-width='1'/%3E%3Ccircle cx='320' cy='40' r='25' fill='none' stroke='rgba(255,255,255,0.06)' stroke-width='1.5'/%3E%3Ccircle cx='340' cy='30' r='12' fill='none' stroke='rgba(255,255,255,0.04)' stroke-width='1'/%3E%3Cpath d='M50 180 L50 60 M50 60 L58 68 M50 60 L42 68' fill='none' stroke='rgba(255,255,255,0.08)' stroke-width='1.5'/%3E%3C/svg%3E") right bottom / 400px 200px no-repeat;
    pointer-events: none;
}
.dash-banner::after {
    content: ''; position: absolute; top: -60px; right: -30px;
    width: 180px; height: 180px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.dash-banner .banner-greeting { font-size: 1.3rem; font-weight: 800; letter-spacing: -0.3px; }
.dash-banner .banner-sub { font-size: 0.88rem; opacity: 0.85; margin-top: 4px; }
.dash-banner .banner-date { font-size: 0.82rem; opacity: 0.7; font-weight: 500; }
.dash-banner .banner-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.dash-banner .banner-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: 10px; font-size: 0.82rem; font-weight: 600;
    text-decoration: none; transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    border: 1.5px solid rgba(255,255,255,0.25); color: #fff;
    background: rgba(255,255,255,0.12); backdrop-filter: blur(4px);
}
.dash-banner .banner-btn:hover {
    background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.4);
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    color: #fff; text-decoration: none;
}
.dash-banner .banner-btn.solid {
    background: #fff; color: #0c6e5e; border-color: transparent;
}
.dash-banner .banner-btn.solid:hover {
    box-shadow: 0 6px 20px rgba(255,255,255,0.3); color: #084a3f;
}
.greet-icon { display: inline-block; font-style: normal; font-size: 1.1em; }
.greet-morning { animation: greetSun 3s ease-in-out infinite; }
.greet-afternoon { animation: greetCloud 4s ease-in-out infinite; }
.greet-evening { animation: greetMoon 5s ease-in-out infinite; }
@keyframes greetSun { 0%,100%{transform:rotate(0deg) scale(1)} 25%{transform:rotate(10deg) scale(1.1)} 75%{transform:rotate(-5deg) scale(1.05)} }
@keyframes greetCloud { 0%,100%{transform:translateX(0)} 50%{transform:translateX(4px)} }
@keyframes greetMoon { 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-3px) scale(1.08)} }

/* ── Section Headers ── */
.dash-section { margin-bottom: 28px; }
.dash-section-title {
    font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1.2px; color: var(--text-muted, #64748b);
    margin-bottom: 14px; padding-left: 4px;
    display: flex; align-items: center; gap: 8px;
}
.dash-section-title::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, var(--border-color, #e2e8f0), transparent);
}

/* ── Stat Cards ── */
.stat-card {
    border: none; border-radius: 16px;
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    position: relative; overflow: hidden;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #f1f5f9);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    text-decoration: none; display: block;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 36px rgba(0,0,0,0.1);
    border-color: transparent;
}
.stat-card .card-body { padding: 22px; }
.stat-card .stat-icon {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; transition: transform 0.3s;
}
.stat-card:hover .stat-icon { transform: scale(1.1) rotate(-3deg); }
.stat-card .stat-value {
    font-size: 1.8rem; font-weight: 900; line-height: 1.15;
    letter-spacing: -0.5px;
}
.stat-card .stat-label {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--text-muted, #64748b); margin-top: 4px;
}
.stat-card .stat-change {
    font-size: 0.7rem; font-weight: 600; display: inline-flex;
    align-items: center; gap: 3px; padding: 2px 8px;
    border-radius: 50px; margin-top: 6px;
}
.stat-card .stat-change.up { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-card .stat-change.down { background: rgba(239,68,68,0.1); color: #ef4444; }
.stat-card .stat-change.neutral { background: rgba(100,116,139,0.1); color: #64748b; }
.stat-card .stat-sub {
    font-size: 0.72rem; color: var(--text-muted, #94a3b8); margin-top: 4px;
}
.stat-card .accent-left {
    position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
    border-radius: 16px 0 0 16px;
}
.stat-card canvas.sparkline { height: 30px !important; margin-top: 8px; }

/* ── Quick Links ── */
.quick-link-card {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 18px; border-radius: 14px;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #f1f5f9);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    text-decoration: none; transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    position: relative; overflow: hidden;
}
.quick-link-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    border-color: var(--ql-color, #0c6e5e);
    text-decoration: none;
}
.quick-link-card .ql-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.quick-link-card .ql-info { flex: 1; min-width: 0; }
.quick-link-card .ql-label { font-size: 0.82rem; font-weight: 700; color: var(--text-primary, #1e293b); }
.quick-link-card .ql-count {
    font-size: 0.72rem; font-weight: 600; padding: 2px 8px;
    border-radius: 50px; flex-shrink: 0;
}
.quick-link-card .ql-arrow {
    font-size: 0.75rem; color: var(--text-muted, #94a3b8);
    transition: transform 0.25s, color 0.25s;
}
.quick-link-card:hover .ql-arrow { transform: translateX(3px); color: var(--ql-color, #0c6e5e); }

/* ── Chart Cards ── */
.chart-card {
    border: none; border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    border: 1px solid var(--border-color, #f1f5f9);
    background: var(--card-bg, #fff);
    transition: box-shadow 0.3s;
}
.chart-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.07); }
.chart-card .card-header {
    background: transparent; border-bottom: 1px solid var(--border-color, #f1f5f9);
    padding: 16px 20px; color: var(--text-primary, #1e293b);
}
.chart-card canvas { max-height: 280px; }

/* ── Top Lists ── */
.top-list-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 0; border-bottom: 1px solid var(--border-color, #f1f5f9);
    transition: background 0.15s;
}
.top-list-item:last-child { border-bottom: none; }
.top-list-item .rank {
    width: 30px; height: 30px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}

/* ── Schedule Search ── */
.sched-search-wrap {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg-secondary, #f8fafc); border-radius: 10px;
    padding: 6px 12px; border: 1px solid var(--border-color, #e2e8f0);
    margin-bottom: 12px; transition: border-color 0.2s;
}
.sched-search-wrap:focus-within { border-color: #0c6e5e; }
.sched-search-wrap input {
    border: none; background: transparent; outline: none;
    font-size: 0.82rem; color: var(--text-primary, #1e293b);
    flex: 1; min-width: 0;
}
.sched-search-wrap input::placeholder { color: var(--text-muted, #94a3b8); }
.sched-search-wrap i { color: var(--text-muted, #94a3b8); font-size: 0.82rem; }

/* ── Inline Verif Actions ── */
.verif-item {
    transition: background 0.15s; position: relative;
}
.verif-item:hover { background: var(--hover-bg, #f8fafc); }
.verif-item .verif-actions {
    opacity: 0; transition: opacity 0.2s;
}
.verif-item:hover .verif-actions { opacity: 1; }
.verif-item .verif-actions-mobile { display: none; }
@media (max-width: 767.98px) {
    .verif-item .verif-actions { display: none; }
    .verif-item .verif-actions-mobile { display: flex; }
}

/* ── Empty States ── */
.empty-state {
    text-align: center; padding: 40px 20px;
    color: var(--text-muted, #94a3b8);
}
.empty-state .empty-svg { width: 80px; height: 80px; margin: 0 auto 16px; opacity: 0.6; }
.empty-state h6 { font-weight: 700; font-size: .9rem; color: var(--text-primary, #1e293b); margin-bottom: 4px; }
.empty-state p { font-size: .82rem; margin: 0 0 14px; max-width: 280px; margin-left: auto; margin-right: auto; }
.empty-state .empty-cta {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: 10px; font-size: .8rem; font-weight: 600;
    text-decoration: none; transition: all .25s cubic-bezier(0.4,0,0.2,1);
    background: linear-gradient(135deg, #0c6e5e, #10b981); color: #fff;
    border: none; box-shadow: 0 4px 12px rgba(12,110,94,0.25);
}
.empty-state .empty-cta:hover {
    transform: translateY(-2px); box-shadow: 0 8px 20px rgba(12,110,94,0.35);
    color: #fff; text-decoration: none;
}
.empty-state .empty-cta:hover i { animation: ctaArrowBounce 0.4s ease; }
@keyframes ctaArrowBounce { 0%,100%{transform:translateX(0)} 50%{transform:translateX(3px)} }
.empty-state .empty-cta i { font-size: .65rem; transition: transform 0.25s; }

/* ── Dark Mode ── */
[data-theme="dark"] .dash-banner {
    background: linear-gradient(135deg, #0f4c3f 0%, #0d7a6e 50%, #134e4a 100%);
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
[data-theme="dark"] .stat-card { box-shadow: 0 2px 12px rgba(0,0,0,0.2); }
[data-theme="dark"] .stat-card:hover { box-shadow: 0 12px 36px rgba(0,0,0,0.35); }
[data-theme="dark"] .chart-card { box-shadow: 0 2px 12px rgba(0,0,0,0.2); }
[data-theme="dark"] .chart-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.3); }
[data-theme="dark"] .quick-link-card { box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
[data-theme="dark"] .quick-link-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
[data-theme="dark"] .verif-item:hover { background: var(--hover-bg, #334155); }

/* ── Responsive ── */
@media (max-width: 991.98px) {
    .dash-banner { padding: 20px 20px; }
    .dash-banner .banner-greeting { font-size: 1.1rem; }
    .stat-card .stat-value { font-size: 1.5rem; }
}
@media (min-width: 576px) and (max-width: 991.98px) {
    .kpi-grid .col-sm-6 { flex: 0 0 50%; max-width: 50%; }
}
@media (max-width: 575.98px) {
    .kpi-grid .col-sm-6 { flex: 0 0 100%; max-width: 100%; }
    .stat-card .stat-value { font-size: 1.4rem; }
    .dash-banner .banner-actions { flex-direction: column; }
    .dash-banner .banner-btn { width: 100%; justify-content: center; }
}

/* ═══ Section enhancements (Binalbagan scope) ═══ */
.card-icon-circle{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0}
.card-icon-teal{background:rgba(12,110,94,.1);color:#0c6e5e}
.card-icon-amber{background:rgba(245,158,11,.12);color:#b45309}
.card-icon-purple{background:rgba(139,92,246,.12);color:#7c3aed}
.card-icon-blue{background:rgba(59,130,246,.12);color:#2563eb}
.card-icon-red{background:rgba(239,68,68,.1);color:#dc2626}
.card-icon-green{background:rgba(16,185,129,.12);color:#059669}
.demo-badge{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;background:rgba(100,116,139,.12);color:#64748b;padding:2px 8px;border-radius:50px}

/* Bookings table: zebra + dividers + avatars + clickable */
.bookings-table tbody tr{cursor:pointer;transition:background .15s}
.bookings-table tbody tr:nth-child(even){background:var(--hover-bg,rgba(248,250,252,.7))}
.bookings-table tbody tr:hover{background:rgba(12,110,94,.05)}
.bookings-table tbody tr td{border-bottom:1px solid var(--border-color,#f1f5f9)}
[data-theme="dark"] .bookings-table tbody tr:nth-child(even){background:rgba(255,255,255,.02)}
[data-theme="dark"] .bookings-table tbody tr:hover{background:rgba(20,184,166,.08)}
.avatar-initials{width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;flex-shrink:0;color:#fff}
.booking-filter{font-size:.78rem;border:1px solid var(--border-color,#e2e8f0);border-radius:8px;padding:5px 10px;background:var(--card-bg,#fff);color:var(--text-primary,#334155);outline:none}
.booking-filter:focus{border-color:#0c6e5e}

/* Booking drawer */
.booking-drawer{position:fixed;top:0;right:-380px;width:360px;max-width:92vw;height:100vh;background:var(--card-bg,#fff);border-left:1px solid var(--border-color,#e2e8f0);box-shadow:-12px 0 40px rgba(0,0,0,.12);z-index:1050;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column}
.booking-drawer.open{right:0}
.booking-drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1040;opacity:0;pointer-events:none;transition:opacity .3s}
.booking-drawer-overlay.show{opacity:1;pointer-events:auto}
.drawer-header{background:linear-gradient(135deg,#0c6e5e,#10b981);color:#fff;padding:18px 20px}
.drawer-body{padding:18px 20px;overflow-y:auto;flex:1}
.drawer-row{display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid var(--border-color,#f1f5f9);font-size:.82rem}
.drawer-row:last-child{border-bottom:none}
.drawer-label{color:var(--text-muted,#64748b);font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px}
.drawer-value{font-weight:600;text-align:right}

/* Activity dividers */
.activity-item{border-bottom:1px solid var(--border-color,#f1f5f9)}
.activity-item:last-child{border-bottom:none}

/* Feedback breakdown */
.fb-bar-row{display:flex;align-items:center;gap:8px;font-size:.75rem;margin-bottom:6px}
.fb-bar-track{flex:1;height:6px;border-radius:6px;background:var(--border-color,#f1f5f9);overflow:hidden}
.fb-bar-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,#f59e0b,#fbbf24)}
.fb-sample{background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);border-radius:10px;padding:10px 12px;font-size:.78rem;margin-top:10px;text-align:left}

/* Schedule today badge */
.today-badge{font-size:.68rem;font-weight:700;background:rgba(12,110,94,.1);color:#0c6e5e;padding:3px 10px;border-radius:50px;display:inline-flex;align-items:center;gap:4px}
.sched-time{font-size:.72rem;font-weight:700;color:#0c6e5e;background:rgba(12,110,94,.08);padding:2px 8px;border-radius:6px;white-space:nowrap}

/* Trend arrow */
.trend-up{color:#10b981;font-size:.7rem}
.trend-flat{color:#94a3b8;font-size:.7rem}
.dest-rating{font-size:.72rem;color:#b45309;font-weight:700}
.dest-barangay{font-size:.7rem;color:var(--text-muted,#94a3b8)}
</style>

<?php
$nowManila = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $nowManila->format('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$greetEmoji = $hour < 12 ? '☀️' : ($hour < 17 ? '🌤️' : '🌙');
$dashDate = $nowManila->format('l, F j, Y');
$displayName = trim($admin['name'] ?? '');
if ($displayName === '' || strtolower($displayName) === 'system administrator') {
    $displayName = 'Admin';
}
?>

<div class="dash-wrap">

<!-- ═══ Banner ═══ -->
<div class="dash-banner">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="banner-greeting">
                <span class="greet-icon greet-<?= $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening') ?>"><?= $greetEmoji ?></span>
                <?= $greeting ?>, <?= sanitize($displayName) ?>!
            </div>
            <div class="banner-date mt-1"><?= $dashDate ?></div>
            <div class="banner-sub">Here's what's happening with BINALGO today.</div>
        </div>
        <div class="banner-actions">
            <a href="<?= BASE_URL ?>/admin/users.php" class="banner-btn solid"><i class="fas fa-user-plus"></i>Add User</a>
            <a href="<?= BASE_URL ?>/admin/destinations.php" class="banner-btn"><i class="fas fa-plus-circle"></i>New Destination</a>
            <a href="<?= BASE_URL ?>/admin/reports.php" class="banner-btn"><i class="fas fa-chart-bar"></i>Reports</a>
        </div>
    </div>
</div>

<!-- ═══ Overview KPIs ═══ -->
<div class="dash-section-title">Overview</div>
<div class="row g-3 mb-4 kpi-grid">
    <?php
    $kpiCards = [
        ['label' => 'Total Revenue', 'value' => '₱' . number_format($paymentStats['total_revenue'] ?? 0), 'icon' => 'fa-dollar-sign', 'color' => '#0c6e5e', 'bg' => 'rgba(12,110,94,0.1)', 'gradient' => 'linear-gradient(135deg,#0c6e5e,#14b8a6)', 'sub' => '₱' . number_format($paymentStats['monthly_revenue'] ?? 0) . ' this month', 'change' => $revChange, 'dailyChange' => $revDailyChange, 'link' => BASE_URL . '/admin/payments.php'],
        ['label' => 'Total Bookings', 'value' => $bookingStats['total'] ?? 0, 'icon' => 'fa-ticket', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'gradient' => 'linear-gradient(135deg,#3b82f6,#60a5fa)', 'sub' => ($bookingStats['confirmed'] ?? 0) . ' confirmed · ' . ($bookingStats['pending'] ?? 0) . ' pending', 'change' => $bkChange, 'dailyChange' => $dailyChange, 'link' => BASE_URL . '/admin/bookings.php'],
        ['label' => 'Active Users', 'value' => $stats['total_users'], 'icon' => 'fa-users', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'gradient' => 'linear-gradient(135deg,#f59e0b,#fbbf24)', 'sub' => $stats['total_guides'] . ' guides · ' . $stats['pending_verifications'] . ' pending', 'change' => $userChange, 'link' => BASE_URL . '/admin/users.php'],
        ['label' => 'Avg. Feedback', 'value' => $avgFeedback > 0 ? $avgFeedback . ' / 5' : '—', 'icon' => 'fa-star', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'gradient' => 'linear-gradient(135deg,#8b5cf6,#a78bfa)', 'sub' => $feedbackCount . ' reviews this week', 'change' => null, 'link' => BASE_URL . '/admin/feedback.php'],
    ];
    foreach ($kpiCards as $kpi): ?>
    <div class="col-sm-6 col-lg-3">
        <a href="<?= $kpi['link'] ?>" class="stat-card" style="--card-accent:<?= $kpi['color'] ?>;">
            <div class="accent-left" style="background:<?= $kpi['gradient'] ?>;"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="color:<?= $kpi['color'] ?>;"><?= $kpi['value'] ?></div>
                        <div class="stat-label"><?= $kpi['label'] ?></div>
                        <?php if (isset($kpi['dailyChange']) && $kpi['dailyChange'] !== null): ?>
                            <span class="stat-change <?= $kpi['dailyChange'] >= 0 ? 'up' : 'down' ?>">
                                <i class="fas fa-<?= $kpi['dailyChange'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= $kpi['dailyChange'] >= 0 ? '+' : '' ?><?= $kpi['dailyChange'] ?>% vs yesterday
                            </span>
                        <?php elseif ($kpi['change'] !== null): ?>
                            <span class="stat-change <?= $kpi['change'] > 0 ? 'up' : ($kpi['change'] < 0 ? 'down' : 'neutral') ?>">
                                <i class="fas fa-<?= $kpi['change'] > 0 ? 'arrow-up' : ($kpi['change'] < 0 ? 'arrow-down' : 'minus') ?>"></i>
                                <?= $kpi['change'] > 0 ? '+' : '' ?><?= $kpi['change'] ?>% vs last month
                            </span>
                        <?php else: ?>
                            <span class="stat-change neutral"><i class="fas fa-minus"></i>No prior data</span>
                        <?php endif; ?>
                        <?php if (!empty($kpi['sub'])): ?>
                            <div class="stat-sub"><?= $kpi['sub'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon" style="background:<?= $kpi['bg'] ?>;color:<?= $kpi['color'] ?>;">
                        <i class="fas <?= $kpi['icon'] ?>"></i>
                    </div>
                </div>
                <canvas class="sparkline" data-color="<?= $kpi['color'] ?>" data-val="<?= $kpi['change'] ?? 0 ?>"></canvas>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ Quick Links ═══ -->
<div class="dash-section-title">Quick Access</div>
<div class="row g-3 mb-4">
    <?php
    $qlLinks = [
        ['label' => 'Bookings', 'count' => $quickCounts['bookings'], 'icon' => 'fa-ticket', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'url' => BASE_URL . '/admin/bookings.php'],
        ['label' => 'Destinations', 'count' => $quickCounts['destinations'], 'icon' => 'fa-map-marker-alt', 'color' => '#0c6e5e', 'bg' => 'rgba(12,110,94,0.1)', 'url' => BASE_URL . '/admin/destinations.php'],
        ['label' => 'Events', 'count' => $quickCounts['events'], 'icon' => 'fa-calendar', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'url' => BASE_URL . '/admin/events.php'],
        ['label' => 'Users', 'count' => $quickCounts['users'], 'icon' => 'fa-users', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'url' => BASE_URL . '/admin/users.php'],
        ['label' => 'Staff & Guides', 'count' => $quickCounts['guides'], 'icon' => 'fa-user-shield', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)', 'url' => BASE_URL . '/admin/staff.php'],
    ];
    foreach ($qlLinks as $ql): ?>
    <div class="col-sm-6 col-lg">
        <a href="<?= $ql['url'] ?>" class="quick-link-card" style="--ql-color:<?= $ql['color'] ?>;">
            <div class="ql-icon" style="background:<?= $ql['bg'] ?>;color:<?= $ql['color'] ?>;"><i class="fas <?= $ql['icon'] ?>"></i></div>
            <div class="ql-info">
                <div class="ql-label"><?= $ql['label'] ?></div>
            </div>
            <span class="ql-count" style="background:<?= $ql['bg'] ?>;color:<?= $ql['color'] ?>;"><?= $ql['count'] ?></span>
            <i class="fas fa-chevron-right ql-arrow"></i>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ Charts Row 1 ═══ -->
<div class="dash-section-title">Analytics</div>
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-line me-2" style="color:#0c6e5e;"></i>Revenue Trend</h6>
                <span class="badge" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;">Last 6 Months</span>
            </div>
            <div class="card-body">
                <?php if (empty($revenueByMonth)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="24" width="64" height="48" rx="6" stroke="#94a3b8" stroke-width="2" stroke-dasharray="4 3"/><path d="M20 52 L28 40 L36 46 L48 32 L60 38" stroke="#0c6e5e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="20" cy="52" r="3" fill="#0c6e5e"/><circle cx="36" cy="46" r="3" fill="#0c6e5e"/><circle cx="48" cy="32" r="3" fill="#0c6e5e"/><circle cx="60" cy="38" r="3" fill="#0c6e5e"/><path d="M30 16 L40 8 L50 16" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="40" y1="8" x2="40" y2="24" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>
                        <h6>No Revenue Data Yet</h6>
                        <p>Revenue will appear here once bookings are paid.</p>
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="empty-cta">View Bookings <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <canvas id="revenueChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-credit-card me-2" style="color:#3b82f6;"></i>Payment Methods</h6>
            </div>
            <div class="card-body">
                <?php if (empty($paymentByMethod)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="18" width="60" height="44" rx="8" stroke="#94a3b8" stroke-width="2"/><rect x="10" y="28" width="60" height="10" fill="#3b82f6" opacity="0.15"/><line x1="18" y1="48" x2="38" y2="48" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/><line x1="18" y1="54" x2="30" y2="54" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" opacity="0.5"/><circle cx="58" cy="50" r="8" stroke="#3b82f6" stroke-width="2" stroke-dasharray="3 2"/><path d="M56 48 L58 50 L62 46" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <h6>No Payment Data Yet</h6>
                        <p>Payment methods will show once transactions are processed.</p>
                        <a href="<?= BASE_URL ?>/admin/payments.php" class="empty-cta">View Payments <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <canvas id="paymentMethodChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Charts Row 2 ═══ -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2" style="color:#0c6e5e;"></i>Bookings by Month</h6>
                <span class="badge" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;">Last 6 Months</span>
            </div>
            <div class="card-body">
                <?php if (empty($bookingByMonth)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="14" y="50" width="10" height="18" rx="3" fill="#0c6e5e" opacity="0.3"/><rect x="28" y="38" width="10" height="30" rx="3" fill="#0c6e5e" opacity="0.5"/><rect x="42" y="28" width="10" height="40" rx="3" fill="#0c6e5e" opacity="0.7"/><rect x="56" y="20" width="10" height="48" rx="3" fill="#0c6e5e" opacity="0.9"/><path d="M10 68 L70 68" stroke="#94a3b8" stroke-width="1.5"/><path d="M19 50 L33 38 L47 28 L61 20" stroke="#0c6e5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4 3"/></svg>
                        <h6>No Booking Data Yet</h6>
                        <p>Monthly booking trends will appear here once tours are booked.</p>
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="empty-cta">View Bookings <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <canvas id="bookingTrendChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2" style="color:#8b5cf6;"></i>Booking Status Distribution</h6>
            </div>
            <div class="card-body">
                <?php if (empty($bookingByStatus)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="40" r="28" stroke="#e2e8f0" stroke-width="6"/><path d="M40 12 A28 28 0 0 1 68 40" stroke="#10b981" stroke-width="6" stroke-linecap="round"/><path d="M68 40 A28 28 0 0 1 40 68" stroke="#3b82f6" stroke-width="6" stroke-linecap="round"/><path d="M40 68 A28 28 0 0 1 12 40" stroke="#f59e0b" stroke-width="6" stroke-linecap="round" opacity="0.4"/><circle cx="40" cy="40" r="14" fill="var(--card-bg, #fff)"/><path d="M35 40 L42 47 L55 33" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <h6>No Status Data Yet</h6>
                        <p>Booking status breakdown will appear here once bookings exist.</p>
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="empty-cta">View Bookings <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <canvas id="bookingStatusChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Top Lists + Activity ═══ -->
<div class="dash-section-title">Rankings & Activity</div>
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2"><span class="card-icon-circle card-icon-teal"><i class="fas fa-map-marker-alt"></i></span>Top Destinations</h6>
                <?php if (!empty($isDemoDests)): ?><span class="demo-badge">Sample</span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($popularDestinations)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M40 10 C40 10 20 30 20 48 C20 59 29 68 40 68 C51 68 60 59 60 48 C60 30 40 10 40 10Z" stroke="#0c6e5e" stroke-width="2" fill="#0c6e5e" opacity="0.08"/><circle cx="40" cy="46" r="10" stroke="#0c6e5e" stroke-width="2"/><circle cx="40" cy="46" r="4" fill="#0c6e5e"/><path d="M40 56 L40 64" stroke="#0c6e5e" stroke-width="2" stroke-linecap="round"/></svg>
                        <h6>No Destinations Yet</h6>
                        <p>Popular destinations will be ranked here.</p>
                        <a href="<?= BASE_URL ?>/admin/destinations.php" class="empty-cta">Add Destination <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <?php foreach ($popularDestinations as $i => $d):
                        $colors = [['#dbeafe','#3b82f6'],['#d1fae5','#10b981'],['#fef3c7','#f59e0b'],['#e0e7ff','#6366f1'],['#fce7f3','#ec4899']];
                        $max = $popularDestinations[0]['booking_count'] ?? 1;
                        $pct = $max > 0 ? round(($d['booking_count'] / $max) * 100) : 0;
                        $rating = $d['rating'] ?? null;
                        $trend = $d['trend'] ?? ($i === 0 ? 'up' : ($i === 2 ? 'flat' : 'up'));
                        $barangay = $d['barangay'] ?? '';
                    ?>
                        <div class="top-list-item">
                            <div class="rank" style="background:<?= $colors[$i % 5][0] ?>;color:<?= $colors[$i % 5][1] ?>;"><?= $i + 1 ?></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <span class="fw-semibold small"><?= sanitize($d['name']) ?></span>
                                    <span class="d-flex align-items-center gap-1">
                                        <?php if ($rating): ?><span class="dest-rating"><i class="fas fa-star"></i> <?= number_format((float)$rating, 1) ?></span><?php endif; ?>
                                        <?php if ($trend === 'up'): ?><span class="trend-up" title="Trending up"><i class="fas fa-arrow-trend-up"></i></span><?php else: ?><span class="trend-flat" title="Stable"><i class="fas fa-minus"></i></span><?php endif; ?>
                                        <span class="fw-bold small" style="color:<?= $colors[$i % 5][1] ?>;"><?= $d['booking_count'] ?></span>
                                    </span>
                                </div>
                                <?php if ($barangay): ?><div class="dest-barangay"><?= sanitize($barangay) ?> · <?= $d['booking_count'] ?> visits</div><?php endif; ?>
                                <div class="mt-1" style="height:4px;border-radius:4px;background:var(--border-color,#f1f5f9);overflow:hidden;">
                                    <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;background:<?= $colors[$i % 5][1] ?>;transition:width 0.6s ease;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2"><span class="card-icon-circle card-icon-amber"><i class="fas fa-bolt"></i></span>Recent Activity <?php if (!empty($isDemoActivity)): ?><span class="demo-badge">Sample</span><?php endif; ?></h6>
                <a href="<?= BASE_URL ?>/admin/activity_logs.php" class="btn btn-sm" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentActivity)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M44 12 L28 40 H38 L34 68 L56 36 H44 Z" stroke="#0c6e5e" stroke-width="2" fill="#0c6e5e" opacity="0.1" stroke-linejoin="round"/></svg>
                        <h6>No Activity Yet</h6>
                        <p>Recent actions will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recentActivity, 0, 6) as $log):
                        $actionIcons = [
                            'login' => ['fa-sign-in-alt','card-icon-green'],
                            'destination_added' => ['fa-map-marker-alt','card-icon-teal'],
                            'booking_created' => ['fa-ticket','card-icon-blue'],
                            'booking_confirmed' => ['fa-check-circle','card-icon-green'],
                            'register' => ['fa-user-plus','card-icon-purple'],
                            'event_created' => ['fa-calendar-plus','card-icon-amber'],
                            'verification_approved' => ['fa-shield-alt','card-icon-teal'],
                            'schedule_published' => ['fa-clock','card-icon-blue'],
                        ];
                        $iconData = $actionIcons[$log['action']] ?? ['fa-circle', str_contains($log['action'] ?? '', 'delete') ? 'card-icon-red' : 'card-icon-amber'];
                    ?>
                        <div class="d-flex align-items-start gap-2 px-3 py-2 activity-item">
                            <div class="card-icon-circle <?= $iconData[1] ?> mt-1" style="width:32px;height:32px;font-size:.65rem;">
                                <i class="fas <?= $iconData[0] ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small fw-semibold"><?= sanitize($log['user_name'] ?? 'System') ?></div>
                                <small class="text-muted"><?= sanitize(truncate($log['details'] ?? $log['action'], 55)) ?></small>
                            </div>
                            <small class="text-muted text-nowrap" style="font-size:0.7rem;"><?= time_ago($log['created_at']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Today's Schedules ═══ -->
<div class="dash-section-title">Today's Schedules</div>
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2"><span class="card-icon-circle card-icon-teal"><i class="fas fa-clock"></i></span>Scheduled Tours
                    <?php if (!empty($todaySchedules)): ?><span class="today-badge"><i class="fas fa-calendar-day"></i>+<?= count($todaySchedules) ?> today</span><?php endif; ?>
                    <?php if (!empty($isDemoSchedules)): ?><span class="demo-badge">Sample</span><?php endif; ?>
                </h6>
                <a href="<?= BASE_URL ?>/admin/schedules.php" class="btn btn-sm" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;">Manage</a>
            </div>
            <div class="card-body">
                <div class="sched-search-wrap" id="schedSearchWrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="schedSearch" placeholder="Search schedules..." aria-label="Search schedules">
                </div>
                <?php if (empty($todaySchedules)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="14" y="14" width="52" height="52" rx="10" stroke="#0c6e5e" stroke-width="2"/><line x1="14" y1="28" x2="66" y2="28" stroke="#0c6e5e" stroke-width="2"/><circle cx="28" cy="44" r="4" fill="#0c6e5e" opacity="0.4"/><circle cx="40" cy="44" r="4" fill="#0c6e5e" opacity="0.6"/><circle cx="52" cy="44" r="4" fill="#0c6e5e" opacity="0.3"/><line x1="28" y1="8" x2="28" y2="18" stroke="#0c6e5e" stroke-width="2" stroke-linecap="round"/><line x1="52" y1="8" x2="52" y2="18" stroke="#0c6e5e" stroke-width="2" stroke-linecap="round"/></svg>
                        <h6>No Schedules Today</h6>
                        <p>Tour schedules for today will appear here.</p>
                        <a href="<?= BASE_URL ?>/admin/schedules.php" class="empty-cta">Create Schedule <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <div id="schedList">
                        <?php foreach ($todaySchedules as $s):
                            $statusColors = ['scheduled' => '#3b82f6', 'in_progress' => '#f59e0b', 'completed' => '#10b981', 'cancelled' => '#ef4444'];
                            $sc = $statusColors[$s['status'] ?? 'scheduled'] ?? '#64748b';
                            $slotTime = $s['slot_time'] ?? (isset($s['start_date']) ? date('g:i A', strtotime($s['start_date'])) : '');
                            $slotInfo = $s['slots'] ?? '';
                        ?>
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom sched-item" style="transition:background 0.15s;">
                            <div style="width:4px;height:44px;border-radius:4px;background:<?= $sc ?>;flex-shrink:0;"></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= sanitize($s['event_title'] ?? 'Untitled') ?></div>
                                <small class="text-muted"><i class="fas fa-user-tie me-1"></i><?= sanitize($s['guide_name'] ?? 'Unassigned') ?> · <i class="fas fa-map-pin me-1"></i><?= sanitize($s['destination_name'] ?? '') ?></small>
                                <?php if ($slotInfo): ?><div><small class="text-muted"><?= sanitize($slotInfo) ?></small></div><?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if ($slotTime): ?><div class="sched-time mb-1"><?= sanitize($slotTime) ?></div><?php endif; ?>
                                <div class="fw-semibold small" style="color:<?= $sc ?>;"><?= format_date($s['start_date'] ?? '') ?></div>
                                <small class="text-muted text-capitalize"><?= str_replace('_', ' ', $s['status'] ?? 'scheduled') ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <!-- Feedback Sparkline Card -->
        <div class="card chart-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2"><span class="card-icon-circle card-icon-purple"><i class="fas fa-star"></i></span>Weekly Feedback</h6>
                <?php if (!empty($isDemoFeedback)): ?><span class="demo-badge">Sample</span><?php endif; ?>
            </div>
            <div class="card-body text-center">
                <?php if ($feedbackCount > 0): ?>
                    <div style="font-size:2rem;font-weight:900;color:#f59e0b;"><?= $avgFeedback ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted,#64748b);margin-bottom:8px;"><?= $feedbackCount ?> reviews this week</div>
                    <canvas id="feedbackSparkline" style="width:100%;height:50px;"></canvas>
                    <?php
                    $fbTotal = array_sum($fbBreak) ?: 1;
                    $fbOrder = [5, 4, 3, 2, 1];
                    ?>
                    <div class="mt-3 text-start">
                        <?php foreach ($fbOrder as $star):
                            $cnt = (int)($fbBreak[$star] ?? 0);
                            if ($cnt === 0 && $star < 3) continue;
                            $pct = round(($cnt / $fbTotal) * 100);
                        ?>
                        <div class="fb-bar-row">
                            <span style="min-width:28px;font-weight:700;"><?= $star ?>★</span>
                            <div class="fb-bar-track"><div class="fb-bar-fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="text-muted" style="min-width:18px;text-align:right;"><?= $cnt ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($fbSample['comments'])): ?>
                    <div class="fb-sample">
                        <div class="d-flex align-items-center gap-1 mb-1" style="color:#b45309;">
                            <?php for ($i = 0; $i < 5; $i++): ?><i class="fas fa-star" style="font-size:.62rem;<?= $i < (int)($fbSample['overall_rating'] ?? 5) ? '' : 'opacity:.3;' ?>"></i><?php endfor; ?>
                            <span style="font-size:.68rem;font-weight:700;margin-left:4px;"><?= sanitize($fbSample['name'] ?? 'Tourist') ?></span>
                        </div>
                        <div style="color:var(--text-primary,#334155);">“<?= sanitize(truncate($fbSample['comments'], 140)) ?>”</div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:20px 10px;">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:56px;height:56px;"><polygon points="40,8 48,30 72,30 52,46 60,70 40,54 20,70 28,46 8,30 32,30" stroke="#8b5cf6" stroke-width="2" fill="#8b5cf6" opacity="0.08"/></svg>
                        <h6>No Feedback Yet</h6>
                        <p>Weekly feedback trends will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Recent Bookings + Pending Verifications ═══ -->
<div class="dash-section-title">Management</div>
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2"><span class="card-icon-circle card-icon-teal"><i class="fas fa-ticket"></i></span>Recent Bookings <?php if (!empty($isDemoBookings)): ?><span class="demo-badge">Sample</span><?php endif; ?></h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="bookingStatusFilter" class="booking-filter" aria-label="Filter bookings by status">
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-sm" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;">View All</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bookings-table" id="recentBookingsTable">
                        <thead>
                            <tr style="background:var(--border-color,#f8fafc);">
                                <th class="ps-3" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted,#64748b);">Tourist</th>
                                <th style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted,#64748b);">Event</th>
                                <th style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted,#64748b);">Destination</th>
                                <th style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted,#64748b);">Date</th>
                                <th style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted,#64748b);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentBookings)): ?>
                                <tr><td colspan="5"><div class="empty-state py-4">
                                    <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:56px;height:56px;"><rect x="12" y="16" width="56" height="48" rx="8" stroke="#0c6e5e" stroke-width="2"/><path d="M12 30 H68" stroke="#0c6e5e" stroke-width="2"/><circle cx="26" cy="46" r="5" fill="#0c6e5e" opacity="0.3"/><circle cx="40" cy="46" r="5" fill="#0c6e5e" opacity="0.5"/><circle cx="54" cy="46" r="5" fill="#0c6e5e" opacity="0.3"/></svg>
                                    <h6>No Bookings Yet</h6>
                                    <p>Recent bookings will appear here.</p>
                                </div></td></tr>
                            <?php else: ?>
                                <?php
                                $avatarPalette = ['#0c6e5e','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#0d9488','#6366f1'];
                                foreach ($recentBookings as $b):
                                    $statusMap = [
                                        'confirmed' => ['success','fa-check-circle'],
                                        'pending' => ['warning','fa-clock'],
                                        'cancelled' => ['danger','fa-times-circle'],
                                        'completed' => ['primary','fa-flag-checkered'],
                                    ];
                                    $s = $statusMap[$b['status']] ?? ['secondary','fa-circle'];
                                    $tName = $b['tourist_name'] ?? 'N/A';
                                    $parts = preg_split('/\s+/', trim($tName));
                                    $initials = strtoupper((substr($parts[0] ?? 'U', 0, 1)) . (isset($parts[1]) ? substr(end($parts), 0, 1) : ''));
                                    $avColor = $avatarPalette[crc32($tName) % count($avatarPalette)];
                                    $rowData = htmlspecialchars(json_encode(['id' => $b['id'] ?? '', 'tourist' => $tName, 'event' => $b['event_title'] ?? '', 'destination' => $b['destination_name'] ?? '', 'date' => $b['start_date'] ?? '', 'status' => $b['status'] ?? '']), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="booking-row" data-status="<?= sanitize($b['status'] ?? '') ?>" data-booking='<?= $rowData ?>' tabindex="0" title="Click to view details">
                                        <td class="ps-3"><span class="d-flex align-items-center gap-2"><span class="avatar-initials" style="background:<?= $avColor ?>;"><?= sanitize($initials) ?></span><span class="fw-semibold small"><?= sanitize($tName) ?></span></span></td>
                                        <td><span class="small"><?= sanitize(truncate($b['event_title'] ?? '—', 28)) ?></span></td>
                                        <td><small class="text-muted"><?= sanitize($b['destination_name'] ?? '—') ?></small></td>
                                        <td><small class="text-muted"><?= format_date($b['start_date'] ?? '') ?></small></td>
                                        <td><span class="badge rounded-pill bg-<?= $s[0] ?> d-inline-flex align-items-center gap-1"><i class="fas <?= $s[1] ?>" style="font-size:0.6rem;"></i><?= ucfirst($b['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2"><span class="card-icon-circle card-icon-red"><i class="fas fa-shield-alt"></i></span>ID Verifications <?php if (!empty($isDemoVerifs)): ?><span class="demo-badge">Sample</span><?php endif; ?></h6>
                <?php if (!empty($pendingVerifications)): ?>
                    <span class="badge bg-danger rounded-pill"><?= count($pendingVerifications) ?> pending</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingVerifications)): ?>
                    <div class="empty-state py-4">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:56px;height:56px;"><circle cx="40" cy="40" r="26" stroke="#0c6e5e" stroke-width="2" fill="#0c6e5e" opacity="0.08"/><path d="M28 40 L36 48 L54 30" stroke="#0c6e5e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <h6>All Caught Up!</h6>
                        <p>No pending ID verifications.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingVerifications as $v):
                        $isDemo = !empty($v['is_demo']);
                        $role = $v['role'] ?? '';
                    ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom verif-item">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:rgba(239,68,68,0.1);color:#ef4444;flex-shrink:0;font-size:0.75rem;font-weight:700;">
                                    <?= strtoupper(substr($v['user_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold small"><?= sanitize($v['user_name'] ?? 'N/A') ?> <?php if ($isDemo): ?><span class="demo-badge">Sample</span><?php endif; ?></div>
                                    <small class="text-muted d-block"><?= sanitize($v['user_email'] ?? '') ?></small>
                                    <?php if ($role): ?><small style="color:#0c6e5e;font-weight:600;"><?= sanitize($role) ?></small><?php endif; ?>
                                    <?php if (!empty($v['created_at'])): ?><small class="text-muted d-block" style="font-size:.66rem;"><?= time_ago($v['created_at']) ?></small><?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isDemo): ?>
                            <div class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm rounded-pill" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;" title="Review in Users">Review</a>
                            </div>
                            <?php else: ?>
                            <div class="d-flex gap-1 verif-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="approve_verification">
                                    <input type="hidden" name="verification_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-sm rounded-pill" style="background:rgba(16,185,129,0.1);color:#10b981;" title="Approve" aria-label="Approve verification for <?= sanitize($v['user_name'] ?? '') ?>"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reject_verification">
                                    <input type="hidden" name="verification_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-sm rounded-pill" style="background:rgba(239,68,68,0.1);color:#ef4444;" title="Reject" aria-label="Reject verification for <?= sanitize($v['user_name'] ?? '') ?>"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                            <div class="d-flex gap-1 verif-actions-mobile">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="approve_verification">
                                    <input type="hidden" name="verification_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-sm rounded-pill" style="background:rgba(16,185,129,0.1);color:#10b981;" title="Approve" aria-label="Approve verification for <?= sanitize($v['user_name'] ?? '') ?>"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reject_verification">
                                    <input type="hidden" name="verification_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-sm rounded-pill" style="background:rgba(239,68,68,0.1);color:#ef4444;" title="Reject" aria-label="Reject verification for <?= sanitize($v['user_name'] ?? '') ?>"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- .dash-wrap -->

<!-- Booking detail drawer -->
<div class="booking-drawer-overlay" id="bookingDrawerOverlay"></div>
<aside class="booking-drawer" id="bookingDrawer" aria-label="Booking details" role="dialog" aria-modal="true">
    <div class="drawer-header d-flex justify-content-between align-items-start">
        <div>
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.8;">Booking Details</div>
            <h6 class="mb-0 fw-bold" id="drawerTourist" style="font-size:1.05rem;">—</h6>
            <small id="drawerId" style="opacity:.8;">—</small>
        </div>
        <button type="button" id="drawerClose" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border-radius:8px;" aria-label="Close details"><i class="fas fa-times"></i></button>
    </div>
    <div class="drawer-body">
        <div class="drawer-row"><span class="drawer-label">Event</span><span class="drawer-value" id="drawerEvent">—</span></div>
        <div class="drawer-row"><span class="drawer-label">Destination</span><span class="drawer-value" id="drawerDest">—</span></div>
        <div class="drawer-row"><span class="drawer-label">Date</span><span class="drawer-value" id="drawerDate">—</span></div>
        <div class="drawer-row"><span class="drawer-label">Status</span><span class="drawer-value" id="drawerStatus">—</span></div>
        <div class="d-grid gap-2 mt-3">
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-sm" style="background:linear-gradient(135deg,#0c6e5e,#10b981);color:#fff;border-radius:10px;font-weight:600;">Open in Bookings <i class="fas fa-arrow-right ms-1"></i></a>
            <button type="button" id="drawerCloseBtn" class="btn btn-sm" style="border:1px solid var(--border-color,#e2e8f0);border-radius:10px;">Close</button>
        </div>
    </div>
</aside>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const teal = '#0c6e5e';
    const tealLight = '#14b8a6';
    const blue = '#3b82f6';
    const green = '#10b981';
    const amber = '#f59e0b';
    const red = '#ef4444';
    const purple = '#8b5cf6';

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";

    // Revenue Trend
    const revData = <?= json_encode($revenueByMonth) ?>;
    if (revData.length > 0) {
        const revCanvas = document.getElementById('revenueChart');
        const revCtx = revCanvas.getContext('2d');
        const revGrad = revCtx.createLinearGradient(0, 0, 0, 280);
        revGrad.addColorStop(0, 'rgba(12,110,94,0.25)');
        revGrad.addColorStop(1, 'rgba(12,110,94,0.01)');
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: revData.map(r => {
                    const [y, m] = r.month.split('-');
                    return new Date(y, m - 1).toLocaleDateString('en', { month: 'short' });
                }),
                datasets: [{
                    label: 'Revenue',
                    data: revData.map(r => parseFloat(r.revenue)),
                    borderColor: teal,
                    backgroundColor: revGrad,
                    fill: true, tension: 0.4, pointRadius: 5, pointHoverRadius: 7,
                    pointBackgroundColor: '#fff', pointBorderColor: teal, pointBorderWidth: 2, borderWidth: 2.5,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: isDark ? '#1e293b' : '#fff', titleColor: isDark ? '#f1f5f9' : '#1e293b', bodyColor: isDark ? '#cbd5e1' : '#475569', borderColor: isDark ? '#334155' : '#e2e8f0', borderWidth: 1, cornerRadius: 10, padding: 12, displayColors: false, callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString() } } },
                scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } } }, x: { grid: { display: false }, ticks: { font: { size: 11 } } } }
            }
        });
    }

    // Payment Methods
    const pmData = <?= json_encode($paymentByMethod) ?>;
    if (pmData.length > 0) {
        const pmLabels = pmData.map(p => p.payment_method === 'gcash' ? 'GCash' : (p.payment_method === 'maya' ? 'Maya' : (p.payment_method === 'card' ? 'Card' : p.payment_method)));
        const pmColors = pmData.map(p => { if (p.payment_method === 'gcash') return '#007dfe'; if (p.payment_method === 'maya') return '#006400'; if (p.payment_method === 'card') return purple; return '#b8860b'; });
        new Chart(document.getElementById('paymentMethodChart'), {
            type: 'doughnut',
            data: { labels: pmLabels, datasets: [{ data: pmData.map(p => parseInt(p.cnt)), backgroundColor: pmColors, borderWidth: 0, hoverOffset: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 12 } } }, tooltip: { backgroundColor: isDark ? '#1e293b' : '#fff', titleColor: isDark ? '#f1f5f9' : '#1e293b', bodyColor: isDark ? '#cbd5e1' : '#475569', borderColor: isDark ? '#334155' : '#e2e8f0', borderWidth: 1, cornerRadius: 10, padding: 12 } } }
        });
    }

    // Booking Trend
    const btData = <?= json_encode($bookingByMonth) ?>;
    if (btData.length > 0) {
        const btCanvas = document.getElementById('bookingTrendChart');
        const btCtx = btCanvas.getContext('2d');
        const btGrad = btCtx.createLinearGradient(0, 0, 0, 280);
        btGrad.addColorStop(0, 'rgba(12,110,94,0.8)');
        btGrad.addColorStop(1, 'rgba(20,184,166,0.4)');
        new Chart(btCtx, {
            type: 'bar',
            data: { labels: btData.map(b => { const [y, m] = b.month.split('-'); return new Date(y, m - 1).toLocaleDateString('en', { month: 'short' }); }), datasets: [{ label: 'Bookings', data: btData.map(b => parseInt(b.cnt)), backgroundColor: btGrad, borderRadius: 8, borderSkipped: false, barThickness: 32 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: isDark ? '#1e293b' : '#fff', titleColor: isDark ? '#f1f5f9' : '#1e293b', bodyColor: isDark ? '#cbd5e1' : '#475569', borderColor: isDark ? '#334155' : '#e2e8f0', borderWidth: 1, cornerRadius: 10, padding: 12, displayColors: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { stepSize: 1, font: { size: 11 } } }, x: { grid: { display: false }, ticks: { font: { size: 11 } } } } }
        });
    }

    // Booking Status
    const bsData = <?= json_encode($bookingByStatus) ?>;
    const bsLabels = Object.keys(bsData);
    const bsValues = Object.values(bsData);
    if (bsLabels.length > 0) {
        const bsColors = bsLabels.map(s => ({ confirmed: green, pending: amber, completed: blue, cancelled: red, in_progress: teal }[s] || '#94a3b8'));
        new Chart(document.getElementById('bookingStatusChart'), {
            type: 'doughnut',
            data: { labels: bsLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)), datasets: [{ data: bsValues, backgroundColor: bsColors, borderWidth: 0, hoverOffset: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 12 } } }, tooltip: { backgroundColor: isDark ? '#1e293b' : '#fff', titleColor: isDark ? '#f1f5f9' : '#1e293b', bodyColor: isDark ? '#cbd5e1' : '#475569', borderColor: isDark ? '#334155' : '#e2e8f0', borderWidth: 1, cornerRadius: 10, padding: 12 } } }
        });
    }

    // Feedback Sparkline (last 7 days)
    const fsData = <?= json_encode($feedbackSparkline) ?>;
    const fsCanvas = document.getElementById('feedbackSparkline');
    if (fsCanvas && fsData.length > 1) {
        const fsCtx = fsCanvas.getContext('2d');
        const w = fsCanvas.parentElement.offsetWidth - 40 || 260;
        fsCanvas.width = w; fsCanvas.height = 50;
        fsCanvas.style.width = w + 'px'; fsCanvas.style.height = '50px';
        const fsGrad = fsCtx.createLinearGradient(0, 0, 0, 50);
        fsGrad.addColorStop(0, 'rgba(245,158,11,0.2)');
        fsGrad.addColorStop(1, 'rgba(245,158,11,0.01)');
        fsCtx.beginPath();
        fsData.forEach(function(d, i) {
            var x = (i / (fsData.length - 1)) * w;
            var y = 50 - ((d.avg_rating || 0) / 5) * 44;
            if (i === 0) fsCtx.moveTo(x, y);
            else {
                var px = ((i - 1) / (fsData.length - 1)) * w;
                var cpx = (px + x) / 2;
                var py = 50 - ((fsData[i - 1].avg_rating || 0) / 5) * 44;
                fsCtx.bezierCurveTo(cpx, py, cpx, y, x, y);
            }
        });
        fsCtx.lineTo(w, 50); fsCtx.lineTo(0, 50); fsCtx.closePath();
        fsCtx.fillStyle = fsGrad; fsCtx.fill();
        fsCtx.beginPath();
        fsData.forEach(function(d, i) {
            var x = (i / (fsData.length - 1)) * w;
            var y = 50 - ((d.avg_rating || 0) / 5) * 44;
            if (i === 0) fsCtx.moveTo(x, y);
            else {
                var px = ((i - 1) / (fsData.length - 1)) * w;
                var cpx = (px + x) / 2;
                var py = 50 - ((fsData[i - 1].avg_rating || 0) / 5) * 44;
                fsCtx.bezierCurveTo(cpx, py, cpx, y, x, y);
            }
        });
        fsCtx.strokeStyle = '#f59e0b'; fsCtx.lineWidth = 2; fsCtx.stroke();
        fsData.forEach(function(d, i) {
            var x = (i / (fsData.length - 1)) * w;
            var y = 50 - ((d.avg_rating || 0) / 5) * 44;
            fsCtx.beginPath(); fsCtx.arc(x, y, 3, 0, Math.PI * 2);
            fsCtx.fillStyle = '#f59e0b'; fsCtx.fill();
            fsCtx.strokeStyle = isDark ? '#1e293b' : '#fff'; fsCtx.lineWidth = 1.5; fsCtx.stroke();
        });
    }

    // Sparkline mini-charts in metric cards
    document.querySelectorAll('.stat-card canvas.sparkline').forEach(canvas => {
        const color = canvas.dataset.color || '#0c6e5e';
        const val = parseFloat(canvas.dataset.val) || 0;
        const ctx = canvas.getContext('2d');
        const w = canvas.parentElement.offsetWidth || 200;
        canvas.width = w; canvas.height = 28;
        canvas.style.width = w + 'px'; canvas.style.height = '28px'; canvas.style.marginTop = '8px';
        const pts = 8; const data = [];
        for (let i = 0; i < pts; i++) {
            const base = 50 + (val / 100) * 20;
            const noise = Math.sin(i * 0.8 + val * 0.1) * 15 + Math.cos(i * 1.2) * 10;
            data.push(Math.max(5, Math.min(95, base + noise)));
        }
        ctx.beginPath();
        data.forEach((y, i) => {
            const x = (i / (pts - 1)) * w; const py = 28 - (y / 100) * 26;
            if (i === 0) ctx.moveTo(x, py);
            else { const prevX = ((i - 1) / (pts - 1)) * w; const cpx = (prevX + x) / 2; const prevY = 28 - (data[i - 1] / 100) * 26; ctx.bezierCurveTo(cpx, prevY, cpx, py, x, py); }
        });
        ctx.lineTo(w, 28); ctx.lineTo(0, 28); ctx.closePath();
        const grad = ctx.createLinearGradient(0, 0, 0, 28);
        grad.addColorStop(0, color + '25'); grad.addColorStop(1, color + '05');
        ctx.fillStyle = grad; ctx.fill();
        ctx.beginPath();
        data.forEach((y, i) => {
            const x = (i / (pts - 1)) * w; const py = 28 - (y / 100) * 26;
            if (i === 0) ctx.moveTo(x, py);
            else { const prevX = ((i - 1) / (pts - 1)) * w; const cpx = (prevX + x) / 2; const prevY = 28 - (data[i - 1] / 100) * 26; ctx.bezierCurveTo(cpx, prevY, cpx, py, x, py); }
        });
        ctx.strokeStyle = color; ctx.lineWidth = 1.5; ctx.stroke();
    });

    // Schedule search
    var schedInput = document.getElementById('schedSearch');
    if (schedInput) {
        schedInput.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.sched-item').forEach(function(item) {
                item.style.display = item.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            });
        });
    }

    // Booking status filter
    var statusFilter = document.getElementById('bookingStatusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            var v = this.value;
            document.querySelectorAll('#recentBookingsTable .booking-row').forEach(function(row) {
                row.style.display = (!v || row.dataset.status === v) ? '' : 'none';
            });
        });
    }

    // Booking drawer
    var drawer = document.getElementById('bookingDrawer');
    var overlay = document.getElementById('bookingDrawerOverlay');
    function openDrawer(data) {
        if (!drawer || !data) return;
        document.getElementById('drawerTourist').textContent = data.tourist || '—';
        document.getElementById('drawerId').textContent = data.id ? 'Booking #' + data.id : '';
        document.getElementById('drawerEvent').textContent = data.event || '—';
        document.getElementById('drawerDest').textContent = data.destination || '—';
        document.getElementById('drawerDate').textContent = data.date || '—';
        document.getElementById('drawerStatus').textContent = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : '—';
        drawer.classList.add('open');
        if (overlay) overlay.classList.add('show');
    }
    function closeDrawer() {
        if (drawer) drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
    }
    document.querySelectorAll('#recentBookingsTable .booking-row').forEach(function(row) {
        row.addEventListener('click', function() {
            try { openDrawer(JSON.parse(row.dataset.booking)); } catch (e) {}
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); try { openDrawer(JSON.parse(row.dataset.booking)); } catch (err) {} }
        });
    });
    var dc = document.getElementById('drawerClose');
    if (dc) dc.addEventListener('click', closeDrawer);
    var dcb = document.getElementById('drawerCloseBtn');
    if (dcb) dcb.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });
});
</script>

<?php }); ?>
