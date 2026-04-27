<?php
// controllers/BookingsController.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class BookingsController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS bookings (
            booking_id  INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            hotel_id    INT NOT NULL,
            hotel_name  VARCHAR(200),
            check_in    DATE NOT NULL,
            check_out   DATE NOT NULL,
            rooms       INT DEFAULT 1,
            guests      INT DEFAULT 2,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status      ENUM('pending','confirmed','cancelled') DEFAULT 'confirmed',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (hotel_id) REFERENCES hotels(hotel_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** POST /bookings */
    public function create(): void {
        $payload    = AuthMiddleware::handle();
        $body       = json_decode(file_get_contents('php://input'), true) ?? [];

        $hotel_id   = (int)($body['hotel_id']    ?? 0);
        $check_in   = $body['check_in']           ?? '';
        $check_out  = $body['check_out']          ?? '';
        $rooms      = max(1, (int)($body['rooms'] ?? 1));
        $guests     = max(1, (int)($body['guests']?? 2));
        $total      = (float)($body['total_price'] ?? 0);

        if (!$hotel_id || !$check_in || !$check_out) {
            Response::error('hotel_id, check_in, and check_out are required.');
        }
        // Validate date ordering
        if (strtotime($check_out) <= strtotime($check_in)) {
            Response::error('check_out must be after check_in.');
        }
        // check_in cannot be in the past
        if (strtotime($check_in) < strtotime('today')) {
            Response::error('check_in cannot be in the past.');
        }

        $hotel = $this->db->prepare("SELECT name, price FROM hotels WHERE hotel_id = ? AND status = 'active'");
        $hotel->execute([$hotel_id]);
        $hotelRow = $hotel->fetch();
        if (!$hotelRow) Response::error('Hotel not found.', 404);

        // Recalculate price server-side to prevent tampering
        $nights = (int)round((strtotime($check_out) - strtotime($check_in)) / 86400);
        $total  = round((float)$hotelRow['price'] * $nights * $rooms, 2);

        $stmt = $this->db->prepare(
            "INSERT INTO bookings (user_id, hotel_id, hotel_name, check_in, check_out, rooms, guests, total_price, status)
             VALUES (?,?,?,?,?,?,?,?,'confirmed')"
        );
        $stmt->execute([
            (int)$payload['user_id'],
            $hotel_id,
            $hotelRow['name'],
            $check_in,
            $check_out,
            $rooms,
            $guests,
            $total,
        ]);

        Response::success([
            'booking_id'  => (int)$this->db->lastInsertId(),
            'hotel_name'  => $hotelRow['name'],
            'total_price' => $total,
            'nights'      => $nights,
        ], 'Booking confirmed.', 201);
    }

    /** GET /bookings — returns current user's bookings */
    public function getUserBookings(): void {
        $payload = AuthMiddleware::handle();
        $stmt    = $this->db->prepare(
            "SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([(int)$payload['user_id']]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['booking_id']  = (int)$r['booking_id'];
            $r['total_price'] = (float)$r['total_price'];
        }
        unset($r);
        Response::success($rows);
    }
}
