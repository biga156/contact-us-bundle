<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\SpamProtection;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Rate limiter for contact form submissions
 */
class ContactRateLimiter
{
    private RateLimiterFactory $limiterFactory;

    public function __construct(
        private RequestStack $requestStack,
        private int $limit = 3,
        private string $interval = '15 minutes'
    ) {
    }

    public function setLimiterFactory(RateLimiterFactory $limiterFactory): void
    {
        $this->limiterFactory = $limiterFactory;
    }

    /**
     * Check if submission is allowed
     * 
     * @return bool True if submission is allowed
     */
    public function isAllowed(): bool
    {
        if (!isset($this->limiterFactory)) {
            // If no factory is set, allow by default
            return true;
        }

        $limiter = $this->limiterFactory->create($this->getIdentifier());
        
        return $limiter->consume(1)->isAccepted();
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts(): int
    {
        if (!isset($this->limiterFactory)) {
            return $this->limit;
        }

        $limiter = $this->limiterFactory->create($this->getIdentifier());
        $limit = $limiter->consume(0);
        
        return $limit->getRemainingTokens();
    }

    /**
     * Get retry after timestamp
     */
    public function getRetryAfter(): ?\DateTimeImmutable
    {
        if (!isset($this->limiterFactory)) {
            return null;
        }

        $limiter = $this->limiterFactory->create($this->getIdentifier());
        $limit = $limiter->consume(0);
        
        return $limit->getRetryAfter();
    }

    /**
     * Get unique identifier for rate limiting (IP + session)
     */
    private function getIdentifier(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return 'unknown';
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $sessionId = $request->hasSession() ? $request->getSession()->getId() : '';
        
        return sprintf('contact_form_%s_%s', md5($ip), md5($sessionId));
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }
}
