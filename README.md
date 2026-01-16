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
- 🌍 **Multilingual** - Full translation support
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

## Quick Start

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

- [Configuration Reference](docs/CONFIGURATION.md)
- [UI Variants](docs/UI_VARIANTS.md)
- [Storage Options](docs/STORAGE.md)
- [Spam Protection](docs/SPAM_PROTECTION.md)
- [REST API](docs/API.md)
- [Customization](docs/CUSTOMIZATION.md)
- [Events](docs/EVENTS.md)

## License

MIT License. See [LICENSE](LICENSE) file for details.
