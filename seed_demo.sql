-- ============================================================
-- BINALGO Tourism — Full Demo Data Seed
-- Password for all demo accounts: password123
-- ============================================================

SET @bcrypt_default = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ── 1. Users ──────────────────────────────────────────────
INSERT INTO users (username, email, password, role, name, gender, age, phone, status, created_at) VALUES
-- Staff
('staff_maria', 'maria@tourism.com', @bcrypt_default, 'staff', 'Maria Santos', 'female', 28, '+639171234567', 'active', DATE_SUB(NOW(), INTERVAL 5 MONTH)),
('staff_jose', 'jose@tourism.com', @bcrypt_default, 'staff', 'Jose Reyes', 'male', 32, '+639181234568', 'active', DATE_SUB(NOW(), INTERVAL 5 MONTH)),
-- Guides
('guide_ana', 'ana@tourism.com', @bcrypt_default, 'guide', 'Ana Dela Cruz', 'female', 26, '+639191234569', 'active', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
('guide_carlo', 'carlo@tourism.com', @bcrypt_default, 'guide', 'Carlo Mendoza', 'male', 30, '+639201234570', 'active', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
('guide_rosa', 'rosa@tourism.com', @bcrypt_default, 'guide', 'Rosa Villanueva', 'female', 35, '+639211234571', 'active', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
-- Tourists
('tourist_rafael', 'rafael@email.com', @bcrypt_default, 'tourist', 'Rafael Lim', 'male', 24, '+639221234572', 'active', DATE_SUB(NOW(), INTERVAL 5 MONTH)),
('tourist_sofia', 'sofia@email.com', @bcrypt_default, 'tourist', 'Sofia Garcia', 'female', 22, '+639231234573', 'active', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
('tourist_miguel', 'miguel@email.com', @bcrypt_default, 'tourist', 'Miguel Torres', 'male', 28, '+639241234574', 'active', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
('tourist_isabella', 'isabella@email.com', @bcrypt_default, 'tourist', 'Isabella Cruz', 'female', 27, '+639251234575', 'active', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('tourist_daniel', 'daniel@email.com', @bcrypt_default, 'tourist', 'Daniel Ramos', 'male', 31, '+639261234576', 'active', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('tourist_camila', 'camila@email.com', @bcrypt_default, 'tourist', 'Camila Fernandez', 'female', 25, '+639271234577', 'active', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
('tourist_andres', 'andres@email.com', @bcrypt_default, 'tourist', 'Andres Aquino', 'male', 29, '+639281234578', 'active', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
('tourist_lucas', 'lucas@email.com', @bcrypt_default, 'tourist', 'Lucas Pascual', 'male', 23, '+639291234579', 'active', DATE_SUB(NOW(), INTERVAL 1 MONTH)),
('tourist_maria_clara', 'mariaclara@email.com', @bcrypt_default, 'tourist', 'Maria Clara Bautista', 'female', 26, '+639301234580', 'active', DATE_SUB(NOW(), INTERVAL 1 MONTH)),
('tourist_pedro', 'pedro@email.com', @bcrypt_default, 'tourist', 'Pedro De Leon', 'male', 34, '+639311234581', 'pending', DATE_SUB(NOW(), INTERVAL 1 WEEK));

-- ── 2. Tourist Profiles ───────────────────────────────────
INSERT INTO tourist_profiles (user_id, emergency_contact, emergency_contact_number, disability) VALUES
(7, 'Roberto Lim', '+639171111111', 'none'),
(8, 'Teresa Garcia', '+639172222222', 'none'),
(9, 'Patricia Torres', '+639173333333', 'none'),
(10, 'Enrique Cruz', '+639174444444', 'none'),
(11, 'Lourdes Ramos', '+639175555555', 'none'),
(12, 'Francisco Fernandez', '+639176666666', 'none'),
(13, 'Carmen Aquino', '+639177777777', 'none'),
(14, 'Ricardo Pascual', '+639178888888', 'none'),
(15, 'Gloria Bautista', '+639179999999', 'none'),
(16, 'Mariano De Leon', '+639180000000', 'none');

-- ── 3. Guide Profiles ─────────────────────────────────────
INSERT INTO guide_profiles (user_id, years_of_experience, languages, specializations, availability_status, bio) VALUES
(4, 5, 'English,Tagalog,Hiligaynon', 'Nature,Hiking,Waterfalls', 'available', 'Passionate nature guide with 5 years of experience in eco-tourism.'),
(5, 8, 'English,Tagalog,Hiligaynon,Ilonggo', 'Heritage,Culture,History', 'available', 'Heritage tour specialist focusing on Binalbagan history and culture.'),
(6, 3, 'English,Tagalog', 'Adventure,Kayaking,Beach', 'available', 'Adventure guide specializing in outdoor activities and water sports.');

-- ── 4. ID Verifications ───────────────────────────────────
INSERT INTO id_verifications (user_id, id_type, id_file_path, status, created_at) VALUES
(7, 'national_id', 'ids/rafael_id.jpg', 'approved', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(8, 'passport', 'ids/sofia_passport.jpg', 'approved', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(9, 'drivers_license', 'ids/miguel_dl.jpg', 'approved', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(10, 'national_id', 'ids/isabella_id.jpg', 'pending', DATE_SUB(NOW(), INTERVAL 1 MONTH)),
(11, 'voters_id', 'ids/daniel_vid.jpg', 'approved', DATE_SUB(NOW(), INTERVAL 2 MONTH));

-- ── 5. Destinations ───────────────────────────────────────
INSERT INTO destinations (name, description, location, difficulty, capacity_limit, entrance_fee, category, featured, status, created_by, created_at) VALUES
('Binalbagan Waterfall', 'A stunning 3-tier waterfall hidden in the lush tropical forest of Binalbagan. Perfect for nature lovers and adventure seekers.', 'Barangay Taywan, Binalbagan', 'moderate', 40, 150.00, 'nature_adventure', 1, 'active', 2, DATE_SUB(NOW(), INTERVAL 5 MONTH)),
('San Jose Church Heritage', 'A centuries-old Spanish colonial church showcasing beautiful architecture and rich religious history dating back to the 1800s.', 'Poblacion, Binalbagan', 'easy', 60, 0.00, 'heritage_culture', 1, 'active', 2, DATE_SUB(NOW(), INTERVAL 5 MONTH)),
('Binalbagan Mangrove Park', 'A thriving mangrove ecosystem perfect for bird watching, kayaking, and eco-tours. Home to diverse marine life.', 'Barangay Calampis, Binalbagan', 'easy', 30, 200.00, 'nature_adventure', 1, 'active', 2, DATE_SUB(NOW(), INTERVAL 4 MONTH)),
('Sta. Rosa Beach Resort', 'A pristine white sand beach with crystal-clear waters ideal for swimming, snorkeling, and beach volleyball.', 'Barangay Sta. Rosa, Binalbagan', 'easy', 80, 100.00, 'beach_island', 1, 'active', 2, DATE_SUB(NOW(), INTERVAL 4 MONTH)),
('Mt. Kanlaon Trail', 'A challenging mountain trail offering breathtaking panoramic views of Negros Occidental. For experienced hikers only.', 'Barangay Buncalan, Binalbagan', 'difficult', 20, 250.00, 'nature_adventure', 1, 'active', 2, DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('Binalbagan Eco Park', 'A family-friendly eco park with butterfly gardens, fish ponds, and nature trails. Great for educational tours.', 'Poblacion, Binalbagan', 'easy', 100, 75.00, 'nature_adventure', 0, 'active', 2, DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('Binalbagan Rice Terraces', 'Scenic terraced rice fields that showcase traditional Filipino farming methods with stunning mountain backdrop.', 'Barangay Bi-ao, Binalbagan', 'moderate', 25, 50.00, 'heritage_culture', 0, 'active', 2, DATE_SUB(NOW(), INTERVAL 2 MONTH)),
('Hinayuan Hot Springs', 'Natural hot springs nestled in the mountains, perfect for relaxation and wellness tourism.', 'Barangay Hinayuan, Binalbagan', 'easy', 35, 180.00, 'wellness', 0, 'active', 2, DATE_SUB(NOW(), INTERVAL 2 MONTH));

-- ── 6. Destination Seasons ────────────────────────────────
INSERT INTO destination_seasons (destination_id, season_type, months, description) VALUES
(1, 'peak', 'Jun,Jul,Aug,Sep,Oct', 'Best waterfall flow during rainy season'),
(1, 'off_peak', 'Dec,Jan,Feb,Mar', 'Lower water levels but less crowded'),
(3, 'peak', 'Nov,Dec,Jan,Feb', 'Migratory bird season'),
(4, 'peak', 'Mar,Apr,May', 'Summer beach season'),
(5, 'peak', 'Nov,Dec,Jan', 'Cool weather for hiking');

-- ── 7. Destination Guides ─────────────────────────────────
INSERT INTO destination_guides (destination_id, guide_id, is_primary, status) VALUES
(1, 4, 1, 'active'),
(2, 5, 1, 'active'),
(3, 6, 1, 'active'),
(4, 4, 0, 'active'),
(5, 6, 1, 'active'),
(6, 5, 0, 'active'),
(7, 5, 1, 'active'),
(8, 4, 0, 'active');

-- ── 8. Events ─────────────────────────────────────────────
INSERT INTO events (title, description, category, destination_id, max_participants, price, duration_hours, status, requires_guide, created_by, event_start_date, event_end_date, event_start_time, event_end_time, created_at) VALUES
('Waterfall Adventure Trek', 'An exciting guided trek through the tropical forest to the stunning Binalbagan Waterfall. Includes lunch and equipment.', 'tourism_event', 1, 30, 500.00, 6.00, 'active', 1, 2, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), '06:00', '12:00', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('Heritage Walking Tour', 'A guided walk through Binalbagan''s historic sites including San Jose Church, ancestral houses, and the old town plaza.', 'tourism_event', 2, 40, 250.00, 3.00, 'active', 1, 2, DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), '08:00', '11:00', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('Mangrove Kayaking Experience', 'Paddle through the serene mangrove waterways while learning about coastal conservation and local marine life.', 'tourism_event', 3, 20, 650.00, 4.00, 'active', 1, 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), '07:00', '11:00', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
('Beach Sunset Festival', 'Enjoy a magical sunset beach party with live music, local food, and fire dancing performances.', 'festival', 4, 100, 300.00, 5.00, 'active', 0, 2, DATE_ADD(CURDATE(), INTERVAL 21 DAY), DATE_ADD(CURDATE(), INTERVAL 21 DAY), '15:00', '20:00', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
('Kanlaon Summit Challenge', 'A challenging full-day hike to the summit of Mt. Kanlaon with experienced guides. Includes meals and camping gear.', 'adventure', 5, 15, 1200.00, 10.00, 'active', 1, 2, DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 30 DAY), '04:00', '14:00', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
('Eco Park Educational Tour', 'A fun educational tour for students and families exploring biodiversity and sustainable farming practices.', 'educational', 6, 50, 150.00, 3.00, 'active', 1, 2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00', '12:00', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
('Balbagan Festival 2026', 'Annual cultural festival celebrating Binalbagan''s rich heritage with street dancing, food fair, and cultural shows.', 'festival', 2, 200, 0.00, 8.00, 'active', 0, 2, DATE_ADD(CURDATE(), INTERVAL 45 DAY), DATE_ADD(CURDATE(), INTERVAL 47 DAY), '08:00', '16:00', DATE_SUB(NOW(), INTERVAL 1 MONTH)),
('Hot Springs Wellness Retreat', 'A half-day wellness retreat at Hinayuan Hot Springs with guided meditation, natural spa treatments, and healthy lunch.', 'wellness', 8, 25, 800.00, 5.00, 'active', 1, 2, DATE_ADD(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), '09:00', '14:00', DATE_SUB(NOW(), INTERVAL 1 MONTH));

-- ── 9. Schedules ──────────────────────────────────────────
INSERT INTO schedules (event_id, guide_id, start_date, end_date, start_time, end_time, available_spots, status) VALUES
-- Event 1: Waterfall Trek
(1, 4, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), '06:00', '12:00', 18, 'scheduled'),
(1, 4, DATE_ADD(CURDATE(), INTERVAL -20 DAY), DATE_ADD(CURDATE(), INTERVAL -20 DAY), '06:00', '12:00', 0, 'completed'),
(1, 4, DATE_ADD(CURDATE(), INTERVAL -50 DAY), DATE_ADD(CURDATE(), INTERVAL -50 DAY), '06:00', '12:00', 0, 'completed'),
-- Event 2: Heritage Tour
(2, 5, DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), '08:00', '11:00', 30, 'scheduled'),
(2, 5, DATE_ADD(CURDATE(), INTERVAL -15 DAY), DATE_ADD(CURDATE(), INTERVAL -15 DAY), '08:00', '11:00', 0, 'completed'),
(2, 5, DATE_ADD(CURDATE(), INTERVAL -45 DAY), DATE_ADD(CURDATE(), INTERVAL -45 DAY), '08:00', '11:00', 0, 'completed'),
-- Event 3: Mangrove Kayaking
(3, 6, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), '07:00', '11:00', 12, 'scheduled'),
(3, 6, DATE_ADD(CURDATE(), INTERVAL -10 DAY), DATE_ADD(CURDATE(), INTERVAL -10 DAY), '07:00', '11:00', 0, 'completed'),
(3, 6, DATE_ADD(CURDATE(), INTERVAL -35 DAY), DATE_ADD(CURDATE(), INTERVAL -35 DAY), '07:00', '11:00', 0, 'completed'),
-- Event 4: Beach Sunset
(4, NULL, DATE_ADD(CURDATE(), INTERVAL 21 DAY), DATE_ADD(CURDATE(), INTERVAL 21 DAY), '15:00', '20:00', 85, 'scheduled'),
(4, NULL, DATE_ADD(CURDATE(), INTERVAL -25 DAY), DATE_ADD(CURDATE(), INTERVAL -25 DAY), '15:00', '20:00', 0, 'completed'),
-- Event 5: Kanlaon Summit
(5, 6, DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 30 DAY), '04:00', '14:00', 10, 'scheduled'),
(5, 6, DATE_ADD(CURDATE(), INTERVAL -40 DAY), DATE_ADD(CURDATE(), INTERVAL -40 DAY), '04:00', '14:00', 0, 'completed'),
-- Event 6: Eco Park Tour
(6, 5, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00', '12:00', 38, 'scheduled'),
(6, 5, DATE_ADD(CURDATE(), INTERVAL -5 DAY), DATE_ADD(CURDATE(), INTERVAL -5 DAY), '09:00', '12:00', 0, 'completed'),
-- Event 7: Balbagan Festival
(7, NULL, DATE_ADD(CURDATE(), INTERVAL 45 DAY), DATE_ADD(CURDATE(), INTERVAL 47 DAY), '08:00', '16:00', 180, 'scheduled'),
-- Event 8: Hot Springs
(8, 4, DATE_ADD(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), '09:00', '14:00', 20, 'scheduled'),
(8, 4, DATE_ADD(CURDATE(), INTERVAL -30 DAY), DATE_ADD(CURDATE(), INTERVAL -30 DAY), '09:00', '14:00', 0, 'completed');

-- ── 10. Bookings (spread over last 6 months) ──────────────
INSERT INTO bookings (tourist_id, schedule_id, num_participants, total_price, status, payment_status, visit_date, created_at) VALUES
-- Month 6 ago (old bookings)
(7, 2, 2, 1000.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -120 DAY), DATE_ADD(NOW(), INTERVAL -125 DAY)),
(8, 3, 3, 1500.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -115 DAY), DATE_ADD(NOW(), INTERVAL -120 DAY)),
(9, 5, 1, 250.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -108 DAY), DATE_ADD(NOW(), INTERVAL -112 DAY)),
(10, 6, 2, 500.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -100 DAY), DATE_ADD(NOW(), INTERVAL -105 DAY)),
(11, 9, 4, 2600.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -95 DAY), DATE_ADD(NOW(), INTERVAL -100 DAY)),
(12, 4, 1, 250.00, 'cancelled', 'refunded', DATE_ADD(NOW(), INTERVAL -90 DAY), DATE_ADD(NOW(), INTERVAL -95 DAY)),
-- Month 5 ago
(7, 8, 2, 1300.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -85 DAY), DATE_ADD(NOW(), INTERVAL -90 DAY)),
(8, 10, 3, 1950.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -80 DAY), DATE_ADD(NOW(), INTERVAL -85 DAY)),
(9, 11, 2, 1000.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -75 DAY), DATE_ADD(NOW(), INTERVAL -80 DAY)),
(13, 12, 1, 500.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -70 DAY), DATE_ADD(NOW(), INTERVAL -75 DAY)),
-- Month 4 ago
(10, 13, 2, 600.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -65 DAY), DATE_ADD(NOW(), INTERVAL -70 DAY)),
(11, 14, 3, 1200.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -60 DAY), DATE_ADD(NOW(), INTERVAL -65 DAY)),
(12, 15, 1, 250.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -55 DAY), DATE_ADD(NOW(), INTERVAL -60 DAY)),
(14, 16, 2, 150.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -50 DAY), DATE_ADD(NOW(), INTERVAL -55 DAY)),
(7, 17, 4, 4800.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -48 DAY), DATE_ADD(NOW(), INTERVAL -52 DAY)),
-- Month 3 ago
(8, 18, 2, 1600.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -42 DAY), DATE_ADD(NOW(), INTERVAL -47 DAY)),
(9, 2, 1, 500.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -38 DAY), DATE_ADD(NOW(), INTERVAL -42 DAY)),
(13, 7, 3, 750.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -35 DAY), DATE_ADD(NOW(), INTERVAL -40 DAY)),
(10, 3, 2, 500.00, 'cancelled', 'refunded', DATE_ADD(NOW(), INTERVAL -33 DAY), DATE_ADD(NOW(), INTERVAL -38 DAY)),
(15, 17, 1, 800.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -30 DAY), DATE_ADD(NOW(), INTERVAL -35 DAY)),
-- Month 2 ago
(11, 2, 2, 1000.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -25 DAY), DATE_ADD(NOW(), INTERVAL -30 DAY)),
(12, 9, 4, 1000.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -22 DAY), DATE_ADD(NOW(), INTERVAL -27 DAY)),
(14, 11, 2, 600.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -20 DAY), DATE_ADD(NOW(), INTERVAL -25 DAY)),
(7, 5, 1, 250.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -18 DAY), DATE_ADD(NOW(), INTERVAL -22 DAY)),
(16, 15, 2, 500.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -15 DAY), DATE_ADD(NOW(), INTERVAL -20 DAY)),
-- Month 1 ago (recent)
(8, 8, 3, 1500.00, 'completed', 'paid', DATE_ADD(NOW(), INTERVAL -12 DAY), DATE_ADD(NOW(), INTERVAL -16 DAY)),
(9, 10, 2, 1300.00, 'confirmed', 'paid', DATE_ADD(NOW(), INTERVAL -8 DAY), DATE_ADD(NOW(), INTERVAL -12 DAY)),
(13, 12, 1, 500.00, 'confirmed', 'paid', DATE_ADD(NOW(), INTERVAL -5 DAY), DATE_ADD(NOW(), INTERVAL -8 DAY)),
(10, 14, 2, 1600.00, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL -3 DAY), DATE_ADD(NOW(), INTERVAL -5 DAY)),
(15, 18, 1, 800.00, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -3 DAY)),
-- This month (current)
(7, 1, 2, 1000.00, 'confirmed', 'paid', DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY)),
(8, 7, 1, 650.00, 'confirmed', 'paid', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY)),
(11, 14, 3, 450.00, 'pending', 'pending', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NOW()),
(14, 16, 2, 1600.00, 'pending', 'pending', DATE_ADD(CURDATE(), INTERVAL 12 DAY), NOW()),
(9, 11, 1, 300.00, 'confirmed', 'paid', DATE_ADD(CURDATE(), INTERVAL 21 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY));

UPDATE bookings SET booking_reference = CONCAT('BK-', LPAD(id, 4, '0')) WHERE booking_reference IS NULL;

-- ── 11. Payments ──────────────────────────────────────────
INSERT INTO payments (booking_id, tourist_id, amount, tax, service_fee, total_amount, payment_method, card_last_four, card_brand, transaction_id, reference_number, payment_status, payment_date, created_at) VALUES
-- Old bookings (month 6)
(1, 7, 950.00, 50.00, 0.00, 1000.00, 'gcash', NULL, NULL, 'GC-20260201-001', 'REF-20260201-001', 'paid', DATE_ADD(NOW(), INTERVAL -124 DAY), DATE_ADD(NOW(), INTERVAL -125 DAY)),
(2, 8, 1425.00, 75.00, 0.00, 1500.00, 'maya', NULL, NULL, 'MY-20260203-001', 'REF-20260203-001', 'paid', DATE_ADD(NOW(), INTERVAL -119 DAY), DATE_ADD(NOW(), INTERVAL -120 DAY)),
(3, 9, 237.50, 12.50, 0.00, 250.00, 'card', '4242', 'Visa', 'CC-20260205-001', 'REF-20260205-001', 'paid', DATE_ADD(NOW(), INTERVAL -111 DAY), DATE_ADD(NOW(), INTERVAL -112 DAY)),
(4, 10, 475.00, 25.00, 0.00, 500.00, 'gcash', NULL, NULL, 'GC-20260210-001', 'REF-20260210-001', 'paid', DATE_ADD(NOW(), INTERVAL -104 DAY), DATE_ADD(NOW(), INTERVAL -105 DAY)),
(5, 11, 2470.00, 130.00, 0.00, 2600.00, 'card', '8888', 'Mastercard', 'CC-20260215-001', 'REF-20260215-001', 'paid', DATE_ADD(NOW(), INTERVAL -99 DAY), DATE_ADD(NOW(), INTERVAL -100 DAY)),
(6, 12, 250.00, 0.00, 0.00, 250.00, 'gcash', NULL, NULL, 'GC-20260218-001', 'REF-20260218-001', 'refunded', NULL, DATE_ADD(NOW(), INTERVAL -95 DAY)),
-- Month 5
(7, 7, 1235.00, 65.00, 0.00, 1300.00, 'maya', NULL, NULL, 'MY-20260220-001', 'REF-20260220-001', 'paid', DATE_ADD(NOW(), INTERVAL -84 DAY), DATE_ADD(NOW(), INTERVAL -85 DAY)),
(8, 8, 1852.50, 97.50, 0.00, 1950.00, 'card', '4242', 'Visa', 'CC-20260225-001', 'REF-20260225-001', 'paid', DATE_ADD(NOW(), INTERVAL -79 DAY), DATE_ADD(NOW(), INTERVAL -80 DAY)),
(9, 9, 950.00, 50.00, 0.00, 1000.00, 'gcash', NULL, NULL, 'GC-20260228-001', 'REF-20260228-001', 'paid', DATE_ADD(NOW(), INTERVAL -74 DAY), DATE_ADD(NOW(), INTERVAL -75 DAY)),
(10, 13, 475.00, 25.00, 0.00, 500.00, 'cash', NULL, NULL, NULL, 'REF-20260301-001', 'paid', DATE_ADD(NOW(), INTERVAL -69 DAY), DATE_ADD(NOW(), INTERVAL -70 DAY)),
-- Month 4
(11, 10, 570.00, 30.00, 0.00, 600.00, 'maya', NULL, NULL, 'MY-20260305-001', 'REF-20260305-001', 'paid', DATE_ADD(NOW(), INTERVAL -64 DAY), DATE_ADD(NOW(), INTERVAL -65 DAY)),
(12, 11, 1140.00, 60.00, 0.00, 1200.00, 'card', '1234', 'Visa', 'CC-20260310-001', 'REF-20260310-001', 'paid', DATE_ADD(NOW(), INTERVAL -59 DAY), DATE_ADD(NOW(), INTERVAL -60 DAY)),
(13, 12, 237.50, 12.50, 0.00, 250.00, 'gcash', NULL, NULL, 'GC-20260315-001', 'REF-20260315-001', 'paid', DATE_ADD(NOW(), INTERVAL -54 DAY), DATE_ADD(NOW(), INTERVAL -55 DAY)),
(14, 14, 142.50, 7.50, 0.00, 150.00, 'cash', NULL, NULL, NULL, 'REF-20260318-001', 'paid', DATE_ADD(NOW(), INTERVAL -49 DAY), DATE_ADD(NOW(), INTERVAL -50 DAY)),
(15, 7, 4560.00, 240.00, 0.00, 4800.00, 'card', '4242', 'Visa', 'CC-20260320-001', 'REF-20260320-001', 'paid', DATE_ADD(NOW(), INTERVAL -47 DAY), DATE_ADD(NOW(), INTERVAL -48 DAY)),
-- Month 3
(16, 8, 1520.00, 80.00, 0.00, 1600.00, 'maya', NULL, NULL, 'MY-20260325-001', 'REF-20260325-001', 'paid', DATE_ADD(NOW(), INTERVAL -41 DAY), DATE_ADD(NOW(), INTERVAL -42 DAY)),
(17, 9, 475.00, 25.00, 0.00, 500.00, 'gcash', NULL, NULL, 'GC-20260328-001', 'REF-20260328-001', 'paid', DATE_ADD(NOW(), INTERVAL -37 DAY), DATE_ADD(NOW(), INTERVAL -38 DAY)),
(18, 13, 712.50, 37.50, 0.00, 750.00, 'card', '5678', 'Mastercard', 'CC-20260330-001', 'REF-20260330-001', 'paid', DATE_ADD(NOW(), INTERVAL -34 DAY), DATE_ADD(NOW(), INTERVAL -35 DAY)),
(19, 10, 500.00, 0.00, 0.00, 500.00, 'gcash', NULL, NULL, 'GC-20260401-001', 'REF-20260401-001', 'refunded', NULL, DATE_ADD(NOW(), INTERVAL -38 DAY)),
(20, 15, 760.00, 40.00, 0.00, 800.00, 'maya', NULL, NULL, 'MY-20260403-001', 'REF-20260403-001', 'paid', DATE_ADD(NOW(), INTERVAL -29 DAY), DATE_ADD(NOW(), INTERVAL -30 DAY)),
-- Month 2
(21, 11, 950.00, 50.00, 0.00, 1000.00, 'card', '9999', 'Visa', 'CC-20260408-001', 'REF-20260408-001', 'paid', DATE_ADD(NOW(), INTERVAL -24 DAY), DATE_ADD(NOW(), INTERVAL -25 DAY)),
(22, 12, 950.00, 50.00, 0.00, 1000.00, 'gcash', NULL, NULL, 'GC-20260410-001', 'REF-20260410-001', 'paid', DATE_ADD(NOW(), INTERVAL -21 DAY), DATE_ADD(NOW(), INTERVAL -22 DAY)),
(23, 14, 570.00, 30.00, 0.00, 600.00, 'cash', NULL, NULL, NULL, 'REF-20260412-001', 'paid', DATE_ADD(NOW(), INTERVAL -19 DAY), DATE_ADD(NOW(), INTERVAL -20 DAY)),
(24, 7, 237.50, 12.50, 0.00, 250.00, 'maya', NULL, NULL, 'MY-20260414-001', 'REF-20260414-001', 'paid', DATE_ADD(NOW(), INTERVAL -17 DAY), DATE_ADD(NOW(), INTERVAL -18 DAY)),
(25, 16, 475.00, 25.00, 0.00, 500.00, 'card', '4242', 'Visa', 'CC-20260416-001', 'REF-20260416-001', 'paid', DATE_ADD(NOW(), INTERVAL -14 DAY), DATE_ADD(NOW(), INTERVAL -15 DAY)),
-- Month 1 (recent)
(26, 8, 1425.00, 75.00, 0.00, 1500.00, 'gcash', NULL, NULL, 'GC-20260420-001', 'REF-20260420-001', 'paid', DATE_ADD(NOW(), INTERVAL -11 DAY), DATE_ADD(NOW(), INTERVAL -12 DAY)),
(27, 9, 1235.00, 65.00, 0.00, 1300.00, 'maya', NULL, NULL, 'MY-20260423-001', 'REF-20260423-001', 'paid', DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL -8 DAY)),
(28, 13, 475.00, 25.00, 0.00, 500.00, 'card', '1234', 'Visa', 'CC-20260425-001', 'REF-20260425-001', 'paid', DATE_ADD(NOW(), INTERVAL -4 DAY), DATE_ADD(NOW(), INTERVAL -5 DAY)),
(29, 10, 1520.00, 80.00, 0.00, 1600.00, 'gcash', NULL, NULL, NULL, 'REF-20260427-001', 'pending', NULL, DATE_ADD(NOW(), INTERVAL -3 DAY)),
(30, 15, 760.00, 40.00, 0.00, 800.00, 'maya', NULL, NULL, NULL, 'REF-20260428-001', 'pending', NULL, DATE_ADD(NOW(), INTERVAL -2 DAY)),
-- This month
(31, 7, 950.00, 50.00, 0.00, 1000.00, 'card', '4242', 'Visa', 'CC-20260430-001', 'REF-20260430-001', 'paid', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW()),
(32, 8, 617.50, 32.50, 0.00, 650.00, 'gcash', NULL, NULL, 'GC-20260501-001', 'REF-20260501-001', 'paid', NOW(), NOW()),
(33, 11, 450.00, 0.00, 0.00, 450.00, 'cash', NULL, NULL, NULL, 'REF-20260502-001', 'pending', NULL, NOW()),
(34, 14, 1520.00, 80.00, 0.00, 1600.00, 'maya', NULL, NULL, NULL, 'REF-20260502-002', 'pending', NULL, NOW()),
(35, 9, 285.00, 15.00, 0.00, 300.00, 'card', '5678', 'Mastercard', 'CC-20260501-002', 'REF-20260501-002', 'paid', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY));

-- ── 12. Guide Payouts ─────────────────────────────────────
INSERT INTO guide_payouts (guide_id, booking_id, payment_id, tour_amount, commission_rate, commission_amount, net_earning, payout_status, created_at) VALUES
(4, 1, 1, 1000.00, 15.00, 150.00, 850.00, 'paid', DATE_ADD(NOW(), INTERVAL -120 DAY)),
(4, 7, 7, 1300.00, 15.00, 195.00, 1105.00, 'paid', DATE_ADD(NOW(), INTERVAL -80 DAY)),
(5, 3, 3, 250.00, 15.00, 37.50, 212.50, 'paid', DATE_ADD(NOW(), INTERVAL -108 DAY)),
(5, 9, 9, 1000.00, 15.00, 150.00, 850.00, 'paid', DATE_ADD(NOW(), INTERVAL -70 DAY)),
(6, 5, 5, 2600.00, 15.00, 390.00, 2210.00, 'paid', DATE_ADD(NOW(), INTERVAL -95 DAY)),
(6, 11, 11, 600.00, 15.00, 90.00, 510.00, 'paid', DATE_ADD(NOW(), INTERVAL -60 DAY)),
(4, 16, 16, 1600.00, 15.00, 240.00, 1360.00, 'approved', DATE_ADD(NOW(), INTERVAL -37 DAY)),
(5, 18, 18, 750.00, 15.00, 112.50, 637.50, 'approved', DATE_ADD(NOW(), INTERVAL -30 DAY)),
(4, 26, 26, 1500.00, 15.00, 225.00, 1275.00, 'pending', DATE_ADD(NOW(), INTERVAL -10 DAY)),
(6, 27, 27, 1300.00, 15.00, 195.00, 1105.00, 'pending', DATE_ADD(NOW(), INTERVAL -5 DAY));

-- ── 13. Feedback ──────────────────────────────────────────
INSERT INTO feedback (booking_id, tourist_id, guide_id, schedule_id, guide_rating, communication_rating, safety_rating, organization_rating, overall_rating, comment, created_at) VALUES
(1, 7, 4, 2, 5, 5, 5, 4, 5, 'Amazing waterfall trek! Ana was an excellent guide.', DATE_ADD(NOW(), INTERVAL -118 DAY)),
(2, 8, 5, 3, 4, 5, 4, 5, 4, 'Great heritage tour, learned so much about Binalbagan history.', DATE_ADD(NOW(), INTERVAL -113 DAY)),
(3, 9, 5, 5, 5, 4, 5, 5, 5, 'Very informative and well-organized walking tour.', DATE_ADD(NOW(), INTERVAL -106 DAY)),
(5, 11, 6, 9, 5, 5, 5, 5, 5, 'Incredible kayaking experience! Carlo made it so fun and safe.', DATE_ADD(NOW(), INTERVAL -93 DAY)),
(7, 7, 4, 8, 4, 5, 4, 4, 4, 'Beautiful waterfall, good guide. Trail was a bit muddy.', DATE_ADD(NOW(), INTERVAL -83 DAY)),
(8, 8, 6, 10, 5, 5, 5, 5, 5, 'Best mangrove tour ever! Highly recommended.', DATE_ADD(NOW(), INTERVAL -78 DAY)),
(11, 10, 5, 13, 4, 4, 5, 4, 4, 'Nice cultural tour, could be a bit more interactive.', DATE_ADD(NOW(), INTERVAL -63 DAY)),
(15, 7, 6, 17, 5, 5, 5, 5, 5, 'Kanlaon summit was breathtaking! Tough but worth it.', DATE_ADD(NOW(), INTERVAL -46 DAY)),
(21, 11, 4, 2, 5, 5, 4, 5, 5, 'Second time doing the waterfall trek, still amazing!', DATE_ADD(NOW(), INTERVAL -23 DAY)),
(26, 8, 5, 8, 4, 5, 4, 4, 4, 'Good heritage tour as always.', DATE_ADD(NOW(), INTERVAL -10 DAY));

-- ── 14. Destination Reviews ───────────────────────────────
INSERT INTO destination_reviews (destination_id, user_id, rating, review, created_at) VALUES
(1, 7, 5, 'Absolutely stunning waterfall! The trek was challenging but the reward was worth every step. Our guide Ana was knowledgeable and friendly.', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(1, 8, 4, 'Beautiful place! The trail could use some maintenance but the waterfall itself is breathtaking.', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(1, 11, 5, 'One of the best waterfalls I have ever visited in the Philippines. Highly recommended!', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(2, 9, 5, 'The heritage church is a masterpiece. Love the Spanish colonial architecture.', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(2, 14, 4, 'Great historical site. Would love to see more information plaques around the area.', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(3, 8, 5, 'The mangrove park is a hidden gem. Loved the kayaking experience!', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(3, 12, 4, 'Very peaceful and educational. Great for families.', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(4, 10, 5, 'Pristine beach with crystal-clear water. Perfect for a weekend getaway.', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(4, 13, 4, 'Nice beach resort, a bit crowded during peak season but still enjoyable.', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(5, 11, 5, 'Challenging but absolutely rewarding. The view from the summit is unforgettable.', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(6, 14, 4, 'Great for kids and families. The butterfly garden was a hit!', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(8, 15, 5, 'The hot springs were so relaxing. Perfect for stress relief.', DATE_SUB(NOW(), INTERVAL 1 MONTH));

-- ── 15. Notifications ─────────────────────────────────────
INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES
(7, 'Booking Confirmed', 'Your Waterfall Adventure Trek booking has been confirmed!', 'booking', 1, '/tourist/bookings.php', DATE_ADD(NOW(), INTERVAL 2 DAY)),
(8, 'Booking Confirmed', 'Your Mangrove Kayaking booking is confirmed for next week.', 'booking', 0, '/tourist/bookings.php', DATE_ADD(NOW(), INTERVAL 1 DAY)),
(1, 'New Registration', 'A new tourist account (Pedro De Leon) is pending verification.', 'registration', 0, '/admin/users.php', DATE_ADD(NOW(), INTERVAL 1 WEEK)),
(2, 'New Booking', '4 new bookings were created this week.', 'booking', 1, '/admin/bookings.php', DATE_ADD(NOW(), INTERVAL 3 DAY)),
(4, 'Tour Assignment', CONCAT('You have been assigned to Waterfall Adventure Trek on ', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 10 DAY), '%M %d'), '.'), 'assignment', 0, '/guide/tours.php', DATE_ADD(NOW(), INTERVAL 2 DAY)),
(5, 'Tour Assignment', 'You have been assigned to Heritage Walking Tour.', 'assignment', 0, '/guide/tours.php', DATE_ADD(NOW(), INTERVAL 3 DAY)),
(11, 'Payment Received', 'Your payment of PHP 450.00 for Eco Park Educational Tour is pending.', 'payment_success', 0, '/tourist/bookings.php', NOW()),
(9, 'Feedback Reminder', 'How was your recent Kanlaon Summit tour? Leave a review!', 'feedback', 0, '/tourist/bookings.php', DATE_ADD(NOW(), INTERVAL 6 DAY)),
(1, 'Monthly Report', 'April 2026 report is ready. Revenue: PHP 10,350.00', 'system', 1, '/admin/reports.php', DATE_ADD(NOW(), INTERVAL 1 DAY));

-- ── 16. Activity Logs ─────────────────────────────────────
INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES
(1, 'login', 'Admin logged in', '192.168.1.1', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'user_management', 'Reviewed pending user accounts', '192.168.1.1', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'login', 'Staff logged in', '192.168.1.2', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'destination_created', 'Created destination: Hinayuan Hot Springs', '192.168.1.2', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(2, 'event_created', 'Created event: Hot Springs Wellness Retreat', '192.168.1.2', DATE_SUB(NOW(), INTERVAL 1 MONTH)),
(7, 'login', 'Tourist logged in', '192.168.1.10', NOW()),
(8, 'login', 'Tourist logged in', '192.168.1.11', DATE_ADD(NOW(), INTERVAL 1 DAY)),
(7, 'booking_created', 'Booked Waterfall Adventure Trek', '192.168.1.10', DATE_ADD(NOW(), INTERVAL 2 DAY)),
(11, 'booking_created', 'Booked Eco Park Educational Tour', '192.168.1.14', NOW()),
(9, 'booking_created', 'Booked Beach Sunset Festival', '192.168.1.12', DATE_ADD(NOW(), INTERVAL 1 DAY)),
(4, 'login', 'Guide logged in', '192.168.1.20', DATE_ADD(NOW(), INTERVAL 2 DAY)),
(5, 'login', 'Guide logged in', '192.168.1.21', DATE_ADD(NOW(), INTERVAL 3 DAY));

-- ── 17. User Message Settings ─────────────────────────────
INSERT INTO user_message_settings (user_id, show_read_receipts, show_online_status, message_notifications, sound_notifications, who_can_message) VALUES
(7, 1, 1, 1, 1, 'everyone'),
(8, 1, 1, 1, 1, 'everyone'),
(9, 1, 1, 1, 1, 'everyone'),
(10, 1, 1, 1, 1, 'everyone'),
(11, 1, 1, 1, 1, 'everyone'),
(12, 1, 1, 1, 1, 'everyone'),
(13, 1, 1, 1, 1, 'everyone'),
(14, 1, 1, 1, 1, 'everyone'),
(15, 1, 1, 1, 1, 'everyone'),
(16, 1, 1, 1, 1, 'everyone');
