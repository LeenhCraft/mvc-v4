<?php

declare(strict_types=1);

namespace App\Services\Csrf;

use App\Services\Session\SessionManager;

/**
 * CSRF Protection Service
 *
 * Generates and validates CSRF tokens
 */
class CsrfProtection
{
    private const SESSION_KEY = '_csrf_tokens';
    private const TOKEN_LENGTH = 32;

    private SessionManager $session;
    private string $tokenName;
    private string $tokenValue;

    public function __construct(SessionManager $session, string $tokenName = 'csrf_token')
    {
        $this->session = $session;
        $this->tokenName = $tokenName;
        $this->tokenValue = $this->generateToken();
    }

    /**
     * Generate new CSRF token
     *
     * @return string
     */
    public function generateToken(): string
    {
        // Get existing tokens
        $tokens = $this->session->get(self::SESSION_KEY, []);

        // Generate new token
        try {
            $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        } catch (\Exception $e) {
            $token = bin2hex(openssl_random_pseudo_bytes(self::TOKEN_LENGTH));
        }

        // Store token with timestamp
        $tokens[$token] = time();

        // Clean old tokens (older than 1 hour)
        $tokens = array_filter($tokens, function ($timestamp) {
            return (time() - $timestamp) < 3600;
        });

        // Limit to 10 most recent tokens
        if (count($tokens) > 10) {
            arsort($tokens);
            $tokens = array_slice($tokens, 0, 10, true);
        }

        $this->session->set(self::SESSION_KEY, $tokens);

        return $token;
    }

    /**
     * Validate CSRF token
     *
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $tokens = $this->session->get(self::SESSION_KEY, []);

        // Check if token exists and is not expired
        if (isset($tokens[$token])) {
            $timestamp = $tokens[$token];
            $age = time() - $timestamp;

            // Token valid for 1 hour
            if ($age < 3600) {
                return true;
            }

            // Remove expired token
            unset($tokens[$token]);
            $this->session->set(self::SESSION_KEY, $tokens);
        }

        return false;
    }

    /**
     * Get token name
     *
     * @return string
     */
    public function getTokenName(): string
    {
        return $this->tokenName;
    }

    /**
     * Get token value
     *
     * @return string
     */
    public function getTokenValue(): string
    {
        return $this->tokenValue;
    }

    /**
     * Get token as hidden input HTML
     *
     * @return string
     */
    public function getTokenInput(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($this->tokenName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->tokenValue, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get tokens as array for meta tags
     *
     * @return array{name: string, value: string}
     */
    public function getTokens(): array
    {
        return [
            'name' => $this->tokenName,
            'value' => $this->tokenValue,
        ];
    }
}
