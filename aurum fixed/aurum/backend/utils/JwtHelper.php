<?php
// utils/JwtHelper.php
class JwtHelper {
    private static function secret(): string {
        $s = $_ENV['JWT_SECRET'] ?? 'HOTEL_SECRET_KEY_CHANGE_ME_IN_PROD';
        return $s;
    }
    private static function ttl(): int {
        return (int)($_ENV['JWT_TTL'] ?? 86400);
    }

    public static function encode(array $payload): string {
        $header  = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + self::ttl();
        $body    = self::b64url(json_encode($payload));
        $sig     = self::b64url(hash_hmac('sha256', "$header.$body", self::secret(), true));
        return "$header.$body.$sig";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $body, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "$header.$body", self::secret(), true));
        if (!hash_equals($expected, $sig)) return null;
        $payload = json_decode(self::b64urlDecode($body), true);
        if (!$payload || $payload['exp'] < time()) return null;
        return $payload;
    }

    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function b64urlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
