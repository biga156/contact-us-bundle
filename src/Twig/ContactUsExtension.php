<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

/**
 * Twig extension to expose bundle configuration to templates
 * and provide translation fallback mechanism
 */
class ContactUsExtension extends AbstractExtension implements GlobalsInterface
{
    private bool $translationEnabled;

    /**
     * @param array<string, string> $templates
     * @param array<string, bool|string|null> $design
     * @param array<string, string|bool> $translation
     */
    public function __construct(
        private array $templates,
        private array $design,
        private array $translation,
        private ?TranslatorInterface $translator = null
    ) {
        // Auto-detect if translation should be enabled
        $this->translationEnabled = match ($this->translation['enabled'] ?? 'auto') {
            'true' => true,
            'false' => false,
            'auto' => $this->translator !== null,
            default => $this->translator !== null,
        };
    }

    public function getGlobals(): array
    {
        return [
            'contact_us_config' => [
                'templates' => $this->templates,
                'design' => $this->design,
                'translation' => [
                    'enabled' => $this->translationEnabled,
                    'domain' => $this->translation['domain'] ?? 'contact_us',
                ],
            ],
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('contact_trans', [$this, 'translate']),
        ];
    }

    /**
     * Translate with fallback to plain text if translator unavailable
     * @param array<string, mixed> $parameters
     */
    public function translate(string $key, array $parameters = [], ?string $domain = null): string
    {
        // If translation disabled, extract plain text from key
        if (!$this->translationEnabled || $this->translator === null) {
            return $this->extractPlainText($key);
        }

        $domain = $domain ?: $this->translation['domain'];
        
        try {
            $translated = $this->translator->trans($key, $parameters, (string) $domain);
            
            // If translation returns the key unchanged, use fallback
            if ($translated === $key) {
                return $this->extractPlainText($key);
            }
            
            return $translated;
        } catch (\Exception) {
            // Fallback to plain text extraction
            return $this->extractPlainText($key);
        }
    }

    /**
     * Extract human-readable text from translation key
     * 
     * Examples:
     *   contact.field.name → Name
     *   contact.submit → Submit
     *   your_custom_label → Your Custom Label
     */
    private function extractPlainText(string $key): string
    {
        // Split by dots and take the last part
        $parts = explode('.', $key);
        $lastPart = end($parts);
        
        // Convert snake_case or camelCase to Title Case
        $text = preg_replace('/[_-]/', ' ', $lastPart);
        
        return ucwords($text ?? '');
    }
}
