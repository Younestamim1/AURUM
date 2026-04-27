<?php
// config/Database.php — loads credentials from .env or environment variables
class Database {
    private static ?Database $instance = null;
    private PDO $conn;

    private function __construct() {
        // Load .env file if it exists (root of backend)
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }

        $host    = $_ENV['DB_HOST']     ?? 'localhost';
        $dbName  = $_ENV['DB_NAME']     ?? 'hotel_management';
        $user    = $_ENV['DB_USER']     ?? 'root';
        $pass    = $_ENV['DB_PASS']     ?? '';
        $charset = $_ENV['DB_CHARSET']  ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->conn = new PDO($dsn, $user, $pass, $options);
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }
}
