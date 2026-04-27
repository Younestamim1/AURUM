<?php
// index.php — Backend entry point
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

// ── Load .env before anything else ────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

// ── CORS ──────────────────────────────────────────────────────
$allowedOrigins = array_filter(explode(',', $_ENV['FRONTEND_URL'] ?? '*'));
$origin         = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $allowedOrigins) || empty($allowedOrigins)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
} else {
    // For GitHub Pages: allow any origin (set FRONTEND_URL=* in .env)
    header('Access-Control-Allow-Origin: *');
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/utils/Response.php';

// ── Dispatch ──────────────────────────────────────────────────
try {
    /** @var Router $router */
    $router = require_once __DIR__ . '/routes/api.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.', 'debug' => $_ENV['APP_DEBUG'] ?? false ? $e->getMessage() : null]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.', 'debug' => $_ENV['APP_DEBUG'] ?? false ? $e->getMessage() : null]);
}
