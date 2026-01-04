<?php

declare(strict_types=1);

namespace App\Services\Session;

/**
 * Session Manager
 *
 * Simple session management wrapper
 */
class SessionManager
{
    private bool $started = false;

    public function __construct()
    {
        $this->start();
    }

    /**
     * Start session if not already started
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        // Configure session settings
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if (isset($_ENV['SESSION_SECURE']) && $_ENV['SESSION_SECURE'] === 'true') {
            ini_set('session.cookie_secure', '1');
        }

        session_start();
        $this->started = true;
    }

    /**
     * Get value from session
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set value in session
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if key exists in session
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove key from session
     *
     * @param string $key
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Destroy session completely
     */
    public function destroy(): void
    {
        if ($this->started) {
            session_destroy();
            $this->started = false;
        }
    }

    /**
     * Regenerate session ID
     *
     * @param bool $deleteOldSession
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Get session ID
     *
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Flash message (available for one request)
     *
     * @param string $key
     * @param mixed $value
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get flash message and remove it
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Check if flash message exists
     *
     * @param string $key
     * @return bool
     */
    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }
}
