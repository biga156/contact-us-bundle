<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Form\DataTransformer;

use Caeligo\ContactUsBundle\Form\Type\CountryCodes;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a full phone number string (+36123456789)
 * and an array with country_code and number parts.
 * 
 * @implements DataTransformerInterface<string|null, array{country_code: string, number: string}|null>
 */
class PhoneNumberTransformer implements DataTransformerInterface
{
    /**
     * @param array<string>|null $allowedCountries
     */
    public function __construct(
        private ?array $allowedCountries = null,
        private string $defaultCountry = 'HU'
    ) {}

    /**
     * Transforms a phone number string to an array for the form.
     * Example: "+36301234567" => ['country_code' => 'HU', 'number' => '301234567']
     * 
     * @param string|null $value
     * @return array{country_code: string, number: string}|null
     */
    public function transform(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return [
                'country_code' => $this->defaultCountry,
                'number' => '',
            ];
        }

        // Try to parse the phone number
        $countries = CountryCodes::getCountries($this->allowedCountries);
        
        // Sort by code length (longest first) to match +421 before +42
        uasort($countries, fn($a, $b) => strlen($b['code']) <=> strlen($a['code']));

        foreach ($countries as $countryCode => $data) {
            $callingCode = $data['code'];
            if (str_starts_with($value, $callingCode)) {
                return [
                    'country_code' => $countryCode,
                    'number' => substr($value, strlen($callingCode)),
                ];
            }
        }

        // No match found - use default country and assume number only
        return [
            'country_code' => $this->defaultCountry,
            'number' => ltrim($value, '+'),
        ];
    }

    /**
     * Transforms the form array back to a phone number string.
     * Example: ['country_code' => 'HU', 'number' => '301234567'] => "+36301234567"
     * 
     * @param array{country_code: string, number: string}|null $value
     * @return string|null
     */
    public function reverseTransform(mixed $value): ?string
    {
        if ($value === null || !is_array($value)) {
            return null;
        }

        $countryCode = $value['country_code'] ?? '';
        $number = $value['number'] ?? '';

        if ($number === '') {
            return null;
        }

        // Clean the number (remove spaces, dashes, etc.)
        $number = preg_replace('/[^0-9]/', '', $number);

        // Get calling code
        $callingCode = CountryCodes::getCallingCode($countryCode);
        
        if ($callingCode === null) {
            throw new TransformationFailedException(sprintf(
                'Invalid country code: %s',
                $countryCode
            ));
        }

        return $callingCode . $number;
    }
}
