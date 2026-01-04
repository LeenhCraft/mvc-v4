<?php

declare(strict_types=1);

namespace App\Services\Csrf;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * CSRF Twig Extension
 *
 * Adds CSRF functions and globals to Twig templates
 */
class CsrfTwigExtension extends AbstractExtension implements GlobalsInterface
{
    private CsrfProtection $csrf;

    public function __construct(CsrfProtection $csrf)
    {
        $this->csrf = $csrf;
    }

    public function getGlobals(): array
    {
        return [
            'csrf_token_name' => $this->csrf->getTokenName(),
            'csrf_token_value' => $this->csrf->getTokenValue(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('csrf_input', [$this, 'csrfInput'], ['is_safe' => ['html']]),
            new TwigFunction('csrf_meta', [$this, 'csrfMeta'], ['is_safe' => ['html']]),
            new TwigFunction('csrf_token_name', [$this, 'csrfTokenName']),
            new TwigFunction('csrf_token_value', [$this, 'csrfTokenValue']),
        ];
    }

    /**
     * Get CSRF hidden input field
     */
    public function csrfInput(): string
    {
        return $this->csrf->getTokenInput();
    }

    /**
     * Get CSRF meta tags for AJAX
     */
    public function csrfMeta(): string
    {
        $name = htmlspecialchars($this->csrf->getTokenName(), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($this->csrf->getTokenValue(), ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<meta name="csrf-token-name" content="%s">' . PHP_EOL .
            '<meta name="csrf-token-value" content="%s">',
            $name,
            $value
        );
    }

    /**
     * Get CSRF token name
     */
    public function csrfTokenName(): string
    {
        return $this->csrf->getTokenName();
    }

    /**
     * Get CSRF token value
     */
    public function csrfTokenValue(): string
    {
        return $this->csrf->getTokenValue();
    }
}
