<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig extension to expose bundle configuration to templates
 */
class ContactUsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private array $templates,
        private array $design
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'contact_us_config' => [
                'templates' => $this->templates,
                'design' => $this->design,
            ],
        ];
    }
}
