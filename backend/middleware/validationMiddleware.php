<?php
/**
 * Food Ordering System - Input Validation Middleware
 * Validates incoming request data (body + query) against defined rules.
 *
 * Stops the request and returns structured validation errors if any rule fails.
 * Passes the request forward only when all validations pass.
 *
 * @package FoodOrderingSystem
 * @subpackage Middleware
 */
declare(strict_types=1);

class ValidationMiddleware extends Middleware
{
    /** @var array<string, string> Validation rules: field => rules|pipe|separated */
    private array $rules;

    /**
     * Constructor - receives validation rules
     *
     * @param array<string, string> $rules Associative array of field => validation rules
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Handle request validation
     *
     * @param Request  $request Incoming request
     * @param callable $next    Next middleware or controller action
     * @return mixed            Response on validation failure, or result of $next on success
     */
    public function handle(Request $request, callable $next)
    {
        $errors = [];

        // Merge body and query parameters (body takes precedence)
        $input = array_merge($request->query(), $request->body());

        foreach ($this->rules as $field => $ruleString) {
            $rules = $this->parseRules($ruleString);
            $value = $input[$field] ?? null;

            foreach ($rules as $rule) {
                $ruleResult = $this->validateRule($field, $value, $rule, $input);

                if ($ruleResult !== true) {
                    $errors[$field][] = $ruleResult;
                }
            }
        }

        // If there are validation errors → stop and return them
        if (!empty($errors)) {
            $formattedErrors = [];
            foreach ($errors as $field => $messages) {
                $formattedErrors[$field] = implode(', ', $messages);
            }

            return Response::validation($formattedErrors, 'Validation failed');
        }

        // All validations passed → continue
        return $this->next($request, $next);
    }

    /**
     * Parse pipe-separated rules string into array
     *
     * @param string $ruleString e.g. "required|email|min:8"
     * @return array
     */
    private function parseRules(string $ruleString): array
    {
        return array_map('trim', explode('|', $ruleString));
    }

    /**
     * Validate a single rule against a field value
     *
     * @param string      $field Field name
     * @param mixed       $value Input value
     * @param string      $rule  Rule name or rule:param
     * @param array       $input Full input array (for context if needed)
     * @return true|string true on success, error message on failure
     */
    private function validateRule(string $field, $value, string $rule, array $input): true|string
    {
        // Handle rules with parameters (min:6, max:255, etc.)
        if (str_contains($rule, ':')) {
            [$ruleName, $param] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $param = null;
        }

        return match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'optional' => true, // explicitly optional - always pass
            'string'   => $this->validateString($field, $value),
            'email'    => $this->validateEmail($field, $value),
            'numeric'  => $this->validateNumeric($field, $value),
            'min'      => $this->validateMin($field, $value, $param),
            'max'      => $this->validateMax($field, $value, $param),
            default    => true, // unknown rule = pass (you can log this in production)
        };
    }

    private function validateRequired(string $field, $value): true|string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "$field is required";
        }
        return true;
    }

    private function validateString(string $field, $value): true|string
    {
        if ($value !== null && !is_string($value)) {
            return "$field must be a string";
        }
        return true;
    }

    private function validateEmail(string $field, $value): true|string
    {
        if ($value === null) {
            return true; // let 'required' handle null
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The $field must be a valid email address";
        }
        return true;
    }

    private function validateNumeric(string $field, $value): true|string
    {
        if ($value === null) {
            return true;
        }

        if (!is_numeric($value)) {
            return "The $field must be numeric";
        }
        return true;
    }

    private function validateMin(string $field, $value, ?string $param): true|string
    {
        if ($value === null || $param === null) {
            return true;
        }

        $min = (int)$param;

        if (is_string($value) && strlen($value) < $min) {
            return "The $field must be at least $min characters";
        }

        if (is_numeric($value) && $value < $min) {
            return "The $field must be at least $min";
        }

        return true;
    }

    private function validateMax(string $field, $value, ?string $param): true|string
    {
        if ($value === null || $param === null) {
            return true;
        }

        $max = (int)$param;

        if (is_string($value) && strlen($value) > $max) {
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
     * // Login validation
     * $validation = new ValidationMiddleware([
     *     'email'    => 'required|email',
     *     'password' => 'required|min:6'
     * ]);
     *
     * // Order creation
     * $validation = new ValidationMiddleware([
     *     'address_id'    => 'required|numeric',
     *     'payment_method'=> 'required|in:cash,card,mobile_money',
     *     'items'         => 'required|array',
     *     'items.*.id'    => 'required|numeric',
     *     'items.*.qty'   => 'required|numeric|min:1'
     * ]);
     *
     * // Using in middleware pipeline
     * Middleware::run(
     *     [
     *         new AuthMiddleware(),
     *         new ValidationMiddleware([
     *             'name' => 'required|string|max:120',
     *             'phone' => 'required|string|min:9|max:15'
     *         ]),
     *         new RoleMiddleware('admin')
     *     ],
     *     $request,
     *     function ($req) {
     *         return (new UserController())->updateProfile($req);
     *     }
     * );
     */
}