# ContactUsBundle

A highly configurable Symfony bundle for contact forms with multiple UI variants, flexible storage options, and accessibility-first design.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x-black.svg)](https://symfony.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## Features

- 🎨 **Multiple UI Variants** - LiveComponent, plain Symfony form, or REST API for external frontends
- 📝 **YAML-driven form configuration** - Define fields, validation, and options in config
- 📧 **Flexible delivery** - Email notification, database storage, or both
- 🛡️ **Multi-layer spam protection** - Honeypot, rate limiting, timing checks, optional third-party captcha
- ♿ **Accessibility-first** - WCAG 2.1 AA compliant, no visual-only captchas
- 🎭 **Themeable templates** - Easy customization with Twig blocks
- 🌍 **Multilingual** - Optional translation support with auto-detect (works without symfony/translation)
- 🔌 **Pluggable architecture** - Events, storage adapters, captcha providers
- 🚀 **Zero-build assets** - Works with Symfony AssetMapper (6.4+)

## Requirements

- PHP 8.2+
- Symfony 6.4+ or 7.x
- Symfony Mailer component

**Optional**:
- Doctrine ORM 2.14+ or 3.x (for database storage)
- Symfony UX LiveComponent 2.0+ (for LiveComponent UI)

## Installation

```bash
composer require caeligo/contact-us-bundle
```

The bundle will be automatically registered via Symfony Flex.

After install, run the setup wizard to generate `config/packages/contact_us.yaml` (and optionally migrations if you pick the bundle entity):

```bash
php bin/console contact:setup
```

Tip: use `--no-interaction` to accept safe defaults when scripting CI/bootstrap.

## Quick Start

### 0. Run the Setup Wizard (recommended)

```bash
php bin/console contact:setup
```

This writes your base configuration and can create migrations if you choose the bundle's entity.

### 1. Configure the Bundle

```yaml
# config/packages/contact_us.yaml
contact_us:
    recipients: ['admin@example.com']
    storage: email  # email|database|both
    spam_protection:
        level: 1  # 1=honeypot+rate limit, 2=+email verification, 3=+captcha provider
```

### 2. Add Form Fields (Optional)

```yaml
# config/packages/contact_us.yaml
contact_us:
    fields:
        name:
            type: text
            required: true
            constraints:
                - NotBlank: ~
                - Length: { max: 100 }
        email:
            type: email
            required: true
        message:
            type: textarea
            required: true
            constraints:
                - Length: { min: 10, max: 5000 }
```

### 3. Use LiveComponent (Recommended)

```twig
{# templates/contact/index.html.twig #}
{{ component('ContactUs') }}
```

### 4. Or Use Plain Form

```yaml
# config/routes.yaml
contact_us_routes:
    resource: '@ContactUsBundle/config/routes.yaml'
```

Visit `/contact` to see the form.

## Documentation

- [📖 Installation Guide](docs/INSTALLATION.md)
- [⚙️ Configuration Reference](docs/CONFIGURATION.md)
- [🎨 Customization & Theming](docs/CUSTOMIZATION.md) - **How to integrate with your app's design**
- [🌍 Translation Guide](docs/TRANSLATION.md) - **Multi-language support (optional)**
- [🧪 Testing Guide](docs/TESTING.md) - **Unit, functional & integration tests**

## Template Customization

The bundle is designed to integrate seamlessly with your existing application design. You can:

- **Use your own base template** - Configure `contact_us.templates.base: 'base.html.twig'`
- **Override bundle templates** - Create templates in `templates/bundles/ContactUsBundle/`
- **Disable default styles** - Set `contact_us.design.load_default_styles: false`
- **Use any CSS framework** - Bootstrap, Tailwind, custom CSS - your choice

**See the [Customization Guide](docs/CUSTOMIZATION.md) for complete examples.**

## Translation Support

The bundle supports **optional translations** with smart auto-detection:

- ✅ **With symfony/translation** - Uses translation keys from `contact_us` domain
- ❌ **Without symfony/translation** - Auto-falls back to plain text labels
- 🔧 **Configurable** - Force enable/disable or use custom domain

```yaml
# config/packages/contact_us.yaml
contact_us:
  translation:
    enabled: auto  # auto | true | false
    domain: contact_us
```

The bundle includes default translations in **English** and **Hungarian**. Add your own in `translations/contact_us.{locale}.yaml`.

**See the [Translation Guide](docs/TRANSLATION.md) for complete documentation.**

## Testing

The bundle includes comprehensive test coverage:

```bash
# Run all tests
./vendor/bin/phpunit

# Run only unit tests
./vendor/bin/phpunit --testsuite="Unit Tests"

# Run with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/
```

Test coverage includes:
- ✅ **Unit tests** - Form validation, spam protection, storage, Twig extensions
- ✅ **Functional tests** - Controller behavior, HTTP interactions (requires Symfony app)
- ✅ **Integration tests** - Complete workflows (requires Symfony app)

**See the [Testing Guide](docs/TESTING.md) for complete documentation.**

## License

MIT License. See [LICENSE](LICENSE) file for details.
