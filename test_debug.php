<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
start_session();

$db = Database::getInstance()->getConnection();

echo "=== Users ===" . PHP_EOL;
$stmt = $db->query("SELECT id, role, status, email FROM users LIMIT 5");
while ($row = $stmt->fetch()) {
    echo "  ID={$row['id']} role={$row['role']} status={$row['status']} email={$row['email']}" . PHP_EOL;
}

echo PHP_EOL . "=== Test login ===" . PHP_EOL;
$result = login('admin@tourism.com', 'admin123');
echo "success: " . ($result['success'] ? 'true' : 'false') . PHP_EOL;
if (!$result['success']) {
    echo "message: " . ($result['message'] ?? 'none') . PHP_EOL;
} else {
    echo "role: " . ($result['user']['role'] ?? 'none') . PHP_EOL;
    echo "redirect would be: " . BASE_URL . "/{$result['user']['role']}/index.php" . PHP_EOL;
}

echo PHP_EOL . "=== Test homepage queries ===" . PHP_EOL;
try {
    $db->query("SELECT COUNT(*) FROM destinations WHERE status='active'");
    echo "destinations: OK" . PHP_EOL;
} catch (Throwable $e) {
    echo "destinations: FAIL - " . $e->getMessage() . PHP_EOL;
}

try {
    $db->query("SELECT COUNT(*) FROM destination_reviews");
    echo "destination_reviews: OK" . PHP_EOL;
} catch (Throwable $e) {
    echo "destination_reviews: FAIL - " . $e->getMessage() . PHP_EOL;
}

try {
    $db->query("SELECT * FROM destinations d WHERE d.status = 'active' ORDER BY d.featured DESC LIMIT 1");
    echo "destinations featured: OK" . PHP_EOL;
} catch (Throwable $e) {
    echo "destinations featured: FAIL - " . $e->getMessage() . PHP_EOL;
}
