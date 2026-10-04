<?php
/**
 * BINALBAGAN TOURISM — Database Seed Script
 * Populates the database with realistic sample data scoped to Binalbagan, Negros Occidental.
 *
 * Run: php seed_binalbagan.php
 * SAFE TO RE-RUN: Clears existing data before seeding.
 */

require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance()->getConnection();
$adminId = 1; // admin@binalgo.com

// ─── Helpers ────────────────────────────────────────────────
function guid(): string {
    return strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-4' . substr(bin2hex(random_bytes(2)), 1) . '-' . substr('89ab', random_int(0, 3), 1) . substr(bin2hex(random_bytes(2)), 1) . '-' . bin2hex(random_bytes(6)));
}

function randomDate(string $start, string $end): string {
    $s = strtotime($start);
    $e = strtotime($end);
    return date('Y-m-d', random_int($s, $e));
}

function randomTime(int $hStart, int $hEnd): string {
    return sprintf('%02d:%02d:00', random_int($hStart, $hEnd), random_int(0, 1) * 30);
}

// ─── Clear existing data (preserve schema) ──────────────────
echo "Clearing existing data...\n";
$tables = ['feedback', 'guide_payouts', 'payments', 'bookings', 'reservations',
           'schedules', 'destination_reviews', 'destination_seasons', 'destination_guides',
           'event_bookmarks', 'dest_bookmarks', 'activity_logs', 'notifications',
           'events', 'destinations'];
foreach ($tables as $t) {
    $db->exec("DELETE FROM `$t`");
    $db->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1");
}
// Preserve admin, create new tourist/guide accounts
$db->exec("DELETE FROM users WHERE role IN ('tourist','guide')");
$db->exec("ALTER TABLE users AUTO_INCREMENT = 20");

echo "Seeding users...\n";

// ─── Tourist Accounts (6) ──────────────────────────────────
$hash = password_hash('password123', PASSWORD_DEFAULT);
$tourists = [
    ['username' => 'maria.santos',   'email' => 'maria.santos@email.com',   'name' => 'Maria Santos',       'gender' => 'female', 'age' => 28],
    ['username' => 'juan.delacruz',  'email' => 'juan.delacruz@email.com',  'name' => 'Juan Dela Cruz',     'gender' => 'male',   'age' => 34],
    ['username' => 'ana.reyes',      'email' => 'ana.reyes@email.com',      'name' => 'Ana Reyes',          'gender' => 'female', 'age' => 22],
    ['username' => 'carlos.garcia',  'email' => 'carlos.garcia@email.com',  'name' => 'Carlos Garcia',      'gender' => 'male',   'age' => 41],
    ['username' => 'rosa.mendoza',   'email' => 'rosa.mendoza@email.com',   'name' => 'Rosa Mendoza',       'gender' => 'female', 'age' => 30],
    ['username' => 'pedro.villanueva','email' => 'pedro.villanueva@email.com','name' => 'Pedro Villanueva', 'gender' => 'male',   'age' => 26],
];

$stmtUser = $db->prepare("INSERT INTO users (username, email, password, role, name, gender, age, phone, status, created_at)
    VALUES (:u, :e, :p, 'tourist', :n, :g, :a, :ph, 'active', NOW() - INTERVAL :days DAY)");
$touristIds = [];
foreach ($tourists as $i => $t) {
    $days = random_int(30, 120);
    $stmtUser->execute([':u' => $t['username'], ':e' => $t['email'], ':p' => $hash, ':n' => $t['name'],
        ':g' => $t['gender'], ':a' => $t['age'], ':ph' => '09' . random_int(100000000, 999999999), ':days' => $days]);
    $touristIds[] = (int) $db->lastInsertId();
}
echo "  Created " . count($touristIds) . " tourists\n";

// ─── Guide Account ──────────────────────────────────────────
$db->prepare("INSERT INTO users (username, email, password, role, name, gender, age, phone, status, created_at)
    VALUES ('brian.guide', 'brian.guide@binalgo.com', :p, 'guide', 'Brian Locsin', 'male', 35, '09181234567', 'active', NOW() - INTERVAL 90 DAY)")
    ->execute([':p' => $hash]);
$guideId = (int) $db->lastInsertId();
echo "  Created guide: Brian Locsin (#$guideId)\n";

// ─── Guide Profile ──────────────────────────────────────────
$db->prepare("INSERT INTO guide_profiles (user_id, years_of_experience, languages, specializations, availability_status, bio)
    VALUES (:uid, 8, 'Filipino,English', 'Nature,Hiking,Cultural', 'available', 'Local guide from Binalbagan. Born and raised in Bi-ao, I know every trail and waterfall in the municipality.')")
    ->execute([':uid' => $guideId]);

echo "Seeding destinations...\n";

// ─── Destinations (6) ───────────────────────────────────────
$destinations = [
    [
        'name' => 'Binadlan Falls (Bambi Falls)',
        'description' => 'A stunning multi-tiered waterfall nestled in Sitio Binadlan, Barangay Bi-ao. The falls cascade through lush tropical forest, forming natural swimming pools. Best visited during the rainy season (June-November) when water flow is at its peak. A moderate 20-minute trek from the drop-off point leads to the main cascade.',
        'location' => 'Sitio Binadlan, Barangay Bi-ao, Binalbagan, Negros Occidental',
        'category' => 'nature_adventure',
        'difficulty' => 'moderate',
        'latitude' => 10.1580, 'longitude' => 122.8620,
        'entrance_fee' => 30.00, 'featured' => 1, 'booking_enabled' => 1,
        'capacity_limit' => 50, 'max_guests_per_booking' => 10,
        'operating_hours_open' => '06:00:00', 'operating_hours_close' => '17:00:00',
        'contact_phone' => '+63 918 123 4567', 'contact_email' => 'binalbagan.tourism@binalgo.gov.ph',
        'facilities' => 'Parking area,Restrooms,Changing rooms,Guide station',
        'rules_regulations' => 'No littering. No alcohol. Follow guide instructions. Children must be accompanied by adults.',
        'cancellation_policy' => 'Free cancellation up to 24 hours before visit. No-shows are non-refundable.',
    ],
    [
        'name' => 'Mount Hermit',
        'description' => 'A prominent peak in Barangay Bi-ao offering panoramic views of Binalbagan and the Panay Gulf. Popular among local hikers and nature enthusiasts. The summit trail passes through secondary forest and grassland. Sunrise hikes are especially popular, with clear days revealing Guimaras Island in the distance.',
        'location' => 'Barangay Bi-ao, Binalbagan, Negros Occidental',
        'category' => 'nature_adventure',
        'difficulty' => 'difficult',
        'latitude' => 10.1650, 'longitude' => 122.8500,
        'entrance_fee' => 50.00, 'featured' => 1, 'booking_enabled' => 1,
        'capacity_limit' => 30, 'max_guests_per_booking' => 8,
        'operating_hours_open' => '05:00:00', 'operating_hours_close' => '16:00:00',
        'contact_phone' => '+63 918 123 4567', 'contact_email' => 'binalbagan.tourism@binalgo.gov.ph',
        'facilities' => 'Parking area,Trail markers,Viewing deck',
        'rules_regulations' => 'Guide required. Start early. Bring water. No solo hiking.',
        'cancellation_policy' => 'Free cancellation up to 48 hours. Weather-dependent rescheduling available.',
    ],
    [
        'name' => 'Binalbagan Poblacion Heritage Walk',
        'description' => 'A guided walking tour through the historic town center of Binalbagan, covering Progreso, San Pedro, and Santo Rosario barangays. Highlights include the centuries-old Catholic church, ancestral houses, the public market, and the municipal plaza. Learn about Binalbagan\'s rich history as one of Negros Occidental\'s oldest municipalities.',
        'location' => 'Poblacion, Binalbagan, Negros Occidental',
        'category' => 'cultural_attractions',
        'difficulty' => 'easy',
        'latitude' => 10.1925, 'longitude' => 122.8705,
        'entrance_fee' => 0.00, 'featured' => 1, 'booking_enabled' => 1,
        'capacity_limit' => 40, 'max_guests_per_booking' => 15,
        'operating_hours_open' => '07:00:00', 'operating_hours_close' => '17:00:00',
        'contact_phone' => '+63 918 123 4567', 'contact_email' => 'binalbagan.tourism@binalgo.gov.ph',
        'facilities' => 'Tourist information center,Public restrooms,Accessible pathways',
        'rules_regulations' => 'Respect private properties. Follow designated routes.',
        'cancellation_policy' => 'Free cancellation anytime.',
    ],
    [
        'name' => 'Binalbagan Coastal Promenade',
        'description' => 'A scenic waterfront stretch along the Panay Gulf coastline. Perfect for evening walks, jogging, and watching spectacular sunsets over Guimaras Island. The promenade passes through the fishing barangays of Enclaro and Marina, offering views of local bancas and the daily catch being brought to shore.',
        'location' => 'Barangay Enclaro, Binalbagan, Negros Occidental',
        'category' => 'beaches',
        'difficulty' => 'easy',
        'latitude' => 10.1850, 'longitude' => 122.8850,
        'entrance_fee' => 0.00, 'featured' => 0, 'booking_enabled' => 0,
        'capacity_limit' => 100, 'max_guests_per_booking' => 20,
        'operating_hours_open' => '05:00:00', 'operating_hours_close' => '21:00:00',
        'contact_phone' => null, 'contact_email' => null,
        'facilities' => 'Benches,Street lights,Fish vendor stalls',
        'rules_regulations' => 'No littering. Keep noise levels down after 9 PM.',
        'cancellation_policy' => null,
    ],
    [
        'name' => 'Binalbagan Public Market & Food Tour',
        'description' => 'Experience the vibrant daily life of Binalbagan at the public market in Poblacion. Sample local delicacies including piaya, batchoy, and fresh seafood. A self-guided food tour through the market and surrounding streets reveals the culinary heart of this 1st class municipality.',
        'location' => 'Poblacion, Binalbagan, Negros Occidental',
        'category' => 'cultural_attractions',
        'difficulty' => 'easy',
        'latitude' => 10.1915, 'longitude' => 122.8715,
        'entrance_fee' => 0.00, 'featured' => 0, 'booking_enabled' => 0,
        'capacity_limit' => 200, 'max_guests_per_booking' => 20,
        'operating_hours_open' => '05:00:00', 'operating_hours_close' => '20:00:00',
        'contact_phone' => null, 'contact_email' => null,
        'facilities' => 'Food stalls,Seating areas,Restrooms',
        'rules_regulations' => 'Dispose of waste properly. Be respectful of vendors.',
        'cancellation_policy' => null,
    ],
    [
        'name' => 'San Vicente Mango Orchard',
        'description' => 'A sprawling mango orchard in Barangay San Vicente that offers agri-tourism experiences. Visitors can tour the orchard, learn about mango cultivation, and purchase fresh or dried mangoes directly from the farm. Seasonal mango-picking activities are available from March to May.',
        'location' => 'Barangay San Vicente, Binalbagan, Negros Occidental',
        'category' => 'nature_adventure',
        'difficulty' => 'easy',
        'latitude' => 10.2050, 'longitude' => 122.8550,
        'entrance_fee' => 25.00, 'featured' => 0, 'booking_enabled' => 1,
        'capacity_limit' => 40, 'max_guests_per_booking' => 12,
        'operating_hours_open' => '07:00:00', 'operating_hours_close' => '17:00:00',
        'contact_phone' => '+63 918 234 5678', 'contact_email' => 'sanvicente.farm@email.com',
        'facilities' => 'Farm tour path,Fruit processing area,Parking',
        'rules_regulations' => 'Stay on designated paths. Do not pick fruit without guide permission.',
        'cancellation_policy' => 'Free cancellation up to 12 hours before visit.',
    ],
];

$destIds = [];
$destStmt = $db->prepare("INSERT INTO destinations
    (name, description, location, category, difficulty, latitude, longitude, entrance_fee, featured,
     booking_enabled, capacity_limit, max_guests_per_booking, operating_hours_open, operating_hours_close,
     contact_phone, contact_email, facilities, rules_regulations, cancellation_policy,
     available_booking_days, booking_cutoff_hours, advance_booking_days, status, guide_required, created_by, created_at)
    VALUES (:name, :desc, :loc, :cat, :diff, :lat, :lng, :fee, :feat,
            :bk, :cap, :maxg, :open, :close,
            :phone, :email, :fac, :rules, :cancel,
            'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 2, 1, 'active', 1, :uid, NOW() - INTERVAL :days DAY)");

foreach ($destinations as $i => $d) {
    $days = random_int(30, 90);
    $destStmt->execute([
        ':name' => $d['name'], ':desc' => $d['description'], ':loc' => $d['location'],
        ':cat' => $d['category'], ':diff' => $d['difficulty'],
        ':lat' => $d['latitude'], ':lng' => $d['longitude'], ':fee' => $d['entrance_fee'],
        ':feat' => $d['featured'], ':bk' => $d['booking_enabled'],
        ':cap' => $d['capacity_limit'], ':maxg' => $d['max_guests_per_booking'],
        ':open' => $d['operating_hours_open'], ':close' => $d['operating_hours_close'],
        ':phone' => $d['contact_phone'], ':email' => $d['contact_email'],
        ':fac' => $d['facilities'], ':rules' => $d['rules_regulations'],
        ':cancel' => $d['cancellation_policy'], ':uid' => $adminId, ':days' => $days,
    ]);
    $destIds[] = (int) $db->lastInsertId();
}
echo "  Created " . count($destIds) . " destinations: " . implode(', ', $destIds) . "\n";

// ─── Assign guide to nature destinations ────────────────────
$gStmt = $db->prepare("INSERT INTO destination_guides (destination_id, guide_id, is_primary) VALUES (:did, :gid, :pri)");
foreach ([$destIds[0], $destIds[1]] as $i => $did) {
    $gStmt->execute([':did' => $did, ':gid' => $guideId, ':pri' => $i === 0 ? 1 : 0]);
}
echo "  Assigned guide to destinations #{$destIds[0]}, #{$destIds[1]}\n";

// ─── Destination Seasons ────────────────────────────────────
$sStmt = $db->prepare("INSERT INTO destination_seasons (destination_id, season_type, months, description) VALUES (:did, :type, :months, :desc)");
$sStmt->execute([':did' => $destIds[0], ':type' => 'peak', ':months' => '6-11', ':desc' => 'Best time to visit — high water flow']);
$sStmt->execute([':did' => $destIds[0], ':type' => 'off_peak', ':months' => '12-5', ':desc' => 'Lower water flow, but still accessible']);
$sStmt->execute([':did' => $destIds[1], ':type' => 'peak', ':months' => '11-2', ':desc' => 'Cool weather, clear skies for summit views']);
$sStmt->execute([':did' => $destIds[5], ':type' => 'peak', ':months' => '3-5', ':desc' => 'Mango-picking season']);

echo "Seeding events...\n";

// ─── Events (3) ─────────────────────────────────────────────
$events = [
    [
        'title' => 'Balbagan Festival 2026',
        'description' => 'The annual Balbagan Festival celebrates the founding of Binalbagan and its vibrant culture. Features street dancing competitions, food fairs, trade exhibits, beauty pageants, and the signature "Sayaw sa Kalsada" street parade. The festival draws visitors from across Negros Occidental and showcases Binalbagan\'s agricultural products and cottage industries.',
        'destination_id' => $destIds[2], // Poblacion Heritage Walk
        'category' => 'festival',
        'event_location' => 'Municipal Plaza, Poblacion, Binalbagan',
        'event_start_date' => '2026-05-15',
        'event_end_date' => '2026-05-22',
        'event_start_time' => '06:00:00',
        'event_end_time' => '22:00:00',
        'organizer' => 'Municipality of Binalbagan',
        'max_participants' => 500,
        'price' => 0.00,
        'status' => 'published',
        'duration_hours' => 16.00,
    ],
    [
        'title' => 'Bi-ao Barangay Fiesta - San Vicente Ferrer',
        'description' => 'Annual barangay fiesta in Bi-ao honoring their patron saint San Vicente Ferrer. Features a novena mass, community feast, parlor games, and live band entertainment. Visitors are welcome to join the celebrations and experience warm Binalbagan hospitality.',
        'destination_id' => $destIds[0], // Binadlan Falls (Bi-ao)
        'category' => 'cultural_event',
        'event_location' => 'Barangay Bi-ao, Binalbagan, Negros Occidental',
        'event_start_date' => '2026-04-28',
        'event_end_date' => '2026-04-29',
        'event_start_time' => '06:00:00',
        'event_end_time' => '23:00:00',
        'organizer' => 'Barangay Bi-ao Council',
        'max_participants' => 200,
        'price' => 0.00,
        'status' => 'published',
        'duration_hours' => 17.00,
    ],
    [
        'title' => 'Enclaro Coastal Cleanup &amp; Mangrove Planting',
        'description' => 'An environmental awareness event combining coastal cleanup and mangrove planting along the Panay Gulf shoreline in Enclaro. Organized in partnership with local fisherfolk and the Binalbagan Environmental Office. Open to volunteers of all ages.',
        'destination_id' => $destIds[3], // Coastal Promenade
        'category' => 'community_event',
        'event_location' => 'Barangay Enclaro, Binalbagan, Negros Occidental',
        'event_start_date' => '2026-06-08',
        'event_end_date' => '2026-06-08',
        'event_start_time' => '06:00:00',
        'event_end_time' => '12:00:00',
        'organizer' => 'Binalbagan Environmental Office',
        'max_participants' => 100,
        'price' => 0.00,
        'status' => 'published',
        'duration_hours' => 6.00,
    ],
];

$eventIds = [];
$evtStmt = $db->prepare("INSERT INTO events
    (title, description, destination_id, category, event_location, event_start_date, event_end_date,
     event_start_time, event_end_time, organizer, max_participants, price, status,
     duration_hours, requires_guide, created_by, created_at)
    VALUES (:title, :desc, :did, :cat, :loc, :start, :end, :stime, :etime, :org, :max, :price, :status,
            :dur, 0, :uid, NOW() - INTERVAL :days DAY)");

foreach ($events as $i => $e) {
    $days = random_int(10, 60);
    $evtStmt->execute([
        ':title' => $e['title'], ':desc' => $e['description'], ':did' => $e['destination_id'],
        ':cat' => $e['category'], ':loc' => $e['event_location'],
        ':start' => $e['event_start_date'], ':end' => $e['event_end_date'],
        ':stime' => $e['event_start_time'], ':etime' => $e['event_end_time'],
        ':org' => $e['organizer'], ':max' => $e['max_participants'],
        ':price' => $e['price'], ':status' => $e['status'],
        ':dur' => $e['duration_hours'], ':uid' => $adminId, ':days' => $days,
    ]);
    $eventIds[] = (int) $db->lastInsertId();
}
echo "  Created " . count($eventIds) . " events: " . implode(', ', $eventIds) . "\n";

echo "Seeding bookings & payments...\n";

// ─── Bookings & Payments (20, spread over 6 months) ─────────
$bookingStatuses = ['confirmed', 'paid', 'completed', 'completed', 'completed', 'cancelled'];
$payMethods = ['gcash', 'maya', 'card', 'cash'];
$bookings = [];
$bookStmt = $db->prepare("INSERT INTO bookings
    (tourist_id, full_name, email, contact_number, destination_id, guide_id, schedule_id,
     booking_reference, booking_date, visit_date, visit_time, num_participants, total_price,
     service_fee, status, payment_status, payment_method, special_requests, created_at)
    VALUES (:tid, :name, :email, :phone, :did, :gid, NULL,
            :ref, :bdate, :vdate, :vtime, :pax, :price,
            :fee, :status, :pstat, :pmeth, :req, :created)");

$payStmt = $db->prepare("INSERT INTO payments
    (booking_id, tourist_id, amount, tax, service_fee, total_amount,
     payment_method, transaction_id, reference_number, payment_status, payment_date, created_at)
    VALUES (:bid, :tid, :amt, :tax, :fee, :total,
            :pmeth, :txid, :ref, :pstat, :pdate, :created)");

// Map: booking ID → generated for feedback
$completedBookingIds = [];

foreach ($touristIds as $ti => $tid) {
    // Each tourist makes 2-4 bookings
    $numBookings = random_int(2, 4);
    for ($b = 0; $b < $numBookings; $b++) {
        // Pick a bookable destination
        $bookableDests = [$destIds[0], $destIds[1], $destIds[2], $destIds[5]];
        $did = $bookableDests[array_rand($bookableDests)];
        $hasGuide = ($did === $destIds[0] || $did === $destIds[1]);
        $gid = $hasGuide ? $guideId : null;

        // Price based on entrance_fee * pax + guide fee
        $feePerPax = ($did === $destIds[0]) ? 30 : (($did === $destIds[1]) ? 50 : (($did === $destIds[2]) ? 0 : 25));
        $guideFee = $hasGuide ? 200 : 0;
        $pax = random_int(1, 5);
        $total = ($feePerPax * $pax) + $guideFee;
        $serviceFee = round($total * 0.05, 2);

        // Spread bookings: some past, some upcoming. Ramp toward May (Balbagan Festival)
        $monthWeights = ['2025-11' => 1, '2025-12' => 2, '2026-01' => 2, '2026-02' => 3, '2026-03' => 3, '2026-04' => 4, '2026-05' => 5, '2026-06' => 3, '2026-07' => 2, '2026-08' => 1];
        $months = array_keys($monthWeights);
        $monthPool = [];
        foreach ($monthWeights as $m => $w) $monthPool = array_merge($monthPool, array_fill(0, $w, $m));
        $randMonth = $monthPool[array_rand($monthPool)];
        $visitDate = $randMonth . '-' . str_pad(random_int(1, 28), 2, '0', STR_PAD_LEFT);
        $bookDate = date('Y-m-d H:i:s', strtotime($visitDate . ' -' . random_int(3, 14) . ' day'));

        $status = $bookingStatuses[array_rand($bookingStatuses)];
        $pstat = in_array($status, ['paid', 'completed']) ? 'paid' : ($status === 'cancelled' ? 'refunded' : 'unpaid');
        $ref = 'BGO-' . strtoupper(substr(uniqid(), -6)) . strtoupper(bin2hex(random_bytes(2)));
        $pmeth = $payMethods[array_rand($payMethods)];
        $req = $pax > 2 ? 'Group booking. Need extra guides.' : null;

        $bookStmt->execute([
            ':tid' => $tid, ':name' => $tourists[$ti]['name'], ':email' => $tourists[$ti]['email'],
            ':phone' => '09' . random_int(100000000, 999999999),
            ':did' => $did, ':gid' => $gid, ':ref' => $ref,
            ':bdate' => $bookDate, ':vdate' => $visitDate,
            ':vtime' => randomTime(6, 15), ':pax' => $pax,
            ':price' => $total, ':fee' => $serviceFee,
            ':status' => $status, ':pstat' => $pstat, ':pmeth' => $pmeth,
            ':req' => $req, ':created' => $bookDate,
        ]);
        $bid = (int) $db->lastInsertId();
        $bookings[] = $bid;

        // Payment record for paid/completed bookings
        if (in_array($status, ['paid', 'completed'])) {
            $tax = round($total * 0.12, 2);
            $payStmt->execute([
                ':bid' => $bid, ':tid' => $tid,
                ':amt' => $total, ':tax' => $tax, ':fee' => $serviceFee,
                ':total' => $total + $tax + $serviceFee,
                ':pmeth' => $pmeth, ':txid' => 'TXN-' . strtoupper(bin2hex(random_bytes(8))),
                ':ref' => strtoupper(substr($ref, -8)),
                ':pstat' => 'paid',
                ':pdate' => $bookDate,
                ':created' => $bookDate,
            ]);
        }

        if ($status === 'completed') {
            $completedBookingIds[] = $bid;
        }
    }
}
echo "  Created " . count($bookings) . " bookings\n";

echo "Seeding destination reviews...\n";

// ─── Destination Reviews (12) ───────────────────────────────
$reviewTexts = [
    ['Binadlan Falls', 5, 'Amazing waterfall! The trek was worth it. Our guide was very knowledgeable and friendly. Perfect for a day trip with friends.'],
    ['Binadlan Falls', 4, 'Beautiful falls but the trail was a bit muddy during rainy season. Still highly recommended for nature lovers.'],
    ['Binadlan Falls', 5, 'The natural pools are perfect for swimming. Clean and refreshing. Will definitely come back!'],
    ['Mount Hermit', 5, 'Challenging but rewarding hike. The view from the summit is breathtaking — you can see all of Binalbagan and even Guimaras Island.'],
    ['Mount Hermit', 4, 'Great hike with stunning views. The trail is well-maintained. Start early to avoid the midday heat.'],
    ['Heritage Walk', 5, 'I grew up in Binalbagan but never appreciated the history until this tour. The ancestral houses are beautiful and our guide brought the stories to life.'],
    ['Heritage Walk', 4, 'Informative and fun walk through town. The church is stunning. Would love more signage for self-guided tours.'],
    ['Coastal Promenade', 4, 'Perfect for evening walks. The sunset over Guimaras is spectacular. Just wish there were more food options nearby.'],
    ['Coastal Promenade', 3, 'Nice enough for a quick walk. The fish vendor stalls add local charm. Could use better seating.'],
    ['Market Food Tour', 5, 'The piaya here is the best I\'ve ever had! The market is vibrant and the vendors are so friendly. A must-visit for foodies.'],
    ['Market Food Tour', 4, 'Great experience trying local food. The batchoy is incredible. Loved every bite.'],
    ['Mango Orchard', 4, 'Fun farm tour! The mangoes are delicious. We got to pick our own during season. Great for families.'],
];

$rvStmt = $db->prepare("INSERT INTO destination_reviews
    (destination_id, user_id, rating, review, is_hidden, created_at)
    VALUES (:did, :uid, :rating, :review, 0, NOW() - INTERVAL :days DAY)");

foreach ($reviewTexts as $i => $r) {
    // Find destination ID by name
    $did = null;
    foreach ($destinations as $di => $d) {
        if (strpos($d['name'], substr($r[0], 0, 10)) !== false) { $did = $destIds[$di]; break; }
    }
    if (!$did) $did = $destIds[0];
    $uid = $touristIds[array_rand($touristIds)];
    $days = random_int(5, 60);
    $rvStmt->execute([':did' => $did, ':uid' => $uid, ':rating' => $r[1], ':review' => $r[2], ':days' => $days]);
}
echo "  Created " . count($reviewTexts) . " reviews\n";

echo "Seeding feedback...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");

// ─── Feedback (8) ───────────────────────────────────────────
$feedbackTexts = [
    ['Very helpful booking system. The directions to Binadlan Falls were accurate and the guide was excellent.', 5],
    ['I wish there were more events listed for Binalbagan. The Balbagan Festival was amazing though!', 4],
    ['The website is easy to use. Booking a tour was straightforward. Great job, BINALGO team!', 5],
    ['It would be nice to have more payment options. GCash integration works well though.', 4],
    ['Love the destination photos and descriptions. Helped me plan my weekend trip perfectly.', 5],
    ['The weather advisory feature is very useful. We rescheduled our hike because of rain and it was the right call.', 4],
    ['Suggestion: Add a feature to save favorite destinations. Also, more info on local food would be great.', 3],
    ['Excellent platform for promoting local tourism. Binalbagan has so much to offer and this site showcases it well.', 5],
];

$fbStmt = $db->prepare("INSERT INTO feedback
    (booking_id, tourist_id, guide_id, guide_rating, communication_rating,
     safety_rating, organization_rating, overall_rating, comment, created_at)
    VALUES (:bid, :tid, :gid, :gr, :cr, :sr, :or, :ov, :comment, NOW() - INTERVAL :days DAY)");

foreach ($feedbackTexts as $i => $f) {
    $bid = !empty($completedBookingIds) ? $completedBookingIds[array_rand($completedBookingIds)] : $bookings[array_rand($bookings)];
    $tid = $touristIds[array_rand($touristIds)];
    $fbStmt->execute([
        ':bid' => $bid, ':tid' => $tid, ':gid' => $guideId,
        ':gr' => $f[1], ':cr' => $f[1], ':sr' => min(5, $f[1] + 1), ':or' => $f[1],
        ':ov' => $f[1], ':comment' => $f[0], ':days' => random_int(3, 45),
    ]);
}
echo "  Created " . count($feedbackTexts) . " feedback entries\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 1");

// ─── Schedules (for bookable destinations) ──────────────────
echo "Seeding schedules...\n";
$schStmt = $db->prepare("INSERT INTO schedules
    (event_id, guide_id, start_date, end_date, start_time, end_time, available_spots, status, created_at)
    VALUES (:eid, :gid, :start, :end, :stime, :etime, :spots, :status, NOW())");

$scheduleCount = 0;
foreach ($destIds as $di => $did) {
    if (!in_array($did, [$destIds[0], $destIds[1], $destIds[2], $destIds[5]])) continue;
    $hasGuide = ($did === $destIds[0] || $did === $destIds[1]);
    // Create 3-5 schedules per bookable destination
    $numSch = random_int(3, 5);
    for ($s = 0; $s < $numSch; $s++) {
        $sd = randomDate('2026-04-01', '2026-08-31');
        $ed = date('Y-m-d', strtotime($sd) + 86400);
        $spots = random_int(5, 20);
        $schStmt->execute([
            ':eid' => $eventIds[array_rand($eventIds)],
            ':gid' => $hasGuide ? $guideId : null,
            ':start' => $sd, ':end' => $ed,
            ':stime' => randomTime(6, 9), ':etime' => randomTime(15, 17),
            ':spots' => $spots, ':status' => 'scheduled',
        ]);
        $scheduleCount++;
    }
}
echo "  Created $scheduleCount schedules\n";

// ─── Summary ────────────────────────────────────────────────
echo "\n═══════════════════════════════════════════\n";
echo "  SEED COMPLETE — BINALBAGAN TOURISM DATA\n";
echo "═══════════════════════════════════════════\n";

$counts = [
    'Users (tourist)'  => $db->query("SELECT COUNT(*) FROM users WHERE role='tourist'")->fetchColumn(),
    'Users (guide)'    => $db->query("SELECT COUNT(*) FROM users WHERE role='guide'")->fetchColumn(),
    'Destinations'     => $db->query("SELECT COUNT(*) FROM destinations")->fetchColumn(),
    '  Featured'       => $db->query("SELECT COUNT(*) FROM destinations WHERE featured=1")->fetchColumn(),
    '  Booking Open'   => $db->query("SELECT COUNT(*) FROM destinations WHERE booking_enabled=1")->fetchColumn(),
    'Events'           => $db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'Bookings'         => $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    '  Completed'      => $db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn(),
    '  Paid'           => $db->query("SELECT COUNT(*) FROM bookings WHERE status='paid'")->fetchColumn(),
    '  Cancelled'      => $db->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn(),
    'Payments'         => $db->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
    '  Total Revenue'  => '₱' . number_format((float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM payments WHERE payment_status='paid'")->fetchColumn(), 2),
    'Reviews'          => $db->query("SELECT COUNT(*) FROM destination_reviews")->fetchColumn(),
    'Feedback'         => $db->query("SELECT COUNT(*) FROM feedback")->fetchColumn(),
    'Schedules'        => $db->query("SELECT COUNT(*) FROM schedules")->fetchColumn(),
];

foreach ($counts as $label => $val) {
    printf("  %-20s %s\n", $label, $val);
}
echo "\nAll data is scoped to Binalbagan, Negros Occidental.\n";
echo "Login: admin@binalgo.com / password123\n";
