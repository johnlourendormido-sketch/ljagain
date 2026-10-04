<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/classes/Reservation.php';
require_role('tourist');

$db = Database::getInstance()->getConnection();
$destModel = new Destination();
$reservationModel = new Reservation();
$user = current_user();
$destId = (int)($_GET['id'] ?? 0);

if (!$destId) redirect('/tourist/destinations.php');

$dest = $destModel->findById($destId);
if (!$dest || $dest['status'] !== 'active') {
    flash_message('error', 'Destination not available.');
    redirect('/tourist/destinations.php');
}

$minDate = date('Y-m-d', strtotime('+' . max(1, (int)$dest['advance_booking_days']) . ' day'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['csrf_token'] ?? null)) {
        flash_message('error', 'Invalid security token.');
        redirect('/tourist/book_now.php?id=' . $destId);
    }

    try {
        $reservationDate = $_POST['reservation_date'] ?? $_POST['visit_date'] ?? '';
        $reservationTime = $_POST['reservation_time'] ?? $_POST['visit_time'] ?? '';
        $visitors = max(1, (int)($_POST['visitors'] ?? $_POST['num_guests'] ?? 1));
        $facility = sanitize(trim($_POST['facility_name'] ?? ''));
        $notes = sanitize(trim($_POST['notes'] ?? $_POST['special_requests'] ?? ''));

        if (!$reservationDate || !$reservationTime) {
            flash_message('error', 'Please select reservation date and time slot.');
            redirect('/tourist/book_now.php?id=' . $destId);
        }

        if (!strtotime($reservationDate) || $reservationDate < date('Y-m-d')) {
            flash_message('error', 'Please select a valid future date.');
            redirect('/tourist/book_now.php?id=' . $destId);
        }

        if ($reservationDate < $minDate) {
            flash_message('error', 'Reservations must be made at least ' . $dest['advance_booking_days'] . ' day(s) in advance.');
            redirect('/tourist/book_now.php?id=' . $destId);
        }

        if ($visitors > $dest['max_guests_per_booking']) {
            flash_message('error', 'Maximum ' . $dest['max_guests_per_booking'] . ' visitors per reservation.');
            redirect('/tourist/book_now.php?id=' . $destId);
        }

        $availability = $reservationModel->checkAvailability($destId, $reservationDate, $visitors);
        if (!$availability['can_book']) {
            flash_message('error', 'Not enough capacity. Only ' . $availability['available'] . ' slots left for this date.');
            redirect('/tourist/book_now.php?id=' . $destId);
        }

        $reservationId = $reservationModel->create([
            'user_id'          => $_SESSION['user_id'],
            'destination_id'   => $destId,
            'facility_name'    => $facility ?: null,
            'reservation_date' => $reservationDate,
            'reservation_time' => $reservationTime,
            'visitors'         => $visitors,
            'notes'            => $notes,
        ]);

        ActivityLog::log($_SESSION['user_id'], 'reservation_created', "Created reservation for {$dest['name']} on {$reservationDate}");

        redirect('/tourist/reservation_confirmation.php?id=' . $reservationId);

    } catch (Exception $e) {
        flash_message('error', 'Reservation failed. Please try again.');
        redirect('/tourist/book_now.php?id=' . $destId);
    }
}

render_page('user', 'book_now.php', 'Reserve ' . $dest['name'], function() use ($dest, $destId, $user, $minDate) {
?>

<style>
.rs-page { max-width: 680px; margin: 0 auto; }
.rs-back { display:inline-flex; align-items:center; gap:8px; color:#00665c; background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:6px 14px 6px 10px; text-decoration:none; font-size:.82rem; font-weight:600; margin-bottom:18px; box-shadow:0 1px 2px rgba(0,0,0,.04); transition:all .2s; }
.rs-back:hover { color:#008075; border-color:#008075; background:#f0fdfa; }
.rs-back .back-icon { width:22px; height:22px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; }
.rs-header { background:linear-gradient(135deg, #008075 0%, #00665c 100%); color:white; border-radius:20px; padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:18px; box-shadow:0 6px 20px rgba(0,102,92,.18); position:relative; overflow:hidden; }
.rs-header::before { content:''; position:absolute; top:-40%; right:-12%; width:280px; height:280px; border-radius:50%; background:radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 70%); pointer-events:none; }
.rs-header-img { width:76px; height:76px; border-radius:16px; object-fit:cover; flex-shrink:0; border:3px solid rgba(255,255,255,.9); box-shadow:0 4px 12px rgba(0,0,0,.12); position:relative; z-index:1; }
.rs-header-info { position:relative; z-index:1; }
.rs-header-info h3 { margin:0; font-size:1.15rem; font-weight:800; letter-spacing:-.01em; }
.rs-header-info p { margin:4px 0 0; font-size:.80rem; opacity:.88; display:flex; align-items:center; gap:5px; }
.rs-header-badge { display:inline-flex; align-items:center; gap:4px; margin-top:8px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.22); color:#fff; border-radius:20px; padding:3px 10px; font-size:.70rem; font-weight:600; }
.rs-card { background:#fff; border-radius:16px; border:none; box-shadow:0 1px 3px rgba(0,0,0,.04), 0 4px 12px rgba(0,0,0,.05); overflow:hidden; margin-bottom:18px; }
.rs-card-body { padding:24px 26px; }
.rs-card-title { font-size:.88rem; font-weight:700; color:#1e293b; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
.rs-card-title .title-icon { width:28px; height:28px; border-radius:8px; background:rgba(0,128,117,.08); color:#008075; display:inline-flex; align-items:center; justify-content:center; font-size:.78rem; }
.rs-step { font-size:.70rem; font-weight:700; color:#008075; background:rgba(0,128,117,.08); border:1px solid rgba(0,128,117,.14); border-radius:20px; padding:2px 8px; margin-left:auto; }
.rs-field { margin-bottom:16px; }
.rs-label { display:block; font-size:.70rem; font-weight:700; color:#64748b; margin-bottom:6px; text-transform:uppercase; letter-spacing:.06em; }
.rs-label span { color:#ef4444; margin-left:2px; }
.rs-input { width:100%; border:1.5px solid #e2e8f0; border-radius:12px; padding:11px 14px; font-size:.86rem; color:#1e293b; transition:all .2s; background:#fff; }
.rs-input:focus { border-color:#008075; outline:none; box-shadow:0 0 0 3px rgba(0,128,117,.09); }
.rs-input::placeholder { color:#94a3b8; }
.rs-guest { display:flex; align-items:center; border:1.5px solid #e2e8f0; border-radius:12px; overflow:hidden; background:#fff; }
.rs-guest button { width:42px; height:46px; border:none; background:#f8fafc; color:#334155; font-size:.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.rs-guest button:hover { background:rgba(0,128,117,.08); color:#008075; }
.rs-guest button:disabled { opacity:.35; cursor:not-allowed; }
.rs-guest .rs-count { flex:1; text-align:center; font-weight:800; font-size:1.05rem; color:#1e293b; border-left:1.5px solid #e2e8f0; border-right:1.5px solid #e2e8f0; padding:10px 0; background:#fff; }
.rs-info { background:#f0fdfa; border:1px solid rgba(0,128,117,.15); border-radius:12px; padding:12px 14px; font-size:.82rem; color:#065f46; display:flex; gap:10px; align-items:flex-start; }
.rs-btn-primary { background:linear-gradient(135deg,#008075,#00665c); color:white; border:none; border-radius:12px; padding:13px 24px; font-weight:700; font-size:.90rem; cursor:pointer; transition:all .2s; width:100%; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 14px rgba(0,102,92,.22); }
.rs-btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 22px rgba(0,102,92,.30); }
.rs-btn-outline { background:#fff; border:1.5px solid #e2e8f0; color:#475569; border-radius:12px; padding:12px 20px; font-weight:600; font-size:.86rem; cursor:pointer; }
@media (max-width:576px) { .rs-header { flex-direction:column; text-align:center; } .rs-card-body { padding:20px; } }
</style>

<div class="rs-page">
    <a href="destination_detail.php?id=<?= $destId ?>" class="rs-back">
        <span class="back-icon"><i class="fas fa-arrow-left"></i></span> Reserve Destination
    </a>

    <div class="rs-header">
        <?php if (!empty($dest['image'])): ?>
            <img src="<?= dest_image_url($dest['image']) ?>" class="rs-header-img" alt="<?= sanitize($dest['name']) ?>">
        <?php else: ?>
            <div class="rs-header-img" style="background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;"><i class="fas fa-map-marked-alt" style="font-size:1.5rem;"></i></div>
        <?php endif; ?>
        <div class="rs-header-info">
            <h3><?= sanitize($dest['name']) ?></h3>
            <p><i class="fas fa-location-dot"></i><?= sanitize($dest['location']) ?></p>
            <span class="rs-header-badge"><i class="fas fa-calendar-check"></i> Reserve a Slot, Schedule, or Facility</span>
        </div>
    </div>

    <div class="rs-info" style="margin-bottom:18px;">
        <i class="fas fa-info-circle" style="margin-top:2px;"></i>
        <div><strong>Reservation</strong> = reserve a slot/place/facility <strong>without immediate payment</strong>. For paid tourism services, use <a href="<?= BASE_URL ?>/tourist/browse.php" style="color:#008075;font-weight:700;">Book an Activity</a> instead.</div>
    </div>

    <form method="POST" id="reservationForm">
        <?= csrf_field() ?>

        <!-- Reservation Details — No payment -->
        <div class="rs-card">
            <div class="rs-card-body">
                <div class="rs-card-title"><span class="title-icon"><i class="fas fa-clipboard-list"></i></span> Reservation Details <span class="rs-step">Reserve a Slot</span></div>

                <div class="rs-field">
                    <label class="rs-label">Facility / Place (optional)</label>
                    <input type="text" name="facility_name" class="rs-input" placeholder="e.g., Cottages, Function Hall, Beach Front (leave blank for general slot)">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="rs-field">
                            <label class="rs-label">Number of Visitors <span>*</span></label>
                            <div class="rs-guest">
                                <button type="button" id="guestMinus" onclick="adjustGuests(-1)"><i class="fas fa-minus"></i></button>
                                <div class="rs-count" id="guestDisplay">1</div>
                                <button type="button" id="guestPlus" onclick="adjustGuests(1)"><i class="fas fa-plus"></i></button>
                                <input type="hidden" name="visitors" id="visitors" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="rs-field">
                            <label class="rs-label">Reservation Date <span>*</span></label>
                            <input type="date" name="reservation_date" class="rs-input" id="reservation_date" min="<?= $minDate ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="rs-field">
                            <label class="rs-label">Time Slot <span>*</span></label>
                            <input type="time" name="reservation_time" class="rs-input" id="reservation_time" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="rs-field">
                            <label class="rs-label">Reservation Notes / Special Requests</label>
                            <input type="text" name="notes" class="rs-input" id="notes" placeholder="Any special request?">
                        </div>
                    </div>
                </div>

                <div class="rs-field">
                    <label class="rs-label">Additional Notes</label>
                    <textarea name="notes2" class="rs-input" rows="3" style="resize:vertical;min-height:80px;" placeholder="Family outing, corporate event, etc."></textarea>
                </div>
                <div class="small text-muted" style="font-size:.78rem;"><i class="fas fa-lock me-1"></i> No payment required now. Admin will review and approve your reservation.</div>
            </div>
        </div>

        <div class="d-flex gap-3">
            <a href="destination_detail.php?id=<?= $destId ?>" class="rs-btn-outline" style="flex:0 0 auto;">Cancel</a>
            <button type="submit" class="rs-btn-primary" style="flex:1;">
                <i class="fas fa-calendar-check"></i> Submit Reservation
            </button>
        </div>
    </form>
</div>

<script>
const maxGuests = <?= $dest['max_guests_per_booking'] ?: 10 ?>;
function adjustGuests(delta) {
    const input = document.getElementById('visitors');
    const display = document.getElementById('guestDisplay');
    let val = parseInt(input.value) || 1;
    val = Math.max(1, Math.min(maxGuests, val + delta));
    input.value = val;
    display.textContent = val;
    document.getElementById('guestMinus').disabled = (val <= 1);
    document.getElementById('guestPlus').disabled = (val >= maxGuests);
}
document.getElementById('guestMinus').disabled = true;
document.getElementById('reservation_date').min = '<?= $minDate ?>';
<?php if ($dest['operating_hours_open']): ?>
document.getElementById('reservation_time').value = '<?= $dest['operating_hours_open'] ?>';
<?php endif; ?>
// merge notes2 into notes on submit
document.getElementById('reservationForm').addEventListener('submit', function(e){
    const n1 = document.getElementById('notes')?.value || '';
    const n2 = this.querySelector('textarea[name="notes2"]')?.value || '';
    const combined = [n1, n2].filter(Boolean).join(' | ');
    let hidden = this.querySelector('input[name="notes"]');
    if(hidden) hidden.value = combined;
    else {
        const h=document.createElement('input'); h.type='hidden'; h.name='notes'; h.value=combined;
        this.appendChild(h);
    }
});
</script>

<?php }); ?>
