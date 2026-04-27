<?php
// middleware/AuthMiddleware.php
// Unified middleware for both guest/owner/admin JWT tokens
require_once __DIR__ . '/../utils/JwtHelper.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    /**
     * Validate Bearer token. Returns decoded payload or sends 401.
     */
    public static function handle(): array {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Apache sometimes moves the header
        if (empty($authHeader)) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? '';
        }

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Missing or malformed Authorization header.');
        }

        $token   = substr($authHeader, 7);
        $payload = JwtHelper::decode($token);

        if ($payload === null) {
            Response::unauthorized('Invalid or expired token.');
        }

        return $payload;
    }

    /**
     * Require a specific role.
     * Frontend roles:  guest < owner < admin
     * Admin roles:     staff < manager < superadmin
     * Both hierarchies are supported.
     */
    public static function requireRole(string $required): array {
        $payload = self::handle();

        // Unified hierarchy covering both role sets
        $hierarchy = [
            'guest'      => 1,
            'staff'      => 1,
            'owner'      => 2,
            'manager'    => 2,
            'admin'      => 3,
            'superadmin' => 3,
        ];

        $userLevel = $hierarchy[$payload['role'] ?? ''] ?? 0;
        $reqLevel  = $hierarchy[$required]              ?? 99;

        if ($userLevel < $reqLevel) {
            Response::forbidden("Requires '{$required}' role or higher.");
        }

        return $payload;
    }
}
