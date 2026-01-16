<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\SpamProtection;

use Symfony\Component\Form\FormInterface;

/**
 * Honeypot validator - checks if honeypot field was filled (bot behavior)
 */
class HoneypotValidator
{
    public function __construct(
        private string $honeypotFieldName = 'email_confirm'
    ) {
    }

    /**
     * Validate that honeypot field is empty
     * 
     * @param FormInterface $form The form to check
     * @return bool True if validation passes (field is empty)
     */
    public function validate(FormInterface $form): bool
    {
        if (!$form->has($this->honeypotFieldName)) {
            return true;
        }

        $honeypotValue = $form->get($this->honeypotFieldName)->getData();
        
        // Honeypot should be empty - if filled, it's likely a bot
        return empty($honeypotValue);
    }
}
