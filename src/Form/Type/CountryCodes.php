<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Form\Type;

/**
 * Country calling codes with emoji flags.
 * 
 * Default list contains ~50 most common countries.
 * If symfony/intl is available, the full list can be used.
 */
final class CountryCodes
{
    /**
     * Default country codes with emoji flags.
     * Format: 'CC' => ['code' => '+XX', 'flag' => '🇨🇨', 'name' => 'Country Name']
     * 
     * @return array<string, array{code: string, flag: string, name: string}>
     */
    public static function getDefaultCountries(): array
    {
        return [
            'AT' => ['code' => '+43', 'flag' => '🇦🇹', 'name' => 'Austria'],
            'AU' => ['code' => '+61', 'flag' => '🇦🇺', 'name' => 'Australia'],
            'BE' => ['code' => '+32', 'flag' => '🇧🇪', 'name' => 'Belgium'],
            'BR' => ['code' => '+55', 'flag' => '🇧🇷', 'name' => 'Brazil'],
            'CA' => ['code' => '+1', 'flag' => '🇨🇦', 'name' => 'Canada'],
            'CH' => ['code' => '+41', 'flag' => '🇨🇭', 'name' => 'Switzerland'],
            'CN' => ['code' => '+86', 'flag' => '🇨🇳', 'name' => 'China'],
            'CZ' => ['code' => '+420', 'flag' => '🇨🇿', 'name' => 'Czech Republic'],
            'DE' => ['code' => '+49', 'flag' => '🇩🇪', 'name' => 'Germany'],
            'DK' => ['code' => '+45', 'flag' => '🇩🇰', 'name' => 'Denmark'],
            'ES' => ['code' => '+34', 'flag' => '🇪🇸', 'name' => 'Spain'],
            'FI' => ['code' => '+358', 'flag' => '🇫🇮', 'name' => 'Finland'],
            'FR' => ['code' => '+33', 'flag' => '🇫🇷', 'name' => 'France'],
            'GB' => ['code' => '+44', 'flag' => '🇬🇧', 'name' => 'United Kingdom'],
            'GR' => ['code' => '+30', 'flag' => '🇬🇷', 'name' => 'Greece'],
            'HR' => ['code' => '+385', 'flag' => '🇭🇷', 'name' => 'Croatia'],
            'HU' => ['code' => '+36', 'flag' => '🇭🇺', 'name' => 'Hungary'],
            'IE' => ['code' => '+353', 'flag' => '🇮🇪', 'name' => 'Ireland'],
            'IL' => ['code' => '+972', 'flag' => '🇮🇱', 'name' => 'Israel'],
            'IN' => ['code' => '+91', 'flag' => '🇮🇳', 'name' => 'India'],
            'IT' => ['code' => '+39', 'flag' => '🇮🇹', 'name' => 'Italy'],
            'JP' => ['code' => '+81', 'flag' => '🇯🇵', 'name' => 'Japan'],
            'KR' => ['code' => '+82', 'flag' => '🇰🇷', 'name' => 'South Korea'],
            'MX' => ['code' => '+52', 'flag' => '🇲🇽', 'name' => 'Mexico'],
            'NL' => ['code' => '+31', 'flag' => '🇳🇱', 'name' => 'Netherlands'],
            'NO' => ['code' => '+47', 'flag' => '🇳🇴', 'name' => 'Norway'],
            'NZ' => ['code' => '+64', 'flag' => '🇳🇿', 'name' => 'New Zealand'],
            'PL' => ['code' => '+48', 'flag' => '🇵🇱', 'name' => 'Poland'],
            'PT' => ['code' => '+351', 'flag' => '🇵🇹', 'name' => 'Portugal'],
            'RO' => ['code' => '+40', 'flag' => '🇷🇴', 'name' => 'Romania'],
            'RS' => ['code' => '+381', 'flag' => '🇷🇸', 'name' => 'Serbia'],
            'RU' => ['code' => '+7', 'flag' => '🇷🇺', 'name' => 'Russia'],
            'SE' => ['code' => '+46', 'flag' => '🇸🇪', 'name' => 'Sweden'],
            'SI' => ['code' => '+386', 'flag' => '🇸🇮', 'name' => 'Slovenia'],
            'SK' => ['code' => '+421', 'flag' => '🇸🇰', 'name' => 'Slovakia'],
            'TR' => ['code' => '+90', 'flag' => '🇹🇷', 'name' => 'Turkey'],
            'UA' => ['code' => '+380', 'flag' => '🇺🇦', 'name' => 'Ukraine'],
            'US' => ['code' => '+1', 'flag' => '🇺🇸', 'name' => 'United States'],
            'ZA' => ['code' => '+27', 'flag' => '🇿🇦', 'name' => 'South Africa'],
        ];
    }

    /**
     * Get countries from symfony/intl if available, otherwise use default.
     * 
     * @param array<string>|null $allowedCountries Filter to these country codes only
     * @return array<string, array{code: string, flag: string, name: string}>
     */
    public static function getCountries(?array $allowedCountries = null): array
    {
        $countries = self::getDefaultCountries();

        // Try to use symfony/intl for country names if available
        if (class_exists(\Symfony\Component\Intl\Countries::class)) {
            try {
                $intlCountries = \Symfony\Component\Intl\Countries::getNames();
                foreach ($countries as $code => &$data) {
                    if (isset($intlCountries[$code])) {
                        $data['name'] = $intlCountries[$code];
                    }
                }
            } catch (\Throwable) {
                // Ignore, use default names
            }
        }

        // Filter by allowed countries if specified
        if ($allowedCountries !== null && !empty($allowedCountries)) {
            $countries = array_filter(
                $countries,
                fn($code) => in_array($code, $allowedCountries, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        return $countries;
    }

    /**
     * Build choices array for ChoiceType.
     * Format: 'HU 🇭🇺 (+36)' => 'HU'
     * 
     * @param array<string>|null $allowedCountries
     * @return array<string, string>
     */
    public static function getChoices(?array $allowedCountries = null): array
    {
        $countries = self::getCountries($allowedCountries);
        $choices = [];

        foreach ($countries as $code => $data) {
            $label = sprintf('%s %s (%s)', $data['flag'], $data['name'], $data['code']);
            $choices[$label] = $code;
        }

        return $choices;
    }

    /**
     * Get the calling code for a country.
     */
    public static function getCallingCode(string $countryCode): ?string
    {
        $countries = self::getDefaultCountries();
        return $countries[strtoupper($countryCode)]['code'] ?? null;
    }
}
