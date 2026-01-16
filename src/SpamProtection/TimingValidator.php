<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\SpamProtection;

use Symfony\Component\Form\FormInterface;

/**
 * Timing validator - checks if form was submitted too quickly (bot behavior)
 */
class TimingValidator
{
    public function __construct(
        private int $minSubmitTime = 3, // seconds
        private string $timingFieldName = '_form_token_time'
    ) {
    }

    /**
     * Validate submission timing
     * 
     * @param FormInterface $form The form to check
     * @return bool True if validation passes (enough time elapsed)
     */
    public function validate(FormInterface $form): bool
    {
        if (!$form->has($this->timingFieldName)) {
            return true;
        }

        $tokenTime = $form->get($this->timingFieldName)->getData();
        
        if (!$tokenTime || !is_numeric($tokenTime)) {
            return false;
        }

        $formLoadTime = (int) $tokenTime;
        $currentTime = time();
        $elapsedTime = $currentTime - $formLoadTime;

        // Check if enough time has passed
        return $elapsedTime >= $this->minSubmitTime;
    }

    public function getMinSubmitTime(): int
    {
        return $this->minSubmitTime;
    }
}
