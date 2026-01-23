<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Form;

use Caeligo\ContactUsBundle\Form\Type\TelephoneType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dynamic contact form type built from YAML configuration
 */
class ContactFormType extends AbstractType
{
    private const TYPE_MAP = [
        'text' => TextType::class,
        'email' => EmailType::class,
        'textarea' => TextareaType::class,
        'tel' => TelephoneType::class,  // Use our custom TelephoneType
        'url' => UrlType::class,
        'number' => NumberType::class,
        'choice' => ChoiceType::class,
    ];

    private const CONSTRAINT_MAP = [
        'NotBlank' => Assert\NotBlank::class,
        'Email' => Assert\Email::class,
        'Length' => Assert\Length::class,
        'Range' => Assert\Range::class,
        'Regex' => Assert\Regex::class,
        'Url' => Assert\Url::class,
        'Choice' => Assert\Choice::class,
    ];

    /**
     * @param array<string, array<string, mixed>> $fieldsConfig
     */
    public function __construct(
        private array $fieldsConfig = []
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($this->fieldsConfig as $name => $config) {
            $fieldType = self::TYPE_MAP[$config['type']] ?? TextType::class;
            $fieldOptions = $this->buildFieldOptions($config);

            $builder->add($name, $fieldType, $fieldOptions);
        }

        // Add honeypot field (hidden from users, bots will fill it)
        $builder->add('email_confirm', TextType::class, [
            'mapped' => false,
            'required' => false,
            'attr' => [
                'class' => 'contact-honeypot',
                'tabindex' => '-1',
                'autocomplete' => 'off',
                'aria-hidden' => 'true',
            ],
        ]);

        // Add timing token (hidden field with form load timestamp)
        $builder->add('_form_token_time', TextType::class, [
            'mapped' => false,
            'required' => false,
            'attr' => [
                'class' => 'contact-timing-token',
            ],
            'data' => (string) time(),
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildFieldOptions(array $config): array
    {
        $options = [
            'required' => $config['required'] ?? false,
            'label' => $config['label'] ?? null,
            'translation_domain' => 'contact_us',
            'attr' => $config['options']['attr'] ?? [],
        ];

        // Add custom options (e.g., choices for ChoiceType, allowed_countries for TelephoneType)
        if (isset($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                if ($key !== 'attr') {
                    $options[$key] = $value;
                }
            }
        }

        // Build constraints
        if (isset($config['constraints']) && is_array($config['constraints'])) {
            $options['constraints'] = $this->buildConstraints($config['constraints']);
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $constraintsConfig
     * @return array<Constraint>
     */
    private function buildConstraints(array $constraintsConfig): array
    {
        $constraints = [];

        foreach ($constraintsConfig as $constraintConfig) {
            if (is_array($constraintConfig)) {
                foreach ($constraintConfig as $name => $options) {
                    if (isset(self::CONSTRAINT_MAP[$name])) {
                        $constraintClass = self::CONSTRAINT_MAP[$name];
                        $constraints[] = new $constraintClass($options ?? []);
                    }
                }
            }
        }

        return $constraints;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'contact_form',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'contact';
    }
}
