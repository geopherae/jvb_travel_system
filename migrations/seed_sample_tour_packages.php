<?php
declare(strict_types=1);

/**
 * Migration Script: Insert 7 Sample Tour Packages
 * Run once via terminal: php migrations/seed_sample_tour_packages.php
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../actions/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die("❌ Database connection failed.\n");
}

// Sample tour package data
$samplePackages = [
    [
        'package_name' => 'Tokyo Explorer - Cherry Blossom Season',
        'package_description' => 'Experience the magic of Japan during cherry blossom season. Visit Tokyo\'s iconic landmarks, traditional temples, and modern attractions. Enjoy authentic Japanese cuisine and immerse yourself in the unique blend of tradition and innovation.',
        'price' => 85000.00,
        'day_duration' => 5,
        'night_duration' => 4,
        'duration' => '5 Days / 4 Nights',
        'origin' => 'MNL',
        'destination' => 'NRT',
        'requires_visa' => 1,
        'is_favorite' => 1,
        'inclusions_json' => json_encode([
            ['icon' => '✈️', 'title' => 'Roundtrip Flight Tickets', 'desc' => 'Economy class tickets from Manila to Tokyo (Narita) with checked baggage allowance and all airport taxes included.'],
            ['icon' => '🏨', 'title' => '4-Night Hotel Accommodation', 'desc' => 'Stay at a centrally-located 4-star hotel in Shinjuku with daily breakfast, free WiFi, and access to hotel amenities.'],
            ['icon' => '🎫', 'title' => 'All-Access Tour Pass', 'desc' => 'Entrance tickets to Tokyo Skytree, Senso-ji Temple, Meiji Shrine, and teamLab Borderless digital art museum.'],
            ['icon' => '🚄', 'title' => 'JR Train Pass (3 Days)', 'desc' => 'Unlimited travel on JR trains within Tokyo, including the Yamanote Line and Chuo Line for convenient city exploration.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Roundtrip Flights, 4N Hotel Stay, Tour Pass, JR Train Pass, English-speaking Guide'
    ],
    [
        'package_name' => 'Bali Paradise Retreat',
        'package_description' => 'Discover the enchanting island of Bali with its stunning beaches, ancient temples, and vibrant culture. Relax at luxury resorts, explore rice terraces, and witness breathtaking sunsets at Tanah Lot.',
        'price' => 52000.00,
        'day_duration' => 4,
        'night_duration' => 3,
        'duration' => '4 Days / 3 Nights',
        'origin' => 'MNL',
        'destination' => 'DPS',
        'requires_visa' => 0,
        'is_favorite' => 1,
        'inclusions_json' => json_encode([
            ['icon' => '🚗', 'title' => 'Private Airport Transfers', 'desc' => 'Air-conditioned vehicle with professional driver for pickup and drop-off at Ngurah Rai International Airport.'],
            ['icon' => '🏖️', 'title' => 'Beach Resort Accommodation', 'desc' => '3 nights at a beachfront resort in Seminyak with ocean view rooms, infinity pool, spa access, and daily breakfast.'],
            ['icon' => '🛕', 'title' => 'Cultural Temple Tour', 'desc' => 'Guided tour to Tanah Lot, Uluwatu Temple, and Tirta Empul with traditional Kecak dance performance viewing.'],
            ['icon' => '🌾', 'title' => 'Tegallalang Rice Terrace Visit', 'desc' => 'Explore the famous UNESCO-heritage rice terraces with photo opportunities and optional jungle swing experience.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Roundtrip Flights, 3N Resort Stay, Temple Tours, Airport Transfers, Breakfast Daily'
    ],
    [
        'package_name' => 'Singapore City Experience',
        'package_description' => 'Explore the futuristic city-state of Singapore. Visit Marina Bay Sands, Gardens by the Bay, and Universal Studios. Experience world-class dining, shopping, and entertainment in this modern metropolis.',
        'price' => 38000.00,
        'day_duration' => 3,
        'night_duration' => 2,
        'duration' => '3 Days / 2 Nights',
        'origin' => 'MNL',
        'destination' => 'SIN',
        'requires_visa' => 0,
        'is_favorite' => 0,
        'inclusions_json' => json_encode([
            ['icon' => '✈️', 'title' => 'Roundtrip Flight Tickets', 'desc' => 'Direct economy class flights from Manila to Singapore Changi Airport with 7kg cabin baggage and 20kg checked baggage.'],
            ['icon' => '🏙️', 'title' => 'City Center Hotel', 'desc' => '2 nights accommodation at a 4-star hotel near Orchard Road with breakfast buffet, gym access, and rooftop pool.'],
            ['icon' => '🎢', 'title' => 'Universal Studios Access', 'desc' => 'Full-day admission ticket to Universal Studios Singapore with express pass for priority access to selected rides.'],
            ['icon' => '🌳', 'title' => 'Gardens by the Bay Entry', 'desc' => 'Tickets to Cloud Forest and Flower Dome conservatories with access to the stunning Supertree Grove light show.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Roundtrip Flights, 2N Hotel, Universal Studios, Gardens by the Bay, Breakfast'
    ],
    [
        'package_name' => 'Seoul K-Culture Adventure',
        'package_description' => 'Immerse yourself in Korean culture with visits to palaces, K-pop studios, traditional markets, and trendy neighborhoods. Enjoy Korean BBQ, explore Myeongdong, and experience the vibrant nightlife of Gangnam.',
        'price' => 68000.00,
        'day_duration' => 5,
        'night_duration' => 4,
        'duration' => '5 Days / 4 Nights',
        'origin' => 'MNL',
        'destination' => 'ICN',
        'requires_visa' => 0,
        'is_favorite' => 0,
        'inclusions_json' => json_encode([
            ['icon' => '🛫', 'title' => 'Manila to Seoul Flights', 'desc' => 'Roundtrip economy class tickets to Incheon International Airport with complimentary meals and entertainment onboard.'],
            ['icon' => '🏨', 'title' => 'Myeongdong Hotel Stay', 'desc' => '4 nights at a centrally-located hotel in Myeongdong shopping district with Korean breakfast, free WiFi, and concierge service.'],
            ['icon' => '🏯', 'title' => 'Palace and Hanbok Experience', 'desc' => 'Guided tour of Gyeongbokgung Palace and Bukchon Hanok Village with traditional hanbok rental for memorable photos.'],
            ['icon' => '🎤', 'title' => 'K-Pop Studio Tour', 'desc' => 'Behind-the-scenes visit to a K-pop entertainment company, Gangnam Style statue photo-op, and COEX Starfield Library.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Roundtrip Flights, 4N Hotel, Palace Tours, Hanbok Rental, K-Pop Experience'
    ],
    [
        'package_name' => 'Bangkok & Pattaya Getaway',
        'package_description' => 'Experience the best of Thailand with a combination of Bangkok\'s bustling city life and Pattaya\'s beautiful beaches. Visit temples, floating markets, enjoy cabaret shows, and indulge in authentic Thai cuisine.',
        'price' => 42000.00,
        'day_duration' => 4,
        'night_duration' => 3,
        'duration' => '4 Days / 3 Nights',
        'origin' => 'MNL',
        'destination' => 'BKK',
        'requires_visa' => 0,
        'is_favorite' => 0,
        'inclusions_json' => json_encode([
            ['icon' => '🚖', 'title' => 'Bangkok-Pattaya Transfers', 'desc' => 'Private air-conditioned coach transfers between Suvarnabhumi Airport, Bangkok hotels, and Pattaya beach resort.'],
            ['icon' => '🛏️', 'title' => 'Split Accommodation', 'desc' => '1 night in Bangkok riverside hotel + 2 nights in Pattaya beachfront resort, both with breakfast and pool access.'],
            ['icon' => '⛩️', 'title' => 'Temple and Market Tour', 'desc' => 'Visit Grand Palace, Wat Pho (Reclining Buddha), and Damnoen Saduak floating market with English-speaking guide.'],
            ['icon' => '🎭', 'title' => 'Alcazar Cabaret Show', 'desc' => 'Premium seating at the world-famous Alcazar Cabaret show in Pattaya with spectacular performances and costumes.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Roundtrip Flights, 3N Hotels (Bangkok + Pattaya), Temple Tours, Cabaret Show'
    ],
    [
        'package_name' => 'Dubai Luxury Escape',
        'package_description' => 'Experience luxury and adventure in Dubai. Visit Burj Khalifa, go on a desert safari, shop at world-class malls, and enjoy traditional Arabian hospitality. Witness the perfect blend of modern architecture and rich heritage.',
        'price' => 125000.00,
        'day_duration' => 6,
        'night_duration' => 5,
        'duration' => '6 Days / 5 Nights',
        'origin' => 'MNL',
        'destination' => 'DXB',
        'requires_visa' => 1,
        'is_favorite' => 0,
        'inclusions_json' => json_encode([
            ['icon' => '✈️', 'title' => 'Premium Flight Tickets', 'desc' => 'Roundtrip business class flights from Manila to Dubai International Airport with lounge access and priority boarding.'],
            ['icon' => '🏨', 'title' => '5-Star Hotel Accommodation', 'desc' => '5 nights at a luxury hotel on Sheikh Zayed Road with Dubai Fountain views, spa access, and gourmet breakfast.'],
            ['icon' => '🏙️', 'title' => 'Burj Khalifa Sky Deck', 'desc' => 'Priority access tickets to Level 124 & 125 observation decks with sunset timing for spectacular city views.'],
            ['icon' => '🐪', 'title' => 'Desert Safari Adventure', 'desc' => 'Evening desert safari with dune bashing, camel riding, BBQ dinner, and traditional belly dance performance under stars.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Business Class Flights, 5N 5-Star Hotel, Burj Khalifa, Desert Safari, City Tours'
    ],
    [
        'package_name' => 'Cebu Island Hopping Adventure',
        'package_description' => 'Explore the beautiful islands of Cebu. Swim with whale sharks in Oslob, chase waterfalls, go island hopping to pristine beaches, and discover the rich marine life. Perfect for nature and adventure lovers.',
        'price' => 18000.00,
        'day_duration' => 3,
        'night_duration' => 2,
        'duration' => '3 Days / 2 Nights',
        'origin' => 'MNL',
        'destination' => 'CEB',
        'requires_visa' => 0,
        'is_favorite' => 0,
        'inclusions_json' => json_encode([
            ['icon' => '🛫', 'title' => 'Manila to Cebu Flights', 'desc' => 'Roundtrip domestic flight tickets with 7kg hand-carry and 15kg checked baggage allowance included.'],
            ['icon' => '🏖️', 'title' => 'Mactan Beach Resort', 'desc' => '2 nights accommodation at a beach resort in Mactan Island with swimming pool, beach access, and daily breakfast.'],
            ['icon' => '🦈', 'title' => 'Whale Shark Encounter', 'desc' => 'Guided tour to Oslob for once-in-a-lifetime whale shark swimming experience with snorkeling gear and underwater photos.'],
            ['icon' => '🏝️', 'title' => 'Island Hopping Tour', 'desc' => 'Full-day island hopping to Hilutungan, Nalusuan, and Pandanon Islands with snorkeling, lunch, and boat transfers.']
        ], JSON_UNESCAPED_UNICODE),
        'tour_inclusions' => 'Roundtrip Flights, 2N Beach Resort, Whale Shark Tour, Island Hopping, All Transfers'
    ]
];

echo "🚀 Starting tour packages seeding process...\n\n";

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO tour_packages (
            package_name,
            package_description,
            inclusions_json,
            tour_inclusions,
            price,
            duration,
            day_duration,
            night_duration,
            origin,
            destination,
            requires_visa,
            is_favorite,
            is_deleted
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $successCount = 0;
    $skippedCount = 0;

    foreach ($samplePackages as $package) {
        // Check if package already exists
        $checkStmt = $conn->prepare("SELECT id FROM tour_packages WHERE package_name = ?");
        $checkStmt->bind_param('s', $package['package_name']);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            echo "⚠️  Skipped: {$package['package_name']} (already exists)\n";
            $skippedCount++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Insert new tour package
        $stmt->bind_param(
            'ssssdsiiisii',
            $package['package_name'],
            $package['package_description'],
            $package['inclusions_json'],
            $package['tour_inclusions'],
            $package['price'],
            $package['duration'],
            $package['day_duration'],
            $package['night_duration'],
            $package['origin'],
            $package['destination'],
            $package['requires_visa'],
            $package['is_favorite']
        );

        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;
            $favoriteStatus = $package['is_favorite'] ? '⭐ FAVORITE' : '';
            $visaStatus = $package['requires_visa'] ? '🛂 Visa Required' : '✅ Visa-Free';
            echo "✅ Inserted: {$package['package_name']}\n";
            echo "   → ID: {$insertedId} | Price: ₱" . number_format($package['price'], 2) . " | {$package['duration']} | {$visaStatus} {$favoriteStatus}\n";
            echo "   → Route: {$package['origin']} → {$package['destination']}\n\n";
            $successCount++;
        } else {
            throw new Exception("Insert failed for {$package['package_name']}: " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo "✨ Tour packages seeding completed successfully!\n";
    echo "   - Inserted: {$successCount} packages\n";
    echo "   - Skipped: {$skippedCount} packages (duplicates)\n";
    echo "   - Favorite packages: 2 (Tokyo Explorer, Bali Paradise)\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "\n✅ Database connection closed.\n";
