<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/classes/Reservation.php';
require_role('tourist');

$db = Database::getInstance()->getConnection();
$reservationModel = new Reservation();
$user = current_user();
$userId = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['csrf_token'] ?? null)) {
        flash_message('error', 'Invalid security token.');
        redirect('/tourist/my_reservations.php');
    }

    if (isset($_POST['cancel_reservation'])) {
        $bid = (int) $_POST['booking_id'];
        if ($reservationModel->cancel($bid, $userId)) {
            ActivityLog::log($userId, 'reservation_cancelled', "Cancelled reservation #{$bid}");
            flash_message('success', 'Reservation cancelled successfully.');
        } else {
            flash_message('error', 'Could not cancel reservation.');
        }
        redirect('/tourist/my_reservations.php?' . http_build_query($_GET));
    }

    if (isset($_POST['delete_reservation'])) {
        $bid = (int) $_POST['booking_id'];
        if ($reservationModel->delete($bid, $userId)) {
            ActivityLog::log($userId, 'reservation_deleted', "Deleted reservation #{$bid}");
            flash_message('success', 'Reservation deleted.');
        } else {
            flash_message('error', 'Could not delete reservation.');
        }
        redirect('/tourist/my_reservations.php?' . http_build_query($_GET));
    }
}

$where = ["r.user_id = :uid"];
$params = [':uid' => $userId];

$filter_status = $_GET['status'] ?? '';
if ($filter_status !== '' && in_array($filter_status, ['pending','approved','declined','completed','cancelled'])) {
    $where[] = "r.reservation_status = :status";
    $params[':status'] = $filter_status;
}

if ($search !== '') {
    $where[] = "(r.reservation_reference LIKE :search OR r.notes LIKE :search2 OR d.name LIKE :search3 OR r.facility_name LIKE :search4)";
    $params[':search'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
    $params[':search4'] = "%{$search}%";
}
if ($date_from !== '') {
    $where[] = "r.reservation_date >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[] = "r.reservation_date <= :date_to";
    $params[':date_to'] = $date_to;
}

$order_by = "r.created_at DESC";
if ($sort === 'date_upcoming') $order_by = "r.reservation_date ASC, r.reservation_time ASC";
elseif ($sort === 'date_desc') $order_by = "r.reservation_date DESC";
elseif ($sort === 'oldest') $order_by = "r.created_at ASC";

$where_clause = 'WHERE ' . implode(' AND ', $where);

$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations r LEFT JOIN destinations d ON r.destination_id = d.id {$where_clause}");
$count_stmt->execute($params);
$total = (int) $count_stmt->fetch()['total'];
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare(
    "SELECT r.*, d.name as dest_name, d.location as dest_location, d.image as dest_image
     FROM reservations r
     LEFT JOIN destinations d ON r.destination_id = d.id
     {$where_clause}
     ORDER BY {$order_by}
     LIMIT {$per_page} OFFSET {$offset}"
);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$stats_stmt = $db->prepare(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN reservation_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN reservation_status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN reservation_status = 'declined' THEN 1 ELSE 0 END) as declined,
        SUM(CASE WHEN reservation_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN reservation_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
     FROM reservations WHERE user_id = :uid"
);
$stats_stmt->execute([':uid' => $userId]);
$stats = $stats_stmt->fetch();

render_page('user', 'my_reservations', 'My Reservations', function() use ($reservations, $filter_status, $page, $total_pages, $total, $stats, $search, $date_from, $date_to, $sort) {
?>

<style>
.mr-page{max-width:100%;margin:0;animation:mrFadeIn .5s ease both;}
@keyframes mrFadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

.mr-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;}
.mr-header-left{display:flex;align-items:center;gap:14px;}
.mr-header-icon{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#008075,#0d9488);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 14px rgba(0,128,117,.25);flex-shrink:0;}
.mr-header h2{font-size:1.4rem;font-weight:800;color:#0f172a;margin:0;letter-spacing:-.03em;}
.mr-header p{color:#64748b;font-size:.82rem;margin:2px 0 0;}
.mr-header-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.mr-meta-pill{font-size:.76rem;font-weight:600;color:#334155;background:#fff;border:1px solid #e2e8f0;padding:8px 14px;border-radius:12px;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.mr-meta-pill:hover{border-color:#cbd5e1;box-shadow:0 2px 8px rgba(0,0,0,.04);}
.mr-meta-pill strong{color:#0f172a;}
.mr-meta-pill.teal{background:linear-gradient(135deg,rgba(0,128,117,.06),rgba(13,148,136,.08));border-color:rgba(0,128,117,.15);color:#00665c;}
.mr-meta-pill.teal strong{color:#00665c;}
.mr-meta-pill.teal i{color:#008075;}
@media(max-width:640px){.mr-header{flex-direction:column;align-items:flex-start}}

.mr-tabs-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:6px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.03);overflow-x:auto;scrollbar-width:none;}
.mr-tabs-wrap::-webkit-scrollbar{display:none}
.mr-tabs{display:flex;gap:4px;min-width:max-content;}
.mr-tab{display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border-radius:12px;font-size:.82rem;font-weight:600;color:#64748b;background:transparent;border:1.5px solid transparent;text-decoration:none;white-space:nowrap;transition:all .25s cubic-bezier(.4,0,.2,1);}
.mr-tab:hover{background:#f8fafc;color:#1e293b;border-color:#f1f5f9;}
.mr-tab.active{background:linear-gradient(135deg,#008075,#0d9488);color:#fff;border-color:#008075;box-shadow:0 3px 12px rgba(0,128,117,.25);transform:scale(1.02);}
.mr-tab .mr-badge{min-width:22px;height:20px;padding:0 7px;border-radius:20px;font-size:.70rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;background:#f1f5f9;color:#475569;transition:all .25s;}
.mr-tab.active .mr-badge{background:rgba(255,255,255,.22);color:#fff;}
.mr-tab:not(.active):hover .mr-badge{background:#e2e8f0;}

.mr-filter{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.03);}
.mr-filter-row{display:flex;gap:10px;flex-wrap:wrap;align-items:end;}
.mr-filter-group{flex:1;min-width:140px;display:flex;flex-direction:column;gap:5px;}
.mr-filter-group.grow{flex:1.5;min-width:180px;}
.mr-filter-group label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:0 0 0 2px;}
.mr-input{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:.84rem;color:#1e293b;background:#fff;transition:all .2s;}
.mr-input:focus{border-color:#008075;box-shadow:0 0 0 3px rgba(0,128,117,.1);outline:none;background:#fff;}
.mr-input::placeholder{color:#94a3b8;}
.mr-select{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:.84rem;color:#1e293b;background:#fff;transition:all .2s;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2 4l4 4 4-4'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
.mr-select:focus{border-color:#008075;box-shadow:0 0 0 3px rgba(0,128,117,.1);outline:none;}
.mr-filter-actions{display:flex;gap:8px;align-items:end;}
.mr-btn{border-radius:10px;font-size:.82rem;font-weight:600;padding:10px 16px;display:inline-flex;align-items:center;gap:6px;transition:all .25s cubic-bezier(.4,0,.2,1);text-decoration:none;cursor:pointer;border:1.5px solid transparent;white-space:nowrap;}
.mr-btn:active{transform:scale(.97);}
.mr-btn-primary{background:linear-gradient(135deg,#008075,#0d9488);color:#fff;border-color:#008075;box-shadow:0 2px 8px rgba(0,128,117,.18);}
.mr-btn-primary:hover{background:linear-gradient(135deg,#00665c,#008075);box-shadow:0 4px 14px rgba(0,128,117,.25);transform:translateY(-1px);color:#fff;}
.mr-btn-soft{background:#fff;color:#334155;border-color:#e2e8f0;}
.mr-btn-soft:hover{background:#f8fafc;border-color:#cbd5e1;color:#0f172a;box-shadow:0 2px 6px rgba(0,0,0,.04);}
.mr-btn-danger{background:#fff;color:#dc2626;border-color:#fecaca;}
.mr-btn-danger:hover{background:#fef2f2;border-color:#fca5a5;box-shadow:0 2px 8px rgba(220,38,38,.1);}

.mr-grid{display:grid;grid-template-columns:1fr;gap:14px;}
.mr-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;display:flex;gap:0;transition:all .3s cubic-bezier(.4,0,.2,1);position:relative;animation:mrCardIn .4s ease both;}
.mr-card:nth-child(1){animation-delay:.05s}
.mr-card:nth-child(2){animation-delay:.1s}
.mr-card:nth-child(3){animation-delay:.15s}
.mr-card:nth-child(4){animation-delay:.2s}
.mr-card:nth-child(5){animation-delay:.25s}
@keyframes mrCardIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.mr-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;transition:all .3s;}
.mr-card.status-pending::before{background:linear-gradient(180deg,#f59e0b,#fbbf24);}
.mr-card.status-approved::before{background:linear-gradient(180deg,#008075,#0d9488);}
.mr-card.status-declined::before{background:linear-gradient(180deg,#ef4444,#f87171);}
.mr-card.status-completed::before{background:linear-gradient(180deg,#3b82f6,#60a5fa);}
.mr-card.status-cancelled::before{background:linear-gradient(180deg,#94a3b8,#cbd5e1);}
.mr-card:hover{border-color:#cbd5e1;box-shadow:0 8px 30px rgba(0,0,0,.07);transform:translateY(-2px);}

.mr-thumb{position:relative;width:180px;min-width:180px;height:140px;border-radius:0;overflow:hidden;background:#f1f5f9;flex-shrink:0;}
.mr-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.4,0,.2,1);}
.mr-card:hover .mr-thumb img{transform:scale(1.06);}
.mr-thumb-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);color:#6366f1;font-size:2rem;}
.mr-status-badge{position:absolute;top:10px;left:10px;z-index:2;display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.4);box-shadow:0 2px 8px rgba(0,0,0,.08);}
.mr-status-badge.pending{background:rgba(254,243,199,.94);color:#92400e;}
.mr-status-badge.approved{background:rgba(209,250,229,.94);color:#065f46;}
.mr-status-badge.declined{background:rgba(254,226,226,.94);color:#991b1b;}
.mr-status-badge.completed{background:rgba(219,234,254,.94);color:#1e40af;}
.mr-status-badge.cancelled{background:rgba(241,245,249,.94);color:#64748b;}

.mr-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;padding:14px 16px;}
.mr-title-row{display:flex;align-items:start;justify-content:space-between;gap:10px;}
.mr-title{font-weight:800;font-size:.95rem;color:#0f172a;line-height:1.3;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
.mr-ref{font-size:.66rem;font-weight:600;color:#94a3b8;font-family:'SF Mono',ui-monospace,Menlo,monospace;background:#f8fafc;border:1px solid #f1f5f9;padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;align-self:flex-start;flex-shrink:0;white-space:nowrap;}
.mr-loc{font-size:.78rem;color:#64748b;display:flex;align-items:center;gap:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mr-loc i{color:#008075;font-size:.72rem;}
.mr-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;}
.mr-chip{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:600;color:#334155;background:#f8fafc;border:1px solid #f1f5f9;padding:5px 10px;border-radius:8px;}
.mr-chip i{color:#008075;font-size:.68rem;}
.mr-notes{font-size:.78rem;color:#475569;background:#f8fafc;border:1px solid #f1f5f9;border-radius:8px;padding:6px 10px;margin-top:6px;display:flex;gap:6px;align-items:start;}
.mr-notes i{color:#94a3b8;margin-top:1px;font-size:.7rem;}

.mr-right{width:180px;min-width:180px;display:flex;flex-direction:column;gap:8px;flex-shrink:0;padding:14px 16px;border-left:1px solid #f1f5f9;}
.mr-right-info{display:flex;flex-direction:column;gap:3px;}
.mr-right-label{font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;}
.mr-right-value{font-size:.84rem;font-weight:600;color:#0f172a;}
.mr-right-value.teal{color:#008075;}
.mr-actions{display:flex;flex-direction:column;gap:6px;margin-top:auto;}
.mr-actions .mr-btn{justify-content:center;padding:8px 10px;font-size:.76rem;border-radius:8px;}
.mr-actions .mr-btn-primary{box-shadow:0 2px 8px rgba(0,128,117,.16);}

@media(max-width:860px){
  .mr-card{flex-direction:column;}
  .mr-thumb{width:100%;height:180px;border-radius:16px 16px 0 0;}
  .mr-card::before{display:none;}
  .mr-right{width:100%;min-width:0;flex-direction:row;flex-wrap:wrap;align-items:center;border-left:none;border-top:1px solid #f1f5f9;padding:12px 16px;}
  .mr-right .mr-actions{flex-direction:row;flex-wrap:wrap;width:100%;}
  .mr-right .mr-actions .mr-btn{flex:1;min-width:110px;}
}

.mr-empty{text-align:center;padding:56px 24px;background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.03);animation:mrCardIn .5s ease both;}
.mr-empty-ill{width:120px;height:96px;margin:0 auto 20px;position:relative;}
.mr-empty-ill svg{animation:mrFloat 4s ease-in-out infinite;}
@keyframes mrFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.mr-empty h5{font-weight:800;color:#0f172a;margin-bottom:6px;font-size:1.05rem;}
.mr-empty p{color:#64748b;font-size:.86rem;max-width:400px;margin:0 auto 20px;line-height:1.65;}
.mr-empty-cta{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#008075,#0d9488);color:#fff;padding:12px 22px;border-radius:12px;font-weight:700;font-size:.88rem;text-decoration:none;box-shadow:0 4px 16px rgba(0,128,117,.2);transition:all .3s;}
.mr-empty-cta:hover{background:linear-gradient(135deg,#00665c,#008075);box-shadow:0 6px 20px rgba(0,128,117,.3);transform:translateY(-2px);color:#fff;}
.mr-empty-link{display:inline-flex;align-items:center;gap:6px;color:#008075;font-weight:600;font-size:.84rem;text-decoration:none;margin-left:14px;}
.mr-empty-link:hover{text-decoration:underline;}

.mr-pagination{display:flex;align-items:center;justify-content:space-between;margin-top:20px;padding:0 2px;}
.mr-pagination-info{font-size:.78rem;color:#64748b;font-weight:500;}
.mr-pagination-nav{display:flex;align-items:center;gap:4px;}
.mr-page-btn{width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:600;color:#64748b;background:#fff;border:1.5px solid #e2e8f0;text-decoration:none;transition:all .2s;}
.mr-page-btn:hover{border-color:#008075;color:#008075;background:#f0fdfa;}
.mr-page-btn.active{background:linear-gradient(135deg,#008075,#0d9488);color:#fff;border-color:#008075;box-shadow:0 2px 8px rgba(0,128,117,.2);}
.mr-page-btn:disabled{opacity:.4;pointer-events:none;}

.mr-modal .modal-content{border:none;border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.15);}
.mr-modal-header{background:linear-gradient(135deg,#008075 0%,#0d9488 50%,#14b8a6 100%);color:#fff;padding:24px 24px 20px;position:relative;overflow:hidden;}
.mr-modal-header::after{content:'';position:absolute;top:-40px;right:-40px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none;}
.mr-modal-header h5{font-weight:800;font-size:1.05rem;margin:0 0 4px;}
.mr-modal-header .mr-modal-sub{font-size:.78rem;opacity:.8;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.mr-modal-body{padding:20px 24px 24px;}
.mr-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;}
.mr-info-cell{padding:12px 14px;border-bottom:1px solid #f1f5f9;display:flex;flex-direction:column;gap:3px;}
.mr-info-cell:nth-child(odd){border-right:1px solid #f1f5f9;}
.mr-info-cell:nth-last-child(-n+2){border-bottom:none;}
.mr-info-label{font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;}
.mr-info-value{font-size:.88rem;font-weight:600;color:#0f172a;line-height:1.3;}
.mr-info-value.teal{color:#008075;}
.mr-info-cell.full{grid-column:1/-1;border-right:none;}

.mr-confirm-icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.mr-confirm-icon.warn{background:linear-gradient(135deg,#fef3c7,#fde68a);box-shadow:0 4px 14px rgba(245,158,11,.15);}
.mr-confirm-icon.danger{background:linear-gradient(135deg,#fee2e2,#fecaca);box-shadow:0 4px 14px rgba(239,68,68,.15);}
.mr-confirm-icon i{font-size:1.4rem;}
.mr-confirm-icon.warn i{color:#f59e0b;}
.mr-confirm-icon.danger i{color:#ef4444;}
.mr-confirm-title{font-weight:800;font-size:1.05rem;text-align:center;margin-bottom:4px;}
.mr-confirm-text{text-align:center;color:#64748b;font-size:.86rem;margin-bottom:4px;}

[data-theme="dark"] .mr-header h2{color:#f1f5f9;}
[data-theme="dark"] .mr-header p{color:#94a3b8;}
[data-theme="dark"] .mr-meta-pill{background:#1e293b;border-color:#334155;color:#cbd5e1;}
[data-theme="dark"] .mr-meta-pill strong{color:#f1f5f9;}
[data-theme="dark"] .mr-tabs-wrap,[data-theme="dark"] .mr-filter,[data-theme="dark"] .mr-card,[data-theme="dark"] .mr-empty{background:#1e293b;border-color:#334155;}
[data-theme="dark"] .mr-tab{color:#94a3b8;}
[data-theme="dark"] .mr-tab:hover{background:#334155;color:#e2e8f0;}
[data-theme="dark"] .mr-input,[data-theme="dark"] .mr-select{background:#0f172a;border-color:#334155;color:#e2e8f0;}
[data-theme="dark"] .mr-input::placeholder{color:#64748b;}
[data-theme="dark"] .mr-chip{background:#0f172a;border-color:#334155;color:#cbd5e1;}
[data-theme="dark"] .mr-title{color:#f1f5f9;}
[data-theme="dark"] .mr-loc{color:#94a3b8;}
[data-theme="dark"] .mr-right{border-left-color:#334155;}
[data-theme="dark"] .mr-ref{background:#0f172a;border-color:#334155;color:#64748b;}
[data-theme="dark"] .mr-notes{background:#0f172a;border-color:#334155;color:#94a3b8;}
[data-theme="dark"] .mr-empty h5{color:#f1f5f9;}
[data-theme="dark"] .mr-empty p{color:#94a3b8;}
[data-theme="dark"] .mr-page-btn{background:#1e293b;border-color:#334155;color:#94a3b8;}
[data-theme="dark"] .mr-page-btn:hover{border-color:#14b8a6;color:#5eead4;}
[data-theme="dark"] .mr-modal .modal-content{background:#1e293b;}
[data-theme="dark"] .mr-modal-body{color:#e2e8f0;}
[data-theme="dark"] .mr-info-grid{border-color:#334155;}
[data-theme="dark"] .mr-info-cell{border-bottom-color:#334155;}
[data-theme="dark"] .mr-info-cell:nth-child(odd){border-right-color:#334155;}
[data-theme="dark"] .mr-info-value{color:#f1f5f9;}
[data-theme="dark"] .mr-confirm-title{color:#f1f5f9;}
[data-theme="dark"] .mr-confirm-text{color:#94a3b8;}
[data-theme="dark"] .mr-card{box-shadow:0 2px 8px rgba(0,0,0,.3);}
[data-theme="dark"] .mr-card:hover{box-shadow:0 8px 30px rgba(0,0,0,.4);}
[data-theme="dark"] .mr-right-value{color:#e2e8f0;}
</style>

<div class="mr-page">
    <div class="mr-header">
        <div class="mr-header-left">
            <div class="mr-header-icon"><i class="fas fa-clipboard-list"></i></div>
            <div>
                <h2>My Reservations</h2>
                <p>Reserve a slot or facility — no payment required until approved</p>
            </div>
        </div>
        <div class="mr-header-meta">
            <span class="mr-meta-pill"><i class="fas fa-layer-group"></i> <strong><?= $total ?></strong> reservation<?= (int)$total !== 1 ? 's' : '' ?></span>
        </div>
    </div>

    <?php
    $tabs = [
        'all'       => ['label'=>'All',       'icon'=>'fa-layer-group',   'count'=>(int)$stats['total']],
        'pending'   => ['label'=>'Pending',   'icon'=>'fa-clock',         'count'=>(int)$stats['pending']],
        'approved'  => ['label'=>'Approved',  'icon'=>'fa-check-circle',  'count'=>(int)$stats['approved']],
        'declined'  => ['label'=>'Declined',  'icon'=>'fa-ban',           'count'=>(int)$stats['declined']],
        'completed' => ['label'=>'Completed', 'icon'=>'fa-flag-checkered','count'=>(int)$stats['completed']],
        'cancelled' => ['label'=>'Cancelled', 'icon'=>'fa-times-circle',  'count'=>(int)$stats['cancelled']],
    ];
    ?>
    <div class="mr-tabs-wrap">
        <div class="mr-tabs">
            <?php foreach ($tabs as $k=>$t): $isActive = ($filter_status==='' && $k==='all') || $filter_status===$k; ?>
                <a href="?status=<?= $k==='all'?'':$k ?>" class="mr-tab <?= $isActive?'active':'' ?>">
                    <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?> <span class="mr-badge"><?= $t['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="GET" class="mr-filter">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <div class="mr-filter-row">
            <div class="mr-filter-group grow">
                <label>Search</label>
                <div style="position:relative;">
                    <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;"></i>
                    <input type="text" name="search" class="mr-input" style="padding-left:32px;" value="<?= htmlspecialchars($search) ?>" placeholder="Destination, reference, facility…">
                </div>
            </div>
            <div class="mr-filter-group">
                <label>From</label>
                <input type="date" name="date_from" class="mr-input" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="mr-filter-group">
                <label>To</label>
                <input type="date" name="date_to" class="mr-input" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="mr-filter-group" style="min-width:150px;">
                <label>Sort By</label>
                <select name="sort" class="mr-select">
                    <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
                    <option value="date_upcoming" <?= $sort==='date_upcoming'?'selected':'' ?>>Upcoming Date</option>
                    <option value="date_desc" <?= $sort==='date_desc'?'selected':'' ?>>Date: Newest</option>
                    <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
                </select>
            </div>
            <div class="mr-filter-actions">
                <button type="submit" class="mr-btn mr-btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search!==''||$date_from!==''||$date_to!==''||$sort!=='newest'): ?>
                    <a href="?status=<?= htmlspecialchars($filter_status) ?>" class="mr-btn mr-btn-soft"><i class="fas fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (empty($reservations)): ?>
        <div class="mr-empty">
            <div class="mr-empty-ill" aria-hidden="true">
                <svg viewBox="0 0 120 88" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
                    <rect x="10" y="14" width="100" height="64" rx="14" fill="#f1f5f9" stroke="#e2e8f0" stroke-width="1.5"/>
                    <rect x="18" y="24" width="60" height="8" rx="4" fill="#e2e8f0"/>
                    <rect x="18" y="36" width="84" height="6" rx="3" fill="#e2e8f0"/>
                    <rect x="18" y="46" width="48" height="6" rx="3" fill="#e2e8f0"/>
                    <circle cx="88" cy="62" r="16" fill="#008075" opacity=".12"/><circle cx="88" cy="62" r="10" fill="#008075"/><path d="M82 62l4 4 7-8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h5>No reservations yet</h5>
            <p>Reserve a slot or facility at any destination. No payment required upfront — admin will review and approve your request.</p>
            <div>
                <a href="<?= BASE_URL ?>/tourist/destinations.php" class="mr-empty-cta"><i class="fas fa-compass"></i> Browse Destinations</a>
                <a href="<?= BASE_URL ?>/tourist/bookings.php" class="mr-empty-link">View My Bookings <i class="fas fa-arrow-right" style="font-size:.7rem;"></i></a>
            </div>
        </div>
    <?php else: ?>
        <div class="mr-grid">
        <?php foreach ($reservations as $r):
            $status = $r['reservation_status'] ?? $r['status'] ?? 'pending';
            $ref = $r['reservation_reference'] ?? '—';
            $img = $r['dest_image'] ? BASE_URL . '/uploads/destinations/' . $r['dest_image'] : '';
            $canCancel = in_array($status, ['pending', 'approved']);
            $canDelete = in_array($status, ['cancelled', 'declined', 'completed']);
        ?>
            <div class="mr-card status-<?= $status ?>">
                <div class="mr-thumb">
                    <?php if ($img): ?>
                        <img src="<?= $img ?>" alt="<?= sanitize($r['dest_name'] ?? '') ?>" loading="lazy">
                    <?php else: ?>
                        <div class="mr-thumb-ph"><i class="fas fa-map-marked-alt"></i></div>
                    <?php endif; ?>
                    <span class="mr-status-badge <?= $status ?>"><i class="fas <?= $status==='pending'?'fa-clock':($status==='approved'?'fa-check-circle':($status==='completed'?'fa-flag-checkered':($status==='declined'?'fa-ban':'fa-times-circle'))) ?>"></i> <?= ucfirst($status) ?></span>
                </div>
                <div class="mr-body">
                    <div class="mr-title-row">
                        <div class="mr-title"><?= sanitize($r['dest_name'] ?? 'Deleted Destination') ?></div>
                        <span class="mr-ref"><i class="fas fa-hashtag"></i><?= sanitize($ref) ?></span>
                    </div>
                    <div class="mr-loc"><i class="fas fa-location-dot"></i><?= sanitize($r['dest_location'] ?? 'Binalbagan') ?></div>
                    <?php if (!empty($r['facility_name'])): ?>
                        <div class="mr-loc"><i class="fas fa-door-open"></i><?= sanitize($r['facility_name']) ?></div>
                    <?php endif; ?>
                    <div class="mr-chips">
                        <span class="mr-chip"><i class="fas fa-calendar-day"></i><?= $r['reservation_date'] ? date('M j, Y', strtotime($r['reservation_date'])) : 'TBA' ?></span>
                        <span class="mr-chip"><i class="fas fa-clock"></i><?= $r['reservation_time'] ? date('h:i A', strtotime($r['reservation_time'])) : 'TBA' ?></span>
                        <span class="mr-chip"><i class="fas fa-users"></i><?= $r['visitors'] ?? 1 ?> visitor<?= ($r['visitors'] ?? 1) != 1 ? 's' : '' ?></span>
                    </div>
                    <?php if (!empty($r['notes'])): ?>
                        <div class="mr-notes"><i class="fas fa-sticky-note"></i><span><?= sanitize($r['notes']) ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="mr-right">
                    <div class="mr-right-info">
                        <div><span class="mr-right-label">Date</span><div class="mr-right-value"><?= $r['reservation_date'] ? date('M j, Y', strtotime($r['reservation_date'])) : 'TBA' ?></div></div>
                        <div><span class="mr-right-label">Visitors</span><div class="mr-right-value"><?= $r['visitors'] ?? 1 ?></div></div>
                    </div>
                    <div class="mr-actions">
                        <button class="mr-btn mr-btn-soft" data-bs-toggle="modal" data-bs-target="#detailModal"
                            data-id="<?= $r['id'] ?>"
                            data-ref="<?= sanitize($ref) ?>"
                            data-dest="<?= sanitize($r['dest_name'] ?? '') ?>"
                            data-loc="<?= sanitize($r['dest_location'] ?? '') ?>"
                            data-facility="<?= sanitize($r['facility_name'] ?? '') ?>"
                            data-date="<?= $r['reservation_date'] ? date('M j, Y', strtotime($r['reservation_date'])) : 'TBA' ?>"
                            data-time="<?= $r['reservation_time'] ? date('h:i A', strtotime($r['reservation_time'])) : 'TBA' ?>"
                            data-visitors="<?= $r['visitors'] ?? 1 ?>"
                            data-status="<?= $status ?>"
                            data-notes="<?= sanitize($r['notes'] ?? '') ?>"
                            data-created="<?= sanitize(format_datetime($r['created_at'])) ?>"
                            onclick="showDetail(this)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <button class="mr-btn mr-btn-soft" onclick="navigator.clipboard.writeText('<?= sanitize($ref) ?>'); this.innerHTML='<i class=\'fas fa-check\'></i> Copied'; setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i> Copy Ref',1500); return false;">
                            <i class="fas fa-copy"></i> Copy Ref
                        </button>
                        <?php if ($canCancel): ?>
                            <button class="mr-btn mr-btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"
                                data-id="<?= $r['id'] ?>"
                                data-dest="<?= sanitize($r['dest_name'] ?? '') ?>"
                                onclick="showCancelConfirm(this)">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <button class="mr-btn mr-btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-id="<?= $r['id'] ?>"
                                data-dest="<?= sanitize($r['dest_name'] ?? '') ?>"
                                onclick="showDeleteConfirm(this)">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="mr-pagination">
            <span class="mr-pagination-info">Showing <?= ($page-1)*$per_page+1 ?>–<?= min($page*$per_page, $total) ?> of <?= $total ?></span>
            <div class="mr-pagination-nav">
                <a class="mr-page-btn" <?= $page<=1?'style="opacity:.4;pointer-events:none;"':'' ?> href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&sort=<?= $sort ?>&page=<?= $page-1 ?>"><i class="fas fa-chevron-left"></i></a>
                <?php for ($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                    <a class="mr-page-btn <?= $i===$page?'active':'' ?>" href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&sort=<?= $sort ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a class="mr-page-btn" <?= $page>=$total_pages?'style="opacity:.4;pointer-events:none;"':'' ?> href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&sort=<?= $sort ?>&page=<?= $page+1 ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade mr-modal" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="mr-modal-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5><i class="fas fa-clipboard-list me-2"></i>Reservation #<span id="det_id"></span></h5>
                        <div class="mr-modal-sub">
                            <span><i class="fas fa-hashtag"></i><span id="det_ref"></span></span>
                            <span>·</span>
                            <span>Created <span id="det_created"></span></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="mr-modal-body">
                <div class="mr-info-grid">
                    <div class="mr-info-cell"><span class="mr-info-label">Destination</span><span class="mr-info-value" id="det_dest"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Location</span><span class="mr-info-value" id="det_loc"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Facility</span><span class="mr-info-value" id="det_facility"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Date</span><span class="mr-info-value" id="det_date"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Time</span><span class="mr-info-value" id="det_time"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Visitors</span><span class="mr-info-value" id="det_visitors"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Status</span><span id="det_status" class="mr-status-badge" style="position:static;display:inline-flex;font-size:.7rem;"></span></div>
                    <div class="mr-info-cell"><span class="mr-info-label">Payment</span><span class="mr-info-value" style="color:#008075;">Not Required</span></div>
                    <div class="mr-info-cell full"><span class="mr-info-label">Notes</span><div class="mr-info-value" id="det_notes" style="font-weight:400;white-space:pre-wrap;"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade mr-modal" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_token() ?>">
                <input type="hidden" name="cancel_reservation" value="1">
                <input type="hidden" name="booking_id" id="cancel_res_id">
                <div style="padding:28px 24px 16px;text-align:center;">
                    <div class="mr-confirm-icon warn"><i class="fas fa-exclamation-triangle"></i></div>
                    <h5 class="mr-confirm-title" style="color:#92400e;">Cancel Reservation</h5>
                    <p class="mr-confirm-text">Are you sure you want to cancel reservation for <strong id="cancel_dest_name"></strong>?</p>
                    <p class="mr-confirm-text" style="font-size:.78rem;color:#64748b;">This cannot be undone.</p>
                </div>
                <div class="d-flex gap-2 px-4 pb-4">
                    <button type="button" class="mr-btn mr-btn-soft flex-grow-1 justify-content-center" data-bs-dismiss="modal">Keep</button>
                    <button type="submit" class="mr-btn mr-btn-danger flex-grow-1 justify-content-center" style="background:#ef4444;color:#fff;border-color:#ef4444;"><i class="fas fa-times me-1"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade mr-modal" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_token() ?>">
                <input type="hidden" name="delete_reservation" value="1">
                <input type="hidden" name="booking_id" id="delete_res_id">
                <div style="padding:28px 24px 16px;text-align:center;">
                    <div class="mr-confirm-icon danger"><i class="fas fa-trash-alt"></i></div>
                    <h5 class="mr-confirm-title" style="color:#991b1b;">Delete Reservation</h5>
                    <p class="mr-confirm-text">Permanently delete reservation for <strong id="delete_dest_name"></strong>?</p>
                    <p class="mr-confirm-text" style="font-size:.78rem;color:#64748b;">All data will be removed.</p>
                </div>
                <div class="d-flex gap-2 px-4 pb-4">
                    <button type="button" class="mr-btn mr-btn-soft flex-grow-1 justify-content-center" data-bs-dismiss="modal">Keep</button>
                    <button type="submit" class="mr-btn flex-grow-1 justify-content-center" style="background:#ef4444;color:#fff;border-color:#ef4444;"><i class="fas fa-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showDetail(btn) {
    document.getElementById('det_id').textContent = btn.dataset.id;
    document.getElementById('det_ref').textContent = btn.dataset.ref;
    document.getElementById('det_dest').textContent = btn.dataset.dest || 'N/A';
    document.getElementById('det_loc').textContent = btn.dataset.loc || 'Binalbagan';
    document.getElementById('det_facility').textContent = btn.dataset.facility || 'General Slot';
    document.getElementById('det_date').textContent = btn.dataset.date;
    document.getElementById('det_time').textContent = btn.dataset.time;
    document.getElementById('det_visitors').textContent = btn.dataset.visitors;
    document.getElementById('det_notes').textContent = btn.dataset.notes || 'None';
    document.getElementById('det_created').textContent = btn.dataset.created;
    const st = btn.dataset.status;
    const statusEl = document.getElementById('det_status');
    statusEl.innerHTML = '<i class="fas ' + ({pending:'fa-clock',approved:'fa-check-circle',declined:'fa-ban',completed:'fa-flag-checkered',cancelled:'fa-times-circle'}[st]||'fa-clock') + '"></i> ' + st.charAt(0).toUpperCase() + st.slice(1);
    statusEl.className = 'mr-status-badge ' + st;
}
function showCancelConfirm(btn) {
    document.getElementById('cancel_res_id').value = btn.dataset.id;
    document.getElementById('cancel_dest_name').textContent = btn.dataset.dest;
}
function showDeleteConfirm(btn) {
    document.getElementById('delete_res_id').value = btn.dataset.id;
    document.getElementById('delete_dest_name').textContent = btn.dataset.dest;
}
</script>

<?php }); ?>
