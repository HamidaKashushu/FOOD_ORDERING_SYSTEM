<?php
/**
 * Food Ordering System - Global Utility Helpers
 * Collection of reusable, secure helper functions for controllers, models, middleware, etc.
 *
 * @package FoodOrderingSystem
 * @subpackage Utils
 */

declare(strict_types=1);

/**
 * Generate a cryptographically secure random alphanumeric string
 *
 * @param int $length Length of the string to generate (default 32)
 * @return string
 * @throws Exception If random_bytes fails
 */
function generateRandomString(int $length = 32): string
{
    if ($length < 1) {
        $length = 32;
    }

    $bytes = random_bytes((int)ceil($length * 5 / 8)); // enough entropy
    $string = bin2hex($bytes);

    return substr($string, 0, $length);
}

/**
 * Format a price with 2 decimal places and currency symbol
 * Uses current locale formatting where possible
 *
 * @param float $amount The amount to format
 * @param string $currencySymbol Default: TZS (Tanzanian Shilling)
 * @return string Formatted price (e.g., "TZS 12,500.00")
 */
function formatPrice(float $amount, string $currencySymbol = 'TZS'): string
{
    $formatted = number_format($amount, 2, '.', ',');
    return $currencySymbol . ' ' . $formatted;
}

/**
 * Sanitize a string input to prevent XSS and clean unwanted characters
 *
 * @param string $input The input string to sanitize
 * @return string Cleaned string
 */
function sanitizeString(string $input): string
{
    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

    return $input;
}

/**
 * Send a JSON response and terminate script execution
 *
 * @param array $data      The data to encode as JSON
 * @param int   $status    HTTP status code (default 200)
 * @return never
 */
function responseJson(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
    }

    // Ensure consistent success flag if not explicitly set
    if (!isset($data['success'])) {
        $data['success'] = ($status >= 200 && $status < 300);
    }

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    // Fallback in case of encoding failure
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'Server error while preparing response'
        ]);
    }

    echo $json;
    exit(0);
}

/**
 * Get current datetime in MySQL-compatible format
 *
 * @return string 'Y-m-d H:i:s'
 */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Validate email address format
 *
 * @param string $email Email to validate
 * @return bool
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Perform a safe HTTP redirect
 *
 * @param string $url      Target URL
 * @param int    $status   HTTP status code (301 or 302)
 * @return never
 */
function redirect(string $url, int $status = 302): never
{
    if (!headers_sent()) {
        if ($status === 301) {
            header('HTTP/1.1 301 Moved Permanently');
        } else {
            header('HTTP/1.1 302 Found');
        }
        header('Location: ' . $url);
    } else {
        // Fallback if headers already sent
        echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    }

    exit(0);
}

/*
 * Usage examples:
 *
 * // Generate secure token
 * $resetToken = generateRandomString(40);
 *
 * // Format price for Tanzania
 * echo formatPrice(12500.50);           // "TZS 12,500.50"
 *
 * // Clean user input
 * $safeName = sanitizeString($_POST['full_name'] ?? '');
 *
 * // Quick JSON response in controller
 * if ($loginFailed) {
 *     responseJson(['success' => false, 'message' => 'Invalid credentials'], 401);
 * }
 *
 * // Current timestamp for DB
 * $orderData = ['created_at' => now()];
 *
 * // Email check
 * if (!validateEmail($request->body('email'))) {
 *     responseJson(['success' => false, 'message' => 'Invalid email format'], 422);
 * }
 */