<?php
// controllers/AuthController.php
// Handles both guest/owner email login AND admin username login
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../utils/JwtHelper.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureUsersTable();
    }

    private function ensureUsersTable(): void {
        // Users table for guest/owner login
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            user_id    INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(150) NOT NULL,
            email      VARCHAR(150) NOT NULL UNIQUE,
            password   VARCHAR(255),
            role       ENUM('guest','owner','admin') DEFAULT 'guest',
            initials   VARCHAR(10),
            hotel_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Admin table for dashboard admin login
        $this->db->exec("CREATE TABLE IF NOT EXISTS admin_users (
            admin_id   INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(80)  NOT NULL UNIQUE,
            email      VARCHAR(150),
            password   VARCHAR(255) NOT NULL,
            role       ENUM('superadmin','manager','staff') DEFAULT 'staff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed demo users if missing
        $this->seedDemoUsers();
    }

    private function seedDemoUsers(): void {
        $demos = [
            ['Hotel Owner', 'owner@aurum.com', 'owner123', 'owner', 'HO', 'Grand Hotel'],
            ['Guest User',  'guest@aurum.com', 'guest123', 'guest', 'GU', null],
        ];
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO users (name, email, password, role, initials, hotel_name) VALUES (?,?,?,?,?,?)"
        );
        foreach ($demos as [$name, $email, $pass, $role, $ini, $hotel]) {
            $existing = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
            $existing->execute([$email]);
            if (!$existing->fetch()) {
                $stmt->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $role, $ini, $hotel]);
            }
        }

        // Seed superadmin
        $adm = $this->db->prepare("SELECT admin_id FROM admin_users WHERE username = 'superadmin'");
        $adm->execute();
        if (!$adm->fetch()) {
            $this->db->prepare(
                "INSERT INTO admin_users (username, email, password, role) VALUES ('superadmin','admin@aurum.com',?,?)"
            )->execute([password_hash('admin123', PASSWORD_BCRYPT), 'superadmin']);
        }
    }

    /** POST /auth/login — email + password (guest/owner) */
    public function login(): void {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';

        if (empty($email) || empty($pass)) {
            Response::error('Email and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format.');
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'] ?? '')) {
            Response::unauthorized('Invalid email or password.');
        }

        $token = JwtHelper::encode([
            'user_id'  => $user['user_id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role'     => $user['role'],
            'initials' => $user['initials'],
        ]);

        unset($user['password']);
        Response::success(['token' => $token, 'user' => $user], 'Login successful.');
    }

    /** POST /auth/register — create guest or owner account */
    public function register(): void {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $name      = trim($body['name'] ?? '');
        $email     = trim($body['email'] ?? '');
        $pass      = $body['password'] ?? '';
        $role      = in_array($body['role'] ?? '', ['guest','owner']) ? $body['role'] : 'guest';
        $hotelName = trim($body['hotel_name'] ?? '');

        if (!$name || !$email || !$pass) {
            Response::error('Name, email, and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format.');
        }
        if (strlen($pass) < 8) {
            Response::error('Password must be at least 8 characters.');
        }

        $check = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            Response::error('Email already registered.', 409);
        }

        $initials = strtoupper(substr($name, 0, 1) . (strpos($name, ' ') !== false ? substr($name, strpos($name,' ')+1, 1) : substr($name, 1, 1)));
        $hashed   = password_hash($pass, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role, initials, hotel_name) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([$name, $email, $hashed, $role, $initials, $hotelName ?: null]);
        $userId = (int)$this->db->lastInsertId();

        $token = JwtHelper::encode([
            'user_id'  => $userId,
            'name'     => $name,
            'email'    => $email,
            'role'     => $role,
            'initials' => $initials,
        ]);

        Response::success([
            'token' => $token,
            'user'  => [
                'user_id'    => $userId,
                'name'       => $name,
                'email'      => $email,
                'role'       => $role,
                'initials'   => $initials,
                'hotel_name' => $hotelName ?: null,
            ],
        ], 'Registration successful.', 201);
    }

    /** POST /auth/admin/login — username + password (admin dashboard) */
    public function adminLogin(): void {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = trim($body['username'] ?? '');
        $pass     = $body['password'] ?? '';

        if (empty($username) || empty($pass)) {
            Response::error('Username and password are required.');
        }

        $stmt = $this->db->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($pass, $admin['password'])) {
            Response::unauthorized('Invalid credentials.');
        }

        $token = JwtHelper::encode([
            'admin_id' => $admin['admin_id'],
            'username' => $admin['username'],
            'role'     => $admin['role'],
        ]);

        unset($admin['password']);
        Response::success(['token' => $token, 'admin' => $admin], 'Login successful.');
    }
}
