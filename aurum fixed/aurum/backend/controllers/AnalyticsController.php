<?php
// controllers/AnalyticsController.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class AnalyticsController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /** GET /analytics/dashboard */
    public function getDashboard(): void {
        $payload  = AuthMiddleware::requireRole('owner');
        $owner_id = (int)$payload['user_id'];

        // Total bookings & revenue for this owner's hotels
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total_bookings,
                    COALESCE(SUM(b.total_price), 0) AS total_revenue
             FROM bookings b
             JOIN hotels h ON b.hotel_id = h.hotel_id
             WHERE h.owner_id = ?"
        );
        $stmt->execute([$owner_id]);
        $stats = $stmt->fetch();

        // Recent 10 bookings
        $stmt2 = $this->db->prepare(
            "SELECT b.*, u.name AS guest_name, u.email AS guest_email
             FROM bookings b
             JOIN users u  ON b.user_id  = u.user_id
             JOIN hotels h ON b.hotel_id = h.hotel_id
             WHERE h.owner_id = ?
             ORDER BY b.created_at DESC
             LIMIT 10"
        );
        $stmt2->execute([$owner_id]);
        $recent = $stmt2->fetchAll();

        // Properties count
        $stmt3 = $this->db->prepare(
            "SELECT COUNT(*) FROM owner_properties WHERE owner_id = ?"
        );
        $stmt3->execute([$owner_id]);
        $propertiesCount = (int)$stmt3->fetchColumn();

        Response::success([
            'stats'           => [
                'total_bookings'  => (int)($stats['total_bookings']  ?? 0),
                'total_revenue'   => (float)($stats['total_revenue'] ?? 0),
                'properties'      => $propertiesCount,
                'occupancy_rate'  => 0,   // calculated if rooms data available
            ],
            'recent_bookings' => $recent,
        ]);
    }
}
