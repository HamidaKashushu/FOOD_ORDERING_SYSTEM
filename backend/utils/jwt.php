<?php
/**
 * Food Ordering System - JWT Utility Functions
 * Secure generation, validation, and decoding of JSON Web Tokens (HS256)
 *
 * Used for stateless authentication in REST API.
 * Integrates with AuthMiddleware for token verification.
 *
 * SECURITY NOTE:
 * - Change JWT_SECRET_KEY to a strong, random, unique value in production!
 * - Never commit the real secret to version control
 * - Store in environment variables or secure config file
 *
 * @package FoodOrderingSystem
 * @subpackage Utils
 */

declare(strict_types=1);

// ────────────────────────────────────────────────
// CONFIGURATION
// ────────────────────────────────────────────────
/**
 * IMPORTANT: Replace this with a strong, randomly generated key (at least 32 characters)
 * Use: bin2hex(random_bytes(32)) or similar to generate one
 * Best: store in .env or server environment variable
 */
define('JWT_SECRET_KEY', 'change_this_to_a_very_strong_random_secret_key_32_chars_or_more');

/**
 * Default token lifetime in seconds (1 hour = 3600)
 */
define('JWT_DEFAULT_EXPIRY', 3600);

// Supported algorithm (only HS256 implemented)
const JWT_ALGORITHM = 'HS256';

/**
 * Generate a signed JWT token
 *
 * @param array $payload Custom claims (user_id, role, email, etc.)
 *                       Do NOT include sensitive data!
 * @param int   $expiry  Token lifetime in seconds (default 1 hour)
 * @return string Signed JWT string
 * @throws Exception If base64 encoding fails or secret is empty
 */
function generateToken(array $payload, int $expiry = JWT_DEFAULT_EXPIRY): string
{
    if (empty(JWT_SECRET_KEY)) {
        throw new RuntimeException('JWT secret key is not configured');
    }

    $header = [
        'typ' => 'JWT',
        'alg' => JWT_ALGORITHM
    ];

    $now = time();
    $payload = array_merge([
        'iat' => $now,                    // Issued at
        'exp' => $now + $expiry,          // Expiration time
    ], $payload);

    // Encode header and payload (URL-safe base64)
    $base64Header  = base64UrlEncode(json_encode($header));
    $base64Payload = base64UrlEncode(json_encode($payload));

    // Create signature
    $signature = hash_hmac(
        'sha256',
        "$base64Header.$base64Payload",
        JWT_SECRET_KEY,
        true
    );

    $base64Signature = base64UrlEncode($signature);

    return "$base64Header.$base64Payload.$base64Signature";
}

/**
 * Validate JWT token (signature + expiration)
 *
 * @param string $token The JWT string from Authorization header
 * @return bool         True if token is valid and not expired
 */
function validateToken(string $token): bool
{
    $payload = decodeToken($token);
    return $payload !== null;
}

/**
 * Decode and verify JWT token, return payload if valid
 *
 * @param string $token JWT string
 * @return array|null   Decoded payload or null if invalid/expired
 */
function decodeToken(string $token): ?array
{
    if (empty(JWT_SECRET_KEY)) {
        return null;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    // Verify algorithm (only HS256 supported)
    $header = json_decode(base64UrlDecode($headerEncoded), true);
    if (!$header || !isset($header['alg']) || $header['alg'] !== JWT_ALGORITHM) {
        return null;
    }

    // Verify signature
    $expectedSignature = hash_hmac(
        'sha256',
        "$headerEncoded.$payloadEncoded",
        JWT_SECRET_KEY,
        true
    );

    if (!hash_equals($expectedSignature, base64UrlDecode($signatureEncoded))) {
        return null; // Invalid signature (timing-safe comparison)
    }

    // Decode payload
    $payload = json_decode(base64UrlDecode($payloadEncoded), true);
    if (!$payload || !is_array($payload)) {
        return null;
    }

    // Check expiration
    if (isset($payload['exp']) && time() > $payload['exp']) {
        return null; // Token expired
    }

    // Optional: check issued-at (not before)
    if (isset($payload['iat']) && time() < $payload['iat']) {
        return null;
    }

    return $payload;
}

/**
 * URL-safe base64 encode (JWT standard)
 *
 * @param string $data
 * @return string
 */
function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * URL-safe base64 decode (JWT standard)
 *
 * @param string $input
 * @return string
 */
function base64UrlDecode(string $input): string
{
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $input .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

/*
 * Usage examples:
 *
 * // In AuthController::login()
 * $user = User::findByEmail($email);
 * if ($user && password_verify($password, $user['password_hash'])) {
 *     $token = generateToken([
 *         'sub'   => $user['id'],
 *         'role'  => $user['role_name'],
 *         'email' => $user['email'],
 *         'name'  => $user['full_name']
 *     ], 3600 * 24); // 24 hours for "remember me" option
 *
 *     Response::success(['token' => $token, 'user' => $user]);
 * }
 *
 * // In AuthMiddleware
 * $authHeader = $request->header('Authorization');
 * if (preg_match('/^Bearer\s+(.+)$/', $authHeader, $matches)) {
 *     $token = $matches[1];
 *     $payload = decodeToken($token);
 *     if ($payload) {
 *         $request->user = $payload; // or fetch full user from DB
 *         return $next($request);
 *     }
 * }
 * return Response::unauthorized('Invalid or expired token');
 */