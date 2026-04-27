<?php
// controllers/HotelsController.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../utils/Response.php';

class HotelsController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS hotels (
            hotel_id     INT AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(200) NOT NULL,
            city         VARCHAR(100) NOT NULL,
            country      VARCHAR(100) NOT NULL,
            stars        TINYINT DEFAULT 5,
            price        DECIMAL(10,2) NOT NULL DEFAULT 0,
            rating       DECIMAL(3,2) DEFAULT 0,
            reviews      INT DEFAULT 0,
            description  TEXT,
            amenities    TEXT COMMENT 'comma-separated',
            max_children INT DEFAULT 4,
            total_rooms  INT DEFAULT 10,
            initial      VARCHAR(10),
            color        VARCHAR(20),
            status       ENUM('active','pending','rejected') DEFAULT 'active',
            owner_id     INT DEFAULT NULL,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed if empty
        $count = (int)$this->db->query("SELECT COUNT(*) FROM hotels")->fetchColumn();
        if ($count === 0) {
            $hotels = [
                ['Le Grand Hôtel',          'Paris',     'France',  5, 450,  4.90, 1284, 'Belle Époque grandeur at the heart of Paris.',           'Wi-Fi,Spa,Restaurant,Concierge,Bar',             4, 3,  'LG', '#1a1208'],
                ['Hôtel de Crillon',         'Paris',     'France',  5, 980,  4.95,  876, 'A palatial 18th-century landmark on Place de la Concorde.','Wi-Fi,Pool,Spa,Restaurant,Concierge',           2, 5,  'HC', '#14100a'],
                ['Burj Al Arab',             'Dubai',     'UAE',     5, 1800, 4.85, 2341, 'The world\'s most iconic sail-shaped luxury hotel.',      'Pool,Spa,Restaurant,Bar,Transfer,Concierge',     3, 2,  'BA', '#0a1218'],
                ['Atlantis The Palm',        'Dubai',     'UAE',     5, 620,  4.70,  985, 'A waterpark resort on the Palm Jumeirah.',               'Pool,Spa,Waterpark,Restaurant,Bar',              4, 10, 'AP', '#0d1e2e'],
                ['The Peninsula',            'Tokyo',     'Japan',   5, 720,  4.90,  998, 'Eastern refinement in the heart of Tokyo.',             'Spa,Pool,Restaurant,Concierge',                  2, 4,  'TP', '#120a10'],
                ['Sofitel Algiers',          'Algiers',   'Algeria', 5, 220,  4.72,  642, 'French elegance in the Algerian capital.',               'Pool,Spa,Restaurant',                            3, 4,  'SA', '#0a1a0e'],
                ['El Djazair Hotel',         'Algiers',   'Algeria', 5, 180,  4.65,  430, 'A colonial-era landmark in Algiers.',                    'Pool,Restaurant,Bar',                            4, 3,  'EJ', '#0e1a0a'],
                ['Four Seasons Bosphorus',   'Istanbul',  'Turkey',  5, 680,  4.91,  774, 'An Ottoman palace on the Bosphorus strait.',            'Spa,Pool,Restaurant,Concierge',                  2, 6,  'FS', '#1a0a08'],
                ['La Mamounia',              'Marrakech', 'Morocco', 5, 750,  4.94,  512, 'Moorish splendour surrounded by gardens.',              'Pool,Spa,Restaurant,Bar',                        3, 8,  'LM', '#1a0e06'],
                ['Hotel Arts Barcelona',     'Barcelona', 'Spain',   5, 480,  4.75,  863, 'A beachfront masterpiece in Barcelona.',                 'Pool,Spa,Restaurant,Bar,Concierge',              3, 7,  'HB', '#0a0e1a'],
            ];
            $sql  = "INSERT INTO hotels (name, city, country, stars, price, rating, reviews, description, amenities, max_children, total_rooms, initial, color) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $this->db->prepare($sql);
            foreach ($hotels as $h) $stmt->execute($h);
        }
    }

    /** GET /hotels */
    public function getAll(): void {
        $stmt = $this->db->query("SELECT * FROM hotels WHERE status = 'active' ORDER BY rating DESC");
        $hotels = $stmt->fetchAll();
        foreach ($hotels as &$h) {
            $h['amenities']    = $h['amenities'] ? explode(',', $h['amenities']) : [];
            $h['hotel_id']     = (int)$h['hotel_id'];
            $h['price']        = (float)$h['price'];
            $h['rating']       = (float)$h['rating'];
            $h['reviews']      = (int)$h['reviews'];
            $h['stars']        = (int)$h['stars'];
            $h['max_children'] = (int)$h['max_children'];
            $h['total_rooms']  = (int)$h['total_rooms'];
        }
        unset($h);
        Response::success($hotels);
    }

    /** GET /hotels/:id */
    public function getById(int $id): void {
        $stmt = $this->db->prepare("SELECT * FROM hotels WHERE hotel_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $hotel = $stmt->fetch();
        if (!$hotel) Response::notFound('Hotel not found.');
        $hotel['amenities'] = $hotel['amenities'] ? explode(',', $hotel['amenities']) : [];
        $hotel['hotel_id']  = (int)$hotel['hotel_id'];
        $hotel['price']     = (float)$hotel['price'];
        Response::success($hotel);
    }
}
