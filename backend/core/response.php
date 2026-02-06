<?php
/**
 * Food Ordering System - HTTP Response Helper
 * Standardized JSON API response handler for RESTful endpoints
 *
 * Ensures consistent response format, proper status codes, and safe JSON output.
 * All API responses (success and error) follow a predictable structure.
 *
 * @package FoodOrderingSystem
 * @subpackage Core
 */
class Response
{
    /**
     * Default response structure keys
     */
    private const KEY_SUCCESS = 'success';
    private const KEY_MESSAGE = 'message';
    private const KEY_DATA    = 'data';
    private const KEY_ERRORS  = 'errors';

    /**
     * Send a raw JSON response with custom status code
     *
     * @param array $data       The payload to encode (will be merged with defaults)
     * @param int   $statusCode HTTP status code (default 200)
     * @return never
     */
    public static function json(array $data, int $statusCode = 200): never
    {
        // Prevent sending headers if already sent (CLI/testing safety)
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        // Ensure success key exists (default to true if not explicitly set)
        if (!isset($data[self::KEY_SUCCESS])) {
            $data[self::KEY_SUCCESS] = ($statusCode >= 200 && $statusCode < 300);
        }

        // Encode with safety flags
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        // Fallback if json_encode fails (very rare with valid UTF-8)
        if ($json === false) {
            $json = json_encode([
                self::KEY_SUCCESS => false,
                self::KEY_MESSAGE => 'Server encountered an error while preparing response'
            ]);
        }

        echo $json;
        exit(0);
    }

    /**
     * Send a successful response
     *
     * @param mixed  $data    Data payload (array/object/scalar)
     * @param string $message Optional success message
     * @param int    $status  HTTP status (200, 201, etc.)
     * @return never
     */
    public static function success($data = [], string $message = 'Success', int $status = 200): never
    {
        self::json([
            self::KEY_SUCCESS => true,
            self::KEY_MESSAGE => $message,
            self::KEY_DATA    => $data
        ], $status);
    }

    /**
     * Send a 201 Created response
     *
     * @param mixed  $data    Usually the newly created resource
     * @param string $message Optional message
     * @return never
     */
    public static function created($data = [], string $message = 'Created successfully'): never
    {
        self::success($data, $message, 201);
    }

    /**
     * Send an error response
     *
     * @param string $message   Main error message
     * @param int    $status    HTTP status code (4xx or 5xx)
     * @param array  $errors    Optional detailed validation/field errors
     * @return never
     */
    public static function error(string $message = 'Error', int $status = 400, $errors = []): never
    {
        $payload = [
            self::KEY_SUCCESS => false,
            self::KEY_MESSAGE => $message
        ];

        if (!empty($errors)) {
            $payload[self::KEY_ERRORS] = $errors;
        }

        self::json($payload, $status);
    }

    /**
     * 404 Not Found response
     *
     * @param string $message Custom message (optional)
     * @return never
     */
    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error($message, 404);
    }

    /**
     * 401 Unauthorized response
     *
     * @param string $message Custom message (optional)
     * @return never
     */
    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    /**
     * 403 Forbidden response
     *
     * @param string $message Custom message (optional)
     * @return never
     */
    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    /**
     * 422 Unprocessable Entity - Validation failed
     *
     * @param array  $errors  Associative array of field => error messages
     * @param string $message Optional general message
     * @return never
     */
    public static function validation(array $errors, string $message = 'Validation failed'): never
    {
        self::error($message, 422, $errors);
    }

    /**
     * 500 Internal Server Error
     * Use only when you cannot handle the error gracefully elsewhere
     *
     * @param string $message Optional (keep generic in production)
     * @return never
     */
    public static function serverError(string $message = 'Internal server error'): never
    {
        self::error($message, 500);
    }

    /*
     * Typical usage in controllers:
     *
     * // Success examples
     * Response::success($products, 'Products retrieved successfully');
     * Response::created($newOrder, 'Order placed successfully');
     *
     * // Error examples
     * Response::error('Invalid credentials', 401);
     * Response::notFound('Product not found');
     * Response::unauthorized('Please login to continue');
     *
     * // Validation example
     * if (!$validator->passes()) {
     *     Response::validation($validator->errors());
     * }
     */
}