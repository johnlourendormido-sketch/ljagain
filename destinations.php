<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$destModel = new Destination();

$search = $_GET['search'] ?? '';
$catFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$csrf = $_SESSION['csrf_token'] ?? generate_token();

$allCategories = $destModel->getCategories();
$allGuides = [];

function dest_stats(PDO $db): array
{
    return [
        'total'        => (int)$db->query("SELECT COUNT(*) FROM destinations")->fetchColumn(),
        'active'       => (int)$db->query("SELECT COUNT(*) FROM destinations WHERE status='active'")->fetchColumn(),
        'featured'     => (int)$db->query("SELECT COUNT(*) FROM destinations WHERE featured=1")->fetchColumn(),
        'booking_open' => (int)$db->query("SELECT COUNT(*) FROM destinations WHERE booking_enabled=1")->fetchColumn(),
    ];
}

function dest_edit_form_html(array $d, array $categories, $csrf): string
{
    $d = $d + ['name' => '', 'description' => '', 'location' => '', 'category' => 'other', 'difficulty' => 'easy',
        'capacity_limit' => 0, 'max_guests_per_booking' => 10, 'available_booking_days' => 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
        'recommended_age_min' => 1, 'recommended_age_max' => 100, 'accessibility_info' => '', 'rules_regulations' => '',
        'facilities' => '', 'entrance_fee' => 0, 'package_price' => '', 'image' => '', 'gallery_images' => '',
        'video_url' => '', 'booking_enabled' => 0, 'guide_required' => 0, 'booking_cutoff_hours' => 2, 'advance_booking_days' => 1,
        'cancellation_policy' => '', 'featured' => 0, 'contact_phone' => '', 'contact_email' => '', 'latitude' => '', 'longitude' => '', 'operating_hours_open' => '', 'operating_hours_close' => ''];
    $catOpts = '';
    foreach ($categories as $ck => $cv) {
        $sel = $d['category'] === $ck ? ' selected' : '';
        $catOpts .= "<option value=\"$ck\"$sel>" . htmlspecialchars($cv) . '</option>';
    }
    $days = explode(',', $d['available_booking_days']);
    $dayCb = '';
    foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dk) {
        $chk = in_array($dk, $days) ? ' checked' : '';
        $dayCb .= '<div class="form-check"><input type="checkbox" name="booking_days[]" value="' . $dk . '" class="form-check-input" id="ed_' . $d['id'] . '_' . $dk . '"' . $chk . '><label class="form-check-label" for="ed_' . $d['id'] . '_' . $dk . '">' . $dk . '</label></div>';
    }
    $diffOpts = '';
    foreach (['easy' => 'Easy', 'moderate' => 'Moderate', 'difficult' => 'Difficult', 'extreme' => 'Extreme'] as $vk => $vl) {
        $diffOpts .= '<option value="' . $vk . '"' . ($d['difficulty'] === $vk ? ' selected' : '') . '>' . $vl . '</option>';
    }
    $gallery = $d['gallery_images'] ? json_decode($d['gallery_images'], true) : [];
    if (!is_array($gallery)) $gallery = [];
    $galleryThumbs = '';
    foreach ($gallery as $gi) {
        $galleryThumbs .= '<div class="gal-thumb" data-file="' . htmlspecialchars($gi) . '"><img src="' . dest_image_url($gi) . '" alt=""><button type="button" class="gal-rm" onclick="rmGalImg(this)"><i class="fa-solid fa-xmark"></i></button></div>';
    }
    $galleryHtml = '<input type="hidden" name="keep_gallery" id="edKeepGallery" value="' . htmlspecialchars(json_encode($gallery), ENT_QUOTES) . '">'
        . '<div class="gal-grid" id="edGalGrid">' . $galleryThumbs
        . '<label class="gal-add" onclick="document.getElementById(\'edGalInput\').click()"><i class="fa-solid fa-plus"></i><span>Add</span></label></div>'
        . '<input type="file" id="edGalInput" name="gallery[]" accept="image/*" multiple hidden>'
        . '<div class="gal-count" id="edGalCount">' . count($gallery) . ' image' . (count($gallery) !== 1 ? 's' : '') . '</div>';
    $imgHtml = !empty($d['image']) ? '<div class="mb-2"><img src="' . dest_image_url($d['image']) . '" class="rounded" style="max-height:80px;object-fit:cover;" alt=""></div>' : '';
    $videoHtml = (!empty($d['video_url']) && !str_starts_with($d['video_url'], 'http')) ? '<div class="mb-2"><video controls style="max-height:120px;border-radius:8px;" preload="metadata"><source src="' . dest_image_url($d['video_url']) . '"></video></div>' : '';

    return '
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">
    <input type="hidden" name="action" value="edit_destination">
    <input type="hidden" name="dest_id" value="' . (int)$d['id'] . '">
    <input type="hidden" name="existing_image" value="' . htmlspecialchars($d['image']) . '">
    <ul class="nav nav-tabs mb-3" id="editDestTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#edBasic">Basic Info</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#edLocation">Location &amp; Hours</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#edPricing">Pricing</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#edVisitor">Visitor Info</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#edBooking">Booking Settings</a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="edBasic">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="' . htmlspecialchars($d['name']) . '" required style="border-radius:10px;"></div>
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Category</label><select name="category" class="form-select" style="border-radius:10px;">' . $catOpts . '</select></div>
                <div class="col-12"><label class="form-label fw-semibold" style="font-size:.82rem;">Description</label><textarea name="description" class="form-control" rows="4" style="border-radius:10px;">' . htmlspecialchars($d['description']) . '</textarea></div>
                <div class="col-12">
                    <label class="form-label fw-semibold" style="font-size:.82rem;">Video <small class="text-muted fw-normal">URL or upload a file</small></label>
                    <input type="url" name="video_url" class="form-control mb-2" value="' . htmlspecialchars($d['video_url']) . '" placeholder="https://www.youtube.com/watch?v=..." style="border-radius:10px;">' . $videoHtml . '
                    <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/quicktime" style="border-radius:10px;font-size:.85rem;">
                    <small class="text-muted" style="font-size:.72rem;">MP4, WebM, or MOV (max 50MB). File overrides URL if both provided.</small>
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Cover Image</label>' . $imgHtml . '<input type="file" name="image" class="form-control" accept="image/*" style="border-radius:10px;"></div>
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Gallery Images</label>' . $galleryHtml . '</div>
                <div class="col-12"><div class="form-check"><input type="checkbox" name="featured" class="form-check-input" id="edFeat"' . ($d['featured'] ? ' checked' : '') . '><label class="form-check-label" for="edFeat">Mark as Featured Destination</label></div></div>
            </div>
        </div>
        <div class="tab-pane fade" id="edLocation">
            <div class="row g-3">
                <div class="col-12"><label class="form-label fw-semibold" style="font-size:.82rem;">Address <span class="text-danger">*</span></label><input type="text" name="location" class="form-control" value="' . htmlspecialchars($d['location']) . '" required style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Latitude</label><input type="text" name="latitude" class="form-control" value="' . htmlspecialchars($d['latitude']) . '" placeholder="e.g., 10.1234567" style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Longitude</label><input type="text" name="longitude" class="form-control" value="' . htmlspecialchars($d['longitude']) . '" placeholder="e.g., 122.1234567" style="border-radius:10px;"></div>
                <div class="col-md-4"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Contact Phone</label><input type="text" name="contact_phone" class="form-control" value="' . htmlspecialchars($d['contact_phone']) . '" style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Contact Email</label><input type="email" name="contact_email" class="form-control" value="' . htmlspecialchars($d['contact_email']) . '" style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Operating Hours Open</label><input type="time" name="operating_hours_open" class="form-control" value="' . htmlspecialchars($d['operating_hours_open']) . '" style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Operating Hours Close</label><input type="time" name="operating_hours_close" class="form-control" value="' . htmlspecialchars($d['operating_hours_close']) . '" style="border-radius:10px;"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="edPricing">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Entrance Fee (₱)</label><input type="number" name="entrance_fee" class="form-control" value="' . (float)$d['entrance_fee'] . '" min="0" step="0.01" style="border-radius:10px;"></div>
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Package Price (₱) <small class="text-muted">Optional</small></label><input type="number" name="package_price" class="form-control" value="' . htmlspecialchars($d['package_price']) . '" min="0" step="0.01" style="border-radius:10px;"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="edVisitor">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Max Visitors/Day</label><input type="number" name="capacity" class="form-control" value="' . (int)$d['capacity_limit'] . '" min="0" style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Max Guests Per Booking</label><input type="number" name="max_guests_per_booking" class="form-control" value="' . (int)$d['max_guests_per_booking'] . '" min="1" style="border-radius:10px;"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem;">Difficulty</label><select name="difficulty" class="form-select" style="border-radius:10px;">' . $diffOpts . '</select></div>
                <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Min Age</label><input type="number" name="age_min" class="form-control" value="' . max(1, (int)$d['recommended_age_min']) . '" min="1" style="border-radius:10px;"></div>
                <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Max Age</label><input type="number" name="age_max" class="form-control" value="' . (int)$d['recommended_age_max'] . '" min="1" style="border-radius:10px;"></div>
                <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Available Booking Days</label><div class="d-flex flex-wrap gap-2">' . $dayCb . '</div></div>
                <div class="col-12"><label class="form-label fw-semibold" style="font-size:.82rem;">Accessibility Info</label><textarea name="accessibility" class="form-control" rows="2" style="border-radius:10px;">' . htmlspecialchars($d['accessibility_info']) . '</textarea></div>
                <div class="col-12"><label class="form-label fw-semibold" style="font-size:.82rem;">Rules &amp; Regulations</label><textarea name="rules_regulations" class="form-control" rows="3" style="border-radius:10px;">' . htmlspecialchars($d['rules_regulations']) . '</textarea></div>
                <div class="col-12"><label class="form-label fw-semibold" style="font-size:.82rem;">Facilities</label><textarea name="facilities" class="form-control" rows="2" style="border-radius:10px;">' . htmlspecialchars($d['facilities']) . '</textarea></div>
            </div>
        </div>
        <div class="tab-pane fade" id="edBooking">
            <div class="row g-3">
                <div class="col-12">
                    <div class="p-3 rounded-3 mb-3" style="background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e2e8f0);">
                        <h6 class="fw-bold mb-3" style="font-size:.88rem;"><i class="fa-solid fa-toggle-on me-2" style="color:#10b981;"></i>Booking Availability</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2"><input type="checkbox" name="booking_enabled" class="form-check-input" id="edBkEn"' . ($d['booking_enabled'] ? ' checked' : '') . '><label class="form-check-label fw-semibold" for="edBkEn" style="font-size:.85rem;">Enable Online Booking</label></div>
                                <div class="form-check form-switch"><input type="checkbox" name="guide_required" class="form-check-input" id="edGdReq"' . ($d['guide_required'] ? ' checked' : '') . '><label class="form-check-label fw-semibold" for="edGdReq" style="font-size:.85rem;">Require Tour Guide</label></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="p-3 rounded-3 mb-3" style="background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e2e8f0);">
                        <h6 class="fw-bold mb-3" style="font-size:.88rem;"><i class="fa-solid fa-clock me-2" style="color:#3b82f6;"></i>Time Restrictions</h6>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Booking Cut-off</label><div class="input-group"><input type="number" name="booking_cutoff_hours" class="form-control" value="' . (int)$d['booking_cutoff_hours'] . '" min="0" style="border-radius:10px 0 0 10px;"><span class="input-group-text" style="border-radius:0 10px 10px 0;background:var(--card-bg,#fff);border-color:var(--border-color,#dee2e6);">hours before visit</span></div></div>
                            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.82rem;">Advance Booking Requirement</label><div class="input-group"><input type="number" name="advance_booking_days" class="form-control" value="' . (int)$d['advance_booking_days'] . '" min="0" style="border-radius:10px 0 0 10px;"><span class="input-group-text" style="border-radius:0 10px 10px 0;background:var(--card-bg,#fff);border-color:var(--border-color,#dee2e6);">day(s) in advance</span></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-12"><div class="p-3 rounded-3" style="background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e2e8f0);"><h6 class="fw-bold mb-3" style="font-size:.88rem;"><i class="fa-solid fa-file-contract me-2" style="color:#f59e0b;"></i>Cancellation Policy</h6><textarea name="cancellation_policy" class="form-control" rows="3" placeholder="e.g., Free cancellation up to 24 hours before the visit. No refund for no-shows." style="border-radius:10px;">' . htmlspecialchars($d['cancellation_policy']) . '</textarea></div></div>
            </div>
        </div>
    </div>
    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
        <button type="submit" class="btn btn-brand" style="border-radius:10px;font-weight:600;"><i class="fa-solid fa-save me-1"></i>Save Changes</button>
    </div>
</form>';
}

// ── AJAX GET: edit form endpoint ────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && ($_GET['mode'] ?? '') === 'form') {
    header('Content-Type: application/json; charset=utf-8');
    $did = (int)($_GET['id'] ?? 0);
    $d = $did ? $destModel->findById($did) : null;
    if (!$d) {
        echo json_encode(['ok' => false, 'message' => 'Destination not found.']);
        exit;
    }
    echo json_encode(['ok' => true, 'html' => dest_edit_form_html($d, $allCategories, $csrf)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX GET: list ──────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $qPage = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 15);
    if (!in_array($perPage, [10, 15, 25, 50], true)) $perPage = 15;
    $qSearch = trim($_GET['search'] ?? '');
    $qCat = $_GET['category'] ?? '';
    $qStatus = $_GET['status'] ?? '';

    $where = [];
    $params = [];
    if ($qSearch) { $where[] = "(d.name LIKE :s1 OR d.location LIKE :s2)"; $params[':s1'] = "%$qSearch%"; $params[':s2'] = "%$qSearch%"; }
    if ($qCat) { $where[] = "d.category = :cat"; $params[':cat'] = $qCat; }
    if ($qStatus) { $where[] = "d.status = :status"; $params[':status'] = $qStatus; }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) as c FROM destinations d $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['c'];
    $pages = max(1, ceil($total / $perPage));
    if ($qPage > $pages) $qPage = $pages;
    $offset = ($qPage - 1) * $perPage;

    $stmt = $db->prepare("SELECT d.* FROM destinations d $whereClause ORDER BY d.featured DESC, d.name ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = array_map(function ($d) {
        return [
            'id'             => (int)$d['id'],
            'name'           => $d['name'] ?? '',
            'category'       => $d['category'] ?? 'other',
            'location'       => $d['location'] ?? '',
            'entrance_fee'   => (float)($d['entrance_fee'] ?? 0),
            'booking_enabled'=> (int)($d['booking_enabled'] ?? 0),
            'featured'       => (int)($d['featured'] ?? 0),
            'status'         => $d['status'] ?? 'inactive',
            'image_url'      => dest_image_url($d['image'] ?? ''),
        ];
    }, $stmt->fetchAll());

    echo json_encode([
        'rows'     => $rows,
        'total'    => $total,
        'pages'    => $pages,
        'page'     => $qPage,
        'per_page' => $perPage,
        'stats'    => dest_stats($db),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST ────────────────────────────────────────────────────
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
        redirect('/admin/destinations.php?' . http_build_query($_GET));
    };

    if (!verify_token($_POST['csrf_token'] ?? null)) {
        $respond(false, 'Invalid security token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_featured' && isset($_POST['dest_id'])) {
        $did = (int)$_POST['dest_id'];
        $destModel->toggleFeatured($did);
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Toggled featured for destination #{$did}");
        $respond(true, 'Featured status updated.');
    }

    if ($action === 'toggle_status' && isset($_POST['dest_id'])) {
        $did = (int)$_POST['dest_id'];
        $destModel->toggleStatus($did);
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Toggled status for destination #{$did}");
        $respond(true, 'Destination status updated.');
    }

    if ($action === 'set_status' && isset($_POST['dest_id'], $_POST['new_status'])) {
        $did = (int)$_POST['dest_id'];
        $newStatus = in_array($_POST['new_status'], ['active', 'inactive', 'closed', 'maintenance'], true) ? $_POST['new_status'] : '';
        if (!$newStatus) $respond(false, 'Invalid status.');
        $db->prepare("UPDATE destinations SET status = :s WHERE id = :id")->execute([':s' => $newStatus, ':id' => $did]);
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Set destination #{$did} status to {$newStatus}");
        $respond(true, 'Destination status updated to ' . ucfirst($newStatus) . '.');
    }

    if ($action === 'toggle_booking' && isset($_POST['dest_id'])) {
        $did = (int)$_POST['dest_id'];
        $destModel->toggleBooking($did);
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Toggled booking for destination #{$did}");
        $respond(true, 'Booking availability updated.');
    }

    if ($action === 'delete_destination' && isset($_POST['dest_id'])) {
        $did = (int)$_POST['dest_id'];
        $destModel->delete($did);
        ActivityLog::log($_SESSION['user_id'], 'destination_delete', 'Deleted destination #' . $did);
        $respond(true, 'Destination deleted.');
    }

    if ($action === 'bulk_delete') {
        $ids = array_filter(array_map('intval', (array)($_POST['dest_ids'] ?? [])));
        if (empty($ids)) $respond(false, 'No destinations selected.');
        foreach ($ids as $id) $destModel->delete($id);
        ActivityLog::log($_SESSION['user_id'], 'destination_delete', "Bulk deleted " . count($ids) . " destinations");
        $respond(true, count($ids) . ' destination(s) deleted.');
    }

    if ($action === 'bulk_featured' && isset($_POST['value'])) {
        $ids = array_filter(array_map('intval', (array)($_POST['dest_ids'] ?? [])));
        $val = $_POST['value'] === '1' ? 1 : 0;
        if (empty($ids)) $respond(false, 'No destinations selected.');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE destinations SET featured = ? WHERE id IN ($ph)")->execute(array_merge([$val], $ids));
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Bulk set featured={$val} for " . count($ids) . " destinations");
        $respond(true, count($ids) . ' destination(s) ' . ($val ? 'featured' : 'unfeatured') . '.');
    }

    if ($action === 'bulk_booking' && isset($_POST['value'])) {
        $ids = array_filter(array_map('intval', (array)($_POST['dest_ids'] ?? [])));
        $val = $_POST['value'] === '1' ? 1 : 0;
        if (empty($ids)) $respond(false, 'No destinations selected.');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE destinations SET booking_enabled = ? WHERE id IN ($ph)")->execute(array_merge([$val], $ids));
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Bulk set booking={$val} for " . count($ids) . " destinations");
        $respond(true, 'Booking ' . ($val ? 'opened' : 'closed') . ' for ' . count($ids) . ' destination(s).');
    }

    if ($action === 'add_destination') {
        // Full add form (non-AJAX) — original logic
        $imagePath = '';
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = upload_file($_FILES['image'], 'destinations', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) $imagePath = $upload['filename'];
            else { $respond(false, 'Image upload failed: ' . $upload['message']); }
        }
        $videoPath = '';
        if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $vExt = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($vExt, ['mp4', 'webm', 'mov'])) $respond(false, 'Video type not allowed. Use MP4, WebM, or MOV.');
            if ($_FILES['video_file']['size'] > 50 * 1024 * 1024) $respond(false, 'Video file exceeds 50MB limit.');
            $vDir = __DIR__ . '/../uploads/destinations';
            if (!is_dir($vDir)) mkdir($vDir, 0755, true);
            $vFilename = uniqid() . '_video_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['video_file']['name']));
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $vDir . '/' . $vFilename)) $videoPath = $vFilename;
            else $respond(false, 'Failed to upload video file.');
        }
        $gallery = [];
        if (!empty($_FILES['gallery']['name'][0])) {
            foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $up = upload_file(['name' => $_FILES['gallery']['name'][$i], 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK], 'destinations', ['jpg', 'jpeg', 'png', 'webp']);
                    if ($up['success']) $gallery[] = $up['filename'];
                }
            }
        }
        $stmt = $db->prepare(
            "INSERT INTO destinations (name, description, location, contact_phone, contact_email, latitude, longitude,
                operating_hours_open, operating_hours_close, category, difficulty, capacity_limit, max_guests_per_booking,
                available_booking_days, recommended_age_min, recommended_age_max, accessibility_info, rules_regulations,
                facilities, entrance_fee, package_price, image, gallery_images, video_url, status, booking_enabled, guide_required,
                booking_cutoff_hours, advance_booking_days, cancellation_policy, featured, created_by, created_at)
             VALUES (:name, :desc, :loc, :phone, :email, :lat, :lng, :open_hr, :close_hr, :cat, :diff, :cap, :maxg,
                :days, :age_min, :age_max, :access, :rules, :fac, :fee, :pkg, :img, :gallery, :video_url, 'active', :bk_en, 0,
                :cutoff, :advance, :cancel, 0, :uid, NOW())"
        );
        $stmt->execute([
            ':name' => sanitize($_POST['name'] ?? ''), ':desc' => sanitize($_POST['description'] ?? ''),
            ':loc' => sanitize($_POST['location'] ?? ''), ':phone' => sanitize($_POST['contact_phone'] ?? ''),
            ':email' => sanitize($_POST['contact_email'] ?? ''), ':lat' => $_POST['latitude'] ?? null,
            ':lng' => $_POST['longitude'] ?? null, ':open_hr' => $_POST['operating_hours_open'] ?: null,
            ':close_hr' => $_POST['operating_hours_close'] ?: null, ':cat' => $_POST['category'] ?? 'other',
            ':diff' => $_POST['difficulty'] ?? 'easy', ':cap' => (int)($_POST['capacity'] ?? 0),
            ':maxg' => (int)($_POST['max_guests_per_booking'] ?? 10),
            ':days' => !empty($_POST['booking_days']) ? implode(',', $_POST['booking_days']) : 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            ':age_min' => max(1, (int)($_POST['age_min'] ?? 1)), ':age_max' => (int)($_POST['age_max'] ?? 100),
            ':access' => sanitize($_POST['accessibility'] ?? ''), ':rules' => sanitize($_POST['rules_regulations'] ?? ''),
            ':fac' => sanitize($_POST['facilities'] ?? ''), ':fee' => (float)($_POST['entrance_fee'] ?? 0),
            ':pkg' => $_POST['package_price'] ? (float)$_POST['package_price'] : null,
            ':img' => $imagePath,
            ':gallery' => !empty($gallery) ? json_encode($gallery) : null,
            ':video_url' => $videoPath ?: sanitize($_POST['video_url'] ?? ''),
            ':bk_en' => isset($_POST['booking_enabled']) ? 1 : 0,
            ':cutoff' => (int)($_POST['booking_cutoff_hours'] ?? 2), ':advance' => (int)($_POST['advance_booking_days'] ?? 1),
            ':cancel' => sanitize($_POST['cancellation_policy'] ?? ''), ':uid' => $_SESSION['user_id'],
        ]);
        ActivityLog::log($_SESSION['user_id'], 'destination_add', 'Added destination: ' . ($_POST['name'] ?? ''));
        $respond(true, 'Destination added successfully.');
    }

    if ($action === 'edit_destination' && isset($_POST['dest_id'])) {
        // Full edit form (non-AJAX) — original logic
        $did = (int)$_POST['dest_id'];
        $existing = $destModel->findById($did);
        if (!$existing) $respond(false, 'Destination not found.');
        $imagePath = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = upload_file($_FILES['image'], 'destinations', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) $imagePath = $upload['filename'];
            else $respond(false, 'Image upload failed: ' . $upload['message']);
        }
        $gallery = !empty($_POST['keep_gallery']) ? json_decode($_POST['keep_gallery'], true) : [];
        if (!is_array($gallery)) $gallery = [];
        if (!empty($_FILES['gallery']['name'][0])) {
            foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $up = upload_file(['name' => $_FILES['gallery']['name'][$i], 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK], 'destinations', ['jpg', 'jpeg', 'png', 'webp']);
                    if ($up['success']) $gallery[] = $up['filename'];
                }
            }
        }
        $videoPath = '';
        if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $vExt = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($vExt, ['mp4', 'webm', 'mov'])) $respond(false, 'Video type not allowed. Use MP4, WebM, or MOV.');
            if ($_FILES['video_file']['size'] > 50 * 1024 * 1024) $respond(false, 'Video file exceeds 50MB limit.');
            $vDir = __DIR__ . '/../uploads/destinations';
            if (!is_dir($vDir)) mkdir($vDir, 0755, true);
            $vFilename = uniqid() . '_video_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['video_file']['name']));
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $vDir . '/' . $vFilename)) $videoPath = $vFilename;
            else $respond(false, 'Failed to upload video file.');
        }
        $stmt = $db->prepare(
            "UPDATE destinations SET name=:name, description=:desc, location=:loc, contact_phone=:phone, contact_email=:email,
                latitude=:lat, longitude=:lng, operating_hours_open=:open_hr, operating_hours_close=:close_hr,
                category=:cat, difficulty=:diff, capacity_limit=:cap, max_guests_per_booking=:maxg,
                available_booking_days=:days, recommended_age_min=:age_min, recommended_age_max=:age_max,
                accessibility_info=:access, rules_regulations=:rules, facilities=:fac,
                entrance_fee=:fee, package_price=:pkg, image=:img, gallery_images=:gallery,
                video_url=:video_url, booking_enabled=:bk_en, guide_required=0, booking_cutoff_hours=:cutoff,
                advance_booking_days=:advance, cancellation_policy=:cancel, featured=:featured, updated_at=NOW()
             WHERE id=:id"
        );
        $stmt->execute([
            ':id' => $did, ':name' => sanitize($_POST['name'] ?? ''), ':desc' => sanitize($_POST['description'] ?? ''),
            ':loc' => sanitize($_POST['location'] ?? ''), ':phone' => sanitize($_POST['contact_phone'] ?? ''),
            ':email' => sanitize($_POST['contact_email'] ?? ''), ':lat' => $_POST['latitude'] ?? null,
            ':lng' => $_POST['longitude'] ?? null, ':open_hr' => $_POST['operating_hours_open'] ?: null,
            ':close_hr' => $_POST['operating_hours_close'] ?: null, ':cat' => $_POST['category'] ?? 'other',
            ':diff' => $_POST['difficulty'] ?? 'easy', ':cap' => (int)($_POST['capacity'] ?? 0),
            ':maxg' => (int)($_POST['max_guests_per_booking'] ?? 10),
            ':days' => !empty($_POST['booking_days']) ? implode(',', $_POST['booking_days']) : 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            ':age_min' => max(1, (int)($_POST['age_min'] ?? 1)), ':age_max' => (int)($_POST['age_max'] ?? 100),
            ':access' => sanitize($_POST['accessibility'] ?? ''), ':rules' => sanitize($_POST['rules_regulations'] ?? ''),
            ':fac' => sanitize($_POST['facilities'] ?? ''), ':fee' => (float)($_POST['entrance_fee'] ?? 0),
            ':pkg' => $_POST['package_price'] ? (float)$_POST['package_price'] : null,
            ':img' => $imagePath,
            ':gallery' => !empty($gallery) ? json_encode($gallery) : null,
            ':video_url' => $videoPath ?: sanitize($_POST['video_url'] ?? ''),
            ':bk_en' => isset($_POST['booking_enabled']) ? 1 : 0,
            ':cutoff' => (int)($_POST['booking_cutoff_hours'] ?? 2), ':advance' => (int)($_POST['advance_booking_days'] ?? 1),
            ':cancel' => sanitize($_POST['cancellation_policy'] ?? ''),
            ':featured' => isset($_POST['featured']) ? 1 : 0,
        ]);
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', 'Edited destination #' . $did);
        $respond(true, 'Destination updated successfully.');
    }

    // Season + guide management (non-AJAX forms)
    if ($action === 'add_season' && isset($_POST['dest_id'])) {
        $did = (int)$_POST['dest_id'];
        $startMonth = (int)($_POST['start_month'] ?? 1);
        $endMonth = (int)($_POST['end_month'] ?? 12);
        $months = $startMonth === $endMonth ? (string)$startMonth : $startMonth . '-' . $endMonth;
        $db->prepare("INSERT INTO destination_seasons (destination_id, season_type, months, description, created_at) VALUES (:dest_id, :season_type, :months, :description, NOW())")
            ->execute([':dest_id' => $did, ':season_type' => $_POST['season_type'] ?? 'peak', ':months' => $months, ':description' => sanitize($_POST['season_description'] ?? '')]);
        ActivityLog::log($_SESSION['user_id'], 'destination_edit', "Added season for destination #{$did}");
        $respond(true, 'Season added.');
    }

    if ($action === 'delete_season' && isset($_POST['season_id'], $_POST['dest_id'])) {
        $sid = (int)$_POST['season_id'];
        $db->prepare("DELETE FROM destination_seasons WHERE id = :id")->execute([':id' => $sid]);
        $respond(true, 'Season removed.');
    }

    if ($action === 'assign_guide' && isset($_POST['dest_id'], $_POST['guide_id'])) {
        $destModel->assignGuide((int)$_POST['dest_id'], (int)$_POST['guide_id'], isset($_POST['is_primary']));
        $respond(true, 'Guide assigned.');
    }

    if ($action === 'remove_guide' && isset($_POST['dest_id'], $_POST['guide_id'])) {
        $destModel->removeGuide((int)$_POST['dest_id'], (int)$_POST['guide_id']);
        $respond(true, 'Guide removed.');
    }

    if ($action === 'set_primary_guide' && isset($_POST['dest_id'], $_POST['guide_id'])) {
        $destModel->setPrimaryGuide((int)$_POST['dest_id'], (int)$_POST['guide_id']);
        $respond(true, 'Primary guide updated.');
    }

    $respond(false, 'Unknown action.');
}

$stats = dest_stats($db);
$manageSeasons = isset($_GET['manage_seasons']) ? (int)$_GET['manage_seasons'] : null;
$seasonDest = $manageSeasons ? $destModel->findWithSeasons($manageSeasons) : null;
$manageGuides = isset($_GET['manage_guides']) ? (int)$_GET['manage_guides'] : null;
$guideDest = $manageGuides ? $destModel->findById($manageGuides) : null;
$assignedGuides = $manageGuides ? $destModel->getAssignedGuides($manageGuides) : [];

render_page('admin', 'destinations.php', 'Destination Management', function () use ($stats, $search, $catFilter, $statusFilter, $csrf, $allCategories, $allGuides, $manageSeasons, $seasonDest, $manageGuides, $guideDest, $assignedGuides, $db, $destModel) {

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<style>
    /* ── KPI Cards ──────────────────────────────────────────── */
    .kpi-card {
        border: none;
        border-radius: 16px;
        background: var(--card-bg, #fff);
        cursor: pointer;
        transition: all .25s cubic-bezier(.4,0,.2,1);
        box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.06);
        position: relative;
        overflow: hidden;
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, var(--brand, #0c6e5e));
        opacity: 0;
        transition: opacity .25s;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,.08); }
    .kpi-card:hover::before { opacity: 1; }
    .kpi-card.active { border: none; box-shadow: 0 0 0 3px rgba(12,110,94,.15), 0 4px 12px rgba(0,0,0,.06); }
    .kpi-card.active::before { opacity: 1; }
    .kpi-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        transition: transform .25s;
    }
    .kpi-card:hover .kpi-icon { transform: scale(1.08); }
    .kpi-num { font-size: 1.75rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
    .kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; color: var(--text-muted, #94a3b8); margin-top: 2px; }

    /* ── Filter Toolbar ─────────────────────────────────────── */
    .sticky-filter { position: sticky; top: 70px; z-index: 30; }
    .filter-card { border: none; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.06); }
    .filter-card .card-body { padding: 12px 16px; }
    .search-wrap { position: relative; }
    .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted, #94a3b8); font-size: .82rem; }
    .search-wrap input { padding-left: 36px; border-radius: 10px; border-color: var(--border-color, #e2e8f0); font-size: .84rem; }
    .search-wrap input:focus { border-color: var(--brand, #0c6e5e); box-shadow: 0 0 0 3px rgba(12,110,94,.08); }
    .filter-select { border-radius: 10px; border-color: var(--border-color, #e2e8f0); font-size: .84rem; }
    .filter-select:focus { border-color: var(--brand, #0c6e5e); box-shadow: 0 0 0 3px rgba(12,110,94,.08); }
    .filter-chip {
        font-size: .72rem; font-weight: 600;
        background: rgba(12,110,94,.08); color: var(--brand, #0c6e5e);
        border-radius: 20px; padding: 3px 12px;
        display: inline-flex; align-items: center; gap: 6px;
        transition: background .2s;
    }
    .filter-chip:hover { background: rgba(12,110,94,.14); }
    .filter-chip a { color: inherit; opacity: .6; transition: opacity .2s; }
    .filter-chip a:hover { opacity: 1; }

    /* ── Table ──────────────────────────────────────────────── */
    .dest-table-card { border: none; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.06); overflow: hidden; }
    .dest-table { border-collapse: separate; border-spacing: 0; }
    .dest-table thead th {
        font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        color: var(--text-muted, #94a3b8); background: var(--card-bg, #fff);
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        padding: 12px 14px; white-space: nowrap;
    }
    .dest-table tbody tr { transition: background .15s; }
    .dest-table tbody tr:hover td { background: rgba(12,110,94,.02); }
    .dest-table tbody td {
        padding: 12px 14px; border-bottom: 1px solid var(--border-color, #f1f5f9);
        font-size: .84rem; vertical-align: middle;
    }
    .dest-table tbody tr:last-child td { border-bottom: none; }
    .dest-thumb { width: 44px; height: 38px; border-radius: 10px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--border-color, #e2e8f0); }
    .id-badge {
        display: inline-flex; align-items: center; justify-content: center;
        font-family: 'SF Mono', 'Cascadia Code', 'Consolas', monospace;
        font-size: .72rem; font-weight: 600;
        background: var(--border-color, #f1f5f9); color: var(--text-muted, #64748b);
        padding: 3px 10px; border-radius: 8px;
    }
    .cat-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; border-radius: 8px;
        font-size: .72rem; font-weight: 600;
        background: rgba(12,110,94,.08); color: var(--brand, #0c6e5e);
    }
    .fee-text { font-weight: 700; font-size: .84rem; color: var(--text-body, #1e293b); }
    .status-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 8px;
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
        cursor: pointer; transition: all .2s;
    }
    .status-chip:hover { filter: brightness(.95); }
    .star-toggle { background: none; border: none; font-size: 1rem; cursor: pointer; transition: all .2s; padding: 4px; border-radius: 6px; }
    .star-toggle:hover { transform: scale(1.15); background: rgba(245,158,11,.08); }
    .action-btn {
        width: 32px; height: 32px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .78rem; border: 1px solid var(--border-color, #e2e8f0);
        background: var(--card-bg, #fff); color: var(--text-muted, #64748b);
        cursor: pointer; transition: all .2s; text-decoration: none;
    }
    .action-btn:hover { background: var(--border-color, #f8fafc); border-color: var(--brand, #0c6e5e); color: var(--brand, #0c6e5e); }
    .action-btn.danger:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }

    /* ── Status select ──────────────────────────────────────── */
    .status-sel { border-radius: 8px; font-size: .78rem; font-weight: 600; border-color: var(--border-color, #e2e8f0); padding: 4px 8px; }
    .status-sel:focus { border-color: var(--brand, #0c6e5e); box-shadow: 0 0 0 2px rgba(12,110,94,.08); }

    /* ── Skeleton loading ───────────────────────────────────── */
    .skeleton { background: linear-gradient(90deg, rgba(130,130,130,.06) 25%, rgba(130,130,130,.14) 37%, rgba(130,130,130,.06) 63%); background-size: 400% 100%; animation: shimmer 1.4s ease infinite; border-radius: 8px; }
    @keyframes shimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }

    /* ── Bulk actions ───────────────────────────────────────── */
    .bulk-bar {
        display: none; align-items: center; gap: 10px;
        border: 1px solid var(--brand, #0c6e5e);
        background: rgba(12,110,94,.06);
        border-radius: 12px; padding: 8px 14px;
        font-size: .84rem;
    }
    .bulk-bar.show { display: flex; }

    /* ── Pagination ─────────────────────────────────────────── */
    .pager { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .pager .btn { border-radius: 8px; font-size: .8rem; font-weight: 600; min-width: 34px; }
    .pager .btn.active { background: var(--brand, #0c6e5e); color: #fff; border-color: var(--brand, #0c6e5e); }

    /* ── Toast ──────────────────────────────────────────────── */
    .toast-container { z-index: 9999; }

    /* ── Page header actions ────────────────────────────────── */
    .page-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .refresh-group {
        display: inline-flex; align-items: center; gap: 0;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 10px;
        padding: 3px;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .refresh-btn {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border: none; border-radius: 7px;
        background: var(--bg-secondary, #f8fafc);
        color: var(--text-muted, #64748b);
        font-size: .8rem;
        cursor: pointer;
        transition: all .2s;
        flex-shrink: 0;
    }
    .refresh-btn:hover { background: var(--brand, #008075); color: #fff; }
    .refresh-btn:disabled { opacity: .5; pointer-events: none; }
    .refresh-divider { width: 1px; height: 20px; background: var(--border-color, #e2e8f0); margin: 0 6px; flex-shrink: 0; }
    .last-updated-wrap {
        display: inline-flex; align-items: center; gap: 6px;
        padding-right: 10px;
        white-space: nowrap;
    }
    .last-updated-wrap .dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.15);
        flex-shrink: 0; animation: pulse-dot 2s infinite;
    }
    @keyframes pulse-dot { 0%,100% { box-shadow: 0 0 0 3px rgba(34,197,94,.15);} 50% { box-shadow: 0 0 0 5px rgba(34,197,94,.07);} }
    .last-updated-text {
        font-size: .78rem; font-weight: 500;
        color: var(--text-muted, #64748b);
    }
    .btn-add-dest {
        display: inline-flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, #008075 0%, #00665c 100%);
        color: #fff; border: none;
        padding: 8px 16px 8px 10px;
        border-radius: 10px;
        font-size: .84rem; font-weight: 700;
        letter-spacing: .01em;
        box-shadow: 0 2px 8px rgba(0,128,117,.28), 0 1px 2px rgba(0,0,0,.06);
        cursor: pointer;
        transition: all .2s cubic-bezier(.4,0,.2,1);
        white-space: nowrap;
    }
    .btn-add-dest:hover { background: linear-gradient(135deg, #007a70 0%, #005a52 100%); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,128,117,.32); color: #fff; }
    .btn-add-dest:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(0,128,117,.28); }
    .btn-add-dest .add-icon {
        width: 22px; height: 22px;
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.2);
        border-radius: 7px;
        font-size: .7rem;
    }

    /* ── Modal ──────────────────────────────────────────────── */
    .modal-xl .modal-content { border: none; border-radius: 16px; }

    /* ── Dest Stepper ─────────────────────────────────────── */
    .dest-stepper{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:24px;padding:16px 0}
    .dest-step{display:flex;flex-direction:column;align-items:center;gap:4px;position:relative;cursor:default;min-width:60px}
    .dest-step .step-num{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;background:var(--border-color,#e2e8f0);color:var(--text-muted,#94a3b8);transition:all .3s;border:2px solid transparent}
    .dest-step.active .step-num{background:#0c6e5e;color:#fff;border-color:#0c6e5e;box-shadow:0 0 0 4px rgba(12,110,94,.15)}
    .dest-step.completed .step-num{background:#10b981;color:#fff;border-color:#10b981}
    .dest-step span{font-size:.68rem;font-weight:600;color:var(--text-muted,#94a3b8);transition:color .3s;white-space:nowrap}
    .dest-step.active span,.dest-step.completed span{color:var(--text-body,#1e293b)}
    .dest-step-line{flex:1;height:2px;background:var(--border-color,#e2e8f0);min-width:20px;max-width:60px;transition:background .3s;margin:0 4px;align-self:flex-start;margin-top:15px}
    .dest-step-line.filled{background:#10b981}

    /* ── Field Label ──────────────────────────────────────── */
    .dest-field-label{font-size:.82rem}

    /* ── Validation ───────────────────────────────────────── */
    .dest-input.is-invalid{border-color:#ef4444!important;box-shadow:0 0 0 3px rgba(239,68,68,.1)!important}
    .invalid-feedback{font-size:.74rem;display:none}
    .dest-input.is-invalid ~ .invalid-feedback{display:block}

    /* ── Character Counter ────────────────────────────────── */
    .dest-char-count{font-size:.72rem;color:var(--text-muted,#94a3b8);text-align:right;margin-top:4px}
    .dest-char-count.warn{color:#f59e0b}
    .dest-char-count.over{color:#ef4444;font-weight:600}

    /* ── Image Preview ────────────────────────────────────── */
    .dest-img-preview{display:flex;flex-wrap:wrap;gap:8px}
    .dest-img-preview:empty{display:none}
    .dest-img-thumb{position:relative;width:80px;height:80px;border-radius:10px;overflow:hidden;border:2px solid var(--border-color,#e2e8f0);flex-shrink:0}
    .dest-img-thumb img{width:100%;height:100%;object-fit:cover}
    .dest-img-thumb .dest-img-rm{position:absolute;top:4px;right:4px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,.55);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:.6rem;cursor:pointer;opacity:0;transition:opacity .15s}
    .dest-img-thumb:hover .dest-img-rm{opacity:1}

    /* ── Address Autocomplete ─────────────────────────────── */
    .dest-autocomplete-wrap{position:relative}
    .dest-ac-dropdown{position:absolute;top:100%;left:0;right:0;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:200;max-height:220px;overflow-y:auto;display:none;margin-top:4px}
    .dest-ac-dropdown.show{display:block}
    .dest-ac-item{padding:10px 14px;cursor:pointer;font-size:.84rem;border-bottom:1px solid var(--border-color,#f1f5f9);transition:background .1s}
    .dest-ac-item:last-child{border-bottom:none}
    .dest-ac-item:hover,.dest-ac-item.focused{background:rgba(12,110,94,.06)}
    .dest-ac-item small{display:block;color:var(--text-muted,#94a3b8);font-size:.72rem;margin-top:2px}
    .dest-ac-loading{padding:12px;text-align:center;color:var(--text-muted,#94a3b8);font-size:.82rem}

    /* ── Mini Map ─────────────────────────────────────────── */
    .dest-mini-map-wrap{border:1px solid var(--border-color,#e2e8f0);border-radius:12px;overflow:hidden;background:var(--border-color,#f1f5f9)}
    .dest-mini-map{width:100%;height:160px}
    .dest-mini-map-label{padding:6px 12px;font-size:.72rem;color:var(--text-muted,#64748b);background:var(--card-bg,#fff);border-top:1px solid var(--border-color,#f1f5f9)}
    [data-theme="dark"] .dest-mini-map-wrap{border-color:#1e293b;background:#0f172a}
    [data-theme="dark"] .dest-mini-map-label{background:#1e293b;border-color:#1e293b}

    /* ── Sticky Footer ────────────────────────────────────── */
    .dest-modal-footer{border-top:1px solid var(--border-color,#e2e8f0);padding:12px 24px;background:var(--card-bg,#fff);border-radius:0 0 16px 16px;position:sticky;bottom:0;z-index:10}

    /* ── Nav Buttons ──────────────────────────────────────── */
    .dest-nav-btn{border-radius:10px;font-weight:600;font-size:.84rem;padding:8px 20px}
    .btn-brand{background:linear-gradient(135deg,#008075,#00665c);color:#fff;border:none}
    .btn-brand:hover{background:linear-gradient(135deg,#007a70,#005a52);color:#fff}
    .dest-submit-btn{border-radius:10px;font-weight:600;font-size:.84rem;padding:8px 20px}

    /* ── Dark mode additions ──────────────────────────────── */
    [data-theme="dark"] .dest-step .step-num{background:#1e293b;color:#94a3b8}
    [data-theme="dark"] .dest-step.active .step-num{background:#0c6e5e;color:#fff}
    [data-theme="dark"] .dest-step.completed .step-num{background:#10b981;color:#fff}
    [data-theme="dark"] .dest-step-line{background:#334155}
    [data-theme="dark"] .dest-step-line.filled{background:#10b981}
    [data-theme="dark"] .dest-ac-dropdown{background:#1e293b;border-color:#334155}
    [data-theme="dark"] .dest-ac-item{border-color:#1e293b;color:#e2e8f0}
    [data-theme="dark"] .dest-ac-item:hover{background:rgba(12,110,94,.15)}
    [data-theme="dark"] .dest-modal-footer{background:#0f172a;border-color:#1e293b}

    /* ── Gallery Uploader ───────────────────────────────────── */
    .gal-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}
    .gal-grid:empty{display:none}
    .gal-thumb{position:relative;width:88px;height:88px;border-radius:10px;overflow:hidden;border:2px solid var(--border-color,#e2e8f0);flex-shrink:0;transition:transform .15s,box-shadow .15s}
    .gal-thumb:hover{transform:scale(1.04);box-shadow:0 2px 8px rgba(0,0,0,.12)}
    .gal-thumb img,.gal-thumb video{width:100%;height:100%;object-fit:cover;display:block}
    .gal-thumb .gal-rm{position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.55);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:.65rem;cursor:pointer;opacity:0;transition:opacity .15s}
    .gal-thumb:hover .gal-rm{opacity:1}
    .gal-thumb .gal-rm:hover{background:#ef4444}
    .gal-add{width:88px;height:88px;border-radius:10px;border:2px dashed var(--border-color,#cbd5e1);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;color:var(--text-muted,#94a3b8);flex-shrink:0;gap:2px}
    .gal-add:hover{border-color:var(--brand,#008075);color:var(--brand,#008075);background:rgba(0,128,117,.04)}
    .gal-add i{font-size:1.1rem}
    .gal-add span{font-size:.58rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
    .gal-count{font-size:.72rem;color:var(--text-muted,#94a3b8);margin-top:2px}
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
    <div>
        <h4 class="mb-1 fw-bold" style="letter-spacing:-.02em;"><i class="fa-solid fa-map-location-dot me-2" style="color:var(--brand,#008075);"></i>Destination Management</h4>
        <div style="color:var(--text-muted,#94a3b8);font-size:.84rem;">Manage listings, featured destinations and booking availability.</div>
    </div>
    <div class="page-actions">
        <div class="refresh-group">
            <button class="refresh-btn" id="refreshBtn" title="Refresh"><i class="fa-solid fa-rotate-right"></i></button>
            <span class="refresh-divider"></span>
            <span class="last-updated-wrap"><span class="dot"></span><span class="last-updated-text" id="refreshTime">Last updated 08:59:34 PM</span></span>
        </div>
        <button class="btn-add-dest" data-bs-toggle="modal" data-bs-target="#addDestModal"><span class="add-icon"><i class="fa-solid fa-plus"></i></span>Add Destination</button>
    </div>
</div>

<div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-lg-3"><div class="kpi-card p-3" data-status="">
        <div class="d-flex align-items-center gap-3"><div class="kpi-icon" style="background:rgba(12,110,94,.08);color:var(--brand,#0c6e5e);"><i class="fa-solid fa-map-marked-alt"></i></div><div><div class="kpi-num" id="kpi-total"><?= $stats['total'] ?></div><div class="kpi-label">Destinations</div></div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="kpi-card p-3" data-status="active">
        <div class="d-flex align-items-center gap-3"><div class="kpi-icon" style="background:rgba(16,185,129,.08);color:#059669;"><i class="fa-solid fa-circle-check"></i></div><div><div class="kpi-num" id="kpi-active" style="color:#059669;"><?= $stats['active'] ?></div><div class="kpi-label">Active</div></div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="kpi-card p-3" data-feat="1">
        <div class="d-flex align-items-center gap-3"><div class="kpi-icon" style="background:rgba(245,158,11,.08);color:#d97706;"><i class="fa-solid fa-star"></i></div><div><div class="kpi-num" id="kpi-featured" style="color:#d97706;"><?= $stats['featured'] ?></div><div class="kpi-label">Featured</div></div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="kpi-card p-3" data-bk="1">
        <div class="d-flex align-items-center gap-3"><div class="kpi-icon" style="background:rgba(59,130,246,.08);color:#2563eb;"><i class="fa-solid fa-ticket"></i></div><div><div class="kpi-num" id="kpi-booking" style="color:#2563eb;"><?= $stats['booking_open'] ?></div><div class="kpi-label">Booking Open</div></div></div>
    </div></div>
</div>

<div class="sticky-filter mb-3">
    <div class="card filter-card">
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search name or location..." value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"></div>
                </div>
                <div class="col-md-2"><select id="catFilter" class="form-select form-select-sm filter-select"><option value="">All Categories</option></select></div>
                <div class="col-md-2"><select id="statusFilter" class="form-select form-select-sm filter-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                    <option value="maintenance" <?= $statusFilter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                </select></div>
                <div class="col-md-2"><select id="perPage" class="form-select form-select-sm filter-select"><option value="10">10 / page</option><option value="15" selected>15 / page</option><option value="25">25 / page</option><option value="50">50 / page</option></select></div>
                <div class="col-md-2 d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary" id="clearFilters" style="border-radius:8px;">Clear</button>
                    <button class="btn btn-sm btn-brand" id="applyFilters" style="border-radius:8px;"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                </div>
            </div>
            <div id="chipRow" class="mt-2 d-flex gap-1 flex-wrap"></div>
        </div>
    </div>
</div>

<div class="bulk-bar mb-3" id="bulkBar">
    <i class="fa-solid fa-check-double text-brand"></i>
    <span class="fw-semibold" id="bulkCount">0 selected</span>
    <div class="ms-auto d-flex gap-1 flex-wrap">
        <button class="btn btn-sm btn-outline-warning" onclick="bulk('bulk_featured','1')"><i class="fa-solid fa-star me-1"></i>Feature</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="bulk('bulk_featured','0')"><i class="fa-regular fa-star me-1"></i>Unfeature</button>
        <button class="btn btn-sm btn-outline-success" onclick="bulk('bulk_booking','1')"><i class="fa-solid fa-ticket me-1"></i>Open Booking</button>
        <button class="btn btn-sm btn-outline-danger" onclick="bulk('bulk_booking','0')"><i class="fa-solid fa-ban me-1"></i>Close Booking</button>
        <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()"><i class="fa-solid fa-trash me-1"></i>Delete</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()"><i class="fa-solid fa-xmark me-1"></i></button>
    </div>
</div>

<div class="card dest-table-card">
    <div class="table-responsive">
        <table class="table dest-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:38px"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <th style="width:55px">ID</th>
                    <th>Destination</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Fee</th>
                    <th>Booking</th>
                    <th>Featured</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="destBody"></tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-top:1px solid var(--border-color,#f1f5f9);background:var(--card-bg,#fff);">
        <div class="text-muted small" id="footerInfo">Loading...</div>
        <div class="pager" id="pager"></div>
    </div>
</div>

<?php if ($manageSeasons && $seasonDest): ?>
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#0c6e5e,#1a8a7a)">
        <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-calendar-days me-2"></i>Manage Seasons — <?= htmlspecialchars($seasonDest['name']) ?></h6>
        <a href="<?= BASE_URL ?>/admin/destinations.php" class="btn btn-sm btn-light"><i class="fa-solid fa-xmark me-1"></i>Close</a>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="fw-bold mb-3" style="font-size:.9rem;">Current Seasons</h6>
                <?php if (empty($seasonDest['seasons'])): ?>
                    <p class="text-muted small">No seasons configured yet.</p>
                <?php else: foreach ($seasonDest['seasons'] as $season):
                    $isPeak = $season['season_type'] === 'peak';
                    $parts = explode('-', $season['months'] ?? '');
                    $startM = (int)($parts[0] ?? 0);
                    $endM = (int)(end($parts) ?: $startM);
                    $monthLabel = ($startM >= 1 && $startM <= 12) ? ($startM === $endM ? $monthNames[$startM] : $monthNames[$startM] . ' – ' . $monthNames[$endM]) : ('Months ' . ($season['months'] ?? ''));
                ?>
                    <div class="d-flex align-items-center gap-2 border rounded-3 p-2 mb-2" style="background:<?= $isPeak ? 'rgba(239,68,68,.05)' : 'rgba(16,185,129,.05)' ?>;border-color:<?= $isPeak ? 'rgba(239,68,68,.15)' : 'rgba(16,185,129,.15)' ?>!important;">
                        <i class="fa-solid <?= $isPeak ? 'fa-fire text-danger' : 'fa-snowflake text-success' ?>"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?= $isPeak ? 'Peak Season' : 'Off-Peak Season' ?></div>
                            <div class="small"><?= htmlspecialchars($monthLabel) ?></div>
                            <?php if (!empty($season['description'])): ?><div class="small text-muted"><?= htmlspecialchars($season['description']) ?></div><?php endif; ?>
                        </div>
                        <form method="POST" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="delete_season"><input type="hidden" name="season_id" value="<?= (int)$season['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Remove this season?"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold mb-3" style="font-size:.9rem;">Add New Season</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_season">
                    <input type="hidden" name="dest_id" value="<?= (int)$seasonDest['id'] ?>">
                    <div class="mb-3"><label class="form-label small">Season Type</label><select name="season_type" class="form-select"><option value="peak">Peak (Busy)</option><option value="off_peak">Off-Peak (Quiet)</option></select></div>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><label class="form-label small">Start Month</label><select name="start_month" class="form-select"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"><?= $monthNames[$m] ?></option><?php endfor; ?></select></div>
                        <div class="col-6"><label class="form-label small">End Month</label><select name="end_month" class="form-select"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"><?= $monthNames[$m] ?></option><?php endfor; ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label small">Description</label><input type="text" name="season_description" class="form-control" placeholder="e.g., Best time for beach activities..."></div>
                    <button class="btn btn-brand w-100"><i class="fa-solid fa-plus me-1"></i>Add Season</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($manageGuides && $guideDest): ?>
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-user-tie me-2 text-brand"></i>Manage Guides — <?= htmlspecialchars($guideDest['name']) ?></h6>
        <a href="<?= BASE_URL ?>/admin/destinations.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Close</a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-0">Guide assignment is managed inside the destination edit form (Guides section).</p>
        <?php if (!empty($assignedGuides)): ?>
            <?php foreach ($assignedGuides as $ag): ?>
                <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
                    <div class="d-flex align-items-center gap-2"><img src="<?= get_avatar_url($ag) ?>" class="rounded-circle" width="36" height="36" alt=""><div><strong class="small"><?= htmlspecialchars($ag['name']) ?></strong> <?php if ($ag['is_primary']): ?><span class="badge bg-warning-subtle text-warning">Primary</span><?php endif; ?><div class="small text-muted"><?= (int)($ag['years_of_experience'] ?? 0) ?> yrs · Rating <?= number_format((float)($ag['avg_rating'] ?? 0), 1) ?></div></div></div>
                    <div class="d-flex gap-1">
                        <?php if (!$ag['is_primary']): ?>
                            <form method="POST" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="set_primary_guide"><input type="hidden" name="dest_id" value="<?= (int)$guideDest['id'] ?>"><input type="hidden" name="guide_id" value="<?= (int)$ag['id'] ?>"><button class="btn btn-sm btn-outline-warning" title="Set as Primary"><i class="fa-solid fa-star"></i></button></form>
                        <?php endif; ?>
                        <form method="POST" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="remove_guide"><input type="hidden" name="dest_id" value="<?= (int)$guideDest['id'] ?>"><input type="hidden" name="guide_id" value="<?= (int)$ag['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Remove this guide?"><i class="fa-solid fa-times"></i></button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small mb-0">No guides assigned.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add modal -->
<div class="modal fade" id="addDestModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#0c6e5e,#10b981)">
                <h5 class="modal-title fw-bold text-white mb-0"><i class="fa-solid fa-plus me-2"></i>Add New Destination</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addDestForm" novalidate>
                <?= csrf_field() ?>
                <div class="modal-body" style="max-height:68vh;overflow-y:auto">
                    <input type="hidden" name="action" value="add_destination">

                    <!-- Progress Stepper -->
                    <div class="dest-stepper">
                        <div class="dest-step active" data-step="0"><div class="step-num">1</div><span>Basic Info</span></div>
                        <div class="dest-step-line"></div>
                        <div class="dest-step" data-step="1"><div class="step-num">2</div><span>Location</span></div>
                        <div class="dest-step-line"></div>
                        <div class="dest-step" data-step="2"><div class="step-num">3</div><span>Pricing</span></div>
                        <div class="dest-step-line"></div>
                        <div class="dest-step" data-step="3"><div class="step-num">4</div><span>Visitor Info</span></div>
                        <div class="dest-step-line"></div>
                        <div class="dest-step" data-step="4"><div class="step-num">5</div><span>Booking</span></div>
                    </div>

                    <div class="tab-content">
                        <!-- Tab 1: Basic Info -->
                        <div class="tab-pane fade show active" id="adBasic">
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control dest-input" required placeholder="Destination name" id="adName">
                                <div class="invalid-feedback" id="err-adName"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Category</label>
                                <select name="category" class="form-select dest-input" id="adCategory">
                                    <?php foreach ($allCategories as $ck => $cv): ?>
                                        <option value="<?= $ck ?>"><?= htmlspecialchars($cv) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Description</label>
                                <textarea name="description" class="form-control dest-input" rows="3" placeholder="Describe this destination..." id="adDesc" maxlength="500"></textarea>
                                <div class="dest-char-count"><span id="adDescCount">0</span>/500</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Video <small class="text-muted fw-normal">Optional — paste a link or upload a file</small></label>
                                <input type="url" name="video_url" class="form-control mb-2 dest-input" placeholder="https://www.youtube.com/watch?v=..." id="adVideoUrl">
                                <div class="invalid-feedback" id="err-adVideoUrl"></div>
                                <input type="file" name="video_file" class="form-control dest-input" accept="video/mp4,video/webm,video/quicktime">
                                <small class="text-muted" style="font-size:.72rem;">MP4, WebM, or MOV (max 50MB).</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Cover Image</label>
                                    <input type="file" name="image" class="form-control dest-input" accept="image/*" id="adCoverImg">
                                    <div class="dest-img-preview mt-2" id="adCoverPreview"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Gallery Images</label>
                                    <div class="gal-grid" id="adGalGrid"><label class="gal-add" onclick="document.getElementById('adGalInput').click()"><i class="fa-solid fa-plus"></i><span>Add</span></label></div>
                                    <input type="file" id="adGalInput" name="gallery[]" accept="image/*" multiple hidden>
                                    <div class="gal-count" id="adGalCount">0 images</div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Location & Hours -->
                        <div class="tab-pane fade" id="adLocation">
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Address <span class="text-danger">*</span></label>
                                <div class="dest-autocomplete-wrap">
                                    <input type="text" name="location" class="form-control dest-input" required placeholder="Search for an address or paste coordinates..." id="adAddress" autocomplete="off">
                                    <div class="dest-ac-dropdown" id="adAcDropdown"></div>
                                </div>
                                <div class="invalid-feedback" id="err-adAddress"></div>
                                <small class="text-muted" style="font-size:.72rem;"><i class="fa-solid fa-circle-info me-1"></i>Type to search, paste Google Earth coordinates, or enter lat/lng below.</small>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Latitude</label>
                                    <input type="text" name="latitude" class="form-control dest-input" id="adLat" placeholder="e.g. 9.8500" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Longitude</label>
                                    <input type="text" name="longitude" class="form-control dest-input" id="adLng" placeholder="e.g. 122.8700" autocomplete="off">
                                </div>
                            </div>
                            <div class="dest-mini-map-wrap mb-3" id="adMiniMapWrap" style="display:none;">
                                <div id="adMiniMap" class="dest-mini-map"></div>
                                <div class="dest-mini-map-label"><i class="fa-solid fa-location-dot me-1"></i><span id="adMapCoords">—</span></div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Contact Phone</label>
                                    <input type="text" name="contact_phone" class="form-control dest-input" id="adPhone" placeholder="+63 9XX XXX XXXX">
                                    <div class="invalid-feedback" id="err-adPhone"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Contact Email</label>
                                    <input type="email" name="contact_email" class="form-control dest-input" id="adEmail" placeholder="example@email.com">
                                    <div class="invalid-feedback" id="err-adEmail"></div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Opening Time</label>
                                    <input type="time" name="operating_hours_open" class="form-control dest-input">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Closing Time</label>
                                    <input type="time" name="operating_hours_close" class="form-control dest-input">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Pricing -->
                        <div class="tab-pane fade" id="adPricing">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Entrance Fee (₱)</label>
                                    <input type="number" name="entrance_fee" class="form-control dest-input" value="0" min="0" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Package Price (₱) <small class="text-muted fw-normal">Optional</small></label>
                                    <input type="number" name="package_price" class="form-control dest-input" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Visitor Info -->
                        <div class="tab-pane fade" id="adVisitor">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold dest-field-label">Max Visitors/Day</label>
                                    <input type="number" name="capacity" class="form-control dest-input" value="0" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold dest-field-label">Max Guests/Booking</label>
                                    <input type="number" name="max_guests_per_booking" class="form-control dest-input" value="10" min="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold dest-field-label">Difficulty</label>
                                    <select name="difficulty" class="form-select dest-input">
                                        <option value="easy">Easy</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="difficult">Difficult</option>
                                        <option value="extreme">Extreme</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold dest-field-label">Min Age</label>
                                    <input type="number" name="age_min" class="form-control dest-input" value="1" min="1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold dest-field-label">Max Age</label>
                                    <input type="number" name="age_max" class="form-control dest-input" value="100" min="1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Booking Days</label>
                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dk): ?>
                                            <div class="form-check">
                                                <input type="checkbox" name="booking_days[]" value="<?= $dk ?>" class="form-check-input" id="ad_<?= $dk ?>" checked>
                                                <label class="form-check-label" for="ad_<?= $dk ?>"><?= $dk ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Facilities</label>
                                <input type="text" name="facilities" class="form-control dest-input" placeholder="e.g., Parking, Restrooms, Gift Shop">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold dest-field-label">Rules &amp; Regulations</label>
                                <textarea name="rules_regulations" class="form-control dest-input" rows="2"></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-semibold dest-field-label">Accessibility Info</label>
                                <input type="text" name="accessibility" class="form-control dest-input" placeholder="Wheelchair access, ramps, etc.">
                            </div>
                        </div>

                        <!-- Tab 5: Booking -->
                        <div class="tab-pane fade" id="adBooking">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" name="booking_enabled" class="form-check-input" id="adBkEn" checked>
                                <label class="form-check-label fw-semibold" for="adBkEn">Enable Online Booking</label>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Booking Cut-off</label>
                                    <div class="input-group">
                                        <input type="number" name="booking_cutoff_hours" class="form-control dest-input" value="2" min="0">
                                        <span class="input-group-text">hrs before</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold dest-field-label">Advance Booking</label>
                                    <div class="input-group">
                                        <input type="number" name="advance_booking_days" class="form-control dest-input" value="1" min="0">
                                        <span class="input-group-text">day(s) ahead</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-semibold dest-field-label">Cancellation Policy</label>
                                <textarea name="cancellation_policy" class="form-control dest-input" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer dest-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary dest-nav-btn" id="adPrevBtn" style="display:none;"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                        <button type="button" class="btn btn-brand dest-nav-btn" id="adNextBtn">Next<i class="fa-solid fa-arrow-right ms-1"></i></button>
                        <button type="submit" class="btn btn-brand dest-submit-btn" id="adSubmitBtn" style="display:none;">
                            <span class="spinner-border spinner-border-sm me-1 d-none" id="adSpin"></span><i class="fa-solid fa-plus me-1" id="adSubmitIcon"></i>Add Destination
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit modal (body injected via AJAX) -->
<div class="modal fade" id="editDestModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDestTitle">Edit Destination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editDestBody" style="max-height:68vh;overflow-y:auto">
                <div class="text-center py-5"><div class="spinner-border text-brand"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3"></div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const CATEGORIES = <?= json_encode(array_map(fn($k, $v) => ['key' => $k, 'label' => $v], array_keys($allCategories), array_values($allCategories))) ?>;
const state = { page: 1, per_page: 15, search: <?= json_encode($search) ?>, cat: <?= json_encode($catFilter) ?>, status: <?= json_encode($statusFilter) ?>, feat: '', bk: '', total: 0, pages: 1, loading: false };
const __dest = {};
let selected = new Set();
/* pendingConfirm lives in script.js */
let debounceTimer = null;

const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);

function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function money(v) { return '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + (type === 'error' ? 'danger' : type) + ' border-0 show';
    el.innerHTML = '<div class="d-flex"><div class="toast-body">' + esc(msg) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    document.querySelector('.toast-container').appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 3200 }); t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
function statusBadge(st) {
    const m = { active: ['bg-success-subtle text-success', 'fa-circle-check'], inactive: ['bg-secondary-subtle text-secondary', 'fa-circle'], closed: ['bg-danger-subtle text-danger', 'fa-circle-xmark'], maintenance: ['bg-warning-subtle text-warning', 'fa-screwdriver-wrench'] };
    const c = m[st] || m.inactive;
    return '<span class="badge ' + c[0] + '"><i class="fa-solid ' + c[1] + ' me-1"></i>' + esc(st) + '</span>';
}
function catLabel(k) { const c = CATEGORIES.find(x => x.key === k); return c ? c.label : k; }
function qs() {
    const p = new URLSearchParams();
    if (state.search) p.set('search', state.search);
    if (state.cat) p.set('category', state.cat);
    if (state.status) p.set('status', state.status);
    p.set('page', state.page); p.set('per_page', state.per_page);
    return p.toString();
}
function skeletonRows(n) {
    let h = '';
    for (let i = 0; i < n; i++) h += '<tr><td><span class="form-check-input d-block"></span></td><td><span class="skeleton" style="width:40px;height:18px;display:inline-block;border-radius:8px"></span></td><td><div class="d-flex align-items-center gap-2"><span class="skeleton dest-thumb"></span><span class="skeleton" style="width:140px;height:10px"></span></div></td><td><span class="skeleton" style="width:72px;height:24px;display:inline-block;border-radius:8px"></span></td><td><span class="skeleton" style="width:120px;height:10px;display:inline-block"></span></td><td><span class="skeleton" style="width:56px;height:10px;display:inline-block"></span></td><td><span class="skeleton" style="width:56px;height:24px;display:inline-block;border-radius:8px"></span></td><td><span class="skeleton" style="width:24px;height:24px;display:inline-block;border-radius:6px"></span></td><td><span class="skeleton" style="width:80px;height:28px;display:inline-block;border-radius:8px"></span></td><td class="text-end"><div class="d-flex gap-1 justify-content-end"><span class="skeleton" style="width:32px;height:32px;border-radius:8px"></span><span class="skeleton" style="width:32px;height:32px;border-radius:8px"></span><span class="skeleton" style="width:32px;height:32px;border-radius:8px"></span></div></td></tr>';
    return h;
}
function applyStats(s) {
    if (!s) return;
    $('#kpi-total').textContent = s.total; $('#kpi-active').textContent = s.active;
    $('#kpi-featured').textContent = s.featured; $('#kpi-booking').textContent = s.booking_open;
    $$('.kpi-card').forEach(k => k.classList.remove('active'));
    if (state.status) { const k = document.querySelector('.kpi-card[data-status="' + state.status + '"]'); if (k) k.classList.add('active'); }
    else if (state.feat) { const k = document.querySelector('.kpi-card[data-feat="1"]'); if (k) k.classList.add('active'); }
    else if (state.bk) { const k = document.querySelector('.kpi-card[data-bk="1"]'); if (k) k.classList.add('active'); }
}
function render(rows) {
    const body = $('#destBody');
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5"><div style="width:56px;height:56px;border-radius:50%;background:var(--border-color,#f1f5f9);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#94a3b8;font-size:1.4rem;"><i class="fa-solid fa-map-marked-alt"></i></div><h6 class="fw-bold" style="color:var(--text-body,#1e293b);">No destinations found</h6><p class="small mb-0" style="color:var(--text-muted,#94a3b8);">Try adjusting your search or filters.</p></td></tr>';
        renderFooter(); return;
    }
    let h = '';
    rows.forEach(d => {
        __dest[d.id] = d;
        const sel = selected.has(d.id) ? 'checked' : '';
        const thumb = d.image_url ? '<img src="' + esc(d.image_url) + '" class="dest-thumb" alt="" onerror="this.style.visibility=\'hidden\'">' : '<div class="dest-thumb d-flex align-items-center justify-content-center" style="background:var(--border-color)"><i class="fa-regular fa-image text-muted"></i></div>';
        h += '<tr data-id="' + d.id + '">'
            + '<td><input type="checkbox" class="form-check-input row-check" data-id="' + d.id + '" ' + sel + '></td>'
            + '<td><span class="id-badge">#' + d.id + '</span></td>'
            + '<td><div class="d-flex align-items-center gap-2">' + thumb + '<span class="fw-semibold">' + esc(d.name) + (d.featured ? ' <i class="fa-solid fa-star text-warning" title="Featured"></i>' : '') + '</span></div></td>'
            + '<td><span class="cat-badge"><i class="fa-solid fa-folder" style="font-size:.6rem"></i>' + esc(catLabel(d.category)) + '</span></td>'
            + '<td class="small" style="color:var(--text-muted,#64748b)">' + esc(d.location) + '</td>'
            + '<td class="fee-text">' + money(d.entrance_fee) + '</td>'
            + '<td><span class="status-chip" data-id="' + d.id + '" data-val="' + d.booking_enabled + '" style="background:' + (d.booking_enabled ? 'rgba(16,185,129,.1)' : 'rgba(107,114,128,.1)') + ';color:' + (d.booking_enabled ? '#059669' : '#6b7280') + '"><i class="fa-solid fa-circle" style="font-size:5px"></i>' + (d.booking_enabled ? 'Open' : 'Closed') + '</span></td>'
            + '<td><button class="star-toggle" data-id="' + d.id + '" data-val="' + d.featured + '" title="' + (d.featured ? 'Unfeature' : 'Feature') + '" style="color:' + (d.featured ? '#f59e0b' : 'var(--text-muted)') + '"><i class="fa-solid fa-star"></i></button></td>'
            + '<td><select class="form-select form-select-sm status-sel" data-id="' + d.id + '" style="min-width:120px">'
            + ['active', 'inactive', 'closed', 'maintenance'].map(s => '<option value="' + s + '" ' + (s === d.status ? 'selected' : '') + '>' + s + '</option>').join('')
            + '</select></td>'
            + '<td class="text-end"><div class="d-flex gap-1 justify-content-end">'
            + '<button class="action-btn" title="Edit" onclick="openEdit(' + d.id + ')"><i class="fa-solid fa-pen"></i></button>'
            + '<button class="action-btn" title="Reservations" onclick="location.href=\'' + BASE_URL + '/admin/reservations.php?destination_id=' + d.id + '\'"><i class="fa-solid fa-clipboard-list"></i></button>'
            + '<button class="action-btn" title="Seasons" onclick="location.href=\'destinations.php?manage_seasons=' + d.id + '\'"><i class="fa-solid fa-calendar-days"></i></button>'
            + '<button class="action-btn danger" title="Delete" onclick="askDelete(' + d.id + ')"><i class="fa-solid fa-trash"></i></button>'
            + '</div></td></tr>';
    });
    body.innerHTML = h;
    renderFooter();
}
function renderFooter() {
    const from = state.total === 0 ? 0 : (state.page - 1) * state.per_page + 1;
    const to = Math.min(state.page * state.per_page, state.total);
    $('#footerInfo').textContent = 'Showing ' + from + '–' + to + ' of ' + state.total + ' destinations';
    const p = $('#pager'); p.innerHTML = '';
    const mk = (label, page, disabled, active) => {
        const b = document.createElement('button');
        b.className = 'btn btn-sm ' + (active ? 'btn-brand' : 'btn-outline-secondary') + (disabled ? ' disabled' : '');
        b.innerHTML = label;
        if (!disabled) b.onclick = () => { state.page = page; load(); };
        p.appendChild(b);
    };
    mk('<i class="fa-solid fa-angles-left"></i>', 1, state.page === 1);
    mk('<i class="fa-solid fa-chevron-left"></i>', state.page - 1, state.page === 1);
    for (let i = 1; i <= state.pages; i++) {
        if (i === 1 || i === state.pages || Math.abs(i - state.page) <= 1) mk(String(i), i, false, i === state.page);
        else if (Math.abs(i - state.page) === 2) mk('…', i, true);
    }
    mk('<i class="fa-solid fa-chevron-right"></i>', state.page + 1, state.page === state.pages);
    mk('<i class="fa-solid fa-angles-right"></i>', state.pages, state.page === state.pages);
}
function setRefreshLoading(loading) {
    const btn = $('#refreshBtn');
    if (!btn) return;
    btn.disabled = loading;
    const icon = btn.querySelector('i');
    if (!icon) return;
    icon.className = loading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-rotate-right';
    btn.title = loading ? 'Loading...' : 'Refresh';
}
function updateRefreshTime() {
    const t = $('#refreshTime');
    if (!t) return;
    t.textContent = 'Last updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
async function load() {
    if (state.loading) return;
    $('#destBody').innerHTML = skeletonRows(5);
    state.loading = true;
    setRefreshLoading(true);
    try {
        const r = await fetch(BASE_URL + '/admin/destinations.php?ajax=1&' + qs());
        const d = await r.json();
        state.total = d.total; state.pages = d.pages; state.page = d.page; state.per_page = d.per_page;
        applyStats(d.stats);
        render(d.rows);
        updateRefreshTime();
        const sa = $('#selectAll'); if (sa) sa.checked = false;
        clearSelection();
    } catch {
        $('#destBody').innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Failed to load destinations.</td></tr>';
    } finally { state.loading = false; setRefreshLoading(false); }
}
function onSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { state.search = $('#searchInput').value.trim(); state.page = 1; load(); }, 400);
}
function applyFilters() {
    state.cat = $('#catFilter').value; state.status = $('#statusFilter').value; state.search = $('#searchInput').value.trim();
    state.page = 1;
    renderChips(); load();
}
function clearFilters() {
    state.cat = state.status = state.search = state.feat = state.bk = '';
    $('#catFilter').value = $('#statusFilter').value = ''; $('#searchInput').value = '';
    renderChips(); load();
}
function renderChips() {
    const w = $('#chipRow');
    const parts = [];
    if (state.cat) parts.push('<span class="filter-chip">Category: ' + esc(catLabel(state.cat)) + ' <a href="#" onclick="clearFilters();return false;"><i class="fa-solid fa-xmark"></i></a></span>');
    if (state.status) parts.push('<span class="filter-chip">Status: ' + esc(state.status) + ' <a href="#" onclick="clearFilters();return false;"><i class="fa-solid fa-xmark"></i></a></span>');
    if (state.feat) parts.push('<span class="filter-chip">Featured <a href="#" onclick="clearFilters();return false;"><i class="fa-solid fa-xmark"></i></a></span>');
    if (state.bk) parts.push('<span class="filter-chip">Booking Open <a href="#" onclick="clearFilters();return false;"><i class="fa-solid fa-xmark"></i></a></span>');
    if (state.search) parts.push('<span class="filter-chip">Search: ' + esc(state.search) + ' <a href="#" onclick="clearFilters();return false;"><i class="fa-solid fa-xmark"></i></a></span>');
    w.innerHTML = parts.join('');
}
function post(data, cb) {
    const fd = new FormData();
    Object.keys(data).forEach(k => {
        if (Array.isArray(data[k])) data[k].forEach(v => fd.append(k, v));
        else fd.append(k, data[k]);
    });
    fetch(BASE_URL + '/admin/destinations.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(r => r.json()).then(d => cb(d.ok, d.message)).catch(() => cb(false, 'Request failed.'));
}
function askDelete(id) {
    const d = __dest[id];
    askConfirm('Delete destination "' + (d ? d.name : '#' + id) + '"?', 'This permanently removes the destination and its related data.', () => {
        post({ action: 'delete_destination', dest_id: id, csrf_token: CSRF }, (ok, msg) => { toast(msg, ok ? 'success' : 'error'); if (ok) load(); });
    });
}
// toggles
document.addEventListener('click', (e) => {
    const star = e.target.closest('.star-toggle');
    if (star) {
        const id = parseInt(star.dataset.id);
        const val = star.dataset.val === '1' ? 0 : 1;
        const d = __dest[id]; if (!d) return;
        d.featured = val; star.dataset.val = val;
        star.style.color = val ? '#f59e0b' : 'var(--text-muted)';
        star.title = val ? 'Unfeature' : 'Feature';
        toast('Updating...', 'info');
        post({ action: 'toggle_featured', dest_id: id, csrf_token: CSRF }, (ok, msg) => { toast(msg, ok ? 'success' : 'error'); load(); });
        return;
    }
    const pill = e.target.closest('.booking-pill');
    if (pill) {
        const id = parseInt(pill.dataset.id);
        const val = pill.dataset.val === '1' ? 0 : 1;
        const d = __dest[id]; if (!d) return;
        d.booking_enabled = val; pill.dataset.val = val;
        pill.style.background = val ? '#d1fae5' : '#f3f4f6';
        pill.style.color = val ? '#059669' : '#6b7280';
        pill.innerHTML = '<i class="fa-solid fa-circle" style="font-size:6px"></i>' + (val ? 'Open' : 'Closed');
        post({ action: 'toggle_booking', dest_id: id, csrf_token: CSRF }, (ok, msg) => { toast(msg, ok ? 'success' : 'error'); if (!ok) load(); });
    }
});
document.addEventListener('change', (e) => {
    if (e.target.classList.contains('status-sel')) {
        const id = parseInt(e.target.dataset.id);
        const st = e.target.value;
        post({ action: 'set_status', dest_id: id, new_status: st, csrf_token: CSRF }, (ok, msg) => { toast(msg, ok ? 'success' : 'error'); load(); });
    }
    if (e.target.classList.contains('row-check')) onSelectChange();
});
// edit
async function openEdit(id) {
    const d = __dest[id]; if (!d) return;
    $('#editDestTitle').textContent = 'Edit Destination — ' + d.name;
    $('#editDestBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-brand"></div></div>';
    bootstrap.Modal.getOrCreateInstance($('#editDestModal')).show();
    try {
        const r = await fetch(BASE_URL + '/admin/destinations.php?ajax=1&mode=form&id=' + id);
        const j = await r.json();
        if (j.ok) $('#editDestBody').innerHTML = j.html;
        else { $('#editDestBody').innerHTML = '<div class="text-center text-danger py-5">' + esc(j.message || 'Failed to load form.') + '</div>'; }
    } catch {
        $('#editDestBody').innerHTML = '<div class="text-center text-danger py-5">Failed to load the edit form.</div>';
    }
}
// select/bulk
function onSelectChange() {
    selected.clear();
    $$('.row-check:checked').forEach(cb => selected.add(parseInt(cb.dataset.id)));
    const bar = $('#bulkBar');
    $('#bulkCount').textContent = selected.size + ' selected';
    bar.classList.toggle('show', selected.size > 0);
}
function clearSelection() {
    selected.clear();
    $$('.row-check').forEach(cb => cb.checked = false);
    const sa = $('#selectAll'); if (sa) sa.checked = false;
    $('#bulkBar').classList.remove('show');
}
function bulk(action, value) {
    if (!selected.size) return;
    const label = { bulk_featured: value === '1' ? 'feature' : 'unfeature', bulk_booking: value === '1' ? 'open booking for' : 'close booking for' }[action] || '';
    askConfirm(label ? ('Set ' + selected.size + ' destination(s) to ' + label + '?') : 'Apply to ' + selected.size + ' destination(s)?', 'This will update the selected destinations.', () => {
        post({ action, value, dest_ids: Array.from(selected), csrf_token: CSRF }, (ok, msg) => { toast(msg, ok ? 'success' : 'error'); if (ok) { clearSelection(); load(); } });
    });
}
function bulkDelete() {
    if (!selected.size) return;
    askConfirm('Delete ' + selected.size + ' destination(s)?', 'This permanently removes the selected destinations.', () => {
        post({ action: 'bulk_delete', dest_ids: Array.from(selected), csrf_token: CSRF }, (ok, msg) => { toast(msg, ok ? 'success' : 'error'); if (ok) { clearSelection(); load(); } });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const cat = $('#catFilter');
    CATEGORIES.forEach(c => cat.insertAdjacentHTML('beforeend', '<option value="' + esc(c.key) + '">' + esc(c.label) + '</option>'));
    cat.value = state.cat;
    $('#searchInput').addEventListener('input', onSearch);
    $('#applyFilters').addEventListener('click', applyFilters);
    $('#clearFilters').addEventListener('click', clearFilters);
    $('#refreshBtn').addEventListener('click', load);
    $('#perPage').addEventListener('change', () => { state.per_page = parseInt($('#perPage').value); state.page = 1; load(); });
    $('#selectAll').addEventListener('change', (e) => { $$('.row-check').forEach(cb => cb.checked = e.target.checked); onSelectChange(); });
    $$('.kpi-card').forEach(k => k.addEventListener('click', () => {
        state.feat = state.bk = '';
        if (k.dataset.status) { state.status = state.status === k.dataset.status ? '' : k.dataset.status; state.cat = ''; }
        else if (k.dataset.feat) state.feat = state.feat ? '' : '1';
        else if (k.dataset.bk) state.bk = state.bk ? '' : '1';
        state.page = 1;
        $('#statusFilter').value = state.status;
        renderChips(); load();
    }));
    renderChips();
    load();
});
/* ── Gallery Uploader ─────────────────────────────────────── */
function rmGalImg(btn){
    const thumb = btn.closest('.gal-thumb');
    const file = thumb.dataset.file;
    const isEdit = thumb.closest('#edGalGrid');
    if(isEdit){
        const keep = document.getElementById('edKeepGallery');
        let arr = JSON.parse(keep.value||'[]');
        arr = arr.filter(f=>f!==file);
        keep.value = JSON.stringify(arr);
    }
    thumb.remove();
    updateGalCount();
}
function updateGalCount(){
    const eg = document.getElementById('edGalGrid');
    const ag = document.getElementById('adGalGrid');
    if(eg){
        const n = eg.querySelectorAll('.gal-thumb').length;
        const c = document.getElementById('edGalCount');
        if(c) c.textContent = n + ' image' + (n!==1?'s':'');
    }
    if(ag){
        const n = ag.querySelectorAll('.gal-thumb').length;
        const c = document.getElementById('adGalCount');
        if(c) c.textContent = n + ' image' + (n!==1?'s':'');
    }
}
function galPreview(input, gridId){
    if(!input.files||!input.files.length) return;
    const grid = document.getElementById(gridId);
    const addBtn = grid.querySelector('.gal-add');
    Array.from(input.files).forEach(file=>{
        if(!file.type.startsWith('image/')) return;
        const url = URL.createObjectURL(file);
        const div = document.createElement('div');
        div.className = 'gal-thumb';
        div.innerHTML = '<img src="'+url+'" alt=""><button type="button" class="gal-rm" onclick="rmGalImg(this)"><i class="fa-solid fa-xmark"></i></button>';
        grid.insertBefore(div, addBtn);
    });
    input.value = '';
    updateGalCount();
}
document.getElementById('edGalInput')?.addEventListener('change',function(){ galPreview(this,'edGalGrid'); });
document.getElementById('adGalInput')?.addEventListener('change',function(){ galPreview(this,'adGalGrid'); });

/* ── Add Dest Form: Stepper, Validation, Geocoding ───────── */
(function(){
    const form = document.getElementById('addDestForm');
    if(!form) return;
    const steps = document.querySelectorAll('.dest-step');
    const lines = document.querySelectorAll('.dest-step-line');
    const panes = ['adBasic','adLocation','adPricing','adVisitor','adBooking'];
    let cur = 0;
    const prevBtn = document.getElementById('adPrevBtn');
    const nextBtn = document.getElementById('adNextBtn');
    const submitBtn = document.getElementById('adSubmitBtn');

    function goStep(i){
        if(i<0||i>=panes.length) return;
        cur=i;
        panes.forEach((p,idx)=>{
            const el=document.getElementById(p);
            el.classList.toggle('show',idx===i);
            el.classList.toggle('active',idx===i);
        });
        steps.forEach((s,idx)=>{
            s.classList.remove('active','completed');
            if(idx<i) s.classList.add('completed');
            else if(idx===i) s.classList.add('active');
        });
        lines.forEach((l,idx)=>{
            l.classList.toggle('filled',idx<i);
        });
        prevBtn.style.display = i===0?'none':'';
        nextBtn.style.display = i===panes.length-1?'none':'';
        submitBtn.style.display = i===panes.length-1?'':'none';
        form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
    }

    prevBtn.addEventListener('click',()=>{ if(cur>0) goStep(cur-1); });

    nextBtn.addEventListener('click',()=>{
        if(!validateTab(cur)) return;
        goStep(cur+1);
    });

    function validateTab(i){
        clearErrors();
        let ok=true;
        if(i===0){
            const name=form.querySelector('#adName');
            if(!name.value.trim()){ markInvalid(name,'Please enter a destination name.'); ok=false; }
        }
        if(i===1){
            const addr=form.querySelector('#adAddress');
            if(!addr.value.trim()){ markInvalid(addr,'Please enter an address.'); ok=false; }
            const phone=form.querySelector('#adPhone');
            if(phone.value.trim() && !/^\+?[\d\s\-()]{7,15}$/.test(phone.value.trim())){
                markInvalid(phone,'Please enter a valid phone number.'); ok=false;
            }
            const email=form.querySelector('#adEmail');
            if(email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())){
                markInvalid(email,'Please enter a valid email address.'); ok=false;
            }
        }
        if(i===2){
            const fee=form.querySelector('[name="entrance_fee"]');
            if(fee.value!==''){ const v=parseFloat(fee.value); if(isNaN(v)||v<0){ markInvalid(fee,'Please enter a valid fee.'); ok=false; } }
        }
        return ok;
    }

    function markInvalid(el,msg){
        el.classList.add('is-invalid');
        const fb=el.parentElement.querySelector('.invalid-feedback')||el.closest('.mb-3,.row')?.querySelector('.invalid-feedback');
        if(fb){ fb.textContent=msg; fb.style.display='block'; }
    }
    function clearErrors(){
        form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(fb=>{ fb.textContent=''; fb.style.display='none'; });
    }

    /* ── Cover Image Preview ─────────────────────────────── */
    const coverInput=document.getElementById('adCoverImg');
    const coverPreview=document.getElementById('adCoverPreview');
    if(coverInput&&coverPreview){
        coverInput.addEventListener('change',function(){
            coverPreview.innerHTML='';
            if(!this.files||!this.files.length) return;
            const file=this.files[0];
            if(!file.type.startsWith('image/')) return;
            const url=URL.createObjectURL(file);
            const div=document.createElement('div');
            div.className='dest-img-thumb';
            div.innerHTML='<img src="'+url+'" alt="Cover preview"><button type="button" class="dest-img-rm" onclick="this.parentElement.remove();"><i class="fa-solid fa-xmark"></i></button>';
            coverPreview.appendChild(div);
        });
    }

    /* ── Description Character Counter ────────────────────── */
    const desc=document.getElementById('adDesc');
    const descCount=document.getElementById('adDescCount');
    if(desc&&descCount){
        desc.addEventListener('input',function(){
            const len=this.value.length;
            descCount.textContent=len;
            const wrap=descCount.parentElement;
            wrap.classList.toggle('warn',len>=400&&len<500);
            wrap.classList.toggle('over',len>=500);
        });
    }

    /* ── URL Validation for Video ─────────────────────────── */
    const videoUrl=document.getElementById('adVideoUrl');
    if(videoUrl){
        videoUrl.addEventListener('blur',function(){
            if(this.value.trim() && !/^https?:\/\/.+/i.test(this.value.trim())){
                markInvalid(this,'Please enter a valid URL starting with http:// or https://');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }

    /* ── Address Autocomplete (Nominatim) ─────────────────── */
    const addrInput=document.getElementById('adAddress');
    const acDropdown=document.getElementById('adAcDropdown');
    const latInput=document.getElementById('adLat');
    const lngInput=document.getElementById('adLng');
    let acTimer=null, acResults=[], acFocusIdx=-1;
    const miniMapWrap=document.getElementById('adMiniMapWrap');
    const mapCoords=document.getElementById('adMapCoords');
    let leafletMap=null, leafletMarker=null;

    if(addrInput){
        /* ── Coordinate detection patterns ─────────────── */
        function parseCoords(str){
            str=str.trim();
            /* DMS: 9°51'0"N 122°52'12"E */
            var dmsRe=/(-?\d{1,3})[°]\s*(\d{1,2})['′]\s*([\d.]+)["″]?\s*([NSEW])\s*[,\s]+(-?\d{1,3})[°]\s*(\d{1,2})['′]\s*([\d.]+)["″]?\s*([NSEW])/i;
            var m=str.match(dmsRe);
            if(m){
                var lat=(parseFloat(m[1])+parseFloat(m[2])/60+parseFloat(m[3])/3600)*(m[4].toUpperCase()==='S'?-1:1);
                var lng=(parseFloat(m[5])+parseFloat(m[6])/60+parseFloat(m[7])/3600)*(m[8].toUpperCase()==='W'?-1:1);
                if(lat>=-90&&lat<=90&&lng>=-180&&lng<=180) return {lat:lat,lng:lng};
            }
            /* Decimal: 9.8500, 122.8700 or 9.8500° N, 122.8700° E or 9.85,122.87 */
            var decRe=/(-?\d{1,3}\.?\d{0,8})\s*[°]?\s*([NSEW])?[,\s]+(-?\d{1,3}\.?\d{0,8})\s*[°]?([NSEW])?/i;
            m=str.match(decRe);
            if(m){
                var lat=parseFloat(m[1])*(m[2]&&m[2].toUpperCase()==='S'?-1:1);
                var lng=parseFloat(m[3])*(m[4]&&m[4].toUpperCase()==='W'?-1:1);
                if(lat>=-90&&lat<=90&&lng>=-180&&lng<=180) return {lat:lat,lng:lng};
            }
            return null;
        }

        /* ── Plus Code → Google Geocoding fallback ────── */
        function tryPlusCode(q,cb){
            var plusRe=/^[23456789CJKLMNPQRUVWXcobdhjqptz+\d]{4,8}\+[23456789CJKLMNPQRUVWXcobdhjqptz+\d]{2,5}$/i;
            if(!plusRe.test(q.replace(/\s/g,''))) return false;
            fetch('https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(q)+'&format=json&limit=1',{headers:{'Accept':'application/json'}})
                .then(function(r){return r.json();})
                .then(function(data){
                    if(data&&data.length){
                        cb({lat:parseFloat(data[0].lat),lng:parseFloat(data[0].lon),name:data[0].display_name});
                    } else {
                        cb(null);
                    }
                }).catch(function(){ cb(null); });
            return true;
        }

        /* ── Manual lat/lng sync ─────────────────────── */
        var latManual=document.getElementById('adLat');
        var lngManual=document.getElementById('adLng');
        function syncLatLng(){
            if(latManual.value&&lngManual.value){
                var lt=parseFloat(latManual.value),ln=parseFloat(lngManual.value);
                if(!isNaN(lt)&&!isNaN(ln)&&lt>=-90&&lt<=90&&ln>=-180&&ln<=180){
                    showMiniMap(lt,ln,latInput.value||lt+', '+ln);
                }
            }
        }
        latManual.addEventListener('input',syncLatLng);
        lngManual.addEventListener('input',syncLatLng);

        function reverseGeocode(lat,lng){
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng+'&zoom=18',{headers:{'Accept':'application/json'}})
                .then(function(r){return r.json();})
                .then(function(d){
                    if(d&&d.display_name){ addrInput.value=d.display_name; }
                }).catch(function(){});
        }

        addrInput.addEventListener('input',function(){
            clearTimeout(acTimer);
            var q=this.value.trim();
            if(q.length<2){ acDropdown.classList.remove('show'); acDropdown.innerHTML=''; return; }
            /* Check if user pasted coordinates */
            var coords=parseCoords(q);
            if(coords){
                latInput.value=coords.lat;
                lngInput.value=coords.lng;
                acDropdown.classList.remove('show');
                acDropdown.innerHTML='';
                showMiniMap(coords.lat,coords.lng,q);
                reverseGeocode(coords.lat,coords.lng);
                return;
            }
            acDropdown.innerHTML='<div class="dest-ac-loading"><i class="fa-solid fa-spinner fa-spin me-1"></i>Searching...</div>';
            acDropdown.classList.add('show');
            acTimer=setTimeout(function(){
                fetch('https://nominatim.openstreetmap.org/search?format=json&q='+encodeURIComponent(q)+'&countrycodes=ph&limit=5',{headers:{'Accept':'application/json'}})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        acResults=data;
                        acFocusIdx=-1;
                        if(!data.length){
                            acDropdown.innerHTML='<div class="dest-ac-loading">No results found</div>'+
                                '<div class="dest-ac-item" id="useAsIs" style="text-align:center;color:#0c6e5e;font-weight:600;cursor:pointer;border-top:1px solid var(--border-color,#e2e8f0);padding:10px;">'+
                                '<i class="fa-solid fa-pen-to-square me-1"></i>Use address as written</div>';
                            return;
                        }
                        acDropdown.innerHTML=data.map(function(d,i){
                            var parts=d.display_name.split(',');
                            var main=parts.slice(0,2).join(',').trim();
                            var rest=parts.slice(2).join(',').trim();
                            return '<div class="dest-ac-item" data-idx="'+i+'"><strong>'+esc(main)+'</strong><small>'+esc(rest)+'</small></div>';
                        }).join('');
                    })
                    .catch(function(){
                        acDropdown.innerHTML='<div class="dest-ac-loading">Search failed</div>'+
                            '<div class="dest-ac-item" id="useAsIs" style="text-align:center;color:#0c6e5e;font-weight:600;cursor:pointer;border-top:1px solid var(--border-color,#e2e8f0);padding:10px;">'+
                            '<i class="fa-solid fa-pen-to-square me-1"></i>Use address as written</div>';
                    });
            },400);
        });

        addrInput.addEventListener('keydown',function(e){
            const items=acDropdown.querySelectorAll('.dest-ac-item');
            if(!items.length) return;
            if(e.key==='ArrowDown'){ e.preventDefault(); acFocusIdx=Math.min(acFocusIdx+1,items.length-1); updateAcFocus(items); }
            else if(e.key==='ArrowUp'){ e.preventDefault(); acFocusIdx=Math.max(acFocusIdx-1,0); updateAcFocus(items); }
            else if(e.key==='Enter'&&acFocusIdx>=0){ e.preventDefault(); selectAcResult(acResults[acFocusIdx]); }
            else if(e.key==='Escape'){ acDropdown.classList.remove('show'); }
        });

        acDropdown.addEventListener('click',function(e){
            /* Handle "Use address as written" fallback */
            if(e.target.closest('#useAsIs')){
                acDropdown.classList.remove('show');
                acDropdown.innerHTML='';
                return;
            }
            var item=e.target.closest('.dest-ac-item');
            if(!item) return;
            var idx=parseInt(item.dataset.idx);
            if(isNaN(idx)||!acResults[idx]) return;
            selectAcResult(acResults[idx]);
        });

        function updateAcFocus(items){
            items.forEach((it,i)=>it.classList.toggle('focused',i===acFocusIdx));
        }

        function selectAcResult(r){
            if(!r) return;
            addrInput.value=r.display_name;
            latInput.value=parseFloat(r.lat);
            lngInput.value=parseFloat(r.lon);
            acDropdown.classList.remove('show');
            showMiniMap(parseFloat(r.lat),parseFloat(r.lon),r.display_name);
        }

        document.addEventListener('click',function(e){
            if(!e.target.closest('.dest-autocomplete-wrap')){
                acDropdown.classList.remove('show');
            }
        });

        /* Detect coords on paste/change (e.g. paste from Google Earth then tab away) */
        addrInput.addEventListener('paste',function(){
            var self=this;
            setTimeout(function(){
                var coords=parseCoords(self.value.trim());
                if(coords){
                    latInput.value=coords.lat;
                    lngInput.value=coords.lng;
                    acDropdown.classList.remove('show');
                    showMiniMap(coords.lat,coords.lng,self.value);
                    reverseGeocode(coords.lat,coords.lng);
                }
            },100);
        });
        addrInput.addEventListener('change',function(){
            var coords=parseCoords(this.value.trim());
            if(coords){
                latInput.value=coords.lat;
                lngInput.value=coords.lng;
                acDropdown.classList.remove('show');
                showMiniMap(coords.lat,coords.lng,this.value);
                reverseGeocode(coords.lat,coords.lng);
            }
        });
    }

    function showMiniMap(lat,lng,label){
        miniMapWrap.style.display='block';
        mapCoords.textContent=lat.toFixed(6)+', '+lng.toFixed(6)+' — '+label.split(',').slice(0,2).join(',');
        if(!leafletMap){
            leafletMap=L.map('adMiniMap',{zoomControl:false,attributionControl:false,scrollWheelZoom:false,dragging:false}).setView([lat,lng],15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(leafletMap);
            leafletMarker=L.marker([lat,lng]).addTo(leafletMap);
        } else {
            leafletMap.setView([lat,lng],15);
            leafletMarker.setLatLng([lat,lng]);
        }
        setTimeout(()=>leafletMap.invalidateSize(),100);
    }

    /* ── Submit Handler ───────────────────────────────────── */
    form.addEventListener('submit',function(e){
        if(!validateTab(cur)){
            e.preventDefault();
            return;
        }
        const spin=document.getElementById('adSpin');
        const icon=document.getElementById('adSubmitIcon');
        if(spin) spin.classList.remove('d-none');
        if(icon) icon.classList.add('d-none');
        submitBtn.disabled=true;
        submitBtn.style.pointerEvents='none';
    });

    /* ── Reset on modal close ─────────────────────────────── */
    const modal=document.getElementById('addDestModal');
    if(modal){
        modal.addEventListener('hidden.bs.modal',function(){
            form.reset();
            goStep(0);
            clearErrors();
            coverPreview.innerHTML='';
            miniMapWrap.style.display='none';
            latInput.value=''; lngInput.value='';
            document.getElementById('adDescCount').textContent='0';
            const spin=document.getElementById('adSpin');
            const icon=document.getElementById('adSubmitIcon');
            if(spin) spin.classList.add('d-none');
            if(icon) icon.classList.remove('d-none');
            submitBtn.disabled=false;
            submitBtn.style.pointerEvents='';
            if(leafletMap){ leafletMap.remove(); leafletMap=null; leafletMarker=null; }
            acDropdown.classList.remove('show');
        });
    }
})();
document.addEventListener('DOMContentLoaded',()=>{
    document.getElementById('edGalInput')?.addEventListener('change',function(){ galPreview(this,'edGalGrid'); });
    document.getElementById('adGalInput')?.addEventListener('change',function(){ galPreview(this,'adGalGrid'); });
});
</script>
<?php }); ?>