<?php

declare(strict_types=1);

namespace App\Validation;

use Exception;

/**
 * Validation Exception
 *
 * Thrown when input validation fails
 */
class ValidationException extends Exception
{
    /** @var array<string, array<int, string>> */
    private array $errors;

    /**
     * @param array<string, array<int, string>> $errors
     * @param string $message
     */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for each field
     *
     * @return array<string, string>
     */
    public function getFirstErrors(): array
    {
        $firstErrors = [];
        foreach ($this->errors as $field => $messages) {
            $firstErrors[$field] = $messages[0] ?? '';
        }
        return $firstErrors;
    }

    /**
     * Check if a specific field has errors
     *
     * @param string $field
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get errors for a specific field
     *
     * @param string $field
     * @return array<int, string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
