<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/classes/Booking.php';
require_role('tourist');

$db = Database::getInstance()->getConnection();
$destModel = new Destination();
$bookingModel = new Booking();
$user = current_user();
$destId = (int)($_GET['id'] ?? 0);

if (!$destId) redirect('/tourist/destinations.php');

$dest = $destModel->findById($destId);
if (!$dest || $dest['status'] !== 'active' || !$dest['booking_enabled']) {
    flash_message('error', 'Booking is not available for this destination.');
    redirect('/tourist/destinations.php');
}

$feePrice = ($dest['package_price'] ?? 0) > 0 ? $dest['package_price'] : $dest['entrance_fee'];
$serviceFeeRate = 0.05;
$minDate = date('Y-m-d', strtotime('+' . max(1, (int)$dest['advance_booking_days']) . ' day'));

// Fetch activities (events) for this destination
$activities = $db->prepare("SELECT id, title, price FROM events WHERE destination_id = :did AND status = 'published' ORDER BY title ASC");
$activities->execute([':did' => $destId]);
$activityList = $activities->fetchAll();

// Fetch guides for this destination
$guides = $db->prepare("SELECT u.id, u.name FROM destination_guides dg JOIN users u ON dg.guide_id = u.id WHERE dg.destination_id = :did AND dg.status='active'");
$guides->execute([':did' => $destId]);
$guideList = $guides->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['csrf_token'] ?? null)) {
        flash_message('error', 'Invalid security token.');
        redirect('/tourist/book_service.php?id=' . $destId);
    }
    try {
        $activityId = (int)($_POST['activity_id'] ?? 0);
        $guideId = (int)($_POST['tour_guide_id'] ?? 0) ?: null;
        $visitDate = $_POST['visit_date'] ?? '';
        $visitTime = $_POST['visit_time'] ?? '';
        $participants = max(1, (int)($_POST['num_participants'] ?? 1));
        $paymentMethod = sanitize(trim($_POST['payment_method'] ?? ''));
        $specialRequests = sanitize(trim($_POST['special_requests'] ?? ''));

        if (!$visitDate || !$visitTime || !$paymentMethod) {
            flash_message('error', 'Please fill in all required fields.');
            redirect('/tourist/book_service.php?id=' . $destId);
        }
        if (!strtotime($visitDate) || $visitDate < date('Y-m-d')) {
            flash_message('error', 'Please select a valid future date.');
            redirect('/tourist/book_service.php?id=' . $destId);
        }
        if ($visitDate < $minDate) {
            flash_message('error', 'Bookings must be made at least ' . $dest['advance_booking_days'] . ' day(s) in advance.');
            redirect('/tourist/book_service.php?id=' . $destId);
        }
        if ($participants > $dest['max_guests_per_booking']) {
            flash_message('error', 'Maximum ' . $dest['max_guests_per_booking'] . ' participants per booking.');
            redirect('/tourist/book_service.php?id=' . $destId);
        }
        if ($dest['guide_required'] && !$guideId) {
            flash_message('error', 'Please select a tour guide.');
            redirect('/tourist/book_service.php?id=' . $destId);
        }

        // Check capacity via bookings (simple)
        $capStmt = $db->prepare("SELECT capacity_limit FROM destinations WHERE id = :id");
        $capStmt->execute([':id' => $destId]);
        $capacity = (int)($capStmt->fetchColumn() ?: 0);
        if ($capacity > 0) {
            $bookedStmt = $db->prepare("SELECT COALESCE(SUM(num_participants),0) FROM bookings WHERE destination_id=:did AND visit_date=:vd AND status NOT IN ('cancelled','rejected')");
            $bookedStmt->execute([':did'=>$destId, ':vd'=>$visitDate]);
            $booked = (int)$bookedStmt->fetchColumn();
            if ($booked + $participants > $capacity) {
                flash_message('error', 'Not enough capacity. Only ' . max(0,$capacity-$booked) . ' spots left for this date.');
                redirect('/tourist/book_service.php?id=' . $destId);
            }
        }

        $activityPrice = $feePrice;
        if ($activityId) {
            $ap = $db->prepare("SELECT price FROM events WHERE id=:id");
            $ap->execute([':id'=>$activityId]);
            $activityPrice = (float)($ap->fetchColumn() ?: $feePrice);
        }
        $subtotal = $activityPrice * $participants;
        $serviceFee = round($subtotal * $serviceFeeRate, 2);
        $totalPrice = $subtotal + $serviceFee;
        $ref = 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -5));

        $stmt = $db->prepare("INSERT INTO bookings (booking_reference, tourist_id, destination_id, activity_id, guide_id, visit_date, visit_time, num_participants, total_price, service_fee, payment_method, status, payment_status, special_requests, created_at) VALUES (:ref, :uid, :did, :aid, :gid, :vd, :vt, :np, :tp, :sf, :pm, 'pending', 'pending', :req, NOW())");
        $stmt->execute([
            ':ref'=>$ref, ':uid'=>$_SESSION['user_id'], ':did'=>$destId, ':aid'=>$activityId ?: null, ':gid'=>$guideId, ':vd'=>$visitDate, ':vt'=>$visitTime, ':np'=>$participants, ':tp'=>$totalPrice, ':sf'=>$serviceFee, ':pm'=>$paymentMethod, ':req'=>$specialRequests
        ]);
        $bookingId = (int)$db->lastInsertId();
        ActivityLog::log($_SESSION['user_id'], 'booking_created', "Created booking for {$dest['name']} on {$visitDate}");
        flash_message('success', 'Booking submitted! Ref: ' . $ref);
        redirect('/tourist/bookings.php');
    } catch (Exception $e) {
        flash_message('error', 'Booking failed: ' . $e->getMessage());
        redirect('/tourist/book_service.php?id=' . $destId);
    }
}

render_page('user', 'book_service.php', 'Book ' . $dest['name'], function() use ($dest, $destId, $feePrice, $user, $minDate, $activityList, $guideList, $serviceFeeRate) {
?>
<style>
.rs-page{max-width:680px;margin:0 auto}
.rs-back{display:inline-flex;align-items:center;gap:8px;color:#00665c;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:6px 14px 6px 10px;text-decoration:none;font-size:.82rem;font-weight:600;margin-bottom:18px}
.rs-back:hover{color:#008075;border-color:#008075;background:#f0fdfa}
.rs-header{background:linear-gradient(135deg,#008075 0%,#00665c 100%);color:#fff;border-radius:20px;padding:20px 24px;margin-bottom:18px;display:flex;align-items:center;gap:18px;box-shadow:0 6px 20px rgba(0,102,92,.18)}
.rs-header-img{width:76px;height:76px;border-radius:16px;object-fit:cover;border:3px solid rgba(255,255,255,.9)}
.rs-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.05);margin-bottom:18px;overflow:hidden}
.rs-card-body{padding:24px 26px}
.rs-card-title{font-size:.88rem;font-weight:700;color:#1e293b;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.rs-card-title .title-icon{width:28px;height:28px;border-radius:8px;background:rgba(0,128,117,.08);color:#008075;display:flex;align-items:center;justify-content:center;font-size:.78rem}
.rs-label{font-size:.70rem;font-weight:700;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em;display:block}
.rs-input{width:100%;border:1.5px solid #e2e8f0;border-radius:12px;padding:11px 14px;font-size:.86rem;color:#1e293b}
.rs-input:focus{border-color:#008075;outline:none;box-shadow:0 0 0 3px rgba(0,128,117,.09)}
.rs-guest{display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden}
.rs-guest button{width:42px;height:46px;border:none;background:#f8fafc;cursor:pointer}
.rs-guest .rs-count{flex:1;text-align:center;font-weight:800;padding:10px 0;border-left:1.5px solid #e2e8f0;border-right:1.5px solid #e2e8f0}
.rs-payment{display:flex;gap:10px;flex-wrap:wrap}
.rs-payment-opt{flex:1;min-width:120px;border:1.5px solid #e2e8f0;border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:10px;cursor:pointer;background:#fff;position:relative}
.rs-payment-opt.selected{border-color:#008075;background:rgba(0,128,117,.06)}
.rs-btn-primary{background:linear-gradient(135deg,#008075,#00665c);color:#fff;border:none;border-radius:12px;padding:13px 24px;font-weight:700;width:100%;display:flex;align-items:center;justify-content:center;gap:8px}
</style>
<div class="rs-page">
    <a href="<?= BASE_URL ?>/tourist/destination_detail.php?id=<?= $destId ?>" class="rs-back"><i class="fas fa-arrow-left"></i> Back to Destination</a>
    <div class="rs-header">
        <?php if(!empty($dest['image'])): ?><img src="<?= dest_image_url($dest['image']) ?>" class="rs-header-img"><?php endif; ?>
        <div><h3 style="margin:0;font-weight:800;"><?= sanitize($dest['name']) ?></h3><p style="margin:4px 0 0;font-size:.82rem;opacity:.9;"><i class="fas fa-map-pin"></i> <?= sanitize($dest['location']) ?></p><span style="display:inline-flex;margin-top:8px;background:rgba(255,255,255,.15);border-radius:20px;padding:3px 10px;font-size:.70rem;font-weight:600;">📅 Book an Activity or Tourism Service</span></div>
    </div>
    <div style="background:#f0fdfa;border:1px solid rgba(0,128,117,.15);border-radius:12px;padding:12px 14px;font-size:.82rem;color:#065f46;margin-bottom:18px;display:flex;gap:10px;"><i class="fas fa-ticket" style="margin-top:2px;"></i><div><strong>Booking</strong> = purchase/avail a tourism service. You will be charged and assigned a tour guide. For free slot reservation, use <a href="<?= BASE_URL ?>/tourist/book_now.php?id=<?= $destId ?>" style="color:#008075;font-weight:700;">Reserve a Slot</a>.</div></div>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="rs-card"><div class="rs-card-body">
            <div class="rs-card-title"><span class="title-icon"><i class="fas fa-list"></i></span> Activity / Service</div>
            <label class="rs-label">Select Activity <span style="color:#94a3b8;font-weight:400">(optional)</span></label>
            <select name="activity_id" class="rs-input" id="activitySelect">
                <option value="">General Visit — ₱<?= number_format($feePrice,2) ?>/pax</option>
                <?php foreach($activityList as $a): ?><option value="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>"><?= sanitize($a['title']) ?> — ₱<?= number_format($a['price'],2) ?></option><?php endforeach; ?>
            </select>
            <?php if($guideList): ?>
                <label class="rs-label" style="margin-top:14px;">Tour Guide <?= $dest['guide_required']?'<span style="color:#ef4444">*</span>':'' ?></label>
                <select name="tour_guide_id" class="rs-input">
                    <option value="">-- Select Guide --</option>
                    <?php foreach($guideList as $g): ?><option value="<?= $g['id'] ?>"><?= sanitize($g['name']) ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div></div>
        <div class="rs-card"><div class="rs-card-body">
            <div class="rs-card-title"><span class="title-icon"><i class="fas fa-calendar"></i></span> Date & Participants</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="rs-label">Booking Date *</label><input type="date" name="visit_date" class="rs-input" id="visit_date" min="<?= $minDate ?>" value="<?= $minDate ?>" required></div>
                <div class="col-md-6"><label class="rs-label">Preferred Time *</label><input type="time" name="visit_time" class="rs-input" required value="<?= $dest['operating_hours_open'] ?? '' ?>"></div>
                <div class="col-md-6"><label class="rs-label">Participants *</label><div class="rs-guest"><button type="button" onclick="chg(-1)"><i class="fas fa-minus"></i></button><div class="rs-count" id="cnt">1</div><button type="button" onclick="chg(1)"><i class="fas fa-plus"></i></button><input type="hidden" name="num_participants" id="np" value="1"></div></div>
                <div class="col-md-6"><label class="rs-label">Total Price</label><div class="rs-input" style="background:#f8fafc;font-weight:800;color:#008075;">₱<span id="totalAmt"><?= number_format($feePrice,2) ?></span></div></div>
            </div>
            <label class="rs-label" style="margin-top:14px;">Special Requests</label><textarea name="special_requests" class="rs-input" rows="2" placeholder="Any special needs?"></textarea>
        </div></div>
        <div class="rs-card"><div class="rs-card-body">
            <div class="rs-card-title"><span class="title-icon"><i class="fas fa-credit-card"></i></span> Payment Method *</div>
            <div class="rs-payment">
                <label class="rs-payment-opt selected" onclick="selPay(this)"><input type="radio" name="payment_method" value="gcash" checked><div style="width:32px;height:32px;border-radius:8px;background:#007dfe;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;">G</div><span style="font-weight:600;">GCash</span></label>
                <label class="rs-payment-opt" onclick="selPay(this)"><input type="radio" name="payment_method" value="maya"><div style="width:32px;height:32px;border-radius:8px;background:#00c4b4;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;">M</div><span style="font-weight:600;">Maya</span></label>
                <label class="rs-payment-opt" onclick="selPay(this)"><input type="radio" name="payment_method" value="card"><div style="width:32px;height:32px;border-radius:8px;background:#6b7280;color:#fff;display:flex;align-items:center;justify-content:center;"><i class="fas fa-credit-card"></i></div><span style="font-weight:600;">Card</span></label>
            </div>
        </div></div>
        <div class="d-flex gap-3 align-items-stretch">
            <a href="<?= BASE_URL ?>/tourist/destination_detail.php?id=<?= $destId ?>" class="d-flex align-items-center justify-content-center" style="flex:0 0 120px;background:#fff;border:1.5px solid #e2e8f0;color:#475569;border-radius:12px;font-weight:600;font-size:.86rem;text-decoration:none;">Cancel</a>
            <button type="submit" class="rs-btn-primary" style="flex:1;white-space:nowrap;"><i class="fas fa-check"></i> Confirm & Pay — Book Now</button>
        </div>
    </form>
</div>
<script>
let basePrice=<?= (float)$feePrice ?>;
function selPay(el){document.querySelectorAll('.rs-payment-opt').forEach(o=>o.classList.remove('selected'));el.classList.add('selected');el.querySelector('input').checked=true;}
function chg(d){let i=document.getElementById('np'),c=document.getElementById('cnt');let v=Math.max(1,Math.min(<?= $dest['max_guests_per_booking']?:10 ?>,parseInt(i.value)+d));i.value=v;c.textContent=v;updateTotal();}
function updateTotal(){let s=document.getElementById('activitySelect');let p=parseFloat(s.selectedOptions[0]?.dataset.price||basePrice);let n=parseInt(document.getElementById('np').value);let sub=p*n;let fee=Math.round(sub*<?= $serviceFeeRate ?>*100)/100;document.getElementById('totalAmt').textContent=(sub+fee).toFixed(2);}
document.getElementById('activitySelect').addEventListener('change',updateTotal);
</script>
<?php }); ?>
