<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\SpamProtection;

/**
 * Null captcha validator - always passes (no captcha)
 */
class NullCaptchaValidator implements CaptchaValidatorInterface
{
    public function validate(string $response, ?string $remoteIp = null): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function getProvider(): string
    {
        return 'none';
    }
}
