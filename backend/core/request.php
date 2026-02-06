<?php
/**
 * Food Ordering System - HTTP Request Abstraction
 * Clean, safe wrapper for incoming request data (GET, POST, JSON, headers)
 *
 * Provides sanitized access to query parameters, body (form or JSON), and headers.
 * Follows security best practices and prevents common input vulnerabilities.
 *
 * @package FoodOrderingSystem
 * @subpackage Core
 */
class Request
{
    private string $method;
    private string $uri;
    private array $query = [];
    private array $body = [];
    private array $headers = [];
    private ?array $jsonData = null;

    /**
     * Constructor - initializes request data safely
     */
    public function __construct()
    {
        // HTTP method
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // URI without query string
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->uri = rtrim($uri, '/');
        if ($this->uri === '') {
            $this->uri = '/';
        }

        // Query parameters ($_GET)
        $this->query = $this->sanitizeArray($_GET ?? []);

        // Headers
        $this->headers = $this->getAllHeaders();

        // Body handling
        $this->initializeBody();
    }

    /**
     * Initialize body data (JSON or form)
     *
     * @return void
     */
    private function initializeBody(): void
    {
        $contentType = strtolower($this->header('Content-Type', ''));

        // Handle JSON input
        if (str_contains($contentType, 'application/json')) {
            $rawInput = $this->getRawInput();
            $decoded = json_decode($rawInput, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->jsonData = $decoded;
                $this->body = $this->sanitizeArray($decoded);
            } else {
                $this->jsonData = [];
                $this->body = [];
                // Optionally log invalid JSON in production
                // error_log("Invalid JSON received: " . json_last_error_msg());
            }
            return;
        }

        // Handle multipart/form-data or x-www-form-urlencoded
        $this->body = $this->sanitizeArray($_POST ?? []);

        // Merge any possible JSON-like fields if sent via form (rare but possible)
        if (isset($_POST['json']) && is_string($_POST['json'])) {
            $json = json_decode($_POST['json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $this->body = array_merge($this->body, $this->sanitizeArray($json));
            }
        }
    }

    /**
     * Read raw php://input only once
     *
     * @return string
     */
    private function getRawInput(): string
    {
        static $input = null;
        if ($input === null) {
            $input = file_get_contents('php://input') ?: '';
        }
        return $input;
    }

    /**
     * Get all HTTP headers (polyfill for getallheaders if needed)
     *
     * @return array
     */
    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }

        return $this->sanitizeHeaders($headers);
    }

    /**
     * Sanitize array values (recursive)
     *
     * @param array $data
     * @return array
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize header values (less aggressive than body)
     *
     * @param array $headers
     * @return array
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        foreach ($headers as $key => $value) {
            $sanitized[$key] = trim(strip_tags($value));
        }
        return $sanitized;
    }

    /**
     * Get the HTTP request method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Check if request uses specific method
     *
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    /**
     * Get the request URI (path only, no query string)
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get all input data (body + query)
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Get a single query parameter
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * Get a single body parameter
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function body(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * Get JSON-decoded body (returns array or empty array if invalid/not JSON)
     *
     * @return array
     */
    public function json(): array
    {
        return $this->jsonData ?? [];
    }

    /**
     * Get any input (body first, then query)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get a request header
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        // Normalize header key (case-insensitive)
        $normalized = array_change_key_case($this->headers, CASE_LOWER);
        $keyLower = strtolower($key);
        return $normalized[$keyLower] ?? $default;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /*
     * Usage example in a controller:
     *
     * $request = new Request();
     *
     * if ($request->isMethod('POST')) {
     *     $data = $request->all();
     *     $userId = $request->input('user_id');
     *     $email = $request->body('email');
     *     $token = $request->header('Authorization');
     *
     *     $productId = $request->query('id');
     *     $payload = $request->json();
     * }
     */
}