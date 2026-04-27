<?php
// controllers/AIConciergeController.php
// Groq API key loaded from .env — never hardcoded
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../utils/Response.php';

class AIConciergeController {
    private PDO    $db;
    private array  $hotels = [];
    private string $groqApiKey;

    public function __construct() {
        $this->db         = Database::getInstance()->getConnection();
        $this->groqApiKey = $_ENV['GROQ_API_KEY'] ?? '';
        $this->ensureTable();
        $this->loadHotels();
    }

    private function ensureTable(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS ai_conversations (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            session_id      VARCHAR(100),
            user_message    TEXT,
            ai_response     TEXT,
            extracted_city  VARCHAR(100),
            extracted_budget INT,
            extracted_rooms  INT,
            extracted_children INT,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function loadHotels(): void {
        try {
            $stmt = $this->db->query(
                "SELECT name, city, country, price, stars, rating, description FROM hotels WHERE status = 'active'"
            );
            $this->hotels = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $this->hotels = [];
        }

        // Fallback seed data when DB is empty
        if (empty($this->hotels)) {
            $this->hotels = [
                ['name'=>'Le Grand Hôtel',         'city'=>'Paris',     'country'=>'France',  'price'=>450,  'stars'=>5, 'rating'=>4.9,  'description'=>'Belle Époque grandeur'],
                ['name'=>'Hôtel de Crillon',        'city'=>'Paris',     'country'=>'France',  'price'=>980,  'stars'=>5, 'rating'=>4.95, 'description'=>'Palatial 18th-century landmark'],
                ['name'=>'Burj Al Arab',            'city'=>'Dubai',     'country'=>'UAE',     'price'=>1800, 'stars'=>5, 'rating'=>4.85, 'description'=>'Iconic sail-shaped tower'],
                ['name'=>'Atlantis The Palm',       'city'=>'Dubai',     'country'=>'UAE',     'price'=>620,  'stars'=>5, 'rating'=>4.70, 'description'=>'Waterpark resort on the Palm'],
                ['name'=>'The Peninsula',           'city'=>'Tokyo',     'country'=>'Japan',   'price'=>720,  'stars'=>5, 'rating'=>4.9,  'description'=>'Eastern refinement'],
                ['name'=>'Sofitel Algiers',         'city'=>'Algiers',   'country'=>'Algeria', 'price'=>220,  'stars'=>5, 'rating'=>4.72, 'description'=>'French elegance in Algiers'],
                ['name'=>'El Djazair Hotel',        'city'=>'Algiers',   'country'=>'Algeria', 'price'=>180,  'stars'=>5, 'rating'=>4.65, 'description'=>'Colonial-era landmark'],
                ['name'=>'Four Seasons Bosphorus',  'city'=>'Istanbul',  'country'=>'Turkey',  'price'=>680,  'stars'=>5, 'rating'=>4.91, 'description'=>'Ottoman palace on the Bosphorus'],
                ['name'=>'La Mamounia',             'city'=>'Marrakech', 'country'=>'Morocco', 'price'=>750,  'stars'=>5, 'rating'=>4.94, 'description'=>'Moorish splendour'],
                ['name'=>'Hotel Arts Barcelona',    'city'=>'Barcelona', 'country'=>'Spain',   'price'=>480,  'stars'=>5, 'rating'=>4.75, 'description'=>'Beachfront masterpiece'],
            ];
        }
    }

    private function parseMessage(string $message): array {
        $text     = strtolower($message);
        $city     = null;
        $budget   = null;
        $rooms    = 1;
        $children = 0;

        $cities = ['paris','dubai','tokyo','algiers','marrakech','istanbul','barcelona','london','new york'];
        foreach ($cities as $c) {
            if (strpos($text, $c) !== false) { $city = $c; break; }
        }

        if (preg_match('/\$?\s*(\d+)\s*(?:per night|\/night|a night)?/', $text, $m)) {
            $budget = (int)$m[1];
        }
        if (preg_match('/(\d+)\s*rooms?/', $text, $m))    $rooms    = (int)$m[1];
        if (preg_match('/(\d+)\s*child/', $text, $m))     $children = (int)$m[1];

        return ['city' => $city, 'budget' => $budget, 'rooms' => $rooms, 'children' => $children];
    }

    private function findMatchingHotels(array $parsed): array {
        $matches = array_filter($this->hotels, function ($h) use ($parsed) {
            if ($parsed['city'] && strtolower($h['city']) !== strtolower($parsed['city'])) return false;
            if ($parsed['budget'] && (float)$h['price'] > $parsed['budget']) return false;
            return true;
        });
        usort($matches, fn($a, $b) => (float)$b['rating'] <=> (float)$a['rating']);
        return array_values($matches);
    }

    /** Real Groq API call via cURL */
    private function callGroqAPI(string $message, array $matches): ?string {
        if (empty($this->groqApiKey)) return null;

        $hotelsList = '';
        foreach (array_slice($matches, 0, 5) as $h) {
            $hotelsList .= "- {$h['name']} in {$h['city']}: \${$h['price']}/night, {$h['stars']}★, rating {$h['rating']}\n";
        }
        if (empty($hotelsList)) {
            foreach (array_slice($this->hotels, 0, 5) as $h) {
                $hotelsList .= "- {$h['name']} in {$h['city']}: \${$h['price']}/night, {$h['stars']}★\n";
            }
        }

        $systemPrompt = "You are AURUM's AI concierge — a luxury hotel booking assistant. "
            . "Available hotels:\n{$hotelsList}\n"
            . "Instructions: Recommend 1-2 specific hotels from the list. Include exact prices. "
            . "Be warm, cultured, and concise (3-4 sentences). "
            . "Respond in the same language as the guest. "
            . "Do not invent hotels not listed above.";

        $payload = [
            'model'    => 'llama3-8b-8192',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $message],
            ],
            'temperature' => 0.7,
            'max_tokens'  => 350,
        ];

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) return null;

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function generateLocalResponse(array $parsed, array $matches): string {
        if (empty($matches)) {
            $city   = $parsed['city']   ? ucfirst($parsed['city'])        : 'your destination';
            $budget = $parsed['budget'] ? "under \${$parsed['budget']}"   : 'in your range';
            return "I couldn't find hotels in {$city} {$budget}. Try a different destination or adjust your budget.";
        }
        $top   = $matches[0];
        $reply = "Based on your request, I recommend <strong>{$top['name']}</strong> in "
            . ucfirst($top['city'])
            . " from \${$top['price']}/night — {$top['description']}.";
        if (count($matches) > 1) {
            $reply .= " Another excellent option is <strong>{$matches[1]['name']}</strong> from \${$matches[1]['price']}/night. Would you like to check availability?";
        }
        return $reply;
    }

    /** POST /ai/concierge */
    public function chat(): void {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $message = trim($body['message'] ?? '');
        if (!$message) Response::error('Message is required.');

        $parsed  = $this->parseMessage($message);
        $matches = $this->findMatchingHotels($parsed);

        // Try real Groq API first; fall back to local response
        $aiText = $this->callGroqAPI($message, $matches)
               ?? $this->generateLocalResponse($parsed, $matches);

        // Log conversation
        try {
            $this->db->prepare(
                "INSERT INTO ai_conversations
                 (session_id, user_message, ai_response, extracted_city, extracted_budget, extracted_rooms, extracted_children)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                $body['session_id'] ?? uniqid('s_'),
                $message,
                $aiText,
                $parsed['city'],
                $parsed['budget'],
                $parsed['rooms'],
                $parsed['children'],
            ]);
        } catch (\Throwable $e) {
            // Non-fatal: logging failure must not break the response
        }

        Response::success([
            'response'    => $aiText,
            'suggestions' => array_slice($matches, 0, 3),
        ], 'AI response');
    }
}
