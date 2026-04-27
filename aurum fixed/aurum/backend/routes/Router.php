<?php
// routes/Router.php
class Router {
    private array $routes = [];

    public function get(string $pattern, callable $handler): void    { $this->add('GET',    $pattern, $handler); }
    public function post(string $pattern, callable $handler): void   { $this->add('POST',   $pattern, $handler); }
    public function put(string $pattern, callable $handler): void    { $this->add('PUT',    $pattern, $handler); }
    public function patch(string $pattern, callable $handler): void  { $this->add('PATCH',  $pattern, $handler); }
    public function delete(string $pattern, callable $handler): void { $this->add('DELETE', $pattern, $handler); }

    private function add(string $method, string $pattern, callable $handler): void {
        $regex          = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = ['method' => $method, 'regex' => "#^{$regex}$#", 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH);
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;
            if (preg_match($route['regex'], $path, $matches)) {
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                $params = array_map(fn($v) => ctype_digit($v) ? (int)$v : $v, $params);
                call_user_func_array($route['handler'], array_values($params));
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Route not found.']);
    }
}
