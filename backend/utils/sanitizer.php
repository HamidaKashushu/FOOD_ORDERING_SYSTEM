
<?php
/**
 * Food Ordering System - Input Sanitization Utilities
 * Securely cleans user-provided input to prevent XSS, injection risks,
 * and unwanted characters before validation or storage.
 *
 * These functions should be used on all incoming user data (POST, JSON, query params).
 *
 * @package FoodOrderingSystem
 * @subpackage Utils
 */

declare(strict_types=1);

/**
 * Sanitize a single string input
 * Removes tags, trims whitespace, encodes special characters (XSS protection)
 *
 * @param string|null $input Input string (null-safe)
 * @return string Cleaned string
 */
function sanitizeString(?string $input): string
{
    if ($input === null) {
        return '';
    }

    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $input;
}

/**
 * Recursively sanitize all values in an array (including nested arrays)
 *
 * @param array $data Input array (associative or indexed)
 * @return array Sanitized array with same structure
 */
function sanitizeArray(array $data): array
{
    $sanitized = [];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value);
        } elseif (is_string($value)) {
            $sanitized[$key] = sanitizeString($value);
        } elseif (is_numeric($value)) {
            $sanitized[$key] = $value; // numbers are safe
        } else {
            $sanitized[$key] = $value; // preserve other types
        }
    }

    return $sanitized;
}

/**
 * Safely convert input to integer
 * Returns 0 if conversion fails
 *
 * @param mixed $input Any value
 * @return int
 */
function sanitizeInt(mixed $input): int
{
    if (is_int($input)) {
        return $input;
    }

    if (is_numeric($input)) {
        return (int)$input;
    }

    return 0;
}

/**
 * Safely convert input to float
 * Returns 0.0 if conversion fails
 *
 * @param mixed $input Any value
 * @return float
 */
function sanitizeFloat(mixed $input): float
{
    if (is_float($input) || is_int($input)) {
        return (float)$input;
    }

    if (is_numeric($input)) {
        return (float)$input;
    }

    return 0.0;
}

/**
 * Sanitize and normalize an email address
 * Trims, removes illegal characters, returns empty string if invalid
 *
 * @param string|null $email Input email
 * @return string Cleaned email or empty string
 */
function sanitizeEmail(?string $email): string
{
    if ($email === null) {
        return '';
    }

    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    // Remove any remaining invalid characters
    $email = preg_replace('/[^a-zA-Z0-9@._-]/', '', $email);

    return $email;
}

/**
 * Escape string for safe output in HTML/JSON context
 * (Use when you need to output data back to frontend in views/templates)
 *
 * @param string|null $input
 * @return string
 */
function escapeOutput(?string $input): string
{
    if ($input === null) {
        return '';
    }

    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/*
 * Usage examples:
 *
 * // In controller or middleware
 * $cleanName = sanitizeString($request->input('full_name'));
 * $cleanEmail = sanitizeEmail($request->body('email'));
 * $cleanPhone = sanitizeString($request->input('phone'));
 *
 * // Bulk sanitize entire request data
 * $safeData = sanitizeArray($request->all());
 *
 * // Numeric fields
 * $quantity = sanitizeInt($request->input('quantity'));
 * $price = sanitizeFloat($request->body('unit_price'));
 *
 * // In ValidationMiddleware or before saving to DB
 * $preparedData = [
 *     'name'    => sanitizeString($_POST['name'] ?? ''),
 *     'email'   => sanitizeEmail($_POST['email'] ?? ''),
 *     'address' => sanitizeString($_POST['address'] ?? ''),
 *     'items'   => sanitizeArray($_POST['items'] ?? []),
 * ];
 */