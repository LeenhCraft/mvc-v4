<?php

declare(strict_types=1);

namespace App\Validation;

use Respect\Validation\Validator as V;
use Respect\Validation\Exceptions\NestedValidationException;

/**
 * Input Validator
 *
 * Wrapper around Respect\Validation for input validation
 */
class Validator
{
    /**
     * Validate data against rules
     *
     * @param array<string, mixed> $data
     * @param array<string, \Respect\Validation\Validatable> $rules
     * @param bool $throw Whether to throw exception on failure
     * @return bool
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, bool $throw = true): bool
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            try {
                $rule->assert($value);
            } catch (NestedValidationException $e) {
                $errors[$field] = $e->getMessages();
            }
        }

        if (!empty($errors)) {
            if ($throw) {
                throw new ValidationException($errors);
            }
            return false;
        }

        return true;
    }

    /**
     * Validate data and return errors without throwing
     *
     * @param array<string, mixed> $data
     * @param array<string, \Respect\Validation\Validatable> $rules
     * @return array<string, array<int, string>>
     */
    public static function check(array $data, array $rules): array
    {
        try {
            self::validate($data, $rules, true);
            return [];
        } catch (ValidationException $e) {
            return $e->getErrors();
        }
    }

    /**
     * Common validation rules for reuse
     */

    /**
     * Email validation rule
     */
    public static function email(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::email();
        return $required ? $rule->notEmpty() : $rule->optional(V::email());
    }

    /**
     * String validation rule
     *
     * @param int|null $minLength
     * @param int|null $maxLength
     */
    public static function string(?int $minLength = null, ?int $maxLength = null, bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::stringType();

        if ($minLength !== null) {
            $rule = $rule->length($minLength, $maxLength);
        }

        return $required ? $rule->notEmpty() : $rule->optional($rule);
    }

    /**
     * Integer validation rule
     */
    public static function integer(bool $required = true, ?int $min = null, ?int $max = null): \Respect\Validation\Validatable
    {
        $rule = V::intType();

        if ($min !== null && $max !== null) {
            $rule = $rule->between($min, $max);
        } elseif ($min !== null) {
            $rule = $rule->min($min);
        } elseif ($max !== null) {
            $rule = $rule->max($max);
        }

        return $required ? $rule : V::optional($rule);
    }

    /**
     * Boolean validation rule
     */
    public static function boolean(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::boolType();
        return $required ? $rule : V::optional($rule);
    }

    /**
     * URL validation rule
     */
    public static function url(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::url();
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Date validation rule
     */
    public static function date(string $format = 'Y-m-d', bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::date($format);
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Array validation rule
     */
    public static function array(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::arrayType();
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * In (enum) validation rule
     *
     * @param array<int, mixed> $values
     */
    public static function in(array $values, bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::in($values);
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Numeric validation rule
     */
    public static function numeric(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::numeric();
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Alpha (letters only) validation rule
     */
    public static function alpha(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::alpha();
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Alphanumeric validation rule
     */
    public static function alnum(bool $required = true): \Respect\Validation\Validatable
    {
        $rule = V::alnum();
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Phone validation rule (basic)
     */
    public static function phone(bool $required = true): \Respect\Validation\Validatable
    {
        // Basic phone validation - adjust regex as needed
        $rule = V::regex('/^[\d\s\-\+\(\)]+$/');
        return $required ? $rule->notEmpty() : V::optional($rule);
    }

    /**
     * Password validation rule (strong password)
     *
     * @param int $minLength Minimum length (default 8)
     */
    public static function password(int $minLength = 8): \Respect\Validation\Validatable
    {
        return V::allOf(
            V::stringType(),
            V::length($minLength, null),
            V::regex('/[A-Z]/')->setTemplate('Must contain at least one uppercase letter'),
            V::regex('/[a-z]/')->setTemplate('Must contain at least one lowercase letter'),
            V::regex('/[0-9]/')->setTemplate('Must contain at least one number')
        )->notEmpty();
    }

    /**
     * Unique validation rule (for database)
     *
     * @param string $table
     * @param string $column
     * @param int|null $excludeId
     */
    public static function unique(string $table, string $column, ?int $excludeId = null): \Respect\Validation\Validatable
    {
        return V::callback(function ($value) use ($table, $column, $excludeId) {
            $query = \Illuminate\Database\Capsule\Manager::table($table)
                ->where($column, $value);

            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }

            return !$query->exists();
        })->setTemplate('This {{name}} is already taken');
    }

    /**
     * Exists validation rule (for database foreign keys)
     *
     * @param string $table
     * @param string $column
     */
    public static function exists(string $table, string $column = 'id'): \Respect\Validation\Validatable
    {
        return V::callback(function ($value) use ($table, $column) {
            return \Illuminate\Database\Capsule\Manager::table($table)
                ->where($column, $value)
                ->exists();
        })->setTemplate('The selected {{name}} does not exist');
    }
}
