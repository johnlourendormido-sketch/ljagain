<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
require_once __DIR__ . '/../includes/classes/Event.php';
require_once __DIR__ . '/../includes/classes/Notification.php';
require_once __DIR__ . '/../includes/classes/Destination.php';

$db = Database::getInstance()->getConnection();
$db->exec("UPDATE events SET status = 'completed', updated_at = NOW() WHERE status = 'published' AND event_end_date IS NOT NULL AND event_end_date < CURDATE()");

$eventModel = new Event();

$search = $_GET['search'] ?? '';
$destFilter = $_GET['destination'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$csrf = $_SESSION['csrf_token'] ?? generate_token();

$destinations = $db->query("SELECT id, name FROM destinations ORDER BY name ASC")->fetchAll();
$categories = ['festival'=>'Festival','cultural_event'=>'Cultural Event','tourism_event'=>'Tourism Event','workshop'=>'Workshop','community_event'=>'Community Event','sports'=>'Sports','arts'=>'Arts','other'=>'Other'];

$allStats = $db->query("SELECT status, COUNT(*) as cnt FROM events GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$eventStats = [
    'total' => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'draft' => (int)($allStats['draft'] ?? 0),
    'published' => (int)($allStats['published'] ?? 0),
    'completed' => (int)($allStats['completed'] ?? 0),
    'cancelled' => (int)($allStats['cancelled'] ?? 0),
];

// ── AJAX data endpoint (GET ?ajax=1) ──────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $qPage = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 15);
    if (!in_array($perPage, [10, 15, 25, 50], true)) $perPage = 15;
    $qSearch = trim($_GET['search'] ?? '');
    $qDest = $_GET['destination'] ?? '';
    $qStatus = $_GET['status'] ?? '';
    $qSort = $_GET['sort'] ?? 'date';
    $qDir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

    $sortMap = [
        'id' => 'e.id',
        'title' => 'e.title',
        'destination' => 'd.name',
        'date' => 'e.event_start_date',
        'price' => 'e.price',
        'status' => 'e.status',
    ];
    $orderBy = ($sortMap[$qSort] ?? 'e.created_at') . ' ' . $qDir . ', e.id DESC';

    $where = [];
    $params = [];
    if ($qSearch !== '') {
        $where[] = '(e.title LIKE :q1 OR e.description LIKE :q2)';
        $params[':q1'] = "%{$qSearch}%"; $params[':q2'] = "%{$qSearch}%";
    }
    if ($qDest !== '') { $where[] = 'e.destination_id = :dest'; $params[':dest'] = (int)$qDest; }
    if ($qStatus !== '') { $where[] = 'e.status = :status'; $params[':status'] = $qStatus; }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $base = 'FROM events e LEFT JOIN destinations d ON e.destination_id = d.id';

    $countStmt = $db->prepare("SELECT COUNT(*) as c {$base} {$whereClause}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['c'];
    $pages = max(1, ceil($total / $perPage));
    if ($qPage > $pages) { $qPage = $pages; }
    $offset = ($qPage - 1) * $perPage;

    $stmt = $db->prepare("SELECT e.id, e.title, e.description, e.category, e.event_image, e.event_location,
        e.event_start_date, e.event_end_date, e.event_start_time, e.event_end_time, e.organizer, e.contact_info,
        e.destination_id, e.max_participants, e.min_participants, e.min_age, e.duration_hours, e.price, e.status,
        d.name as destination_name,
        (SELECT COUNT(*) FROM bookings b JOIN schedules s2 ON b.schedule_id = s2.id WHERE s2.event_id = e.id AND b.status IN ('confirmed','completed')) as attendee_count
        {$base} {$whereClause} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'rows'      => $rows,
        'total'     => $total,
        'pages'     => $pages,
        'page'      => $qPage,
        'per_page'  => $perPage,
        'stats'     => $eventStats,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1');
    $sendJson = function (array $payload): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };
    $respond = function (bool $ok, string $message) use ($isAjax, $sendJson) {
        if ($isAjax) $sendJson(['ok' => $ok, 'message' => $message]);
        $ok ? flash_message('success', $message) : flash_message('error', $message);
        redirect('/admin/events.php?' . http_build_query($_GET));
    };

    if (!verify_token($_POST['csrf_token'] ?? null)) {
        $respond(false, 'Invalid security token. Please refresh and try again.');
    }

    $action = $_POST['action'] ?? '';
    $eid = (int)($_POST['event_id'] ?? 0);

    if ($action === 'add_event' || $action === 'edit_event') {
        $title = trim($_POST['title'] ?? '');
        $destId = (int)($_POST['destination_id'] ?? 0);
        if ($title === '' || !$destId) { $respond(false, 'Title and destination are required.'); }

        $image_path = null;
        if ($action === 'edit_event') {
            $image_path = $db->query("SELECT event_image FROM events WHERE id=$eid")->fetchColumn() ?: null;
        }
        if (isset($_FILES['event_image']) && (($_FILES['event_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
            $upload = upload_file($_FILES['event_image'], 'events', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) $image_path = $upload['path'];
        }

        $data = [
            'destination_id'   => $destId,
            'title'            => $title,
            'description'      => trim($_POST['description'] ?? ''),
            'category'         => $_POST['category'] ?? 'tourism_event',
            'event_image'      => $image_path,
            'event_location'   => trim($_POST['event_location'] ?? '') ?: null,
            'event_start_date' => ($_POST['event_start_date'] ?? '') ?: null,
            'event_end_date'   => ($_POST['event_end_date'] ?? '') ?: null,
            'event_start_time' => ($_POST['event_start_time'] ?? '') ?: null,
            'event_end_time'   => ($_POST['event_end_time'] ?? '') ?: null,
            'organizer'        => trim($_POST['organizer'] ?? ''),
            'contact_info'     => trim($_POST['contact_info'] ?? ''),
            'max_participants' => max(1, (int)($_POST['max_participants'] ?? 20)),
            'min_participants' => max(1, (int)($_POST['min_participants'] ?? 1)),
            'min_age'          => max(1, (int)($_POST['min_age'] ?? 1)),
            'max_age'          => ($_POST['max_age'] ?? '') ? (int)$_POST['max_age'] : null,
            'duration_hours'   => (float)($_POST['duration_hours'] ?? 1),
            'price'            => (float)($_POST['price'] ?? 0),
        ];

        if ($action === 'add_event') {
            $data['status'] = 'draft';
            $data['created_by'] = (int)$_SESSION['user_id'];
            $newId = $eventModel->create($data);
            ActivityLog::log($_SESSION['user_id'], 'event_add', 'Created event: ' . $title);
            $respond(true, 'Event created as draft.');
        } else {
            if (!$eid) { $respond(false, 'Invalid event.'); }
            $eventModel->update($eid, $data);
            ActivityLog::log($_SESSION['user_id'], 'event_edit', 'Edited event #' . $eid);
            $respond(true, 'Event updated.');
        }
    }

    if ($action === 'publish_event' && $eid) {
        $db->prepare("UPDATE events SET status = 'published', updated_at = NOW() WHERE id = :id")->execute([':id' => $eid]);
        $notif = new Notification(); $notif->notifyEventPublished($eid);
        ActivityLog::log($_SESSION['user_id'], 'event_publish', 'Published event #' . $eid);
        $respond(true, 'Event published!');
    }

    if ($action === 'unpublish_event' && $eid) {
        $db->prepare("UPDATE events SET status = 'draft', updated_at = NOW() WHERE id = :id")->execute([':id' => $eid]);
        ActivityLog::log($_SESSION['user_id'], 'event_unpublish', 'Unpublished event #' . $eid);
        $respond(true, 'Event unpublished.');
    }

    if ($action === 'cancel_event' && $eid) {
        $db->prepare("UPDATE events SET status = 'cancelled', updated_at = NOW() WHERE id = :id")->execute([':id' => $eid]);
        $notif = new Notification(); $notif->notifyEventCancelled($eid);
        ActivityLog::log($_SESSION['user_id'], 'event_cancel', 'Cancelled event #' . $eid);
        $respond(true, 'Event cancelled.');
    }

    if ($action === 'delete_event' && $eid) {
        $deps = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE event_id=$eid")->fetchColumn();
        if ($deps > 0) {
            $respond(false, 'Cannot delete: this event has ' . $deps . ' linked schedule(s). Cancel or delete them first.');
        }
        $eventModel->delete($eid);
        ActivityLog::log($_SESSION['user_id'], 'event_delete', 'Deleted event #' . $eid);
        $respond(true, 'Event deleted.');
    }

    $respond(false, 'Unknown action.');
}

render_page('admin', 'events.php', 'Event Management', function () use ($search, $destFilter, $statusFilter, $eventStats, $destinations, $categories, $csrf, $db) {
$statusColors = ['draft'=>'#6b7280','published'=>'#10b981','cancelled'=>'#ef4444','completed'=>'#06b6d4'];
$statusIcons = ['draft'=>'fa-file','published'=>'fa-globe','cancelled'=>'fa-ban','completed'=>'fa-check-circle'];
$catColors = ['festival'=>'#ec4899','cultural_event'=>'#f59e0b','tourism_event'=>'#3b82f6','workshop'=>'#8b5cf6','community_event'=>'#10b981','sports'=>'#ef4444','arts'=>'#06b6d4','other'=>'#6b7280'];
?>

<style>
/* ── Hero ─────────────────────────────────────────────────── */
.page-hero{background:linear-gradient(135deg,#008075 0%,#00665c 100%);color:#fff;border-radius:20px;padding:28px 32px;margin-bottom:1.25rem;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,128,117,.18)}.page-hero::before{content:'';position:absolute;top:-50%;right:-10%;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.08) 0%,transparent 70%);pointer-events:none}.page-hero h4{font-weight:800;letter-spacing:-.02em;margin-bottom:4px;position:relative;z-index:1}.page-hero p{opacity:.85;font-size:.88rem;position:relative;z-index:1;margin-bottom:0}.btn-hero-cta{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.95);color:#00665c;border:1px solid rgba(255,255,255,.3);border-radius:10px;font-weight:700;font-size:.84rem;padding:9px 16px;box-shadow:0 2px 8px rgba(0,0,0,.08);transition:all .2s;position:relative;z-index:1}.btn-hero-cta:hover{background:#fff;color:#005a52;transform:translateY(-1px);box-shadow:0 6px 16px rgba(0,0,0,.12)}.btn-hero-cta .add-icon{width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;background:rgba(0,128,117,.12);border-radius:7px;font-size:.7rem}
/* ── KPI Cards ────────────────────────────────────────────── */
.kpi-card{border:none;border-radius:16px;background:var(--card-bg,#fff);cursor:pointer;transition:all .25s cubic-bezier(.4,0,.2,1);box-shadow:0 1px 3px rgba(0,0,0,.04),0 1px 2px rgba(0,0,0,.06);position:relative;overflow:hidden}.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,var(--brand,#008075));opacity:0;transition:opacity .25s}.kpi-card:hover{transform:translateY(-3px);box-shadow:0 10px 25px rgba(0,0,0,.08)}.kpi-card:hover::before{opacity:1}.kpi-card.active{box-shadow:0 0 0 3px rgba(0,128,117,.15),0 4px 12px rgba(0,0,0,.06)}.kpi-card.active::before{opacity:1}.kpi-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:transform .25s}.kpi-card:hover .kpi-icon{transform:scale(1.08)}.kpi-num{font-size:1.65rem;font-weight:800;line-height:1.1;letter-spacing:-.02em}.kpi-label{font-size:.70rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600;color:var(--text-muted,#94a3b8);margin-top:2px}
/* ── Filter ───────────────────────────────────────────────── */
.filter-card{background:var(--card-bg,#fff);border:none;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.04),0 1px 2px rgba(0,0,0,.06);padding:14px 16px;margin-bottom:1rem}.filter-card .form-control,.filter-card .form-select{border-radius:10px;border-color:var(--border-color,#e2e8f0);font-size:.84rem;background:var(--card-bg,#fff);color:var(--text-primary,#1e293b)}.filter-card .form-control:focus,.filter-card .form-select:focus{border-color:#008075;box-shadow:0 0 0 3px rgba(0,128,117,.09)}.filter-card .form-label{font-size:.70rem;font-weight:700;color:var(--text-muted,#64748b);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.sticky-filter{position:sticky;top:70px;z-index:30}
.filter-input-wrap{position:relative}.filter-input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted,#94a3b8);font-size:.82rem;pointer-events:none}.filter-input{padding-left:36px}
.filter-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(0,128,117,.08);border:1px solid rgba(0,128,117,.18);color:#00665c;font-size:.72rem;font-weight:600;padding:3px 12px;border-radius:20px;transition:background .2s}[data-theme="dark"] .filter-chip{background:rgba(16,185,129,.12);color:#5eead4;border-color:rgba(16,185,129,.3)}.filter-chip:hover{background:rgba(0,128,117,.14)}.filter-chip .chip-x{border:none;background:none;color:inherit;font-size:.85rem;line-height:1;padding:0 0 0 4px;cursor:pointer;opacity:.6}.filter-chip .chip-x:hover{opacity:1}
.btn-soft{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#fff;color:#008075;border:1.5px solid #008075;padding:7px 14px;border-radius:10px;font-size:.82rem;font-weight:600;transition:all .2s}.btn-soft:hover{background:#f0fdfa;border-color:#00665c;color:#00665c}
.refresh-group{display:inline-flex;align-items:center;gap:0;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:10px;padding:3px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.refresh-group .refresh-btn{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border:none;border-radius:7px;background:var(--bg-secondary,#f8fafc);color:var(--text-muted,#64748b);font-size:.8rem;cursor:pointer;transition:all .2s;flex-shrink:0}.refresh-group .refresh-btn:hover{background:var(--brand,#008075);color:#fff}.refresh-divider{width:1px;height:20px;background:var(--border-color,#e2e8f0);margin:0 6px;flex-shrink:0}.last-updated-text{font-size:.78rem;font-weight:500;color:var(--text-muted,#64748b);padding-right:10px;white-space:nowrap}
.table-card{background:var(--card-bg,#fff);border:none;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04),0 1px 2px rgba(0,0,0,.06)}.logs-table{border-collapse:separate;border-spacing:0;min-width:1080px}.logs-table thead th{background:var(--card-bg,#f8fafc);border-bottom:2px solid var(--border-color,#e2e8f0);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#64748b);padding:14px 16px}.logs-table tbody tr{transition:all .15s}.logs-table tbody tr:hover{background:rgba(12,110,94,.02)}.logs-table tbody td{padding:14px 16px;border-bottom:1px solid var(--border-color,#f1f5f9);vertical-align:middle;font-size:.88rem;color:var(--text-primary,#1e293b)}
.logs-table th.sortable{cursor:pointer;user-select:none;white-space:nowrap;transition:color .2s}.logs-table th.sortable:hover{color:#0c6e5e}.logs-table th.sortable.active{color:#0c6e5e}.logs-table th.sortable .th-arrow{margin-left:6px;font-size:.7rem;color:var(--text-muted,#94a3b8)}.logs-table th.sortable.active .th-arrow{color:#0c6e5e}
.status-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:8px;font-size:.75rem;font-weight:700}
.row-id{font-family:'SF Mono',Consolas,monospace;font-size:.78rem;padding:3px 10px;border-radius:6px;background:var(--border-color,#f1f5f9);color:var(--text-muted,#64748b)}.cell-main{font-weight:600;font-size:.88rem}.cell-sub{font-size:.75rem;color:var(--text-muted,#94a3b8)}
.action-btn{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;border:1px solid var(--border-color,#e2e8f0);background:var(--card-bg,#fff);color:var(--text-primary,#475569);transition:all .2s;padding:0}.action-btn:hover{border-color:#0c6e5e;color:#0c6e5e;background:rgba(12,110,94,.05)}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:rgba(239,68,68,.05)}.action-btn.success:hover{border-color:#10b981;color:#10b981;background:rgba(16,185,129,.05)}.action-btn.primary:hover{border-color:#3b82f6;color:#3b82f6;background:rgba(59,130,246,.05)}.action-btn.warning:hover{border-color:#f59e0b;color:#f59e0b;background:rgba(245,158,11,.05)}
.empty-state{text-align:center;padding:40px 20px;color:var(--text-muted,#94a3b8)}.empty-state .empty-icon{width:56px;height:56px;border-radius:14px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem}.empty-state h6{font-weight:700;font-size:.9rem;color:var(--text-primary,#1e293b);margin-bottom:4px}.empty-state p{font-size:.82rem;margin:0}
.pagination .page-link{border-radius:10px;margin:0 3px;font-size:.85rem;font-weight:600;border:1px solid var(--border-color,#e2e8f0);color:var(--text-primary,#1e293b);padding:6px 14px;cursor:pointer}.pagination .page-item.active .page-link{background:#0c6e5e;border-color:#0c6e5e;color:#fff}.pagination .page-item.disabled .page-link{cursor:default}
.skel{position:relative;overflow:hidden;height:14px;border-radius:6px;background:var(--border-color,#e2e8f0)}.skel::after{content:'';position:absolute;inset:0;transform:translateX(-100%);background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);animation:shimmer 1.3s infinite}@keyframes shimmer{to{transform:translateX(100%)}}
.modal-content{border:none;border-radius:16px;overflow:hidden;background:var(--card-bg,#fff)}.modal-header{border-bottom:1px solid var(--border-color,#f1f5f9);padding:18px 24px}.modal-header .modal-title{font-weight:700;font-size:1rem;color:var(--text-primary,#1e293b)}.modal-body{padding:24px}.modal-footer{border-top:1px solid var(--border-color,#f1f5f9);padding:16px 24px}.detail-card{background:var(--card-bg,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:12px;padding:14px}.detail-card .label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#94a3b8);margin-bottom:4px}.detail-card .value{font-weight:700;font-size:.9rem;color:var(--text-primary,#1e293b)}
.event-thumb{width:44px;height:44px;border-radius:10px;object-fit:cover;border:2px solid var(--border-color,#e2e8f0)}
.event-thumb-placeholder{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff}
.app-toast{position:fixed;top:calc(var(--topbar-height) + 14px);right:24px;z-index:9999;display:flex;align-items:center;gap:8px;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-left:4px solid #10b981;border-radius:12px;padding:12px 18px;font-size:.88rem;font-weight:600;color:var(--text-primary,#1e293b);box-shadow:0 12px 32px rgba(0,0,0,.15);opacity:0;transform:translateY(-8px);pointer-events:none;transition:all .3s}.app-toast.show{opacity:1;transform:translateY(0)}.app-toast.danger{border-left-color:#ef4444}
@media (max-width: 991.98px){.sticky-filter{top:12px}}

/* ═══ Event Modal Stepper ══════════════════════════════════ */
.ev-stepper{display:flex;align-items:center;justify-content:center;gap:0;padding:16px 24px;background:var(--card-bg,#fff);border-bottom:1px solid var(--border-color,#f1f5f9)}
.ev-step{display:flex;align-items:center;gap:6px;cursor:default;position:relative}
.ev-step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;background:var(--border-color,#e2e8f0);color:var(--text-muted,#94a3b8);transition:all .3s;border:2px solid transparent;flex-shrink:0}
.ev-step.active .ev-step-num{background:#0c6e5e;color:#fff;border-color:#0c6e5e;box-shadow:0 0 0 4px rgba(12,110,94,.12)}
.ev-step.done .ev-step-num{background:#10b981;color:#fff;border-color:#10b981}
.ev-step-label{font-size:.72rem;font-weight:600;color:var(--text-muted,#94a3b8);transition:color .3s;white-space:nowrap}
.ev-step.active .ev-step-label,.ev-step.done .ev-step-label{color:var(--text-primary,#1e293b)}
.ev-step-line{width:32px;height:2px;background:var(--border-color,#e2e8f0);margin:0 4px;flex-shrink:0;transition:background .3s}
.ev-step-line.done{background:#10b981}
.ev-step-pane{display:none;animation:evPaneIn .3s ease}
.ev-step-pane.active{display:block}
@keyframes evPaneIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

/* Field card */
.ev-field{background:var(--card-bg,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:12px;padding:12px 14px;transition:border-color .2s,box-shadow .2s}
.ev-field:focus-within{border-color:#0c6e5e;box-shadow:0 0 0 3px rgba(12,110,94,.06)}
.ev-field-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted,#64748b);margin-bottom:6px;display:flex;align-items:center;gap:4px}
.ev-field-label .req{color:#ef4444;font-weight:800}
.ev-field .form-control,.ev-field .form-select{border:1px solid transparent;background:transparent;padding:4px 0;font-size:.88rem;color:var(--text-primary,#1e293b);box-shadow:none}
.ev-field .form-control:focus,.ev-field .form-select:focus{box-shadow:none;border-color:transparent}
.ev-field.is-invalid{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.08)}
.ev-field .ev-helper{font-size:.68rem;color:var(--text-muted,#94a3b8);margin-top:4px}
.ev-field.is-invalid .ev-helper{color:#ef4444}
.ev-field .ev-error{font-size:.68rem;color:#ef4444;margin-top:4px;display:none}
.ev-field.is-invalid .ev-error{display:block}

/* Character counter */
.ev-charcount{font-size:.66rem;color:var(--text-muted,#94a3b8);text-align:right;margin-top:2px}
.ev-charcount.warn{color:#f59e0b}
.ev-charcount.over{color:#ef4444;font-weight:600}

/* Dropzone */
.ev-dropzone{border:2px dashed var(--border-color,#cbd5e1);border-radius:12px;padding:24px 16px;text-align:center;cursor:pointer;transition:all .2s;background:var(--card-bg,#f8fafc)}
.ev-dropzone:hover,.ev-dropzone.dragover{border-color:#0c6e5e;background:rgba(12,110,94,.03)}
.ev-dropzone.has-file{border-style:solid;border-color:#10b981;background:rgba(16,185,129,.03)}
.ev-dropzone-icon{font-size:1.6rem;color:var(--text-muted,#94a3b8);margin-bottom:6px}
.ev-dropzone-text{font-size:.82rem;color:var(--text-muted,#64748b);font-weight:500}
.ev-dropzone-text strong{color:#0c6e5e}
.ev-dropzone-hint{font-size:.68rem;color:var(--text-muted,#94a3b8);margin-top:4px}
.ev-dropzone-preview{display:none;margin-top:10px}
.ev-dropzone-preview img{max-width:100%;max-height:140px;border-radius:8px;object-fit:cover}
.ev-dropzone-remove{display:inline-flex;align-items:center;gap:4px;margin-top:6px;padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:600;background:rgba(239,68,68,.08);color:#ef4444;border:1px solid rgba(239,68,68,.15);cursor:pointer}

/* Destination autocomplete */
.ev-ac-wrap{position:relative}
.ev-ac-list{position:absolute;top:100%;left:0;right:0;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:200;max-height:180px;overflow-y:auto;display:none;margin-top:4px}
.ev-ac-list.show{display:block}
.ev-ac-item{padding:8px 12px;cursor:pointer;font-size:.84rem;border-bottom:1px solid var(--border-color,#f1f5f9);transition:background .1s}
.ev-ac-item:last-child{border-bottom:none}
.ev-ac-item:hover,.ev-ac-item.focused{background:rgba(12,110,94,.06)}
.ev-ac-empty{padding:10px;text-align:center;color:var(--text-muted,#94a3b8);font-size:.8rem}

/* Rich text editor */
.ev-richtext{border:1px solid var(--border-color,#e2e8f0);border-radius:10px;overflow:hidden;transition:border-color .2s}
.ev-richtext:focus-within{border-color:#0c6e5e}
.ev-richtext-toolbar{display:flex;gap:2px;padding:6px 8px;background:var(--border-color,#f8fafc);border-bottom:1px solid var(--border-color,#f1f5f9)}
.ev-richtext-toolbar button{width:28px;height:28px;border:none;border-radius:6px;background:transparent;color:var(--text-muted,#64748b);font-size:.78rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s}
.ev-richtext-toolbar button:hover{background:rgba(12,110,94,.08);color:#0c6e5e}
.ev-richtext-toolbar button.active{background:#0c6e5e;color:#fff}
.ev-richtext-editor{min-height:80px;padding:10px 12px;font-size:.88rem;color:var(--text-primary,#1e293b);outline:none;line-height:1.5}
.ev-richtext-editor:empty::before{content:attr(data-placeholder);color:var(--text-muted,#94a3b8)}

/* Review step */
.ev-review-section{margin-bottom:16px}
.ev-review-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted,#94a3b8);margin-bottom:6px}
.ev-review-value{font-size:.88rem;color:var(--text-primary,#1e293b);font-weight:500}
.ev-review-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ev-review-card{background:var(--card-bg,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:10px;padding:10px 14px}

/* Footer buttons */
.ev-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 24px;border-top:1px solid var(--border-color,#f1f5f9);background:var(--card-bg,#fff)}
.ev-footer-left,.ev-footer-right{display:flex;gap:8px;align-items:center}
.ev-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:.82rem;font-weight:600;border:none;cursor:pointer;transition:all .2s}
.ev-btn:disabled{opacity:.55;pointer-events:none}
.ev-btn-next{background:linear-gradient(135deg,#0c6e5e,#10b981);color:#fff}
.ev-btn-next:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(12,110,94,.3)}
.ev-btn-prev{background:var(--card-bg,#fff);color:var(--text-primary,#475569);border:1.5px solid var(--border-color,#e2e8f0)}
.ev-btn-prev:hover{border-color:#0c6e5e;color:#0c6e5e}
.ev-btn-draft{background:rgba(245,158,11,.08);color:#b45309;border:1.5px solid rgba(245,158,11,.2)}
.ev-btn-draft:hover{background:rgba(245,158,11,.15)}
.ev-btn-save{background:linear-gradient(135deg,#0c6e5e,#10b981);color:#fff}
.ev-btn-save:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(12,110,94,.3)}
.ev-btn .spinner{display:none;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:evSpin .6s linear infinite}
.ev-btn.loading .spinner{display:inline-block}
.ev-btn.loading .fa-icon{display:none}
@keyframes evSpin{to{transform:rotate(360deg)}}

/* Mobile: full-screen modal */
@media(max-width:767.98px){
    .ev-modal .modal-dialog{max-width:100%;margin:0;height:100vh}
    .ev-modal .modal-content{height:100vh;border-radius:0;display:flex;flex-direction:column}
    .ev-modal .modal-body{max-height:none;flex:1;overflow-y:auto}
    .ev-step-label{display:none}
    .ev-step-line{width:20px}
    .ev-review-grid{grid-template-columns:1fr}
}
</style>

<div class="page-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4><i class="fas fa-calendar-alt me-2"></i>Event Management</h4>
            <p id="eventsHeroInfo"><?= (int)$eventStats['total'] ?> total event<?= (int)$eventStats['total'] !== 1 ? 's' : '' ?> · <?= (int)$eventStats['published'] ?> published</p>
        </div>
        <button class="btn-hero-cta" id="addEventBtn"><span class="add-icon"><i class="fas fa-plus"></i></span>Create Event</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['id'=>'kpiTotal','val'=>$eventStats['total']??0, 'label'=>'Total Events','icon'=>'fa-calendar-alt','color'=>'#008075','bg'=>'rgba(0,128,117,.08)'],
        ['id'=>'kpiDraft','val'=>$eventStats['draft']??0, 'label'=>'Draft','icon'=>'fa-file','color'=>'#6b7280','bg'=>'#f3f4f6'],
        ['id'=>'kpiPublished','val'=>$eventStats['published']??0, 'label'=>'Published','icon'=>'fa-globe','color'=>'#059669','bg'=>'#d1fae5'],
        ['id'=>'kpiCompleted','val'=>$eventStats['completed']??0, 'label'=>'Completed','icon'=>'fa-check-circle','color'=>'#06b6d4','bg'=>'#cffafe'],
        ['id'=>'kpiCancelled','val'=>$eventStats['cancelled']??0, 'label'=>'Cancelled','icon'=>'fa-ban','color'=>'#ef4444','bg'=>'#fee2e2'],
    ];
    foreach ($statCards as $sc): ?>
    <div class="col-xl col-md-4 col-6">
        <div class="kpi-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;"><i class="fas <?= $sc['icon'] ?>"></i></div>
                <div><div class="kpi-num" style="color:<?= $sc['color'] ?>;" id="<?= $sc['id'] ?>"><?= $sc['val'] ?></div><div class="kpi-label"><?= $sc['label'] ?></div></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="filter-card sticky-filter">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label">Search</label>
            <div class="filter-input-wrap">
                <i class="fas fa-search filter-input-icon"></i>
                <input type="text" id="filterSearch" class="form-control filter-input" placeholder="Event name or description..." value="<?= sanitize($search) ?>">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Destination</label>
            <select id="filterDest" class="form-select">
                <option value="">All Destinations</option>
                <?php foreach ($destinations as $dest): ?>
                <option value="<?= $dest['id'] ?>" <?= $destFilter == $dest['id'] ? 'selected' : '' ?>><?= sanitize($dest['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Status</label>
            <select id="filterStatus" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['draft','published','completed','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1 d-flex justify-content-md-end">
            <button type="button" class="btn-soft w-100" id="clearFilters" title="Clear filters"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap mt-3" id="eventsChips" style="display:none;"></div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <div><span class="small fw-semibold" style="color:var(--text-muted,#64748b);" id="eventsCount"></span></div>
    <div class="d-flex gap-2 align-items-center">
        <div class="refresh-group">
            <button type="button" class="refresh-btn" id="eventsRefresh" title="Refresh"><i class="fas fa-rotate-right"></i></button>
            <span class="refresh-divider"></span>
            <span class="last-updated-text" id="refreshTime" style="padding-right:10px;">Last updated --:--</span>
        </div>
        <select id="perPage" class="form-select form-select-sm" style="width:auto;border-radius:10px;border-color:var(--border-color,#e2e8f0);font-size:.82rem;font-weight:600;">
            <option value="10">10 / page</option>
            <option value="15" selected>15 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
        </select>
    </div>
</div>

<div class="table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table logs-table align-middle mb-0">
                <thead><tr>
                    <th class="sortable" data-sort="id"># <i class="fas fa-sort th-arrow"></i></th>
                    <th class="sortable" data-sort="title">Event <i class="fas fa-sort th-arrow"></i></th>
                    <th>Category</th>
                    <th class="sortable" data-sort="destination">Destination <i class="fas fa-sort th-arrow"></i></th>
                    <th class="sortable" data-sort="date">Date <i class="fas fa-sort th-arrow"></i></th>
                    <th class="sortable" data-sort="price">Price <i class="fas fa-sort th-arrow"></i></th>
                    <th>Attendees</th>
                    <th class="sortable" data-sort="status">Status <i class="fas fa-sort th-arrow"></i></th>
                    <th class="text-center">Actions</th>
                </tr></thead>
                <tbody id="eventsBody">
                    <?php for ($i = 0; $i < 8; $i++): ?>
                    <tr><?php for ($c = 0; $c < 9; $c++): ?><td><div class="skel"></div></td><?php endfor; ?></tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<nav class="mt-3" id="eventsPager"></nav>

<div class="modal fade ev-modal" id="eventModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg, #0c6e5e, #10b981);color:#fff;padding:18px 24px;border:none;">
        <div class="d-flex align-items-center gap-2">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;"><i class="fas fa-calendar-plus" style="font-size:1rem;"></i></div>
            <div>
                <h6 class="mb-0 fw-bold" id="eventModalTitle" style="font-size:1.05rem;">Create New Event</h6>
                <p class="mb-0" style="font-size:.72rem;opacity:.8;" id="eventModalSub">Step 1 of 4 — Event Info</p>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:brightness(0) invert(1);opacity:.8;"></button>
    </div>

    <!-- Stepper Progress -->
    <div class="ev-stepper" id="evStepper">
        <div class="ev-step active" data-step="0"><div class="ev-step-num">1</div><span class="ev-step-label">Info</span></div>
        <div class="ev-step-line"></div>
        <div class="ev-step" data-step="1"><div class="ev-step-num">2</div><span class="ev-step-label">Media & Location</span></div>
        <div class="ev-step-line"></div>
        <div class="ev-step" data-step="2"><div class="ev-step-num">3</div><span class="ev-step-label">Schedule</span></div>
        <div class="ev-step-line"></div>
        <div class="ev-step" data-step="3"><div class="ev-step-num">4</div><span class="ev-step-label">Review</span></div>
    </div>

    <form id="eventForm" enctype="multipart/form-data"><div class="modal-body" style="max-height:65vh;overflow-y:auto;padding:0;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" id="eventAction" value="add_event">
        <input type="hidden" name="event_id" id="eventId" value="">

        <!-- Step 1: Event Info -->
        <div class="ev-step-pane active" id="evPane0" style="padding:20px 24px;">
            <div class="d-flex align-items-center gap-2 mb-3"><i class="fas fa-info-circle" style="color:#0c6e5e;font-size:.7rem;"></i><span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted,#94a3b8);">Event Information</span><span style="flex:1;height:1px;background:var(--border-color,#e2e8f0);"></span></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ev-field" id="fTitle"><div class="ev-field-label">Title <span class="req">*</span></div><input type="text" name="title" id="evTitle" class="form-control" required maxlength="100" placeholder="Event name"><div class="ev-charcount"><span id="evTitleCount">0</span>/100</div><div class="ev-error">Title is required</div></div>
                </div>
                <div class="col-md-4">
                    <div class="ev-field" id="fCategory"><div class="ev-field-label">Category</div><select name="category" id="evCategory" class="form-select"><?php foreach ($categories as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="col-md-4">
                    <div class="ev-field" id="fDest"><div class="ev-field-label">Destination <span class="req">*</span></div>
                        <div class="ev-ac-wrap"><input type="text" id="evDestInput" class="form-control" placeholder="Search destination..." autocomplete="off"><input type="hidden" name="destination_id" id="evDest" value=""><div class="ev-ac-list" id="evAcList"></div></div>
                        <div class="ev-helper">Start typing to search destinations</div><div class="ev-error">Please select a destination</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="ev-field" id="fDesc"><div class="ev-field-label">Description</div>
                        <div class="ev-richtext"><div class="ev-richtext-toolbar"><button type="button" data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button><button type="button" data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button><button type="button" data-cmd="insertUnorderedList" title="Bullet List"><i class="fas fa-list-ul"></i></button><button type="button" data-cmd="insertOrderedList" title="Numbered List"><i class="fas fa-list-ol"></i></button></div><div class="ev-richtext-editor" id="evDescEditor" contenteditable="true" data-placeholder="Describe the event..." role="textbox" aria-label="Description"></div></div>
                        <textarea name="description" id="evDesc" class="d-none"></textarea>
                        <div class="ev-charcount"><span id="evDescCount">0</span>/1000</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Media & Location -->
        <div class="ev-step-pane" id="evPane1" style="padding:20px 24px;">
            <div class="d-flex align-items-center gap-2 mb-3"><i class="fas fa-image" style="color:#0c6e5e;font-size:.7rem;"></i><span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted,#94a3b8);">Media & Location</span><span style="flex:1;height:1px;background:var(--border-color,#e2e8f0);"></span></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ev-field"><div class="ev-field-label">Banner Image</div>
                        <div class="ev-dropzone" id="evDropzone"><input type="file" name="event_image" id="evImage" accept="image/*" class="d-none"><div class="ev-dropzone-icon"><i class="fas fa-cloud-arrow-up"></i></div><div class="ev-dropzone-text"><strong>Click to upload</strong> or drag & drop</div><div class="ev-dropzone-hint">JPG, PNG, or WebP (max 5MB)</div></div>
                        <div class="ev-dropzone-preview" id="evImgPreview"><img src="" alt="Preview"><button type="button" class="ev-dropzone-remove" id="evImgRemove"><i class="fas fa-trash"></i> Remove</button></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ev-field"><div class="ev-field-label">Location</div><input type="text" name="event_location" id="evLocation" class="form-control" placeholder="Event venue or address"><div class="ev-helper">e.g. Municipal Plaza, Poblacion</div></div>
                </div>
            </div>
        </div>

        <!-- Step 3: Schedule -->
        <div class="ev-step-pane" id="evPane2" style="padding:20px 24px;">
            <div class="d-flex align-items-center gap-2 mb-3"><i class="fas fa-clock" style="color:#0c6e5e;font-size:.7rem;"></i><span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted,#94a3b8);">Schedule</span><span style="flex:1;height:1px;background:var(--border-color,#e2e8f0);"></span></div>
            <div class="row g-3">
                <div class="col-md-3"><div class="ev-field" id="fStartDate"><div class="ev-field-label">Start Date</div><input type="date" name="event_start_date" id="evStartDate" class="form-control"><div class="ev-helper">When the event begins</div></div></div>
                <div class="col-md-3"><div class="ev-field" id="fEndDate"><div class="ev-field-label">End Date</div><input type="date" name="event_end_date" id="evEndDate" class="form-control"><div class="ev-helper">Auto-fills from start date</div><div class="ev-error">End date cannot be before start date</div></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Start Time</div><input type="time" name="event_start_time" id="evStartTime" class="form-control"></div></div>
                <div class="col-md-3"><div class="ev-field" id="fEndTime"><div class="ev-field-label">End Time</div><input type="time" name="event_end_time" id="evEndTime" class="form-control"><div class="ev-error">End time cannot be before start time</div></div></div>
            </div>
        </div>

        <!-- Step 4: Review -->
        <div class="ev-step-pane" id="evPane3" style="padding:20px 24px;">
            <div class="d-flex align-items-center gap-2 mb-3"><i class="fas fa-clipboard-check" style="color:#0c6e5e;font-size:.7rem;"></i><span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted,#94a3b8);">Review & Pricing</span><span style="flex:1;height:1px;background:var(--border-color,#e2e8f0);"></span></div>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Organizer</div><input type="text" name="organizer" id="evOrganizer" class="form-control"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Contact</div><input type="text" name="contact_info" id="evContact" class="form-control"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Price (₱)</div><input type="number" name="price" id="evPrice" class="form-control" min="0" step="0.01" value="0"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Max Participants</div><input type="number" name="max_participants" id="evMax" class="form-control" min="1" value="20"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Min Participants</div><input type="number" name="min_participants" id="evMin" class="form-control" min="1" value="1"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Duration (hrs)</div><input type="number" name="duration_hours" id="evDuration" class="form-control" min="0.5" step="0.5" value="1"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Min Age</div><input type="number" name="min_age" id="evMinAge" class="form-control" min="1" value="1"></div></div>
                <div class="col-md-3"><div class="ev-field"><div class="ev-field-label">Max Age</div><input type="number" name="max_age" id="evMaxAge" class="form-control" min="1" placeholder="Optional"></div></div>
            </div>
            <!-- Summary -->
            <div class="ev-review-grid" id="evReviewGrid"></div>
        </div>

    </div>
    <div class="ev-footer">
        <div class="ev-footer-left">
            <button type="button" class="ev-btn ev-btn-prev" id="evPrevBtn" style="display:none;"><i class="fas fa-chevron-left"></i> Back</button>
        </div>
        <div class="ev-footer-right">
            <button type="button" class="ev-btn ev-btn-draft" id="evDraftBtn" style="display:none;"><i class="fas fa-save"></i> Save as Draft</button>
            <button type="button" class="ev-btn ev-btn-next" id="evNextBtn">Next <i class="fas fa-chevron-right"></i></button>
            <button type="submit" class="ev-btn ev-btn-save" id="evSubmitBtn" style="display:none;"><span class="fa-icon"><i class="fas fa-check"></i></span><span class="spinner"></span> Save Event</button>
        </div>
    </div>
    </form>
</div></div></div>

<div class="app-toast" id="appToast"></div>

<script>
(function () {
    var CSRF = <?= json_encode($csrf) ?>;
    var CATS = <?= json_encode(array_map(fn($k, $v) => ['k' => $k, 'v' => $v], array_keys($categories), array_values($categories)), JSON_UNESCAPED_UNICODE) ?>;
    var INIT = <?= json_encode(['search' => $search, 'destination' => $destFilter, 'status' => $statusFilter], JSON_UNESCAPED_UNICODE) ?>;

    var state = { page: 1, per_page: 15, sort: 'date', dir: 'desc', search: INIT.search || '', destination: INIT.destination || '', status: INIT.status || '' };
    var timer = null;

    var $body = document.getElementById('eventsBody');
    var $pager = document.getElementById('eventsPager');
    var $count = document.getElementById('eventsCount');
    var $chips = document.getElementById('eventsChips');

    var SCC = { draft: ['#f3f4f6', '#6b7280', 'fa-file'], published: ['#d1fae5', '#059669', 'fa-globe'], completed: ['#cffafe', '#0891b2', 'fa-check-circle'], cancelled: ['#fee2e2', '#dc2626', 'fa-ban'] };
    var CATCOLORS = { festival: '#ec4899', cultural_event: '#f59e0b', tourism_event: '#3b82f6', workshop: '#8b5cf6', community_event: '#10b981', sports: '#ef4444', arts: '#06b6d4', other: '#6b7280' };

    function esc(s) { s = (s == null) ? '' : String(s); var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function fmtDate(s) { if (!s) return ''; var d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return s; return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
    function money(v) { v = parseFloat(v) || 0; return v > 0 ? '\u20b1' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Free'; }
    function statusChip(st) { var c = SCC[st] || ['#e2e8f0', '#475569', 'fa-circle']; return '<span class="status-chip" style="background:' + c[0] + ';color:' + c[1] + ';"><i class="fas ' + c[2] + ' me-1"></i>' + (st ? st.charAt(0).toUpperCase() + st.slice(1) : 'N/A') + '</span>'; }
    function catChip(cat) { var cc = CATCOLORS[cat] || '#6b7280'; var lbl = (cat || '').replace(/_/g, ' '); lbl = lbl.replace(/\b\w/g, function (x) { return x.toUpperCase(); }); return '<span class="status-chip" style="background:' + cc + '18;color:' + cc + ';">' + esc(lbl) + '</span>'; }

    function qs() {
        var p = new URLSearchParams();
        p.set('ajax', '1');
        p.set('page', state.page);
        p.set('per_page', state.per_page);
        p.set('sort', state.sort);
        p.set('dir', state.dir);
        if (state.destination) p.set('destination', state.destination);
        if (state.status) p.set('status', state.status);
        if (state.search) p.set('search', state.search);
        return p.toString();
    }

    function skeletonRows(n) {
        var h = '';
        for (var i = 0; i < n; i++) { h += '<tr>'; for (var c = 0; c < 9; c++) { h += '<td><div class="skel"></div></td>'; } h += '</tr>'; }
        return h;
    }

    function actionButtons(r) {
        var h = '';
        if (r.status === 'draft') h += '<button class="action-btn success" data-act="publish" data-id="' + r.id + '" title="Publish"><i class="fas fa-globe"></i></button>';
        if (r.status === 'published') h += '<button class="action-btn warning" data-act="unpublish" data-id="' + r.id + '" title="Unpublish"><i class="fas fa-eye-slash"></i></button>';
        if (r.status === 'published') h += '<button class="action-btn danger" data-act="cancel" data-id="' + r.id + '" title="Cancel"><i class="fas fa-ban"></i></button>';
        if (r.status !== 'cancelled' && r.status !== 'completed') h += '<button class="action-btn primary" data-act="edit" data-id="' + r.id + '" title="Edit"><i class="fas fa-pen"></i></button>';
        h += '<button class="action-btn danger" data-act="delete" data-id="' + r.id + '" title="Delete"><i class="fas fa-trash"></i></button>';
        return h;
    }

    function thumbHtml(r) {
        var cc = CATCOLORS[r.category] || '#6b7280';
        if (r.event_image) return '<img src="' + esc(r.event_image) + '" class="event-thumb" alt="">';
        return '<div class="event-thumb-placeholder" style="background:' + cc + ';"><i class="fas fa-calendar"></i></div>';
    }

    function renderRows(rows) {
        window.__events = {};
        if (!rows || !rows.length) {
            $body.innerHTML = '<tr><td colspan="9" class="empty-state"><div class="empty-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="fas fa-calendar-alt"></i></div><h6>No events found</h6><p>Try adjusting your filters or create a new event.</p></td></tr>';
            return;
        }
        var h = '';
        for (var k = 0; k < rows.length; k++) {
            var r = rows[k];
            window.__events[r.id] = r;
            h += '<tr>' +
                '<td><span class="row-id">#' + r.id + '</span></td>' +
                '<td><div class="d-flex align-items-center gap-2">' + thumbHtml(r) + '<div><div class="cell-main">' + esc(r.title) + '</div><div class="cell-sub">' + (esc(r.organizer) || '') + '</div></div></div></td>' +
                '<td>' + catChip(r.category) + '</td>' +
                '<td><span style="font-size:.85rem;color:var(--text-muted,#64748b);">' + (esc(r.destination_name) || 'N/A') + '</span></td>' +
                '<td><span style="font-size:.85rem;color:var(--text-muted,#64748b);">' + (r.event_start_date ? esc(fmtDate(r.event_start_date)) : 'TBA') + '</span></td>' +
                '<td><span class="fw-bold" style="color:#0c6e5e;font-size:.9rem;">' + money(r.price) + '</span></td>' +
                '<td><span class="fw-semibold" style="font-size:.88rem;">' + (r.attendee_count || 0) + '</span></td>' +
                '<td>' + statusChip(r.status) + '</td>' +
                '<td class="text-center"><div class="d-flex gap-1 justify-content-center">' + actionButtons(r) + '</div></td>' +
                '</tr>';
        }
        $body.innerHTML = h;
        updateSortIndicators();
    }

    function pageItem(p, pages, enabled, label) {
        return '<li class="page-item ' + (enabled ? '' : 'disabled') + '"><a class="page-link" href="#" data-page="' + (enabled ? p : '') + '" tabindex="-1">' + label + '</a></li>';
    }

    function renderPager(pages, cur) {
        if (pages <= 1) { $pager.innerHTML = ''; return; }
        var h = '<ul class="pagination justify-content-center mb-0">';
        h += pageItem(cur - 1, pages, cur > 1, '<i class="fas fa-chevron-left"></i>');
        var start = Math.max(1, cur - 2), end = Math.min(pages, cur + 2);
        for (var i = start; i <= end; i++) {
            h += '<li class="page-item ' + (i === cur ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        h += pageItem(cur + 1, pages, cur < pages, '<i class="fas fa-chevron-right"></i>');
        h += '</ul>';
        $pager.innerHTML = h;
    }

    function renderCount(total, shown) {
        $count.textContent = shown === undefined
            ? total + ' event' + (total !== 1 ? 's' : '') + ' found'
            : 'Showing ' + shown + ' of ' + total + ' event' + (total !== 1 ? 's' : '');
    }

    function updateStats(s) {
        if (!s) return;
        var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
        set('kpiTotal', s.total);
        set('kpiDraft', s.draft);
        set('kpiPublished', s.published);
        set('kpiCompleted', s.completed);
        set('kpiCancelled', s.cancelled);
        set('eventsHeroInfo', s.total + ' total event' + (s.total !== 1 ? 's' : '') + ' \u00b7 ' + s.published + ' published');
    }

    function updateChips() {
        $chips.innerHTML = '';
        var add = function (label, clearFn) {
            var c = document.createElement('span');
            c.className = 'filter-chip';
            c.innerHTML = '<span>' + esc(label) + '</span><button type="button" class="chip-x" aria-label="Clear">&times;</button>';
            c.querySelector('.chip-x').addEventListener('click', function () { clearFn(); load(); });
            $chips.appendChild(c);
        };
        if (state.destination) add('Destination: ' + state.destination, function () { state.destination = ''; document.getElementById('filterDest').value = ''; });
        if (state.status) add('Status: ' + state.status, function () { state.status = ''; document.getElementById('filterStatus').value = ''; });
        if (state.search) add('Search: ' + state.search, function () { state.search = ''; document.getElementById('filterSearch').value = ''; });
        $chips.style.display = $chips.children.length ? 'flex' : 'none';
    }

    function updateSortIndicators() {
        var ths = document.querySelectorAll('th.sortable');
        for (var i = 0; i < ths.length; i++) {
            var th = ths[i], col = th.getAttribute('data-sort'), ic = th.querySelector('.th-arrow');
            if (!ic) continue;
            if (col === state.sort) {
                ic.className = 'fas ' + (state.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
                th.classList.add('active');
            } else {
                ic.className = 'fas fa-sort';
                th.classList.remove('active');
            }
        }
    }

    function toast(msg, type) {
        var box = document.getElementById('appToast');
        box.className = 'app-toast show' + (type === 'danger' ? ' danger' : '');
        box.innerHTML = '<i class="fas ' + (type === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i><span>' + esc(msg) + '</span>';
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { box.classList.remove('show'); }, 3000);
    }

    function setRefreshLoading(loading){
        var btn=document.getElementById('eventsRefresh'); if(!btn) return;
        btn.disabled=loading;
        var ic=btn.querySelector('i'); if(!ic) return;
        ic.className=loading?'fas fa-spinner fa-spin':'fas fa-rotate-right';
    }
    function updateRefreshTime(){
        var el=document.getElementById('refreshTime'); if(!el) return;
        el.textContent='Last updated '+new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }
    function load() {
        setRefreshLoading(true);
        $body.innerHTML = skeletonRows(8);
        fetch('events.php?' + qs())
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (data) {
                if (data.rows === undefined) throw new Error('bad payload');
                state.page = data.page || 1;
                renderRows(data.rows);
                renderPager(data.pages, data.page);
                renderCount(data.total, data.rows.length);
                updateStats(data.stats);
                updateChips();
                updateSortIndicators();
                updateRefreshTime();
            })
            .catch(function () {
                $body.innerHTML = '<tr><td colspan="9" class="empty-state"><div class="empty-icon" style="background:rgba(239,68,68,.1);color:#ef4444;"><i class="fas fa-triangle-exclamation"></i></div><h6>Could not load events</h6><p>Please try again.</p></td></tr>';
            }).finally(function(){ setRefreshLoading(false); });
    }

    function resetForm() {
        document.getElementById('eventModalTitle').textContent = 'Create New Event';
        document.getElementById('eventModalSub').textContent = 'Step 1 of 4 — Event Info';
        document.getElementById('eventAction').value = 'add_event';
        document.getElementById('eventId').value = '';
        document.getElementById('evTitle').value = '';
        document.getElementById('evDesc').value = '';
        document.getElementById('evDescEditor').innerHTML = '';
        document.getElementById('evLocation').value = '';
        document.getElementById('evStartDate').value = '';
        document.getElementById('evEndDate').value = '';
        document.getElementById('evStartTime').value = '';
        document.getElementById('evEndTime').value = '';
        document.getElementById('evOrganizer').value = '';
        document.getElementById('evContact').value = '';
        document.getElementById('evPrice').value = 0;
        document.getElementById('evMax').value = 20;
        document.getElementById('evMin').value = 1;
        document.getElementById('evDuration').value = 1;
        document.getElementById('evMinAge').value = 1;
        document.getElementById('evMaxAge').value = '';
        document.getElementById('evImage').value = '';
        document.getElementById('evDest').value = '';
        document.getElementById('evDestInput').value = '';
        document.getElementById('evCategory').selectedIndex = 0;
        document.getElementById('evImgPreview').style.display = 'none';
        document.getElementById('evDropzone').classList.remove('has-file');
        document.getElementById('evCharTitleCount').textContent = '0';
        document.getElementById('evCharDescCount').textContent = '0';
        clearStepErrors();
        goToStep(0);
    }

    var currentStep = 0;
    var totalSteps = 4;
    var stepLabels = ['Event Info', 'Media & Location', 'Schedule', 'Review & Pricing'];

    function goToStep(n) {
        currentStep = Math.max(0, Math.min(n, totalSteps - 1));
        document.querySelectorAll('.ev-step-pane').forEach(function(p, i) { p.classList.toggle('active', i === currentStep); });
        document.querySelectorAll('.ev-step').forEach(function(s, i) {
            s.classList.remove('active', 'done');
            if (i < currentStep) s.classList.add('done');
            else if (i === currentStep) s.classList.add('active');
        });
        document.querySelectorAll('.ev-step-line').forEach(function(l, i) { l.classList.toggle('done', i < currentStep); });
        document.getElementById('evPrevBtn').style.display = currentStep > 0 ? '' : 'none';
        document.getElementById('evNextBtn').style.display = currentStep < totalSteps - 1 ? '' : 'none';
        document.getElementById('evSubmitBtn').style.display = currentStep === totalSteps - 1 ? '' : 'none';
        document.getElementById('evDraftBtn').style.display = currentStep > 0 ? '' : 'none';
        document.getElementById('eventModalSub').textContent = 'Step ' + (currentStep + 1) + ' of ' + totalSteps + ' — ' + stepLabels[currentStep];
        if (currentStep === totalSteps - 1) renderReview();
    }

    function clearStepErrors() {
        document.querySelectorAll('.ev-field.is-invalid').forEach(function(f) { f.classList.remove('is-invalid'); });
    }

    function markField(id, msg) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('is-invalid');
        if (msg) { var err = el.querySelector('.ev-error'); if (err) err.textContent = msg; }
    }

    function validateStep(n) {
        clearStepErrors();
        var ok = true;
        if (n === 0) {
            var title = document.getElementById('evTitle').value.trim();
            if (!title) { markField('fTitle', 'Title is required'); ok = false; }
            else if (title.length > 100) { markField('fTitle', 'Title must be under 100 characters'); ok = false; }
            var dest = document.getElementById('evDest').value;
            if (!dest) { markField('fDest', 'Please select a destination'); ok = false; }
        }
        if (n === 2) {
            var sd = document.getElementById('evStartDate').value;
            var ed = document.getElementById('evEndDate').value;
            if (sd && ed && ed < sd) { markField('fEndDate', 'End date cannot be before start date'); ok = false; }
            var st = document.getElementById('evStartTime').value;
            var et = document.getElementById('evEndTime').value;
            if (sd && ed && sd === ed && st && et && et < st) { markField('fEndTime', 'End time cannot be before start time on same day'); ok = false; }
        }
        return ok;
    }

    function renderReview() {
        var grid = document.getElementById('evReviewGrid');
        var title = document.getElementById('evTitle').value.trim() || '—';
        var cat = document.getElementById('evCategory');
        var catText = cat.options[cat.selectedIndex] ? cat.options[cat.selectedIndex].text : '—';
        var dest = document.getElementById('evDestInput').value || '—';
        var desc = document.getElementById('evDescEditor').innerText.trim() || '—';
        var sd = document.getElementById('evStartDate').value || 'TBA';
        var ed = document.getElementById('evEndDate').value || sd || 'TBA';
        var st = document.getElementById('evStartTime').value || '';
        var et = document.getElementById('evEndTime').value || '';
        var time = st && et ? st + ' – ' + et : (st || et || 'TBA');
        var org = document.getElementById('evOrganizer').value || '—';
        var price = parseFloat(document.getElementById('evPrice').value) || 0;
        var maxP = document.getElementById('evMax').value || '20';
        var dur = document.getElementById('evDuration').value || '1';
        grid.innerHTML =
            '<div class="ev-review-card"><div class="ev-review-label">Title</div><div class="ev-review-value">' + esc(title) + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Category</div><div class="ev-review-value">' + esc(catText) + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Destination</div><div class="ev-review-value">' + esc(dest) + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Organizer</div><div class="ev-review-value">' + esc(org) + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Schedule</div><div class="ev-review-value">' + esc(sd) + ' → ' + esc(ed) + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Time</div><div class="ev-review-value">' + esc(time) + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Price</div><div class="ev-review-value">' + (price > 0 ? '₱' + price.toFixed(2) : 'Free') + '</div></div>' +
            '<div class="ev-review-card"><div class="ev-review-label">Capacity</div><div class="ev-review-value">' + maxP + ' pax · ' + dur + 'h</div></div>';
        if (desc !== '—') grid.innerHTML += '<div class="ev-review-card" style="grid-column:1/-1;"><div class="ev-review-label">Description</div><div class="ev-review-value" style="font-size:.82rem;white-space:pre-wrap;max-height:60px;overflow:hidden;">' + esc(desc) + '</div></div>';
    }

    function openAdd() {
        resetForm();
        if (window.bootstrap) new bootstrap.Modal(document.getElementById('eventModal')).show();
    }

    function openEdit(id) {
        var r = window.__events[id];
        if (!r) return;
        resetForm();
        document.getElementById('eventModalTitle').textContent = 'Edit Event #' + id;
        document.getElementById('eventAction').value = 'edit_event';
        document.getElementById('eventId').value = id;
        document.getElementById('evTitle').value = r.title || '';
        document.getElementById('evDesc').value = r.description || '';
        document.getElementById('evDescEditor').innerHTML = r.description ? r.description.replace(/\n/g, '<br>') : '';
        document.getElementById('evLocation').value = r.event_location || '';
        document.getElementById('evStartDate').value = r.event_start_date || '';
        document.getElementById('evEndDate').value = r.event_end_date || '';
        document.getElementById('evStartTime').value = r.event_start_time || '';
        document.getElementById('evEndTime').value = r.event_end_time || '';
        document.getElementById('evOrganizer').value = r.organizer || '';
        document.getElementById('evContact').value = r.contact_info || '';
        document.getElementById('evPrice').value = r.price || 0;
        document.getElementById('evMax').value = r.max_participants || 20;
        document.getElementById('evMin').value = r.min_participants || 1;
        document.getElementById('evDuration').value = r.duration_hours || 1;
        document.getElementById('evMinAge').value = r.min_age || 1;
        document.getElementById('evMaxAge').value = r.max_age || '';
        document.getElementById('evDest').value = r.destination_id || '';
        document.getElementById('evDestInput').value = r.destination_name || '';
        for (var i = 0; i < CATS.length; i++) { if (CATS[i].k === r.category) { document.getElementById('evCategory').selectedIndex = i; break; } }
        updateCharCounts();
        if (r.event_image) { document.getElementById('evDropzone').classList.add('has-file'); }
        if (window.bootstrap) new bootstrap.Modal(document.getElementById('eventModal')).show();
    }

    function postForm(fd) {
        return fetch('events.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function submitEvent(asDraft) {
        /* sync rich text → hidden textarea */
        document.getElementById('evDesc').value = document.getElementById('evDescEditor').innerHTML;
        var btn = asDraft ? document.getElementById('evDraftBtn') : document.getElementById('evSubmitBtn');
        btn.classList.add('loading');
        btn.disabled = true;
        var fd = new FormData(document.getElementById('eventForm'));
        fd.append('ajax', '1');
        if (asDraft) fd.set('action', 'add_event');
        postForm(fd).then(function (d) {
            if (d && d.ok) {
                toast(d.message || 'Saved.', 'success');
                var m = bootstrap.Modal.getInstance(document.getElementById('eventModal'));
                if (m) m.hide();
                load();
            } else {
                toast((d && d.message) || 'Save failed.', 'danger');
            }
        }).catch(function () { toast('Request failed. Check your connection.', 'danger'); })
        .finally(function () { btn.classList.remove('loading'); btn.disabled = false; });
    }

    document.getElementById('eventForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validateStep(0) || !validateStep(2)) { goToStep(currentStep === 2 ? 2 : 0); return; }
        submitEvent(false);
    });

    /* ── Step Navigation ── */
    document.getElementById('evNextBtn').addEventListener('click', function () {
        if (validateStep(currentStep)) goToStep(currentStep + 1);
    });
    document.getElementById('evPrevBtn').addEventListener('click', function () { goToStep(currentStep - 1); });
    document.getElementById('evDraftBtn').addEventListener('click', function () { submitEvent(true); });

    /* ── Character Counters ── */
    function updateCharCounts() {
        var tl = document.getElementById('evTitle').value.length;
        var tc = document.getElementById('evTitleCount');
        tc.textContent = tl; tc.parentElement.className = 'ev-charcount' + (tl > 90 ? ' warn' : '') + (tl > 100 ? ' over' : '');
        var dl = document.getElementById('evDescEditor').innerText.length;
        var dc = document.getElementById('evDescCount');
        dc.textContent = dl; dc.parentElement.className = 'ev-charcount' + (dl > 900 ? ' warn' : '') + (dl > 1000 ? ' over' : '');
    }
    document.getElementById('evTitle').addEventListener('input', updateCharCounts);
    document.getElementById('evDescEditor').addEventListener('input', updateCharCounts);
    var evCharTitleCount = document.getElementById('evTitleCount');
    var evCharDescCount = document.getElementById('evDescCount');
    window.evCharTitleCount = evCharTitleCount;
    window.evCharDescCount = evCharDescCount;

    /* ── Rich Text Toolbar ── */
    document.querySelectorAll('.ev-richtext-toolbar button').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.execCommand(btn.dataset.cmd, false, null);
            document.getElementById('evDescEditor').focus();
        });
    });

    /* ── Smart Date Logic ── */
    document.getElementById('evStartDate').addEventListener('change', function () {
        var sd = this.value;
        var ed = document.getElementById('evEndDate');
        if (!ed.value || ed.value < sd) ed.value = sd;
        ed.min = sd;
    });

    /* ── Dropzone ── */
    var dropzone = document.getElementById('evDropzone');
    var fileInput = document.getElementById('evImage');
    var preview = document.getElementById('evImgPreview');
    dropzone.addEventListener('click', function () { fileInput.click(); });
    dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('dragover'); });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault(); dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; showImagePreview(e.dataTransfer.files[0]); }
    });
    fileInput.addEventListener('change', function () { if (this.files.length) showImagePreview(this.files[0]); });
    function showImagePreview(file) {
        if (!file || !file.type.startsWith('image/')) return;
        var reader = new FileReader();
        reader.onload = function (e) { preview.querySelector('img').src = e.target.result; preview.style.display = 'block'; dropzone.classList.add('has-file'); };
        reader.readAsDataURL(file);
    }
    document.getElementById('evImgRemove').addEventListener('click', function () {
        fileInput.value = ''; preview.style.display = 'none'; dropzone.classList.remove('has-file');
    });

    /* ── Destination Autocomplete ── */
    var DESTS = <?= json_encode(array_map(fn($d) => ['id' => $d['id'], 'name' => $d['name']], $destinations), JSON_UNESCAPED_UNICODE) ?>;
    var destInput = document.getElementById('evDestInput');
    var destHidden = document.getElementById('evDest');
    var acList = document.getElementById('evAcList');
    var acIdx = -1;
    destInput.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        acIdx = -1;
        if (q.length < 1) { acList.classList.remove('show'); destHidden.value = ''; return; }
        var matches = DESTS.filter(function (d) { return d.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 6);
        if (!matches.length) { acList.innerHTML = '<div class="ev-ac-empty">No destinations found</div>'; acList.classList.add('show'); return; }
        acList.innerHTML = matches.map(function (d, i) { return '<div class="ev-ac-item" data-id="' + d.id + '" data-name="' + esc(d.name) + '">' + esc(d.name) + '</div>'; }).join('');
        acList.classList.add('show');
    });
    destInput.addEventListener('keydown', function (e) {
        var items = acList.querySelectorAll('.ev-ac-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); acIdx = Math.min(acIdx + 1, items.length - 1); items.forEach(function (it, i) { it.classList.toggle('focused', i === acIdx); }); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); acIdx = Math.max(acIdx - 1, 0); items.forEach(function (it, i) { it.classList.toggle('focused', i === acIdx); }); }
        else if (e.key === 'Enter' && acIdx >= 0) { e.preventDefault(); selectDestItem(items[acIdx]); }
        else if (e.key === 'Escape') { acList.classList.remove('show'); }
    });
    acList.addEventListener('click', function (e) {
        var item = e.target.closest('.ev-ac-item');
        if (item) selectDestItem(item);
    });
    function selectDestItem(el) {
        destHidden.value = el.dataset.id;
        destInput.value = el.dataset.name;
        acList.classList.remove('show');
    }
    document.addEventListener('click', function (e) { if (!e.target.closest('.ev-ac-wrap')) acList.classList.remove('show'); });

    /* ── Keyboard: Esc closes modal ── */
    document.getElementById('eventModal').addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { var m = bootstrap.Modal.getInstance(this); if (m) m.hide(); }
    });
    document.getElementById('addEventBtn').addEventListener('click', openAdd);

    function doAction(id, action, confirmMsg) {
        askConfirm(confirmMsg, '', function() {
            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('csrf_token', CSRF);
            fd.append('action', action);
            fd.append('event_id', id);
            postForm(fd).then(function (d) {
                if (d && d.ok) { toast(d.message || 'Done.', 'success'); load(); }
                else { toast((d && d.message) || 'Action failed.', 'danger'); }
            }).catch(function () { toast('Request failed. Check your connection.', 'danger'); });
        });
    }

    $pager.addEventListener('click', function (e) {
        var a = e.target.closest('a.page-link');
        if (!a) return;
        e.preventDefault();
        var p = parseInt(a.getAttribute('data-page'), 10);
        if (!p) return;
        state.page = p;
        load();
    });

    $body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        e.preventDefault();
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var act = btn.getAttribute('data-act');
        if (act === 'edit') { openEdit(id); return; }
        var msg = act === 'publish' ? 'Publish this event?' : (act === 'unpublish' ? 'Unpublish this event?' : (act === 'cancel' ? 'Cancel this event?' : 'Permanently delete this event?'));
        doAction(id, act + '_event', msg);
    });

    document.querySelectorAll('th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            var col = th.getAttribute('data-sort');
            if (state.sort === col) { state.dir = state.dir === 'asc' ? 'desc' : 'asc'; }
            else { state.sort = col; state.dir = (col === 'title' || col === 'destination') ? 'asc' : 'desc'; }
            state.page = 1;
            load();
        });
    });

    function resetPage() { state.page = 1; load(); }

    document.getElementById('filterDest').addEventListener('change', function () { state.destination = this.value; resetPage(); });
    document.getElementById('filterStatus').addEventListener('change', function () { state.status = this.value; resetPage(); });
    document.getElementById('filterSearch').addEventListener('input', function () {
        var v = this.value;
        clearTimeout(timer);
        timer = setTimeout(function () {
            if (state.search !== v) { state.search = v; state.page = 1; load(); }
        }, 400);
    });

    document.getElementById('clearFilters').addEventListener('click', function () {
        state.destination = ''; state.status = ''; state.search = '';
        document.getElementById('filterDest').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterSearch').value = '';
        resetPage();
    });

    document.getElementById('perPage').addEventListener('change', function () {
        state.per_page = parseInt(this.value, 10);
        state.page = 1;
        load();
    });

    document.getElementById('eventsRefresh').addEventListener('click', function () { load(); });

    updateSortIndicators();
    load();
})();
</script>

<?php }); ?>
