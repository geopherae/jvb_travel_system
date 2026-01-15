<?php
declare(strict_types=1);

/**
 * Migration Script: Insert Tour Package Itineraries
 * Run once via terminal: php migrations/seed_tour_package_itineraries.php
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../actions/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die("❌ Database connection failed.\n");
}

// Sample itineraries for each tour package
$packageItineraries = [
    // Package ID 2: Tokyo Explorer - Cherry Blossom Season (5D/4N)
    [
        'package_id' => 2,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival in Tokyo & Shinjuku Exploration',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Departure from Manila (Ninoy Aquino International Airport)'],
                    ['time' => '13:30', 'title' => 'Arrival at Narita International Airport'],
                    ['time' => '15:00', 'title' => 'Hotel check-in at Shinjuku district'],
                    ['time' => '17:00', 'title' => 'Evening stroll through Shinjuku Gyoen National Garden (cherry blossoms)'],
                    ['time' => '19:00', 'title' => 'Welcome dinner at local izakaya restaurant']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Modern Tokyo & Sky Views',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '10:00', 'title' => 'Visit Tokyo Skytree observation deck'],
                    ['time' => '13:00', 'title' => 'Lunch at Asakusa district'],
                    ['time' => '14:30', 'title' => 'Explore Senso-ji Temple and Nakamise Shopping Street'],
                    ['time' => '18:00', 'title' => 'teamLab Borderless digital art museum experience'],
                    ['time' => '20:00', 'title' => 'Dinner at Odaiba waterfront']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'Cultural Heritage & Harajuku',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Visit Meiji Shrine and surrounding forest'],
                    ['time' => '11:00', 'title' => 'Explore Harajuku Takeshita Street for shopping'],
                    ['time' => '13:00', 'title' => 'Lunch at trendy Omotesando café'],
                    ['time' => '15:00', 'title' => 'Shibuya Crossing and Hachiko Statue photo stop'],
                    ['time' => '17:00', 'title' => 'Shopping time at Shibuya 109 and Center Gai'],
                    ['time' => '19:00', 'title' => 'Yakiniku (Japanese BBQ) dinner']
                ]
            ],
            [
                'day_number' => 4,
                'day_title' => 'Day Trip & Cherry Blossom Viewing',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Early breakfast'],
                    ['time' => '09:00', 'title' => 'JR train to Ueno Park for hanami (cherry blossom viewing)'],
                    ['time' => '12:00', 'title' => 'Picnic lunch under cherry trees'],
                    ['time' => '14:00', 'title' => 'Visit Tokyo National Museum'],
                    ['time' => '17:00', 'title' => 'Akihabara Electric Town for anime and electronics'],
                    ['time' => '19:30', 'title' => 'Farewell dinner at traditional kaiseki restaurant']
                ]
            ],
            [
                'day_number' => 5,
                'day_title' => 'Last-Minute Shopping & Departure',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Hotel breakfast and check-out'],
                    ['time' => '10:00', 'title' => 'Last-minute souvenir shopping at Tokyo Station'],
                    ['time' => '12:00', 'title' => 'Airport transfer via Narita Express'],
                    ['time' => '15:00', 'title' => 'Departure flight from Narita to Manila'],
                    ['time' => '19:00', 'title' => 'Arrival at Manila']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ],
    
    // Package ID 3: Bali Paradise Retreat (4D/3N)
    [
        'package_id' => 3,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival & Beach Relaxation',
                'activities' => [
                    ['time' => '10:00', 'title' => 'Flight departure from Manila'],
                    ['time' => '14:00', 'title' => 'Arrival at Ngurah Rai International Airport, Bali'],
                    ['time' => '15:30', 'title' => 'Check-in at beachfront resort in Seminyak'],
                    ['time' => '17:00', 'title' => 'Sunset beach walk and welcome drinks'],
                    ['time' => '19:00', 'title' => 'Seafood dinner at beachfront restaurant']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Cultural Temple Tour',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Breakfast at resort'],
                    ['time' => '09:00', 'title' => 'Visit Tanah Lot Temple (iconic sea temple)'],
                    ['time' => '12:00', 'title' => 'Traditional Balinese lunch'],
                    ['time' => '14:00', 'title' => 'Tegallalang Rice Terrace photo session'],
                    ['time' => '16:00', 'title' => 'Optional jungle swing experience'],
                    ['time' => '18:00', 'title' => 'Uluwatu Temple and Kecak Fire Dance performance'],
                    ['time' => '20:00', 'title' => 'Seafood dinner at Jimbaran Bay']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'Water Activities & Spa',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Breakfast at resort'],
                    ['time' => '10:00', 'title' => 'Visit Tirta Empul Holy Water Temple'],
                    ['time' => '13:00', 'title' => 'Lunch at Ubud organic restaurant'],
                    ['time' => '15:00', 'title' => 'Traditional Balinese massage and spa treatment'],
                    ['time' => '18:00', 'title' => 'Sunset at Double Six Beach'],
                    ['time' => '19:30', 'title' => 'Dinner at Seminyak trendy restaurant']
                ]
            ],
            [
                'day_number' => 4,
                'day_title' => 'Shopping & Departure',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Final breakfast at resort'],
                    ['time' => '10:00', 'title' => 'Check-out and last-minute beach time'],
                    ['time' => '11:00', 'title' => 'Shopping at Seminyak boutiques'],
                    ['time' => '13:00', 'title' => 'Airport transfer'],
                    ['time' => '15:00', 'title' => 'Departure flight to Manila'],
                    ['time' => '18:00', 'title' => 'Arrival at Manila']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ],
    
    // Package ID 4: Singapore City Experience (3D/2N)
    [
        'package_id' => 4,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival & Marina Bay Exploration',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Flight from Manila to Singapore'],
                    ['time' => '13:00', 'title' => 'Arrival at Changi Airport and hotel check-in'],
                    ['time' => '15:00', 'title' => 'Visit Gardens by the Bay - Cloud Forest and Flower Dome'],
                    ['time' => '18:00', 'title' => 'Marina Bay Sands SkyPark observation deck'],
                    ['time' => '19:30', 'title' => 'Spectra light and water show at Marina Bay'],
                    ['time' => '20:30', 'title' => 'Dinner at hawker center (local food court)']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Universal Studios & Sentosa Island',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '09:00', 'title' => 'Full day at Universal Studios Singapore with Express Pass'],
                    ['time' => '13:00', 'title' => 'Lunch inside Universal Studios'],
                    ['time' => '17:00', 'title' => 'Continue exploring rides and attractions'],
                    ['time' => '19:00', 'title' => 'Sentosa Boardwalk evening stroll'],
                    ['time' => '20:00', 'title' => 'Wings of Time night show at Sentosa Beach']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'Shopping & Departure',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Hotel breakfast and check-out'],
                    ['time' => '09:30', 'title' => 'Shopping at Orchard Road (ION, Takashimaya, Paragon)'],
                    ['time' => '12:00', 'title' => 'Lunch at famous Hainanese chicken rice restaurant'],
                    ['time' => '13:30', 'title' => 'Visit Merlion Park for photo opportunity'],
                    ['time' => '15:00', 'title' => 'Airport transfer'],
                    ['time' => '17:00', 'title' => 'Departure flight to Manila'],
                    ['time' => '21:00', 'title' => 'Arrival at Manila']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ],
    
    // Package ID 5: Seoul K-Culture Adventure (5D/4N)
    [
        'package_id' => 5,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival & Myeongdong Night Market',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Departure from Manila'],
                    ['time' => '12:00', 'title' => 'Arrival at Incheon International Airport'],
                    ['time' => '14:00', 'title' => 'Hotel check-in at Myeongdong'],
                    ['time' => '16:00', 'title' => 'Explore N Seoul Tower and Namsan Park'],
                    ['time' => '18:00', 'title' => 'Myeongdong shopping street for cosmetics and fashion'],
                    ['time' => '20:00', 'title' => 'Street food dinner at Myeongdong Night Market']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Palaces & Traditional Culture',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '09:00', 'title' => 'Gyeongbokgung Palace tour with changing of guards'],
                    ['time' => '11:00', 'title' => 'Hanbok rental and photoshoot in Bukchon Hanok Village'],
                    ['time' => '13:00', 'title' => 'Lunch at traditional Korean restaurant'],
                    ['time' => '15:00', 'title' => 'Visit Insadong for traditional crafts and tea houses'],
                    ['time' => '17:00', 'title' => 'Cheonggyecheon Stream walk'],
                    ['time' => '19:00', 'title' => 'Korean BBQ dinner experience']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'K-Pop & Gangnam Style',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Breakfast at café'],
                    ['time' => '10:00', 'title' => 'K-Pop entertainment company tour'],
                    ['time' => '12:00', 'title' => 'Lunch at COEX Mall food court'],
                    ['time' => '13:30', 'title' => 'COEX Starfield Library and K-Pop store'],
                    ['time' => '15:00', 'title' => 'Gangnam Style statue at Gangnam Station'],
                    ['time' => '16:00', 'title' => 'Shopping at Gangnam fashion district'],
                    ['time' => '19:00', 'title' => 'Dinner at trendy Gangnam restaurant']
                ]
            ],
            [
                'day_number' => 4,
                'day_title' => 'Modern Seoul & Han River',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '10:00', 'title' => 'Visit Dongdaemun Design Plaza (DDP)'],
                    ['time' => '12:00', 'title' => 'Lunch at Gwangjang Traditional Market'],
                    ['time' => '14:00', 'title' => 'Shopping at Dongdaemun fashion district'],
                    ['time' => '17:00', 'title' => 'Han River Park cruise and picnic'],
                    ['time' => '19:00', 'title' => 'Hongdae area for nightlife and street performances'],
                    ['time' => '21:00', 'title' => 'Dinner at Hongdae trendy restaurant']
                ]
            ],
            [
                'day_number' => 5,
                'day_title' => 'Last Shopping & Departure',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Hotel breakfast and check-out'],
                    ['time' => '10:00', 'title' => 'Last-minute shopping at duty-free stores'],
                    ['time' => '12:00', 'title' => 'Airport transfer via AREX train'],
                    ['time' => '14:00', 'title' => 'Departure from Incheon Airport'],
                    ['time' => '18:00', 'title' => 'Arrival at Manila']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ],
    
    // Package ID 6: Bangkok & Pattaya Getaway (4D/3N)
    [
        'package_id' => 6,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival & Bangkok Temples',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Flight from Manila to Bangkok'],
                    ['time' => '11:00', 'title' => 'Arrival at Suvarnabhumi Airport'],
                    ['time' => '13:00', 'title' => 'Hotel check-in and lunch'],
                    ['time' => '15:00', 'title' => 'Grand Palace and Temple of the Emerald Buddha tour'],
                    ['time' => '17:00', 'title' => 'Wat Pho - Temple of the Reclining Buddha'],
                    ['time' => '19:00', 'title' => 'Dinner cruise on Chao Phraya River'],
                    ['time' => '21:00', 'title' => 'Asiatique night market shopping']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Floating Market & Transfer to Pattaya',
                'activities' => [
                    ['time' => '07:00', 'title' => 'Early breakfast'],
                    ['time' => '08:00', 'title' => 'Visit Damnoen Saduak Floating Market'],
                    ['time' => '11:00', 'title' => 'Boat ride and shopping at floating market'],
                    ['time' => '13:00', 'title' => 'Lunch at local Thai restaurant'],
                    ['time' => '14:00', 'title' => 'Private coach transfer to Pattaya (2.5 hours)'],
                    ['time' => '17:00', 'title' => 'Check-in at Pattaya beach resort'],
                    ['time' => '19:00', 'title' => 'Alcazar Cabaret Show (world-famous ladyboy show)'],
                    ['time' => '21:00', 'title' => 'Walking Street nightlife exploration']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'Pattaya Beach & Water Activities',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Breakfast at resort'],
                    ['time' => '09:00', 'title' => 'Coral Island speedboat tour (snorkeling, parasailing)'],
                    ['time' => '13:00', 'title' => 'Beachside seafood lunch'],
                    ['time' => '15:00', 'title' => 'Return to hotel for relaxation'],
                    ['time' => '17:00', 'title' => 'Visit Big Buddha Hill viewpoint'],
                    ['time' => '19:00', 'title' => 'Seafood dinner at Pattaya Beach Road'],
                    ['time' => '21:00', 'title' => 'Thai massage and spa treatment']
                ]
            ],
            [
                'day_number' => 4,
                'day_title' => 'Departure Day',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Final breakfast at resort'],
                    ['time' => '10:00', 'title' => 'Check-out and souvenir shopping at Central Festival Pattaya'],
                    ['time' => '12:00', 'title' => 'Private transfer to Bangkok airport'],
                    ['time' => '15:00', 'title' => 'Departure flight to Manila'],
                    ['time' => '19:00', 'title' => 'Arrival at Manila']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ],
    
    // Package ID 7: Dubai Luxury Escape (6D/5N)
    [
        'package_id' => 7,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival & Dubai Mall',
                'activities' => [
                    ['time' => '02:00', 'title' => 'Overnight business class flight from Manila'],
                    ['time' => '07:00', 'title' => 'Arrival at Dubai International Airport'],
                    ['time' => '09:00', 'title' => 'Check-in at 5-star hotel on Sheikh Zayed Road'],
                    ['time' => '12:00', 'title' => 'Lunch at hotel restaurant'],
                    ['time' => '15:00', 'title' => 'Dubai Mall shopping and Dubai Aquarium visit'],
                    ['time' => '18:00', 'title' => 'Burj Khalifa Sky Deck (Levels 124 & 125) at sunset'],
                    ['time' => '20:00', 'title' => 'Dubai Fountain show viewing'],
                    ['time' => '21:00', 'title' => 'Dinner at Dubai Mall fine dining restaurant']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Desert Safari Adventure',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '10:00', 'title' => 'Visit Gold Souk and Spice Souk in Deira'],
                    ['time' => '12:00', 'title' => 'Abra (traditional boat) ride across Dubai Creek'],
                    ['time' => '13:00', 'title' => 'Lunch at Al Fahidi Historical District'],
                    ['time' => '15:00', 'title' => 'Return to hotel for rest'],
                    ['time' => '16:00', 'title' => 'Desert safari pickup - dune bashing adventure'],
                    ['time' => '18:00', 'title' => 'Camel riding and sandboarding'],
                    ['time' => '19:30', 'title' => 'BBQ dinner under stars with belly dance show']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'Modern Dubai & Beach',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '10:00', 'title' => 'Visit Palm Jumeirah and Atlantis The Palm'],
                    ['time' => '12:00', 'title' => 'Lunch at Atlantis restaurant'],
                    ['time' => '14:00', 'title' => 'Beach time at JBR (Jumeirah Beach Residence)'],
                    ['time' => '17:00', 'title' => 'Visit Ain Dubai observation wheel'],
                    ['time' => '19:00', 'title' => 'Dinner at Marina waterfront restaurant'],
                    ['time' => '21:00', 'title' => 'Dubai Marina Walk evening stroll']
                ]
            ],
            [
                'day_number' => 4,
                'day_title' => 'Abu Dhabi Day Trip',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Early breakfast'],
                    ['time' => '09:00', 'title' => 'Private transfer to Abu Dhabi (1.5 hours)'],
                    ['time' => '11:00', 'title' => 'Sheikh Zayed Grand Mosque tour'],
                    ['time' => '13:00', 'title' => 'Lunch at Emirates Palace'],
                    ['time' => '15:00', 'title' => 'Louvre Abu Dhabi museum visit'],
                    ['time' => '17:00', 'title' => 'Return journey to Dubai'],
                    ['time' => '19:00', 'title' => 'Dinner at hotel with skyline views']
                ]
            ],
            [
                'day_number' => 5,
                'day_title' => 'Shopping & Entertainment',
                'activities' => [
                    ['time' => '09:00', 'title' => 'Breakfast at hotel'],
                    ['time' => '10:00', 'title' => 'Mall of the Emirates and Ski Dubai visit'],
                    ['time' => '13:00', 'title' => 'Lunch at Mall of the Emirates'],
                    ['time' => '15:00', 'title' => 'Continue shopping for luxury brands'],
                    ['time' => '17:00', 'title' => 'Visit Madinat Jumeirah Souk'],
                    ['time' => '19:00', 'title' => 'Traditional Arabic dinner at Al Hadheerah'],
                    ['time' => '21:00', 'title' => 'La Perle by Dragone show']
                ]
            ],
            [
                'day_number' => 6,
                'day_title' => 'Final Shopping & Departure',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Hotel breakfast and check-out'],
                    ['time' => '10:00', 'title' => 'Last-minute shopping at Dubai Outlet Mall'],
                    ['time' => '13:00', 'title' => 'Lunch at airport lounge'],
                    ['time' => '15:00', 'title' => 'Business class departure from Dubai'],
                    ['time' => '00:30', 'title' => 'Arrival at Manila (next day)']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ],
    
    // Package ID 8: Cebu Island Hopping Adventure (3D/2N)
    [
        'package_id' => 8,
        'itinerary_json' => json_encode([
            [
                'day_number' => 1,
                'day_title' => 'Arrival & Mactan Beach',
                'activities' => [
                    ['time' => '08:00', 'title' => 'Flight from Manila to Cebu'],
                    ['time' => '10:00', 'title' => 'Arrival at Mactan-Cebu International Airport'],
                    ['time' => '11:00', 'title' => 'Check-in at Mactan Island beach resort'],
                    ['time' => '12:00', 'title' => 'Welcome lunch at resort restaurant'],
                    ['time' => '14:00', 'title' => 'Beach relaxation and swimming'],
                    ['time' => '16:00', 'title' => 'Visit Lapu-Lapu Shrine and Magellan Marker'],
                    ['time' => '18:00', 'title' => 'Sunset viewing at beach'],
                    ['time' => '19:00', 'title' => 'Seafood dinner at beachfront grill']
                ]
            ],
            [
                'day_number' => 2,
                'day_title' => 'Oslob Whale Shark Encounter',
                'activities' => [
                    ['time' => '04:00', 'title' => 'Very early breakfast box'],
                    ['time' => '05:00', 'title' => 'Departure to Oslob (3-hour drive south)'],
                    ['time' => '08:00', 'title' => 'Whale shark swimming briefing'],
                    ['time' => '09:00', 'title' => 'Once-in-a-lifetime whale shark encounter and snorkeling'],
                    ['time' => '11:00', 'title' => 'Visit Tumalog Falls for refreshing swim'],
                    ['time' => '13:00', 'title' => 'Lunch at local restaurant'],
                    ['time' => '14:30', 'title' => 'Return journey to Mactan'],
                    ['time' => '18:00', 'title' => 'Arrival at resort, free time to rest'],
                    ['time' => '19:30', 'title' => 'Dinner at resort']
                ]
            ],
            [
                'day_number' => 3,
                'day_title' => 'Island Hopping & Departure',
                'activities' => [
                    ['time' => '07:00', 'title' => 'Early breakfast at resort'],
                    ['time' => '08:00', 'title' => 'Island hopping boat tour departure'],
                    ['time' => '09:00', 'title' => 'Hilutungan Island - snorkeling with colorful fish'],
                    ['time' => '11:00', 'title' => 'Nalusuan Island - marine sanctuary exploration'],
                    ['time' => '13:00', 'title' => 'Beachside lunch on Pandanon Island'],
                    ['time' => '15:00', 'title' => 'Return to resort, check-out and freshen up'],
                    ['time' => '16:30', 'title' => 'Airport transfer'],
                    ['time' => '18:00', 'title' => 'Departure flight to Manila'],
                    ['time' => '20:00', 'title' => 'Arrival at Manila']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
    ]
];

echo "🚀 Starting tour package itineraries seeding process...\n\n";

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO tour_package_itinerary (
            package_id,
            itinerary_json,
            updated_at
        ) VALUES (?, ?, NOW())
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $successCount = 0;
    $skippedCount = 0;

    foreach ($packageItineraries as $itinerary) {
        // Check if itinerary already exists for this package
        $checkStmt = $conn->prepare("SELECT id FROM tour_package_itinerary WHERE package_id = ?");
        $checkStmt->bind_param('i', $itinerary['package_id']);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            echo "⚠️  Skipped: Package ID {$itinerary['package_id']} (itinerary already exists)\n";
            $skippedCount++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Get package name for display
        $nameStmt = $conn->prepare("SELECT package_name, duration FROM tour_packages WHERE id = ?");
        $nameStmt->bind_param('i', $itinerary['package_id']);
        $nameStmt->execute();
        $nameStmt->bind_result($packageName, $duration);
        $nameStmt->fetch();
        $nameStmt->close();

        // Insert new itinerary
        $stmt->bind_param(
            'is',
            $itinerary['package_id'],
            $itinerary['itinerary_json']
        );

        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;
            
            // Count days in itinerary
            $itineraryData = json_decode($itinerary['itinerary_json'], true);
            $dayCount = count($itineraryData);
            
            echo "✅ Inserted itinerary for: {$packageName}\n";
            echo "   → Itinerary ID: {$insertedId} | Package ID: {$itinerary['package_id']} | Days: {$dayCount} ({$duration})\n\n";
            $successCount++;
        } else {
            throw new Exception("Insert failed for Package ID {$itinerary['package_id']}: " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo "✨ Tour package itineraries seeding completed successfully!\n";
    echo "   - Inserted: {$successCount} itineraries\n";
    echo "   - Skipped: {$skippedCount} itineraries (duplicates)\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "\n✅ Database connection closed.\n";
