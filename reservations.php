<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/classes/Reservation.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$reservationModel = new Reservation();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['csrf_token'] ?? null)) {
        flash_message('error', 'Invalid security token.');
        redirect('/admin/reservations.php');
    }

    $bid = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'approve':
            if ($reservationModel->approve($bid)) {
                ActivityLog::log($_SESSION['user_id'], 'reservation_approved', "Approved reservation #{$bid}");
                flash_message('success', 'Reservation approved.');
            } else {
                flash_message('error', 'Could not approve reservation.');
            }
            break;
        case 'decline':
            if ($reservationModel->decline($bid)) {
                ActivityLog::log($_SESSION['user_id'], 'reservation_declined', "Declined reservation #{$bid}");
                flash_message('success', 'Reservation declined.');
            } else {
                flash_message('error', 'Could not decline reservation.');
            }
            break;
        case 'complete':
            if ($reservationModel->complete($bid)) {
                ActivityLog::log($_SESSION['user_id'], 'reservation_completed', "Completed reservation #{$bid}");
                flash_message('success', 'Reservation marked as completed.');
            } else {
                flash_message('error', 'Could not complete reservation.');
            }
            break;
        case 'cancel':
            if ($reservationModel->cancel($bid)) {
                ActivityLog::log($_SESSION['user_id'], 'reservation_cancelled', "Cancelled reservation #{$bid}");
                flash_message('success', 'Reservation cancelled.');
            } else {
                flash_message('error', 'Could not cancel reservation.');
            }
            break;
        // Backwards compat for old action names
        case 'confirm': if ($reservationModel->approve($bid)) flash_message('success','Reservation approved.'); break;
        case 'reject': if ($reservationModel->decline($bid)) flash_message('success','Reservation declined.'); break;
    }
    redirect('/admin/reservations.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$destFilter = (int)($_GET['destination_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$filters = ['limit' => $perPage, 'offset' => ($page - 1) * $perPage];
if ($search) $filters['search'] = $search;
if ($status !== 'all') $filters['status'] = $status;
if ($dateFrom) $filters['date_from'] = $dateFrom;
if ($dateTo) $filters['date_to'] = $dateTo;
if ($destFilter) $filters['destination_id'] = $destFilter;

$reservations = $reservationModel->getAllReservations($filters);
$total = $reservationModel->countAll($filters);
$totalPages = max(1, ceil($total / $perPage));
$stats = $reservationModel->getStats();

$statusColors = [
    'pending'   => ['#fef3c7', '#92400e'],
    'approved'  => ['#d1fae5', '#065f46'],
    'declined'  => ['#fee2e2', '#991b1b'],
    'completed' => ['#dbeafe', '#1e40af'],
    'cancelled' => ['#f3f4f6', '#6b7280'],
];

render_page('admin', 'reservations', 'Reservation Management', function() use ($reservations, $total, $totalPages, $page, $stats, $search, $status, $dateFrom, $dateTo, $destFilter, $statusColors, $db) {
?>

<style>
.rm-page { }
.rm-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.rm-header h2 { font-size:1.3rem; font-weight:800; color:#1e293b; margin:0; display:flex; align-items:center; gap:10px; }
.rm-header h2 i { color:#008075; }
.rm-header p { color:#64748b; font-size:.85rem; margin:4px 0 0; }
.rm-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:24px; }
.rm-stat { background:white; border-radius:12px; padding:16px; text-align:center; border:1px solid #e2e8f0; }
.rm-stat-num { font-size:1.6rem; font-weight:800; line-height:1; }
.rm-stat-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; margin-top:4px; }
.rm-filters { background:white; border-radius:12px; padding:16px 20px; margin-bottom:20px; border:1px solid #e2e8f0; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.rm-filter-group { display:flex; flex-direction:column; gap:4px; }
.rm-filter-group label { font-size:.72rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.3px; }
.rm-filter-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:8px 12px; font-size:.82rem; color:#1e293b; background:white; }
.rm-filter-input:focus { border-color:#008075; outline:none; box-shadow:0 0 0 2px rgba(0,128,117,.1); }
.rm-filter-btn { padding:8px 16px; border-radius:8px; font-size:.82rem; font-weight:600; border:none; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:6px; }
.rm-filter-btn-primary { background:#008075; color:white; }
.rm-filter-btn-primary:hover { background:#00665c; }
.rm-filter-btn-outline { background:white; border:1.5px solid #e2e8f0; color:#475569; }
.rm-filter-btn-outline:hover { background:#f8fafc; }
.rm-table-wrap { background:white; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; }
.rm-table { width:100%; border-collapse:collapse; }
.rm-table th { background:#f8fafc; padding:12px 16px; font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; text-align:left; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.rm-table td { padding:12px 16px; font-size:.84rem; color:#1e293b; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.rm-table tr:hover td { background:#f8fafc; }
.rm-ref { font-family:monospace; font-weight:700; font-size:.78rem; color:#008075; }
.rm-tourist { font-weight:600; }
.rm-dest { color:#475569; }
.rm-date { white-space:nowrap; color:#64748b; font-size:.82rem; }
.rm-amount { font-weight:700; color:#059669; }
.rm-status { display:inline-flex; padding:4px 10px; border-radius:6px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
.rm-actions { display:flex; gap:4px; flex-wrap:nowrap; }
.rm-act { width:32px; height:32px; border-radius:8px; border:1px solid #e2e8f0; background:white; color:#475569; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.8rem; transition:all .2s; }
.rm-act:hover { background:#f8fafc; border-color:#cbd5e1; }
.rm-act.green { color:#059669; }
.rm-act.green:hover { background:#ecfdf5; border-color:#a7f3d0; }
.rm-act.red { color:#dc2626; }
.rm-act.red:hover { background:#fef2f2; border-color:#fecaca; }
.rm-act.blue { color:#2563eb; }
.rm-act.blue:hover { background:#eff6ff; border-color:#bfdbfe; }
.rm-pagination { display:flex; justify-content:center; align-items:center; gap:8px; margin-top:20px; }
.rm-page-btn { padding:8px 14px; border-radius:8px; font-size:.82rem; font-weight:600; border:1px solid #e2e8f0; background:white; color:#475569; cursor:pointer; transition:all .2s; text-decoration:none; }
.rm-page-btn:hover { background:#f8fafc; border-color:#cbd5e1; }
.rm-page-btn.active { background:#008075; color:white; border-color:#008075; }
.rm-empty { text-align:center; padding:48px; color:#94a3b8; }
.rm-empty i { font-size:2rem; margin-bottom:12px; display:block; }
@media (max-width:992px) { .rm-table { font-size:.78rem; } .rm-table th, .rm-table td { padding:10px 12px; } }
</style>

<div class="rm-page">
    <div class="rm-header">
        <div>
            <h2><i class="fas fa-clipboard-list"></i> Reservations</h2>
            <p>Reserve a Slot, Schedule, or Facility — no payment required until approval</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="rm-stats">
        <div class="rm-stat">
            <div class="rm-stat-num" style="color:#1e293b;"><?= $stats['total'] ?? 0 ?></div>
            <div class="rm-stat-label">Total</div>
        </div>
        <div class="rm-stat">
            <div class="rm-stat-num" style="color:#d97706;"><?= $stats['pending'] ?? 0 ?></div>
            <div class="rm-stat-label">Pending</div>
        </div>
        <div class="rm-stat">
            <div class="rm-stat-num" style="color:#059669;"><?= $stats['approved'] ?? 0 ?></div>
            <div class="rm-stat-label">Approved</div>
        </div>
        <div class="rm-stat">
            <div class="rm-stat-num" style="color:#2563eb;"><?= $stats['completed'] ?? 0 ?></div>
            <div class="rm-stat-label">Completed</div>
        </div>
        <div class="rm-stat">
            <div class="rm-stat-num" style="color:#6b7280;"><?= $stats['cancelled'] ?? 0 ?></div>
            <div class="rm-stat-label">Cancelled</div>
        </div>
        <div class="rm-stat">
            <div class="rm-stat-num" style="color:#dc2626;"><?= $stats['declined'] ?? 0 ?></div>
            <div class="rm-stat-label">Declined</div>
        </div>
    </div>

    <!-- Filters -->
    <form class="rm-filters" method="GET">
        <?php if ($destFilter): ?>
        <?php
            $destStmt = $db->prepare("SELECT name FROM destinations WHERE id = :id");
            $destStmt->execute([':id' => $destFilter]);
            $destName = $destStmt->fetchColumn() ?: 'Destination #' . $destFilter;
        ?>
        <div class="rm-filter-group">
            <label>Destination</label>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold" style="font-size:.84rem;"><?= sanitize($destName) ?></span>
                <a href="<?= BASE_URL ?>/admin/reservations.php" class="text-decoration-none" style="color:#dc2626;font-size:.78rem;"><i class="fas fa-times"></i> Clear</a>
            </div>
        </div>
        <?php endif; ?>
        <input type="hidden" name="destination_id" value="<?= $destFilter ?>">
        <div class="rm-filter-group" style="flex:1;min-width:200px;">
            <label>Search</label>
            <input type="text" name="search" class="rm-filter-input" value="<?= sanitize($search) ?>" placeholder="Search reference, destination, tourist..." style="width:100%;">
        </div>
        <div class="rm-filter-group">
            <label>Status</label>
            <select name="status" class="rm-filter-input">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="declined" <?= $status === 'declined' ? 'selected' : '' ?>>Declined</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="rm-filter-group">
            <label>From</label>
            <input type="date" name="date_from" class="rm-filter-input" value="<?= $dateFrom ?>">
        </div>
        <div class="rm-filter-group">
            <label>To</label>
            <input type="date" name="date_to" class="rm-filter-input" value="<?= $dateTo ?>">
        </div>
        <button type="submit" class="rm-filter-btn rm-filter-btn-primary"><i class="fas fa-search"></i> Filter</button>
        <a href="<?= BASE_URL ?>/admin/reservations.php" class="rm-filter-btn rm-filter-btn-outline"><i class="fas fa-rotate-right"></i> Reset</a>
    </form>

    <!-- Table -->
    <div class="rm-table-wrap">
        <?php if (empty($reservations)): ?>
            <div class="rm-empty">
                <i class="fas fa-inbox"></i>
                <p>No reservations found.</p>
            </div>
        <?php else: ?>
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Reservation Ref</th>
                        <th>Tourist</th>
                        <th>Destination / Facility</th>
                        <th>Reservation Date</th>
                        <th>Time Slot</th>
                        <th>Visitors</th>
                        <th class="d-none d-lg-table-cell">Notes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r):
                        $status = $r['reservation_status'] ?? $r['status'] ?? 'pending';
                        $colors = $statusColors[$status] ?? ['#f1f5f9', '#475569'];
                        $ref = $r['reservation_reference'] ?? $r['booking_reference'] ?? '—';
                        $facility = $r['facility_name'] ?? '';
                        $rdate = $r['reservation_date'] ?? $r['visit_date'] ?? '';
                        $rtime = $r['reservation_time'] ?? $r['visit_time'] ?? '';
                        $visitors = $r['visitors'] ?? $r['num_participants'] ?? 1;
                        $notes = $r['notes'] ?? $r['special_requests'] ?? '';
                    ?>
                        <tr>
                            <td><span class="rm-ref"><?= $ref ?></span></td>
                            <td class="rm-tourist"><?= sanitize($r['tourist_name'] ?? $r['full_name'] ?? '—') ?></td>
                            <td class="rm-dest"><?= sanitize($r['dest_name'] ?? '—') ?><?= $facility ? '<br><small style="color:#008075;">'.sanitize($facility).'</small>' : '' ?></td>
                            <td class="rm-date"><?= $rdate ? date('M j, Y', strtotime($rdate)) : '—' ?></td>
                            <td class="rm-date"><?= $rtime ? date('h:i A', strtotime($rtime)) : '—' ?></td>
                            <td><?= $visitors ?></td>
                            <td class="d-none d-lg-table-cell" style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.82rem;color:#64748b;" title="<?= sanitize($notes) ?>"><?= $notes ? sanitize(mb_strimwidth($notes, 0, 40, '…')) : '—' ?></td>
                            <td>
                                <span class="rm-status" style="background:<?= $colors[0] ?>;color:<?= $colors[1] ?>;">
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td>
                                <div class="rm-actions">
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="rm-act green" title="Approve"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Decline this reservation?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" class="rm-act red" title="Decline"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($status === 'approved'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="rm-act blue" title="Mark Completed"><i class="fas fa-flag-checkered"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($status, ['pending', 'approved'])): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this reservation?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="rm-act red" title="Cancel"><i class="fas fa-ban"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="rm-pagination">
            <?php
            $baseUrl = BASE_URL . '/admin/reservations.php?search=' . urlencode($search) . '&status=' . urlencode($status);
            if ($dateFrom) $baseUrl .= '&date_from=' . urlencode($dateFrom);
            if ($dateTo) $baseUrl .= '&date_to=' . urlencode($dateTo);
            if ($destFilter) $baseUrl .= '&destination_id=' . (int)$destFilter;
            ?>
            <a href="<?= $baseUrl ?>&page=<?= max(1, $page - 1) ?>" class="rm-page-btn" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fas fa-chevron-left"></i></a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="rm-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $baseUrl ?>&page=<?= min($totalPages, $page + 1) ?>" class="rm-page-btn" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="fas fa-chevron-right"></i></a>
        </div>
    <?php endif; ?>
</div>

<?php }); ?>
