<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Form\Type;

use Caeligo\ContactUsBundle\Form\DataTransformer\PhoneNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Compound form type for phone numbers with country code selector.
 * 
 * Displays two fields:
 * - Country code dropdown with emoji flags
 * - Phone number input
 * 
 * Stores as a single international format string: +36301234567
 * 
 * Usage in config:
 * ```yaml
 * fields:
 *   phone:
 *     type: tel
 *     options:
 *       allowed_countries: ['HU', 'AT', 'DE']  # Optional - restrict countries
 *       default_country: 'HU'                   # Optional - default selection
 * ```
 */
class TelephoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedCountries = $options['allowed_countries'];
        $defaultCountry = $options['default_country'];
        
        $builder
            ->add('country_code', ChoiceType::class, [
                'choices' => CountryCodes::getChoices($allowedCountries),
                'data' => $defaultCountry,
                'label' => false,
                'attr' => [
                    'class' => 'contact-phone-country',
                    'aria-label' => 'Country code',
                ],
                'choice_attr' => function($choice, $key, $value) {
                    // Add data attributes for potential JS enhancement
                    $countries = CountryCodes::getDefaultCountries();
                    if (isset($countries[$value])) {
                        return [
                            'data-code' => $countries[$value]['code'],
                            'data-flag' => $countries[$value]['flag'],
                        ];
                    }
                    return [];
                },
            ])
            ->add('number', TelType::class, [
                'label' => false,
                'attr' => [
                    'class' => 'contact-phone-number',
                    'placeholder' => '301234567',
                    'aria-label' => 'Phone number',
                ],
            ]);

        $builder->addModelTransformer(
            new PhoneNumberTransformer($allowedCountries, $defaultCountry)
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['allowed_countries'] = $options['allowed_countries'];
        $view->vars['default_country'] = $options['default_country'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allowed_countries' => null,
            'default_country' => 'HU',
            'compound' => true,
            'error_bubbling' => false,
        ]);

        $resolver->setAllowedTypes('allowed_countries', ['null', 'array']);
        $resolver->setAllowedTypes('default_country', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'contact_telephone';
    }
}
