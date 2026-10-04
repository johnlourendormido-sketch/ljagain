<?php
/**
 * BINALGO Booking Engine - Unified API Router
 * Routes requests to Hotel, Event, or Resort modules
 * Provides JSON responses with proper HTTP status codes
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../includes/classes/booking_engine/BookingEngineDB.php';
require_once __DIR__ . '/../includes/classes/booking_engine/HotelModule.php';
require_once __DIR__ . '/../includes/classes/booking_engine/EventModule.php';
require_once __DIR__ . '/../includes/classes/booking_engine/ResortModule.php';
require_once __DIR__ . '/../includes/classes/booking_engine/NotificationSystem.php';
require_once __DIR__ . '/../includes/auth.php';

// ── Auth Check ─────────────────────────────────────────────
function requireAuth(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required.']);
        exit;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'role'  => $_SESSION['role'] ?? 'tourist',
        'name'  => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
    ];
}

function requireAdmin(): array
{
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required.']);
        exit;
    }
    return $user;
}

// ── Input Helpers ──────────────────────────────────────────
function getInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
    return array_merge($_GET, $_POST);
}

function success($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function error(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ── Route Parsing ──────────────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim(str_replace('/Tourism/api/', '', $uri), '/');
$segments = explode('/', $uri);
$module = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = isset($segments[2]) ? (int) $segments[2] : null;

// ── Router ─────────────────────────────────────────────────
try {
    switch ($module) {

        // ═══════════════════════════════════════════════════════
        // HOTEL MODULE
        // ═══════════════════════════════════════════════════════
        case 'hotel':
            $hotel = new HotelModule();
            $input = getInput();

            switch ($action) {
                case 'properties':
                    success($hotel->getProperties());
                    break;

                case 'property':
                    if ($id) {
                        $prop = $hotel->getProperty($id);
                        if (!$prop) error('Property not found.', 404);
                        $prop['room_types'] = $hotel->getRoomTypes($id);
                        success($prop);
                    } else {
                        error('Property ID required.', 400);
                    }
                    break;

                case 'create-property':
                    requireAdmin();
                    $id = $hotel->createProperty($input + ['created_by' => $_SESSION['user_id']]);
                    success(['id' => $id, 'message' => 'Property created.'], 201);
                    break;

                case 'room-types':
                    $pid = $id ?? ($input['property_id'] ?? 0);
                    if (!$pid) error('Property ID required.', 400);
                    success($hotel->getRoomTypes((int) $pid));
                    break;

                case 'create-room-type':
                    requireAdmin();
                    $id = $hotel->createRoomType($input);
                    success(['id' => $id, 'message' => 'Room type created.'], 201);
                    break;

                case 'availability':
                    $rtid = (int) ($input['room_type_id'] ?? 0);
                    $ci = $input['check_in_date'] ?? '';
                    $co = $input['check_out_date'] ?? '';
                    if (!$rtid || !$ci || !$co) error('room_type_id, check_in_date, check_out_date required.', 400);
                    success($hotel->checkAvailability($rtid, $ci, $co));
                    break;

                case 'book':
                    requireAuth();
                    $input['user_id'] = $_SESSION['user_id'];
                    $result = $hotel->createBooking($input);

                    // Send notification
                    $notif = new NotificationSystem();
                    $notif->sendBookingNotification('hotel_confirmed', $result['booking_id']);

                    success($result, 201);
                    break;

                case 'confirm':
                    requireAdmin();
                    if (!$id) error('Booking ID required.', 400);
                    $hotel->confirmBooking($id);
                    success(['message' => 'Booking confirmed.']);
                    break;

                case 'cancel':
                    $user = requireAuth();
                    if (!$id) error('Booking ID required.', 400);
                    $booking = $hotel->getBooking($id);
                    if (!$booking) error('Booking not found.', 404);
                    if ($user['role'] !== 'admin' && $booking['user_id'] != $user['id']) {
                        error('Unauthorized.', 403);
                    }
                    $hotel->cancelBooking($id);

                    $notif = new NotificationSystem();
                    $notif->sendBookingNotification('hotel_cancelled', $id);

                    success(['message' => 'Booking cancelled.']);
                    break;

                case 'check-in':
                    requireAdmin();
                    if (!$id) error('Booking ID required.', 400);
                    $hotel->checkIn($id);
                    success(['message' => 'Guest checked in.']);
                    break;

                case 'check-out':
                    requireAdmin();
                    if (!$id) error('Booking ID required.', 400);
                    $hotel->checkOut($id);
                    success(['message' => 'Guest checked out.']);
                    break;

                case 'my-bookings':
                    $user = requireAuth();
                    success($hotel->getUserBookings($user['id']));
                    break;

                case 'booking':
                    if (!$id) error('Booking ID required.', 400);
                    $booking = $hotel->getBooking($id);
                    if (!$booking) error('Not found.', 404);
                    success($booking);
                    break;

                case 'stats':
                    requireAdmin();
                    success($hotel->getStats());
                    break;

                default:
                    error('Unknown hotel action.', 404);
            }
            break;

        // ═══════════════════════════════════════════════════════
        // EVENT MODULE
        // ═══════════════════════════════════════════════════════
        case 'event':
            $eventMod = new EventModule();
            $input = getInput();

            switch ($action) {
                case 'list':
                    success($eventMod->getPublishedEvents());
                    break;

                case 'venues':
                    success($eventMod->getVenues());
                    break;

                case 'create-venue':
                    requireAdmin();
                    $id = $eventMod->createVenue($input + ['created_by' => $_SESSION['user_id']]);
                    success(['id' => $id, 'message' => 'Venue created.'], 201);
                    break;

                case 'detail':
                    if (!$id) error('Event ID required.', 400);
                    $ev = $eventMod->getEvent($id);
                    if (!$ev) error('Event not found.', 404);
                    $ev['tiers'] = $eventMod->getTiers($id);
                    success($ev);
                    break;

                case 'create':
                    requireAdmin();
                    $id = $eventMod->createEvent($input + ['created_by' => $_SESSION['user_id']]);
                    success(['id' => $id, 'message' => 'Event created.'], 201);
                    break;

                case 'publish':
                    requireAdmin();
                    if (!$id) error('Event ID required.', 400);
                    $eventMod->publishEvent($id);
                    success(['message' => 'Event published.']);
                    break;

                case 'create-tier':
                    requireAdmin();
                    $id = $eventMod->createTier($input);
                    success(['id' => $id, 'message' => 'Ticket tier created.'], 201);
                    break;

                case 'tiers':
                    if (!$id) error('Event ID required.', 400);
                    success($eventMod->getTiers($id));
                    break;

                case 'lock-tickets':
                    $user = requireAuth();
                    $tierId = (int) ($input['tier_id'] ?? 0);
                    $qty = (int) ($input['quantity'] ?? 1);
                    if (!$tierId) error('tier_id required.', 400);
                    if ($qty < 1) error('quantity must be at least 1.', 400);
                    success($eventMod->lockTickets($tierId, $user['id'], $qty), 201);
                    break;

                case 'release-lock':
                    $user = requireAuth();
                    $lockId = (int) ($input['lock_id'] ?? 0);
                    if (!$lockId) error('lock_id required.', 400);
                    $eventMod->releaseLock($lockId, $user['id']);
                    success(['message' => 'Lock released.']);
                    break;

                case 'purchase':
                    $user = requireAuth();
                    $input['user_id'] = $user['id'];
                    $result = $eventMod->createOrder($input);

                    // Confirm tickets and send notification
                    $eventMod->confirmTickets($result['order_id']);
                    $notif = new NotificationSystem();
                    $notif->sendBookingNotification('event_ticket_purchased', $result['order_id']);

                    success($result, 201);
                    break;

                case 'verify-ticket':
                    $token = $input['qr_token'] ?? ($input['token'] ?? '');
                    if (!$token) error('qr_token required.', 400);
                    $ticket = $eventMod->verifyTicket($token);
                    if (!$ticket) error('Invalid ticket.', 404);
                    success($ticket);
                    break;

                case 'check-in':
                    requireAdmin();
                    $ticketId = (int) ($input['ticket_id'] ?? $id ?? 0);
                    if (!$ticketId) error('ticket_id required.', 400);
                    $eventMod->checkInTicket($ticketId);
                    success(['message' => 'Ticket checked in.']);
                    break;

                case 'cancel-order':
                    $user = requireAuth();
                    if (!$id) error('Order ID required.', 400);
                    $order = $eventMod->getOrder($id);
                    if (!$order) error('Not found.', 404);
                    if ($user['role'] !== 'admin' && $order['user_id'] != $user['id']) {
                        error('Unauthorized.', 403);
                    }
                    $eventMod->cancelOrder($id);
                    success(['message' => 'Order cancelled.']);
                    break;

                case 'my-orders':
                    $user = requireAuth();
                    success($eventMod->getUserOrders($user['id']));
                    break;

                case 'order':
                    if (!$id) error('Order ID required.', 400);
                    $order = $eventMod->getOrder($id);
                    if (!$order) error('Not found.', 404);
                    $order['tickets'] = $eventMod->getOrderTickets($id);
                    success($order);
                    break;

                case 'stats':
                    requireAdmin();
                    success($eventMod->getStats());
                    break;

                default:
                    error('Unknown event action.', 404);
            }
            break;

        // ═══════════════════════════════════════════════════════
        // RESORT MODULE
        // ═══════════════════════════════════════════════════════
        case 'resort':
            $resort = new ResortModule();
            $input = getInput();

            switch ($action) {
                case 'properties':
                    success($resort->getProperties());
                    break;

                case 'property':
                    if (!$id) error('Property ID required.', 400);
                    $prop = $resort->getProperty($id);
                    if (!$prop) error('Not found.', 404);
                    $prop['amenities'] = $resort->getAmenities($id);
                    $prop['addons'] = $resort->getAddons($id);
                    success($prop);
                    break;

                case 'create-property':
                    requireAdmin();
                    $id = $resort->createProperty($input + ['created_by' => $_SESSION['user_id']]);
                    success(['id' => $id, 'message' => 'Property created.'], 201);
                    break;

                case 'amenities':
                    $pid = $id ?? ($input['property_id'] ?? 0);
                    if (!$pid) error('Property ID required.', 400);
                    $type = $input['type'] ?? null;
                    success($resort->getAmenities((int) $pid, $type));
                    break;

                case 'create-amenity':
                    requireAdmin();
                    $id = $resort->createAmenity($input);
                    success(['id' => $id, 'message' => 'Amenity created.'], 201);
                    break;

                case 'addons':
                    $pid = $id ?? ($input['property_id'] ?? 0);
                    if (!$pid) error('Property ID required.', 400);
                    success($resort->getAddons((int) $pid));
                    break;

                case 'create-addon':
                    requireAdmin();
                    $id = $resort->createAddon($input);
                    success(['id' => $id, 'message' => 'Add-on created.'], 201);
                    break;

                case 'time-slots':
                    success($resort->getTimeSlots());
                    break;

                case 'availability':
                    $aid = (int) ($input['amenity_id'] ?? 0);
                    $date = $input['date'] ?? '';
                    $tsid = (int) ($input['time_slot_id'] ?? 0);
                    if (!$aid || !$date || !$tsid) error('amenity_id, date, time_slot_id required.', 400);
                    success($resort->checkAvailability($aid, $date, $tsid));
                    break;

                case 'book':
                    requireAuth();
                    $input['user_id'] = $_SESSION['user_id'];
                    $result = $resort->createBooking($input);

                    $notif = new NotificationSystem();
                    $notif->sendBookingNotification('resort_confirmed', $result['booking_id']);

                    success($result, 201);
                    break;

                case 'confirm':
                    requireAdmin();
                    if (!$id) error('Booking ID required.', 400);
                    $resort->confirmBooking($id);
                    success(['message' => 'Booking confirmed.']);
                    break;

                case 'cancel':
                    $user = requireAuth();
                    if (!$id) error('Booking ID required.', 400);
                    $booking = $resort->getBooking($id);
                    if (!$booking) error('Not found.', 404);
                    if ($user['role'] !== 'admin' && $booking['user_id'] != $user['id']) {
                        error('Unauthorized.', 403);
                    }
                    $resort->cancelBooking($id);
                    success(['message' => 'Booking cancelled.']);
                    break;

                case 'complete':
                    requireAdmin();
                    if (!$id) error('Booking ID required.', 400);
                    $resort->completeBooking($id);

                    $booking = $resort->getBooking($id);
                    if ($booking && (float) $booking['security_deposit'] > 0) {
                        $notif = new NotificationSystem();
                        $notif->sendBookingNotification('resort_deposit_returned', $id);
                    }

                    success(['message' => 'Booking completed.']);
                    break;

                case 'my-bookings':
                    $user = requireAuth();
                    success($resort->getUserBookings($user['id']));
                    break;

                case 'booking':
                    if (!$id) error('Booking ID required.', 400);
                    $booking = $resort->getBooking($id);
                    if (!$booking) error('Not found.', 404);
                    $booking['addons'] = $resort->getBookingAddons($id);
                    success($booking);
                    break;

                case 'stats':
                    requireAdmin();
                    success($resort->getStats());
                    break;

                default:
                    error('Unknown resort action.', 404);
            }
            break;

        // ═══════════════════════════════════════════════════════
        // CROSS-MODULE
        // ═══════════════════════════════════════════════════════
        case 'cleanup':
            requireAdmin();
            $eventMod = new EventModule();
            $released = $eventMod->cleanupExpiredLocks();
            success(['expired_locks_released' => $released]);
            break;

        case 'health':
            success(['status' => 'ok', 'time' => date('c')]);
            break;

        default:
            error('Unknown module.', 404);
    }
} catch (InvalidArgumentException $e) {
    error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    error($e->getMessage(), 409);
} catch (Exception $e) {
    error('Internal server error: ' . $e->getMessage(), 500);
}
