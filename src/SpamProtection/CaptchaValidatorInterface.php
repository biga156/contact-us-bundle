<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\SpamProtection;

/**
 * Captcha validator interface
 */
interface CaptchaValidatorInterface
{
    /**
     * Validate captcha response
     * 
     * @param string $response The captcha response token/value
     * @param string|null $remoteIp Optional client IP address
     * @return bool True if validation passes
     */
    public function validate(string $response, ?string $remoteIp = null): bool;

    /**
     * Check if this validator is enabled/configured
     */
    public function isEnabled(): bool;

    /**
     * Get the provider name
     */
    public function getProvider(): string;
}
