<?php
/**
 * Food Ordering System - Password Security Utilities
 * Secure password hashing, verification, and strength checking using PHP's native functions.
 *
 * This file provides best-practice password handling for user registration, login,
 * and password reset flows. Always use these functions instead of custom hashing.
 *
 * @package FoodOrderingSystem
 * @subpackage Utils
 */

declare(strict_types=1);

/**
 * Create a secure password hash using PHP's password_hash()
 *
 * Uses PASSWORD_DEFAULT algorithm (currently bcrypt, may upgrade in future PHP versions).
 * Returns a string suitable for storing in the database.
 *
 * @param string $password The plain-text password to hash
 * @return string The hashed password
 * @throws RuntimeException If hashing fails (very rare)
 */
function hashPassword(string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($hash === false) {
        throw new RuntimeException('Password hashing failed');
    }

    return $hash;
}

/**
 * Verify a plain password against a stored hash
 *
 * Uses PHP's password_verify() which is timing-attack safe.
 *
 * @param string $password Plain-text password to check
 * @param string $hash     Stored hash from database
 * @return bool            True if password matches, false otherwise
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check if a password meets minimum strength requirements
 *
 * Current policy (adjust as needed):
 * - At least 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 *
 * @param string $password The password to validate
 * @return bool            True if password is strong enough, false otherwise
 */
function isStrongPassword(string $password): bool
{
    // Minimum length
    if (strlen($password) < 8) {
        return false;
    }

    // Contains at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Contains at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Contains at least one digit
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // Contains at least one special character
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) {
        return false;
    }

    return true;
}

/**
 * Optional: Get a human-readable message when password is weak
 * Useful for returning specific validation feedback
 *
 * @param string $password
 * @return string|null Error message or null if password is strong
 */
function getPasswordStrengthError(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number';
    }

    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) {
        return 'Password must contain at least one special character';
    }

    return null;
}

/*
 * Typical usage examples:
 *
 * // In registration (AuthController::register)
 * $plainPassword = $request->body('password');
 *
 * if (!isStrongPassword($plainPassword)) {
 *     $error = getPasswordStrengthError($plainPassword);
 *     Response::validation(['password' => $error ?? 'Password is too weak']);
 * }
 *
 * $hashedPassword = hashPassword($plainPassword);
 * // Save $hashedPassword to users table
 *
 * // In login (AuthController::login)
 * $user = User::findByEmail($email);
 * if ($user && verifyPassword($request->body('password'), $user['password_hash'])) {
 *     // Login successful → generate JWT
 * } else {
 *     Response::unauthorized('Invalid email or password');
 * }
 */