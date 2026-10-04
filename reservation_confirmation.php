<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/classes/Reservation.php';
require_role('tourist');

$reservationModel = new Reservation();
$reservationId = (int)($_GET['id'] ?? 0);

if (!$reservationId) redirect('/tourist/destinations.php');

$reservation = $reservationModel->findById($reservationId);
$ownerId = $reservation['user_id'] ?? $reservation['tourist_id'] ?? 0;
if (!$reservation || (int)$ownerId !== (int)$_SESSION['user_id']) {
    flash_message('error', 'Reservation not found.');
    redirect('/tourist/destinations.php');
}

$statusColors = [
    'pending'   => ['#fef3c7', '#92400e'],
    'approved'  => ['#d1fae5', '#065f46'],
    'declined'  => ['#fee2e2', '#991b1b'],
    'completed' => ['#dbeafe', '#1e40af'],
    'cancelled' => ['#f3f4f6', '#6b7280'],
];
$status = $reservation['reservation_status'] ?? $reservation['status'] ?? 'pending';
$colors = $statusColors[$status] ?? ['#f1f5f9', '#475569'];

$ref = $reservation['reservation_reference'] ?? $reservation['booking_reference'] ?? '—';
$rdate = $reservation['reservation_date'] ?? $reservation['visit_date'] ?? '';
$rtime = $reservation['reservation_time'] ?? $reservation['visit_time'] ?? '';
$visitors = $reservation['visitors'] ?? $reservation['num_participants'] ?? 1;
$facility = $reservation['facility_name'] ?? '';
$notes = $reservation['notes'] ?? $reservation['special_requests'] ?? '';

render_page('user', 'reservation_confirmation', 'Reservation Confirmed', function() use ($reservation, $colors, $status, $ref, $rdate, $rtime, $visitors, $facility, $notes) {
?>

<style>
.rc-page { max-width:600px; margin:0 auto; }
.rc-success { text-align:center; padding:32px 24px; background:linear-gradient(135deg, #008075, #00665c); color:white; border-radius:16px 16px 0 0; }
.rc-success .rc-icon { width:64px; height:64px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:1.8rem; }
.rc-success h2 { margin:0; font-size:1.3rem; font-weight:700; }
.rc-success p { margin:6px 0 0; font-size:.85rem; opacity:.85; }
.rc-details { background:white; border-radius:0 0 16px 16px; box-shadow:0 4px 16px rgba(0,0,0,.08); padding:28px; }
.rc-row { display:flex; justify-content:space-between; align-items:flex-start; padding:12px 0; border-bottom:1px solid #f1f5f9; }
.rc-row:last-child { border-bottom:none; }
.rc-row .rc-label { font-size:.82rem; color:#64748b; font-weight:500; min-width:140px; }
.rc-row .rc-value { font-size:.88rem; font-weight:600; color:#1e293b; text-align:right; max-width:60%; }
.rc-row .rc-ref { font-size:1rem; font-weight:800; color:#008075; letter-spacing:.5px; }
.rc-status { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:8px; font-size:.78rem; font-weight:700; text-transform:capitalize; }
.rc-note { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; font-size:.85rem; color:#475569; margin-top:4px; }
.rc-actions { margin-top:24px; display:flex; gap:12px; }
.rc-btn { flex:1; padding:14px; border-radius:12px; font-weight:700; font-size:.9rem; text-align:center; text-decoration:none; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:8px; }
.rc-btn-primary { background:linear-gradient(135deg,#008075,#00665c); color:white; border:none; cursor:pointer; }
.rc-btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(0,128,117,.3); color:white; }
.rc-btn-outline { background:white; border:1.5px solid #e2e8f0; color:#475569; }
.rc-btn-outline:hover { background:#f8fafc; border-color:#cbd5e1; color:#475569; }
</style>

<div class="rc-page">
    <div class="rc-success">
        <div class="rc-icon"><i class="fas fa-calendar-check"></i></div>
        <h2>Reservation Submitted!</h2>
        <p>Reserve a Slot — no payment required. Awaiting admin approval.</p>
    </div>

    <div class="rc-details">
        <div class="rc-row">
            <span class="rc-label">Reservation Reference</span>
            <span class="rc-value rc-ref"><?= $ref ?></span>
        </div>
        <div class="rc-row">
            <span class="rc-label">Destination / Facility</span>
            <span class="rc-value"><?= sanitize($reservation['dest_name'] ?? 'N/A') ?><?= $facility ? ' — ' . sanitize($facility) : '' ?></span>
        </div>
        <div class="rc-row">
            <span class="rc-label">Reservation Date</span>
            <span class="rc-value"><?= $rdate ? date('F j, Y', strtotime($rdate)) : '—' ?></span>
        </div>
        <div class="rc-row">
            <span class="rc-label">Time Slot</span>
            <span class="rc-value"><?= $rtime ? date('h:i A', strtotime($rtime)) : '—' ?></span>
        </div>
        <div class="rc-row">
            <span class="rc-label">Number of Visitors</span>
            <span class="rc-value"><?= $visitors ?></span>
        </div>
        <?php if ($notes): ?>
        <div class="rc-row" style="flex-direction:column; align-items:stretch;">
            <span class="rc-label" style="margin-bottom:6px;">Special Request / Notes</span>
            <div class="rc-note"><?= nl2br(sanitize($notes)) ?></div>
        </div>
        <?php endif; ?>
        <div class="rc-row">
            <span class="rc-label">Reservation Status</span>
            <span class="rc-status" style="background:<?= $colors[0] ?>;color:<?= $colors[1] ?>;">
                <?= ucfirst($status) ?>
            </span>
        </div>

        <div class="rc-actions">
            <a href="<?= BASE_URL ?>/tourist/my_reservations.php" class="rc-btn rc-btn-primary">
                <i class="fas fa-list"></i> View My Reservations
            </a>
            <a href="<?= BASE_URL ?>/tourist/bookings.php" class="rc-btn rc-btn-outline">
                <i class="fas fa-ticket"></i> My Bookings
            </a>
        </div>
    </div>
</div>

<?php }); ?>
