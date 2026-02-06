<?php
/**
 * Food Ordering System - Input Validation Utilities
 * Reusable validation functions for request data, forms, and API inputs.
 *
 * Designed to be used standalone in controllers or inside ValidationMiddleware.
 * Returns structured error array compatible with Response::validation().
 *
 * @package FoodOrderingSystem
 * @subpackage Utils
 */

declare(strict_types=1);

/**
 * Validate input data against a set of rules
 *
 * @param array $data  Input data (e.g. $request->all(), $_POST, $_GET)
 * @param array $rules Associative array: 'field' => 'rule1|rule2|rule3'
 *                     Example: ['email' => 'required|email', 'age' => 'numeric|min:18']
 * @return array       Empty array if valid, or ['field' => 'Error message'] on failure
 */
function validate(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $fieldRules = array_map('trim', explode('|', $ruleString));
        $value = $data[$field] ?? null;

        foreach ($fieldRules as $rule) {
            if ($rule === 'optional' && $value === null) {
                continue 2; // skip all rules for this field if optional and missing
            }

            $error = validateSingleRule($field, $value, $rule, $data);

            if ($error !== true) {
                $errors[$field] = $error;
                break; // stop on first error for this field
            }
        }
    }

    return $errors;
}

/**
 * Validate a single rule for a field
 *
 * @param string      $field Field name (for error messages)
 * @param mixed       $value The value to validate
 * @param string      $rule  Rule name or rule:param
 * @param array       $data  Full input array (for context if needed)
 * @return true|string true on success, error message string on failure
 */
function validateSingleRule(string $field, $value, string $rule, array $data): true|string
{
    [$ruleName, $param] = str_contains($rule, ':') 
        ? explode(':', $rule, 2) 
        : [$rule, null];

    return match (strtolower($ruleName)) {
        'required'  => isRequired($value) ? true : "$field is required",
        'string'    => is_string($value) ? true : "$field must be a string",
        'numeric'   => isNumeric($value) ? true : "$field must be a number",
        'email'     => isEmail((string)$value) ? true : "The $field must be a valid email address",
        'min'       => validateMin($value, (int)$param, $field),
        'max'       => validateMax($value, (int)$param, $field),
        'optional'  => true, // handled earlier
        default     => true, // unknown rule = pass (log in production if desired)
    };
}

/**
 * Check if value is present and not empty
 *
 * @param mixed $value
 * @return bool
 */
function isRequired($value): bool
{
    if ($value === null) {
        return false;
    }
    if (is_string($value)) {
        return trim($value) !== '';
    }
    if (is_array($value)) {
        return !empty($value);
    }
    return true;
}

/**
 * Check if value is a valid email address
 *
 * @param string $value
 * @return bool
 */
function isEmail(string $value): bool
{
    if ($value === '') {
        return true; // let 'required' handle empty
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if value is numeric (int, float, or numeric string)
 *
 * @param mixed $value
 * @return bool
 */
function isNumeric($value): bool
{
    return is_numeric($value);
}

/**
 * Validate minimum length/value
 *
 * @param mixed  $value
 * @param int    $min
 * @param string $field
 * @return true|string
 */
function validateMin($value, int $min, string $field): true|string
{
    if ($value === null) {
        return true;
    }

    if (is_string($value) && mb_strlen($value) < $min) {
        return "The $field must be at least $min characters";
    }

    if (is_numeric($value) && $value < $min) {
        return "The $field must be at least $min";
    }

    return true;
}

/**
 * Validate maximum length/value
 *
 * @param mixed  $value
 * @param int    $max
 * @param string $field
 * @return true|string
 */
function validateMax($value, int $max, string $field): true|string
{
    if ($value === null) {
        return true;
    }

    if (is_string($value) && mb_strlen($value) > $max) {
        return "The $field may not be greater than $max characters";
    }

    if (is_numeric($value) && $value > $max) {
        return "The $field may not be greater than $max";
    }

    return true;
}

/*
 * Usage examples:
 *
 * // In controller or middleware
 * $input = $request->all();
 *
 * $errors = validate($input, [
 *     'full_name'     => 'required|string|min:2|max:120',
 *     'email'         => 'required|email',
 *     'phone'         => 'required|string|min:9|max:15',
 *     'password'      => 'required|string|min:8',
 *     'age'           => 'numeric|min:18',
 *     'notes'         => 'optional|string|max:500'
 * ]);
 *
 * if (!empty($errors)) {
 *     Response::validation($errors, 'Please correct the following errors');
 *     // exit handled by Response class
 * }
 *
 * // Single field quick check
 * if (!isEmail($request->input('email'))) {
 *     Response::error('Invalid email format', 422);
 * }
 */