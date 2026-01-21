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
    private const DEFAULT_LOCALE = 'en';

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
     * Translate with fallback to English locale if translation not found
     * @param array<string, mixed> $parameters
     */
    public function translate(string $key, array $parameters = [], ?string $domain = null): string
    {
        $domain = $domain ?: ($this->translation['domain'] ?? 'contact_us');

        // If translation disabled or translator unavailable, use English fallback
        if (!$this->translationEnabled || $this->translator === null) {
            return $this->getEnglishFallback($key, $parameters, $domain);
        }

        try {
            $translated = $this->translator->trans($key, $parameters, (string) $domain);
            
            // If translation returns the key unchanged, try English fallback
            if ($translated === $key) {
                return $this->getEnglishFallback($key, $parameters, $domain);
            }
            
            return $translated;
        } catch (\Exception) {
            // Fallback to English
            return $this->getEnglishFallback($key, $parameters, $domain);
        }
    }

    /**
     * Get translation from English locale as fallback
     * @param array<string, mixed> $parameters
     */
    private function getEnglishFallback(string $key, array $parameters, string $domain): string
    {
        if ($this->translator === null) {
            return $this->extractPlainText($key);
        }

        try {
            // Try to get translation from English locale explicitly
            $translated = $this->translator->trans($key, $parameters, $domain, self::DEFAULT_LOCALE);
            
            // If still returns the key, use plain text extraction
            if ($translated === $key) {
                return $this->extractPlainText($key);
            }
            
            return $translated;
        } catch (\Exception) {
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
