<?php
// controllers/OwnerPropertiesController.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class OwnerPropertiesController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS owner_properties (
            property_id INT AUTO_INCREMENT PRIMARY KEY,
            owner_id    INT NOT NULL,
            name        VARCHAR(200) NOT NULL,
            city        VARCHAR(100),
            country     VARCHAR(100),
            stars       TINYINT DEFAULT 5,
            rooms       INT DEFAULT 10,
            price_from  DECIMAL(10,2) DEFAULT 0,
            description TEXT,
            amenities   TEXT,
            status      ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** GET /owner/properties */
    public function getAll(): void {
        $payload = AuthMiddleware::requireRole('owner');
        $stmt    = $this->db->prepare(
            "SELECT * FROM owner_properties WHERE owner_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([(int)$payload['user_id']]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['amenities']  = $r['amenities'] ? explode(',', $r['amenities']) : [];
            $r['property_id']= (int)$r['property_id'];
            $r['price_from'] = (float)$r['price_from'];
        }
        unset($r);
        Response::success($rows);
    }

    /** POST /owner/properties */
    public function create(): void {
        $payload   = AuthMiddleware::requireRole('owner');
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $name      = trim($body['name'] ?? '');

        if (!$name) Response::error('Property name is required.');

        $amenities = is_array($body['amenities'] ?? null)
            ? implode(',', array_map('trim', $body['amenities']))
            : trim($body['amenities'] ?? '');

        $stmt = $this->db->prepare(
            "INSERT INTO owner_properties (owner_id, name, city, country, stars, rooms, price_from, description, amenities)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            (int)$payload['user_id'],
            $name,
            trim($body['city']        ?? ''),
            trim($body['country']     ?? ''),
            (int)($body['stars']      ?? 5),
            (int)($body['rooms']      ?? 10),
            (float)($body['price_from'] ?? 0),
            trim($body['description'] ?? ''),
            $amenities,
        ]);

        Response::success(
            ['property_id' => (int)$this->db->lastInsertId()],
            'Property submitted for review.',
            201
        );
    }

    /** PUT /owner/properties/:id */
    public function update(int $id): void {
        $payload = AuthMiddleware::requireRole('owner');
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];

        $check = $this->db->prepare(
            "SELECT property_id FROM owner_properties WHERE property_id = ? AND owner_id = ?"
        );
        $check->execute([$id, (int)$payload['user_id']]);
        if (!$check->fetch()) Response::forbidden('Not your property.');

        $fields = [];
        $params = [];
        foreach (['name','city','country','stars','rooms','price_from','description'] as $f) {
            if (array_key_exists($f, $body)) {
                $fields[]     = "$f = ?";
                $params[]     = $body[$f];
            }
        }
        if (isset($body['amenities']) && is_array($body['amenities'])) {
            $fields[] = "amenities = ?";
            $params[] = implode(',', array_map('trim', $body['amenities']));
        }
        if (empty($fields)) Response::error('No fields to update.');

        $params[] = $id;
        $sql      = "UPDATE owner_properties SET " . implode(', ', $fields) . " WHERE property_id = ?";
        $this->db->prepare($sql)->execute($params);
        Response::success(null, 'Property updated.');
    }

    /** DELETE /owner/properties/:id */
    public function delete(int $id): void {
        $payload = AuthMiddleware::requireRole('owner');
        $stmt    = $this->db->prepare(
            "DELETE FROM owner_properties WHERE property_id = ? AND owner_id = ?"
        );
        $stmt->execute([$id, (int)$payload['user_id']]);
        Response::success(null, 'Property deleted.');
    }
}
