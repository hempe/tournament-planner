<?php

declare(strict_types=1);

namespace TP\Core;

final class ValidationRule
{
    public function __construct(
        public readonly string $field,
        public readonly array $rules,
        public readonly string $message = ''
    ) {
    }
}

final class ValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message
    ) {
    }
}

final class ValidationResult
{
    /** @param ValidationError[] $errors */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = []
    ) {
    }

    /** @return string[] */
    public function getErrorMessages(): array
    {
        return array_map(fn(ValidationError $error) => $error->message, $this->errors);
    }

    /** @return array<string, string[]> */
    public function getErrorsByField(): array
    {
        $result = [];
        foreach ($this->errors as $error) {
            $result[$error->field][] = $error->message;
        }
        return $result;
    }
}

final class Validator
{
    /** @param ValidationRule[] $rules */
    public function validate(array $data, array $rules): ValidationResult
    {
        $errors = [];

        foreach ($rules as $rule) {
            $value = $data[$rule->field] ?? null;
            $fieldErrors = $this->validateField($rule->field, $value, $rule->rules);
            $errors = array_merge($errors, $fieldErrors);
        }

        return new ValidationResult(empty($errors), $errors);
    }

    /** @return ValidationError[] */
    private function validateField(string $field, mixed $value, array $rules): array
    {
        $errors = [];

        foreach ($rules as $ruleName => $ruleValue) {
            if (is_int($ruleName)) {
                $ruleName = $ruleValue;
                $ruleValue = true;
            }

            $error = $this->applyRule($field, $value, $ruleName, $ruleValue);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function applyRule(string $field, mixed $value, string $rule, mixed $parameter): ?ValidationError
    {
        return match ($rule) {
            'required' => $this->validateRequired($field, $value),
            'string' => $this->validateString($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'email' => $this->validateEmail($field, $value),
            'min' => $this->validateMin($field, $value, $parameter),
            'max' => $this->validateMax($field, $value, $parameter),
            'date' => $this->validateDate($field, $value),
            'boolean' => $this->validateBoolean($field, $value),
            'in' => $this->validateIn($field, $value, $parameter),
            default => null,
        };
    }

    private function validateRequired(string $field, mixed $value): ?ValidationError
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return new ValidationError($field, "The {$field} field is required.");
        }
        return null;
    }

    private function validateString(string $field, mixed $value): ?ValidationError
    {
        if ($value !== null && !is_string($value)) {
            return new ValidationError($field, "The {$field} field must be a string.");
        }
        return null;
    }

    private function validateInteger(string $field, mixed $value): ?ValidationError
    {
        if ($value !== null && !is_int($value) && !ctype_digit((string) $value)) {
            return new ValidationError($field, "The {$field} field must be an integer.");
        }
        return null;
    }

    private function validateEmail(string $field, mixed $value): ?ValidationError
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new ValidationError($field, "The {$field} field must be a valid email address.");
        }
        return null;
    }

    private function validateMin(string $field, mixed $value, int $min): ?ValidationError
    {
        if ($value === null)
            return null;

        if (is_string($value) && strlen($value) < $min) {
            return new ValidationError($field, "The {$field} field must be at least {$min} characters.");
        }

        if (is_numeric($value) && $value < $min) {
            return new ValidationError($field, "The {$field} field must be at least {$min}.");
        }

        return null;
    }

    private function validateMax(string $field, mixed $value, int $max): ?ValidationError
    {
        if ($value === null)
            return null;

        if (is_string($value) && strlen($value) > $max) {
            return new ValidationError($field, "The {$field} field must not exceed {$max} characters.");
        }

        if (is_numeric($value) && $value > $max) {
            return new ValidationError($field, "The {$field} field must not exceed {$max}.");
        }

        return null;
    }

    private function validateDate(string $field, mixed $value): ?ValidationError
    {
        if ($value === null)
            return null;

        if (!is_string($value) || !strtotime($value)) {
            return new ValidationError($field, "The {$field} field must be a valid date.");
        }

        return null;
    }

    private function validateBoolean(string $field, mixed $value): ?ValidationError
    {
        if ($value !== null && !is_bool($value) && !in_array($value, ['0', '1', 'true', 'false', 0, 1], true)) {
            return new ValidationError($field, "The {$field} field must be a boolean.");
        }
        return null;
    }

    private function validateIn(string $field, mixed $value, array $allowed): ?ValidationError
    {
        if ($value !== null && !in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            return new ValidationError($field, "The {$field} field must be one of: {$allowedStr}.");
        }
        return null;
    }
}