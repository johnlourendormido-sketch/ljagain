<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

$totalBookings = (int) $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue = (float) $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE payment_status = 'paid'")->fetchColumn();
$activeTours = (int) $db->query("SELECT COUNT(*) FROM schedules WHERE status IN ('scheduled', 'in_progress')")->fetchColumn();
$completedTours = (int) $db->query("SELECT COUNT(*) FROM schedules WHERE status = 'completed'")->fetchColumn();
$totalUsers = (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$totalEvents = (int) $db->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn();
$avgRating = (float) $db->query("SELECT COALESCE(AVG(overall_rating), 0) FROM feedback")->fetchColumn();
$pendingVerifications = (int) $db->query("SELECT COUNT(*) FROM id_verifications WHERE status = 'pending'")->fetchColumn();
$totalRevenuePaid = (float) $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE payment_status = 'paid'")->fetchColumn();
$monthlyRevenue = (float) $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE payment_status = 'paid' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

// Month-over-month comparison
$prevMonthStart = date('Y-m-01', strtotime('-1 month'));
$prevMonthEnd = date('Y-m-t', strtotime('-1 month'));
$pRev = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM payments WHERE payment_status='paid' AND created_at >= :from AND created_at <= :to");
$pRev->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevRevenueVal = (float)$pRev->fetchColumn();
$revChange = $prevRevenueVal > 0 ? round((($monthlyRevenue - $prevRevenueVal) / $prevRevenueVal) * 100) : null;

$pBk = $db->prepare("SELECT COUNT(*) FROM bookings WHERE created_at >= :from AND created_at <= :to");
$pBk->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevBookingsVal = (int)$pBk->fetchColumn();
$bkChange = $prevBookingsVal > 0 ? round((($totalBookings - $prevBookingsVal) / $prevBookingsVal) * 100) : null;

$pUsr = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at >= :from AND created_at <= :to");
$pUsr->execute([':from'=>$prevMonthStart, ':to'=>$prevMonthEnd.' 23:59:59']);
$prevUsersVal = (int)$pUsr->fetchColumn();
$userChange = $prevUsersVal > 0 ? round((($totalUsers - $prevUsersVal) / $prevUsersVal) * 100) : null;

$bookingsByMonth = $db->query(
    "SELECT DATE_FORMAT(b.created_at, '%Y-%m') as month, COUNT(*) as count,
            SUM(CASE WHEN b.status IN ('confirmed','completed') THEN b.total_price ELSE 0 END) as revenue
     FROM bookings b
     WHERE b.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
     ORDER BY month ASC"
)->fetchAll();

$revenueByMonth = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as count
     FROM payments
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month ASC"
)->fetchAll();

$popularDestinations = $db->query(
    "SELECT d.name, COUNT(b.id) as booking_count
     FROM destinations d
     LEFT JOIN events e ON e.destination_id = d.id
     LEFT JOIN schedules s ON s.event_id = e.id
     LEFT JOIN bookings b ON b.schedule_id = s.id AND b.status IN ('confirmed', 'completed')
     GROUP BY d.id, d.name
     ORDER BY booking_count DESC
     LIMIT 8"
)->fetchAll();

$usersByMonth = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month ASC"
)->fetchAll();

$bookingByStatus = $db->query(
    "SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$paymentByMethod = $db->query(
    "SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as total
     FROM payments WHERE payment_status = 'paid'
     GROUP BY payment_method"
)->fetchAll();

$topEvents = $db->query(
    "SELECT e.title, COUNT(b.id) as booking_count, SUM(b.total_price) as revenue
     FROM events e
     JOIN schedules s ON s.event_id = e.id
     JOIN bookings b ON b.schedule_id = s.id AND b.status IN ('confirmed','completed')
     GROUP BY e.id, e.title
     ORDER BY booking_count DESC
     LIMIT 5"
)->fetchAll();

$recentFeedback = $db->query(
    "SELECT f.overall_rating, f.comment, u.name as tourist_name, e.title as event_title
     FROM feedback f
     JOIN users u ON f.tourist_id = u.id
     LEFT JOIN bookings b ON f.booking_id = b.id
     LEFT JOIN schedules s ON b.schedule_id = s.id
     LEFT JOIN events e ON s.event_id = e.id
     ORDER BY f.created_at DESC
     LIMIT 5"
)->fetchAll();

$recentBookings = $db->query(
    "SELECT b.*, u.name as tourist_name, e.title as event_title, d.name as destination_name, s.start_date
     FROM bookings b
     LEFT JOIN users u ON b.tourist_id = u.id
     LEFT JOIN schedules s ON b.schedule_id = s.id
     LEFT JOIN events e ON s.event_id = e.id
     LEFT JOIN destinations d ON e.destination_id = d.id
     ORDER BY b.created_at DESC LIMIT 10"
)->fetchAll();

$allBookings = $db->query(
    "SELECT b.id, b.booking_reference, u.name as tourist_name, e.title as event_title,
            d.name as destination_name, s.start_date, b.num_participants, b.total_price, b.status, b.created_at
     FROM bookings b
     LEFT JOIN users u ON b.tourist_id = u.id
     LEFT JOIN schedules s ON b.schedule_id = s.id
     LEFT JOIN events e ON s.event_id = e.id
     LEFT JOIN destinations d ON e.destination_id = d.id
     ORDER BY b.created_at DESC"
)->fetchAll();

$allPayments = $db->query(
    "SELECT p.*, u.name as tourist_name
     FROM payments p
     LEFT JOIN users u ON p.tourist_id = u.id
     ORDER BY p.created_at DESC"
)->fetchAll();

render_page('admin', 'reports.php', 'Reports & Analytics', function () use (
    $totalBookings, $totalRevenue, $totalRevenuePaid, $activeTours, $completedTours, $totalUsers,
    $totalEvents, $avgRating, $pendingVerifications, $monthlyRevenue,
    $bookingsByMonth, $revenueByMonth, $popularDestinations,
    $usersByMonth, $bookingByStatus, $paymentByMethod, $topEvents, $recentFeedback,
    $recentBookings, $allBookings, $allPayments,
    $bkChange, $revChange, $userChange
) {
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
.report-stat {
    border: none; border-radius: 16px; transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    position: relative; overflow: hidden; background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #f1f5f9);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}
.report-stat:hover { transform: translateY(-4px); box-shadow: 0 12px 36px rgba(0,0,0,0.1); border-color: transparent; }
.report-stat .card-body { padding: 22px; }
.report-stat .stat-icon {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; transition: transform 0.3s;
}
.report-stat:hover .stat-icon { transform: scale(1.1) rotate(-3deg); }
.report-stat .stat-value { font-size: 1.8rem; font-weight: 900; line-height: 1.15; letter-spacing: -0.5px; }
.report-stat .stat-label {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--text-muted, #64748b); margin-top: 4px;
}
.report-stat .accent-left {
    position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
    border-radius: 16px 0 0 16px;
}
.report-stat .stat-change {
    font-size: 0.7rem; font-weight: 600; display: inline-flex;
    align-items: center; gap: 3px; padding: 2px 8px;
    border-radius: 50px; margin-top: 6px;
}
.report-stat .stat-change.up { background: rgba(16,185,129,0.1); color: #10b981; }
.report-stat .stat-change.down { background: rgba(239,68,68,0.1); color: #ef4444; }
.report-stat .stat-change.neutral { background: rgba(100,116,139,0.1); color: #64748b; }
.report-stat.skeleton .stat-value,
.report-stat.skeleton .stat-label,
.report-stat.skeleton .stat-change {
    background: linear-gradient(90deg, rgba(130,130,130,.06) 25%, rgba(130,130,130,.14) 37%, rgba(130,130,130,.06) 63%);
    background-size: 400% 100%; animation: shimmer 1.4s ease infinite; border-radius: 6px; color: transparent !important;
}

/* ═══ Section Titles ═══ */
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

/* ═══ Chart Cards ═══ */
.chart-card {
    border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    border: 1px solid var(--border-color, #f1f5f9); background: var(--card-bg, #fff);
    transition: box-shadow 0.3s;
}
.chart-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.07); }
.chart-card .card-header {
    background: transparent; border-bottom: 1px solid var(--border-color, #f1f5f9);
    padding: 16px 20px; color: var(--text-primary, #1e293b);
}
.chart-card canvas { max-height: 280px; }

/* ═══ Page Header ═══ */
.page-header-card {
    background: var(--card-bg, #fff); border-radius: 16px;
    border: 1px solid var(--border-color, #f1f5f9);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px;
    padding: 20px 24px;
}

/* ═══ Filter Bar ═══ */
.filter-bar {
    background: var(--card-bg, #fff); border-radius: 16px;
    border: 1px solid var(--border-color, #f1f5f9);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 24px;
    padding: 16px 20px;
    border-left: 3px solid #0c6e5e;
}
.filter-bar select, .filter-bar input[type="date"] {
    border: 1.5px solid var(--border-color, #e2e8f0); border-radius: 10px;
    padding: 8px 14px; font-size: 0.82rem; font-weight: 500;
    background: var(--card-bg, #fff); color: var(--text-primary, #1e293b);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.filter-bar select:focus, .filter-bar input[type="date"]:focus {
    border-color: #0c6e5e; box-shadow: 0 0 0 3px rgba(12,110,94,0.1);
    outline: none;
}
.filter-group {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 14px; border-radius: 12px;
    background: var(--bg-secondary, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
}
.filter-group-label {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--text-muted, #64748b);
    white-space: nowrap;
}
.active-range-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 50px; font-size: 0.72rem;
    font-weight: 600; background: rgba(12,110,94,0.1); color: #0c6e5e;
    border: 1px solid rgba(12,110,94,0.2);
}
.active-range-badge i { font-size: 0.6rem; }

/* Segmented Pill Filter */
.pill-filter {
    display: inline-flex; gap: 0; background: var(--bg-secondary, #f1f5f9);
    border-radius: 12px; padding: 4px; border: 1px solid var(--border-color, #e2e8f0);
}
.pill-filter .pill-btn {
    padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 0.78rem; font-weight: 600; transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    background: transparent; color: var(--text-muted, #64748b);
    display: inline-flex; align-items: center; gap: 6px;
}
.pill-filter .pill-btn:hover {
    color: var(--text-primary, #1e293b); background: rgba(255,255,255,0.5);
}
.pill-filter .pill-btn.active {
    background: #0c6e5e; color: #fff;
    box-shadow: 0 2px 8px rgba(12,110,94,0.25);
}
.pill-filter .pill-btn i { font-size: 0.72rem; }

/* ═══ Export Buttons ═══ */
.export-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: 10px; font-size: 0.82rem;
    font-weight: 600; border: 1.5px solid var(--border-color, #e2e8f0); cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1); text-decoration: none;
    background: var(--card-bg, #fff); color: var(--text-primary, #1e293b);
    position: relative; overflow: hidden;
}
.export-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
.export-btn:active { transform: translateY(0); }
.export-btn i { font-size: 0.85rem; transition: transform 0.25s; }
.export-btn:hover i { transform: scale(1.1); }
.export-btn.pdf { border-color: rgba(239,68,68,0.3); color: #ef4444; }
.export-btn.pdf:hover { background: rgba(239,68,68,0.08); border-color: #ef4444; box-shadow: 0 6px 16px rgba(239,68,68,0.15); }
.export-btn.excel { border-color: rgba(16,185,129,0.3); color: #10b981; }
.export-btn.excel:hover { background: rgba(16,185,129,0.08); border-color: #10b981; box-shadow: 0 6px 16px rgba(16,185,129,0.15); }
.export-btn.print { border-color: rgba(12,110,94,0.3); color: #0c6e5e; }
.export-btn.print:hover { background: rgba(12,110,94,0.08); border-color: #0c6e5e; box-shadow: 0 6px 16px rgba(12,110,94,0.15); }
.export-btn.loading { pointer-events: none; opacity: 0.7; }
.export-btn.loading i { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.export-btn:disabled { opacity: 0.4; pointer-events: none; cursor: not-allowed; }
.export-btn:disabled:hover { transform: none; box-shadow: none; }

/* ═══ Summary Sidebar ═══ */
.summary-card {
    border: none; border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    border: 1px solid var(--border-color, #f1f5f9);
    background: var(--card-bg, #fff);
    overflow: hidden;
}
.summary-card .card-header {
    background: transparent; border-bottom: 1px solid var(--border-color, #f1f5f9);
    padding: 16px 20px; color: var(--text-primary, #1e293b);
}
.summary-section { padding: 16px 20px; }
.summary-section + .summary-section {
    border-top: 1px solid var(--border-color, #f1f5f9);
}
.summary-section-title {
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: var(--text-muted, #94a3b8);
    margin-bottom: 12px;
}
.summary-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 0; transition: background 0.15s;
}
.summary-item:hover { background: var(--hover-bg, transparent); margin: 0 -8px; padding: 8px; border-radius: 8px; }
.summary-item-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; flex-shrink: 0;
}
.summary-item-label { font-size: 0.82rem; color: var(--text-muted, #64748b); flex: 1; }
.summary-item-value { font-size: 0.88rem; font-weight: 700; color: var(--text-primary, #1e293b); }

/* ═══ Empty States ═══ */
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

/* ═══ Top Lists ═══ */
.top-list-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 0; border-bottom: 1px solid var(--border-color, #f1f5f9);
}
.top-list-item:last-child { border-bottom: none; }
.top-list-item .rank {
    width: 30px; height: 30px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}

/* ═══ Toast ═══ */
.report-toast {
    position: fixed; top: 20px; right: 20px; z-index: 9999;
    padding: 12px 20px; border-radius: 12px; font-size: 0.85rem; font-weight: 600;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    animation: toastIn 0.3s cubic-bezier(0.4,0,0.2,1);
}
.report-toast.success { background: #10b981; color: #fff; }
.report-toast.error { background: #ef4444; color: #fff; }
@keyframes toastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* ═══ Dark Mode ═══ */
[data-theme="dark"] .report-stat { box-shadow: 0 2px 12px rgba(0,0,0,0.2); }
[data-theme="dark"] .report-stat:hover { box-shadow: 0 12px 36px rgba(0,0,0,0.35); }
[data-theme="dark"] .chart-card { box-shadow: 0 2px 12px rgba(0,0,0,0.2); }
[data-theme="dark"] .chart-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.3); }
[data-theme="dark"] .summary-card { box-shadow: 0 2px 12px rgba(0,0,0,0.2); }
[data-theme="dark"] .page-header-card { box-shadow: 0 2px 12px rgba(0,0,0,0.2); }

/* ═══ Print ═══ */
@media print {
    .app-sidebar, .sidebar-overlay, #sidebarToggle,
    .topbar, .topbar-wrapper, #appSidebar,
    .notification-wrapper, .notification-dropdown,
    .export-btn, .greeting-banner .d-flex:last-child,
    .btn, form, .filter-bar { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
    body { background: #fff !important; color: #000 !important; }
    .stat-card, .report-stat, .chart-card, .summary-card { background: #fff !important; border-color: #ddd !important; }
    .page-header-card { background: #fff !important; border-color: #ddd !important; }
    .accent-left { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    canvas { max-width: 100% !important; }
}

/* ═══ Responsive ═══ */
@media (max-width: 991.98px) {
    .filter-bar { padding: 12px 16px; }
    .filter-group { flex-wrap: wrap; }
    .summary-card { margin-top: 20px; }
}
@media (max-width: 575.98px) {
    .page-header-card { padding: 16px; }
    .page-header-card .d-flex { flex-direction: column; align-items: flex-start !important; }
    .export-actions { width: 100%; }
    .export-btn { flex: 1; justify-content: center; }
    .pill-filter { flex-wrap: wrap; }
    .pill-filter .pill-btn { padding: 6px 12px; font-size: 0.72rem; }
    .report-stat .stat-value { font-size: 1.4rem; }
}
</style>

<?php
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$dashDate = date('l, F j, Y');
?>

<div class="page-header-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1" style="font-weight:800;color:var(--text-primary,#1e293b);">
                <i class="fas fa-chart-line me-2" style="color:#0c6e5e;"></i>Reports & Analytics
            </h4>
            <p class="mb-0" style="font-size:0.82rem;color:var(--text-muted,#64748b);">
                <?= $dashDate ?> &mdash; Comprehensive insights for BINALGO Tourism
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap export-actions">
            <button type="button" onclick="exportPDF(this)" class="export-btn pdf" id="btnPDF" aria-label="Export as PDF">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" onclick="exportExcel(this)" class="export-btn excel" id="btnExcel" aria-label="Export as Excel">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" onclick="window.print()" class="export-btn print" id="btnPrint" aria-label="Print report">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<div class="filter-bar">
    <div class="d-flex align-items-center flex-wrap gap-3">
        <div class="filter-group">
            <span class="filter-group-label"><i class="fas fa-filter me-1" style="color:#0c6e5e;"></i>Period</span>
            <div class="pill-filter" id="periodPills">
                <button type="button" class="pill-btn" data-period="today" onclick="selectPeriod(this)"><i class="fas fa-calendar-day"></i> Today</button>
                <button type="button" class="pill-btn" data-period="7d" onclick="selectPeriod(this)"><i class="fas fa-calendar-week"></i> 7D</button>
                <button type="button" class="pill-btn active" data-period="month" onclick="selectPeriod(this)"><i class="fas fa-calendar"></i> 30D</button>
                <button type="button" class="pill-btn" data-period="quarter" onclick="selectPeriod(this)"><i class="fas fa-calendar-days"></i> Quarter</button>
                <button type="button" class="pill-btn" data-period="year" onclick="selectPeriod(this)"><i class="fas fa-chart-simple"></i> YTD</button>
                <button type="button" class="pill-btn" data-period="custom" onclick="selectPeriod(this)"><i class="fas fa-sliders"></i> Custom</button>
            </div>
        </div>
        <div class="filter-group custom-dates" id="customDates" style="display:none !important;">
            <span class="filter-group-label">From</span>
            <input type="date" class="form-control form-control-sm" style="width:auto;" id="dateFrom" aria-label="Start date">
            <span class="filter-group-label">To</span>
            <input type="date" class="form-control form-control-sm" style="width:auto;" id="dateTo" aria-label="End date">
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="active-range-badge" id="activeRangeBadge">
                <i class="fas fa-calendar-check"></i>
                <span id="activeRangeText">This Month</span>
            </span>
            <button class="btn btn-sm" style="border:1.5px solid var(--border-color,#e2e8f0);border-radius:10px;font-size:0.8rem;font-weight:600;color:var(--text-muted,#64748b);" onclick="resetFilters()" aria-label="Reset filters">
                <i class="fas fa-rotate-right me-1"></i> Reset
            </button>
        </div>
    </div>
</div>

<!-- Primary KPIs -->
<div class="dash-section-title">Key Metrics</div>
<div class="row g-3 mb-4">
    <?php
    $kpiCards = [
        ['label' => 'Total Bookings', 'value' => $totalBookings, 'icon' => 'fa-ticket', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'gradient' => 'linear-gradient(135deg,#3b82f6,#60a5fa)', 'change' => $bkChange, 'link' => BASE_URL . '/admin/bookings.php'],
        ['label' => 'Total Revenue', 'value' => '₱' . number_format($totalRevenuePaid), 'icon' => 'fa-dollar-sign', 'color' => '#0c6e5e', 'bg' => 'rgba(12,110,94,0.1)', 'gradient' => 'linear-gradient(135deg,#0c6e5e,#14b8a6)', 'change' => $revChange, 'link' => BASE_URL . '/admin/payments.php'],
        ['label' => 'Active Users', 'value' => $totalUsers, 'icon' => 'fa-users', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'gradient' => 'linear-gradient(135deg,#f59e0b,#fbbf24)', 'change' => $userChange, 'link' => BASE_URL . '/admin/users.php'],
        ['label' => 'Avg Rating', 'value' => number_format($avgRating, 1), 'icon' => 'fa-star', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'gradient' => 'linear-gradient(135deg,#8b5cf6,#a78bfa)', 'change' => null, 'link' => BASE_URL . '/admin/feedback.php'],
    ];
    foreach ($kpiCards as $kpi): ?>
    <div class="col-sm-6 col-lg-3">
        <a href="<?= $kpi['link'] ?>" class="text-decoration-none report-stat card" style="cursor:pointer;">
            <div class="accent-left" style="background:<?= $kpi['gradient'] ?>;"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="color:<?= $kpi['color'] ?>;"><?= $kpi['value'] ?></div>
                        <div class="stat-label"><?= $kpi['label'] ?></div>
                        <?php if ($kpi['change'] !== null): ?>
                            <span class="stat-change <?= $kpi['change'] > 0 ? 'up' : ($kpi['change'] < 0 ? 'down' : 'neutral') ?>">
                                <i class="fas fa-<?= $kpi['change'] > 0 ? 'arrow-up' : ($kpi['change'] < 0 ? 'arrow-down' : 'minus') ?>"></i>
                                <?= $kpi['change'] > 0 ? '+' : '' ?><?= $kpi['change'] ?>% vs last month
                            </span>
                        <?php else: ?>
                            <span class="stat-change neutral"><i class="fas fa-minus"></i>No prior data</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon" style="background:<?= $kpi['bg'] ?>;color:<?= $kpi['color'] ?>;">
                        <i class="fas <?= $kpi['icon'] ?>"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secondary KPIs -->
<div class="row g-3 mb-4">
    <?php
    $secKpis = [
        ['label' => 'Active Tours', 'value' => $activeTours, 'icon' => 'fa-route', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'link' => BASE_URL . '/admin/schedules.php'],
        ['label' => 'Completed Tours', 'value' => $completedTours, 'icon' => 'fa-flag-checkered', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'link' => BASE_URL . '/admin/schedules.php'],
        ['label' => 'Published Events', 'value' => $totalEvents, 'icon' => 'fa-calendar', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)', 'link' => BASE_URL . '/admin/events.php'],
    ];
    foreach ($secKpis as $kpi): ?>
    <div class="col-sm-6 col-lg-3">
        <a href="<?= $kpi['link'] ?>" class="text-decoration-none report-stat card" style="cursor:pointer;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="font-size:1.35rem;color:<?= $kpi['color'] ?>;"><?= $kpi['value'] ?></div>
                        <div class="stat-label"><?= $kpi['label'] ?></div>
                    </div>
                    <div class="stat-icon" style="background:<?= $kpi['bg'] ?>;color:<?= $kpi['color'] ?>;">
                        <i class="fas <?= $kpi['icon'] ?>"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts + Summary Row -->
<div class="dash-section-title">Trends & Distribution</div>
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
                        <h6>No Revenue Data</h6>
                        <p>Revenue will appear here once bookings are paid.</p>
                        <button onclick="resetFilters()" class="empty-cta">Try wider date range <i class="fas fa-arrow-right"></i></button>
                    </div>
                <?php else: ?>
                    <div style="height:280px;"><canvas id="revenueChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <!-- Report Summary Sidebar -->
        <div class="summary-card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-clipboard-list" style="color:#0c6e5e;"></i>
                <h6 class="mb-0 fw-bold">Report Summary</h6>
            </div>
            <div class="summary-section">
                <div class="summary-section-title"><i class="fas fa-coins me-1" style="color:#0c6e5e;"></i>Financial</div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(12,110,94,0.1);color:#0c6e5e;"><i class="fas fa-dollar-sign"></i></div>
                    <span class="summary-item-label">Total Revenue</span>
                    <span class="summary-item-value">₱<?= number_format($totalRevenuePaid) ?></span>
                </div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-calendar-check"></i></div>
                    <span class="summary-item-label">This Month</span>
                    <span class="summary-item-value">₱<?= number_format($monthlyRevenue) ?></span>
                </div>
            </div>
            <div class="summary-section">
                <div class="summary-section-title"><i class="fas fa-briefcase me-1" style="color:#3b82f6;"></i>Operations</div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="fas fa-ticket"></i></div>
                    <span class="summary-item-label">Total Bookings</span>
                    <span class="summary-item-value"><?= $totalBookings ?></span>
                </div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-route"></i></div>
                    <span class="summary-item-label">Active Tours</span>
                    <span class="summary-item-value"><?= $activeTours ?></span>
                </div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="fas fa-flag-checkered"></i></div>
                    <span class="summary-item-label">Completed</span>
                    <span class="summary-item-value"><?= $completedTours ?></span>
                </div>
            </div>
            <div class="summary-section">
                <div class="summary-section-title"><i class="fas fa-users me-1" style="color:#f59e0b;"></i>Users & Events</div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="fas fa-users"></i></div>
                    <span class="summary-item-label">Active Users</span>
                    <span class="summary-item-value"><?= $totalUsers ?></span>
                </div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;"><i class="fas fa-calendar"></i></div>
                    <span class="summary-item-label">Published Events</span>
                    <span class="summary-item-value"><?= $totalEvents ?></span>
                </div>
                <div class="summary-item">
                    <div class="summary-item-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-star"></i></div>
                    <span class="summary-item-label">Avg. Rating</span>
                    <span class="summary-item-value"><?= number_format($avgRating, 1) ?> / 5</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="dash-section-title">Trends</div>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2" style="color:#0c6e5e;"></i>Bookings by Month</h6>
                <span class="badge" style="background:rgba(12,110,94,0.1);color:#0c6e5e;font-weight:600;">Last 6 Months</span>
            </div>
            <div class="card-body">
                <?php if (empty($bookingsByMonth)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="14" y="50" width="10" height="18" rx="3" fill="#0c6e5e" opacity="0.3"/><rect x="28" y="38" width="10" height="30" rx="3" fill="#0c6e5e" opacity="0.5"/><rect x="42" y="28" width="10" height="40" rx="3" fill="#0c6e5e" opacity="0.7"/><rect x="56" y="20" width="10" height="48" rx="3" fill="#0c6e5e" opacity="0.9"/><path d="M10 68 L70 68" stroke="#94a3b8" stroke-width="1.5"/><path d="M19 50 L33 38 L47 28 L61 20" stroke="#0c6e5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4 3"/></svg>
                        <h6>No Booking Data</h6>
                        <p>Monthly booking trends will appear once tours are booked.</p>
                        <button onclick="resetFilters()" class="empty-cta">Try wider date range <i class="fas fa-arrow-right"></i></button>
                    </div>
                <?php else: ?>
                    <div style="height:280px;"><canvas id="bookingTrendChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2" style="color:#10b981;"></i>User Registrations</h6>
                <span class="badge" style="background:rgba(16,185,129,0.1);color:#10b981;font-weight:600;">Last 6 Months</span>
            </div>
            <div class="card-body">
                <?php if (empty($usersByMonth)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="28" cy="30" r="8" stroke="#94a3b8" stroke-width="2"/><path d="M14 60 C14 48 22 40 28 40 C34 40 42 48 42 60" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/><circle cx="52" cy="28" r="6" stroke="#10b981" stroke-width="2"/><path d="M42 58 C42 48 46 42 52 42 C58 42 62 48 62 58" stroke="#10b981" stroke-width="2" stroke-linecap="round"/><path d="M36 24 L44 24" stroke="#10b981" stroke-width="2" stroke-linecap="round"/><path d="M40 20 L40 28" stroke="#10b981" stroke-width="2" stroke-linecap="round"/></svg>
                        <h6>No User Data</h6>
                        <p>Registrations will appear once users sign up.</p>
                        <button onclick="resetFilters()" class="empty-cta">Try wider date range <i class="fas fa-arrow-right"></i></button>
                    </div>
                <?php else: ?>
                    <div style="height:280px;"><canvas id="userRegChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 3 -->
<div class="dash-section-title">Breakdown</div>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-credit-card me-2" style="color:#3b82f6;"></i>Payment Methods</h6>
            </div>
            <div class="card-body">
                <?php if (empty($paymentByMethod)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="18" width="60" height="44" rx="8" stroke="#94a3b8" stroke-width="2"/><rect x="10" y="28" width="60" height="10" fill="#3b82f6" opacity="0.15"/><line x1="18" y1="48" x2="38" y2="48" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/><line x1="18" y1="54" x2="30" y2="54" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" opacity="0.5"/><circle cx="58" cy="50" r="8" stroke="#3b82f6" stroke-width="2" stroke-dasharray="3 2"/><path d="M56 48 L58 50 L62 46" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <h6>No Payment Data</h6>
                        <p>Payment methods will show once transactions are processed.</p>
                        <a href="<?= BASE_URL ?>/admin/payments.php" class="empty-cta">View Payments <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <div style="height:280px;"><canvas id="paymentMethodChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-days me-2" style="color:#f59e0b;"></i>Top Events</h6>
            </div>
            <div class="card-body">
                <?php if (empty($topEvents)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="16" width="56" height="48" rx="8" stroke="#94a3b8" stroke-width="2"/><path d="M12 30 H68" stroke="#94a3b8" stroke-width="2"/><circle cx="26" cy="46" r="5" fill="#f59e0b" opacity="0.3"/><circle cx="40" cy="46" r="5" fill="#f59e0b" opacity="0.5"/><circle cx="54" cy="46" r="5" fill="#f59e0b" opacity="0.3"/><line x1="20" y1="22" x2="28" y2="22" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/><line x1="32" y1="22" x2="44" y2="22" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" opacity="0.5"/></svg>
                        <h6>No Event Data</h6>
                        <p>Event analytics will appear once bookings are made.</p>
                        <a href="<?= BASE_URL ?>/admin/events.php" class="empty-cta">Manage Events <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <div style="height:280px;"><canvas id="topEventsChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Data Tables -->
<div class="dash-section-title">Rankings & Insights</div>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-map-marker-alt me-2" style="color:#0c6e5e;"></i>Popular Destinations</h6>
            </div>
            <div class="card-body">
                <?php if (empty($popularDestinations)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M40 10 C40 10 20 30 20 48 C20 59 29 68 40 68 C51 68 60 59 60 48 C60 30 40 10 40 10Z" stroke="#94a3b8" stroke-width="2" fill="#0c6e5e" opacity="0.08"/><circle cx="40" cy="46" r="10" stroke="#0c6e5e" stroke-width="2"/><circle cx="40" cy="46" r="4" fill="#0c6e5e"/><path d="M40 56 L40 64" stroke="#0c6e5e" stroke-width="2" stroke-linecap="round"/></svg>
                        <h6>No Destination Data</h6>
                        <p>Destination rankings will appear as bookings come in.</p>
                        <a href="<?= BASE_URL ?>/admin/destinations.php" class="empty-cta">Manage Destinations <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <?php foreach ($popularDestinations as $i => $pd):
                        $barWidth = $popularDestinations[0]['booking_count'] > 0 ? ($pd['booking_count'] / $popularDestinations[0]['booking_count']) * 100 : 0;
                        $colors = [['#3b82f6','#dbeafe'],['#10b981','#d1fae5'],['#f59e0b','#fef3c7'],['#ef4444','#fee2e2'],['#8b5cf6','#ede9fe'],['#06b6d4','#cffafe'],['#ec4899','#fce7f3'],['#64748b','#f1f5f9']];
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold small"><?= $i+1 ?>. <?= sanitize($pd['name']) ?></span>
                                <span class="fw-bold small" style="color:<?= $colors[$i][0] ?>;"><?= $pd['booking_count'] ?></span>
                            </div>
                            <div class="progress" style="height:6px;border-radius:6px;">
                                <div class="progress-bar" style="width:<?= $barWidth ?>%;background:<?= $colors[$i][0] ?>;border-radius:6px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2" style="color:#8b5cf6;"></i>Booking Status</h6>
            </div>
            <div class="card-body">
                <?php if (empty($bookingByStatus)): ?>
                    <div class="empty-state">
                        <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="40" r="28" stroke="#e2e8f0" stroke-width="6"/><path d="M40 12 A28 28 0 0 1 68 40" stroke="#10b981" stroke-width="6" stroke-linecap="round"/><path d="M68 40 A28 28 0 0 1 40 68" stroke="#3b82f6" stroke-width="6" stroke-linecap="round"/><path d="M40 68 A28 28 0 0 1 12 40" stroke="#f59e0b" stroke-width="6" stroke-linecap="round" opacity="0.4"/><circle cx="40" cy="40" r="14" fill="var(--card-bg, #fff)"/><path d="M35 40 L42 47 L55 33" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <h6>No Status Data</h6>
                        <p>Booking status breakdown will appear once bookings exist.</p>
                        <button onclick="resetFilters()" class="empty-cta">Try wider date range <i class="fas fa-arrow-right"></i></button>
                    </div>
                <?php else: ?>
                    <div style="height:260px;"><canvas id="bookingStatusChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Feedback -->
<div class="card chart-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fas fa-comments me-2" style="color:#0c6e5e;"></i>Recent Feedback</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentFeedback)): ?>
            <div class="empty-state">
                <svg class="empty-svg" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="14" width="60" height="44" rx="8" stroke="#94a3b8" stroke-width="2" fill="#0c6e5e" opacity="0.05"/><path d="M26 32 L54 32" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/><path d="M26 40 L46 40" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" opacity="0.5"/><path d="M26 48 L38 48" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" opacity="0.3"/><path d="M44 58 L54 48" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" opacity="0.4"/><circle cx="56" cy="36" r="10" fill="#0c6e5e" opacity="0.1" stroke="#0c6e5e" stroke-width="1.5"/><path d="M54 36 L55.5 38 L59 34" stroke="#0c6e5e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <h6>No Feedback Data</h6>
                <p>Reviews from tourists will show up here after completed tours.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr style="background:var(--border-color,#f8fafc);">
                            <th class="ps-3" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted,#64748b);">Tourist</th>
                            <th style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted,#64748b);">Event</th>
                            <th style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted,#64748b);">Rating</th>
                            <th style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted,#64748b);">Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentFeedback as $fb): ?>
                            <tr>
                                <td class="ps-3 fw-semibold small"><?= sanitize($fb['tourist_name']) ?></td>
                                <td class="small"><?= sanitize($fb['event_title'] ?? 'N/A') ?></td>
                                <td>
                                    <?php for ($s=1; $s<=5; $s++): ?>
                                        <i class="fas fa-star" style="font-size:.65rem;color:<?= $s <= round($fb['overall_rating']) ? '#f59e0b' : '#e2e8f0' ?>"></i>
                                    <?php endfor; ?>
                                </td>
                                <td class="small text-muted" style="max-width:300px;"><?= sanitize(truncate($fb['comment'] ?? '', 80)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden data for exports -->
<script>
const exportData = {
    bookings: <?= json_encode($allBookings) ?>,
    payments: <?= json_encode($allPayments) ?>,
    summary: {
        totalBookings: <?= $totalBookings ?>,
        totalRevenue: <?= $totalRevenuePaid ?>,
        monthlyRevenue: <?= $monthlyRevenue ?>,
        activeUsers: <?= $totalUsers ?>,
        avgRating: <?= number_format($avgRating, 2) ?>,
        activeTours: <?= $activeTours ?>,
        completedTours: <?= $completedTours ?>,
        publishedEvents: <?= $totalEvents ?>,
    }
};
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const teal = '#0c6e5e';
    const blue = '#3b82f6';
    const green = '#10b981';
    const amber = '#f59e0b';
    const red = '#ef4444';
    const purple = '#8b5cf6';

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = "'Inter', sans-serif";

    const revData = <?= json_encode($revenueByMonth) ?>;
    if (revData.length > 0) {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 280);
        grad.addColorStop(0, 'rgba(12,110,94,0.25)');
        grad.addColorStop(1, 'rgba(12,110,94,0.01)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: revData.map(r => { const [y,m] = r.month.split('-'); return new Date(y,m-1).toLocaleDateString('en',{month:'short'}); }),
                datasets: [{ label:'Revenue', data: revData.map(r => parseFloat(r.revenue)), borderColor: teal, backgroundColor: grad, fill:true, tension:0.4, pointRadius:5, pointHoverRadius:7, pointBackgroundColor:'#fff', pointBorderColor:teal, pointBorderWidth:2, borderWidth:2.5 }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:{backgroundColor:isDark?'#1e293b':'#fff',titleColor:isDark?'#f1f5f9':'#1e293b',bodyColor:isDark?'#cbd5e1':'#475569',borderColor:isDark?'#334155':'#e2e8f0',borderWidth:1,cornerRadius:10,padding:12,displayColors:false,callbacks:{label:c=>'₱'+c.parsed.y.toLocaleString()}} }, scales:{ y:{beginAtZero:true,grid:{color:gridColor},ticks:{callback:v=>'₱'+v.toLocaleString(),font:{size:11}}}, x:{grid:{display:false},ticks:{font:{size:11}}} } }
        });
    }

    const bsData = <?= json_encode($bookingByStatus) ?>;
    if (Object.keys(bsData).length > 0) {
        const bsColors = Object.keys(bsData).map(s => ({confirmed:green,pending:amber,completed:blue,cancelled:red,in_progress:teal}[s]||'#94a3b8'));
        new Chart(document.getElementById('bookingStatusChart'), {
            type:'doughnut',
            data:{ labels:Object.keys(bsData).map(s=>s.charAt(0).toUpperCase()+s.slice(1)), datasets:[{data:Object.values(bsData),backgroundColor:bsColors,borderWidth:0,hoverOffset:6}] },
            options:{ responsive:true, maintainAspectRatio:false, cutout:'68%', plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:16,font:{size:12}}}} }
        });
    }

    const btData = <?= json_encode($bookingsByMonth) ?>;
    if (btData.length > 0) {
        const btCtx = document.getElementById('bookingTrendChart').getContext('2d');
        const btGrad = btCtx.createLinearGradient(0, 0, 0, 280);
        btGrad.addColorStop(0, 'rgba(12,110,94,0.8)');
        btGrad.addColorStop(1, 'rgba(20,184,166,0.4)');
        new Chart(btCtx, {
            type:'bar',
            data:{ labels:btData.map(b=>{const[y,m]=b.month.split('-');return new Date(y,m-1).toLocaleDateString('en',{month:'short'});}), datasets:[{label:'Bookings',data:btData.map(b=>parseInt(b.count)),backgroundColor:btGrad,borderRadius:8,borderSkipped:false,barThickness:32}] },
            options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:gridColor},ticks:{stepSize:1,font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}} }
        });
    }

    const urData = <?= json_encode($usersByMonth) ?>;
    if (urData.length > 0) {
        const urCtx = document.getElementById('userRegChart').getContext('2d');
        const urGrad = urCtx.createLinearGradient(0, 0, 0, 280);
        urGrad.addColorStop(0, 'rgba(16,185,129,0.8)');
        urGrad.addColorStop(1, 'rgba(16,185,129,0.3)');
        new Chart(urCtx, {
            type:'bar',
            data:{ labels:urData.map(u=>{const[y,m]=u.month.split('-');return new Date(y,m-1).toLocaleDateString('en',{month:'short'});}), datasets:[{label:'Users',data:urData.map(u=>parseInt(u.count)),backgroundColor:urGrad,borderRadius:8,borderSkipped:false,barThickness:32}] },
            options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:gridColor},ticks:{stepSize:1,font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}} }
        });
    }

    const pmData = <?= json_encode($paymentByMethod) ?>;
    if (pmData.length > 0) {
        const pmLabels = pmData.map(p => p.payment_method==='gcash'?'GCash':(p.payment_method==='maya'?'Maya':(p.payment_method==='card'?'Card':p.payment_method)));
        const pmColors = pmData.map(p => { if(p.payment_method==='gcash') return '#007dfe'; if(p.payment_method==='maya') return '#006400'; if(p.payment_method==='card') return purple; return '#b8860b'; });
        new Chart(document.getElementById('paymentMethodChart'), {
            type:'doughnut',
            data:{ labels:pmLabels, datasets:[{data:pmData.map(p=>parseInt(p.cnt)),backgroundColor:pmColors,borderWidth:0,hoverOffset:6}] },
            options:{ responsive:true, maintainAspectRatio:false, cutout:'68%', plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:16,font:{size:12}}}} }
        });
    }

    const teData = <?= json_encode($topEvents) ?>;
    if (teData.length > 0) {
        new Chart(document.getElementById('topEventsChart'), {
            type:'bar',
            data:{ labels:teData.map(e=>e.title.length>20?e.title.substring(0,20)+'...':e.title), datasets:[{label:'Bookings',data:teData.map(e=>e.booking_count),backgroundColor:['#0c6e5e','#3b82f6','#10b981','#f59e0b','#8b5cf6'],borderRadius:8,barThickness:24}] },
            options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,grid:{color:gridColor},ticks:{stepSize:1,font:{size:11}}},y:{grid:{display:false},ticks:{font:{size:11}}}} }
        });
    }
});

function selectPeriod(btn) {
    document.querySelectorAll('#periodPills .pill-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    var period = btn.dataset.period;
    var customDates = document.getElementById('customDates');
    var from = document.getElementById('dateFrom');
    var to = document.getElementById('dateTo');
    var badge = document.getElementById('activeRangeText');
    var now = new Date();
    var rangeLabels = {
        'today': 'Today', '7d': 'Last 7 Days', 'month': 'This Month',
        'quarter': 'This Quarter', 'year': 'Year to Date', 'custom': 'Custom Range'
    };

    badge.textContent = rangeLabels[period] || 'Custom';

    if (period === 'custom') {
        customDates.style.display = 'flex';
        from.value = '';
        to.value = '';
        return;
    }
    customDates.style.display = 'none';

    to.value = now.toISOString().slice(0,10);
    if (period === 'today') {
        from.value = now.toISOString().slice(0,10);
    } else if (period === '7d') {
        var d = new Date(now);
        d.setDate(d.getDate() - 7);
        from.value = d.toISOString().slice(0,10);
    } else if (period === 'month') {
        from.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0,10);
    } else if (period === 'quarter') {
        var q = Math.floor(now.getMonth() / 3);
        from.value = new Date(now.getFullYear(), q * 3, 1).toISOString().slice(0,10);
    } else if (period === 'year') {
        from.value = new Date(now.getFullYear(), 0, 1).toISOString().slice(0,10);
    }
}

function resetFilters() {
    document.querySelectorAll('#periodPills .pill-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('#periodPills .pill-btn[data-period="month"]').classList.add('active');
    document.getElementById('customDates').style.display = 'none';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('activeRangeText').textContent = 'This Month';
}

function exportPDF(btn) {
    btn.classList.add('loading');
    var origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner"></i> Generating...';
    setTimeout(function() {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const pageWidth = doc.internal.pageSize.getWidth();

            doc.setFontSize(18);
            doc.setTextColor(12, 110, 94);
            doc.text('BINALGO Tourism - Reports & Analytics', pageWidth / 2, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text('Generated: ' + new Date().toLocaleDateString('en', {year:'numeric',month:'long',day:'numeric'}), pageWidth / 2, 28, { align: 'center' });

            let y = 38;
            doc.setFontSize(12);
            doc.setTextColor(30, 41, 59);
            doc.text('Key Metrics', 14, y);
            y += 8;

            doc.setFontSize(10);
            const metrics = [
                ['Total Bookings', exportData.summary.totalBookings],
                ['Total Revenue', 'P' + exportData.summary.totalRevenue.toLocaleString()],
                ['Monthly Revenue', 'P' + exportData.summary.monthlyRevenue.toLocaleString()],
                ['Active Users', exportData.summary.activeUsers],
                ['Avg Rating', exportData.summary.avgRating],
                ['Active Tours', exportData.summary.activeTours],
                ['Completed Tours', exportData.summary.completedTours],
                ['Published Events', exportData.summary.publishedEvents],
            ];
            metrics.forEach(function(pair) {
                doc.setTextColor(100);
                doc.text(pair[0] + ':', 14, y);
                doc.setTextColor(30, 41, 59);
                doc.text(String(pair[1]), 80, y);
                y += 7;
            });

            y += 5;
            doc.setFontSize(12);
            doc.setTextColor(30, 41, 59);
            doc.text('Bookings List', 14, y);
            y += 3;

            if (exportData.bookings.length > 0) {
                doc.autoTable({
                    startY: y,
                    head: [['Tourist', 'Event', 'Destination', 'Date', 'Participants', 'Total', 'Status']],
                    body: exportData.bookings.map(function(b) {
                        return [b.tourist_name || 'N/A', (b.event_title || '').substring(0, 25), b.destination_name || '', b.start_date || '', b.num_participants, 'P' + Number(b.total_price).toLocaleString(), b.status];
                    }),
                    theme: 'grid',
                    headStyles: { fillColor: [12, 110, 94], fontSize: 8 },
                    bodyStyles: { fontSize: 7 },
                    margin: { left: 14, right: 14 },
                });
                y = doc.lastAutoTable.finalY + 10;
            }

            if (y > 240) { doc.addPage(); y = 20; }
            doc.setFontSize(12);
            doc.setTextColor(30, 41, 59);
            doc.text('Payments List', 14, y);
            y += 3;

            if (exportData.payments.length > 0) {
                doc.autoTable({
                    startY: y,
                    head: [['Tourist', 'Amount', 'Method', 'Status', 'Date']],
                    body: exportData.payments.map(function(p) {
                        return [p.tourist_name || 'N/A', 'P' + Number(p.total_amount).toLocaleString(), p.payment_method || '', p.payment_status || '', p.payment_date || p.created_at || ''];
                    }),
                    theme: 'grid',
                    headStyles: { fillColor: [12, 110, 94], fontSize: 8 },
                    bodyStyles: { fontSize: 7 },
                    margin: { left: 14, right: 14 },
                });
            }

            doc.save('BINALGO_Reports_' + new Date().toISOString().slice(0,10) + '.pdf');
            showReportToast('PDF exported successfully!', 'success');
        } catch(e) { console.error(e); showReportToast('Export failed. Please try again.', 'error'); }
        btn.innerHTML = origHTML;
        btn.classList.remove('loading');
    }, 600);
}

function exportExcel(btn) {
    btn.classList.add('loading');
    var origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner"></i> Generating...';
    setTimeout(function() {
        try {
            var wb = XLSX.utils.book_new();
            var summaryRows = [
                ['BINALGO Tourism - Reports & Analytics'],
                ['Generated', new Date().toLocaleDateString()],
                [],
                ['Metric', 'Value'],
                ['Total Bookings', exportData.summary.totalBookings],
                ['Total Revenue', exportData.summary.totalRevenue],
                ['Monthly Revenue', exportData.summary.monthlyRevenue],
                ['Active Users', exportData.summary.activeUsers],
                ['Avg Rating', exportData.summary.avgRating],
                ['Active Tours', exportData.summary.activeTours],
                ['Completed Tours', exportData.summary.completedTours],
                ['Published Events', exportData.summary.publishedEvents],
            ];
            var wsSummary = XLSX.utils.aoa_to_sheet(summaryRows);
            wsSummary['!cols'] = [{ wch: 20 }, { wch: 15 }];
            XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary');

            if (exportData.bookings.length > 0) {
                var bHeaders = ['ID', 'Reference', 'Tourist', 'Event', 'Destination', 'Date', 'Participants', 'Total Price', 'Status', 'Created At'];
                var bRows = exportData.bookings.map(function(b) {
                    return [b.id, b.booking_reference || '', b.tourist_name || '', b.event_title || '', b.destination_name || '', b.start_date || '', b.num_participants, b.total_price, b.status, b.created_at || ''];
                });
                var wsBookings = XLSX.utils.aoa_to_sheet([bHeaders].concat(bRows));
                wsBookings['!cols'] = bHeaders.map(function() { return { wch: 15 }; });
                XLSX.utils.book_append_sheet(wb, wsBookings, 'Bookings');
            }

            if (exportData.payments.length > 0) {
                var pHeaders = ['ID', 'Tourist', 'Amount', 'Tax', 'Service Fee', 'Total', 'Method', 'Status', 'Reference', 'Date'];
                var pRows = exportData.payments.map(function(p) {
                    return [p.id, p.tourist_name || '', p.amount, p.tax, p.service_fee, p.total_amount, p.payment_method || '', p.payment_status || '', p.reference_number || '', p.payment_date || p.created_at || ''];
                });
                var wsPayments = XLSX.utils.aoa_to_sheet([pHeaders].concat(pRows));
                wsPayments['!cols'] = pHeaders.map(function() { return { wch: 15 }; });
                XLSX.utils.book_append_sheet(wb, wsPayments, 'Payments');
            }

            XLSX.writeFile(wb, 'BINALGO_Reports_' + new Date().toISOString().slice(0,10) + '.xlsx');
            showReportToast('Excel exported successfully!', 'success');
        } catch(e) { console.error(e); showReportToast('Export failed. Please try again.', 'error'); }
        btn.innerHTML = origHTML;
        btn.classList.remove('loading');
    }, 600);
}

function showReportToast(msg, type) {
    var el = document.createElement('div');
    el.className = 'report-toast ' + (type === 'error' ? 'error' : 'success');
    el.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i>' + msg;
    document.body.appendChild(el);
    setTimeout(function() { el.style.opacity = '0'; el.style.transform = 'translateX(100%)'; el.style.transition = 'all 0.3s'; setTimeout(function() { el.remove(); }, 300); }, 3000);
}
</script>

<?php }); ?>
