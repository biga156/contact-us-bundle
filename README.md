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
- 🗂️ **Built-in admin CRUD** - Database storage ships with an overridable CRUD for the bundle entity

## Requirements

- PHP 8.2+
- Symfony 6.4+ or 7.x
- Symfony Mailer component

**Optional**:
- Doctrine ORM 2.14+ or 3.x (for database storage)
- Symfony UX LiveComponent 2.0+ (for LiveComponent UI)
- Symfony Mailer (for email or email+database functions)

## Installation

```bash
composer require caeligo/contact-us-bundle
```

The bundle will be automatically registered via Symfony Flex.

**Run the interactive setup wizard:**

```bash
php bin/console contact:setup
```

The wizard automatically:
- Generates `config/packages/contact_us.yaml` 
- Imports routes to `config/routes.yaml` (adapts to your locale prefixes)
- Clears cache and compiles assets
- Optionally creates and runs database migrations

**Tip:** Use `--no-interaction` to accept safe defaults when scripting CI/bootstrap.

## Quick Start

### 1. Run the Setup Wizard (recommended)

```bash
php bin/console contact:setup
```

After setup completes, visit `/contact` to see the form.
If you selected database storage with the bundle entity, admin CRUD routes are available by the selection in the setup wizard (for example. `/asmin/contact`) (auto-imported by the wizard).

When you choose **email-only storage** or **switch to a custom entity**, the wizard detects if the bundle's table already exists and offers to drop it with a double confirmation (prompt + short random code) to avoid accidental data loss.

### 2. Or Use LiveComponent (no route import needed)

```twig
{# templates/contact/index.html.twig #}
{{ component('ContactUs') }}
```

### 3. Customize Configuration (optional)

Best practice: rerun `php bin/console contact:setup` when you need to change configuration so routes, migrations, and defaults stay in sync. You can still edit `config/packages/contact_us.yaml` by hand for most tweaks:

```yaml
contact_us:
    storage: database  # email|database|both
    recipients: []     # only needed for email or both
    spam_protection:
        # Base protections are always enabled: Honeypot, Timing, Rate limiting
        rate_limit:
            limit: 3
            interval: '15 minutes'
        min_submit_time: 3
        captcha:
            provider: none   # none|turnstile|hcaptcha|recaptcha|friendly
            site_key: ~      # set when provider is enabled
            secret_key: ~    # set when provider is enabled
    email_verification:
        enabled: false       # only applicable when storage=both
        token_ttl: '24 hours'
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

Manual edits apply immediately in `dev`; in `prod` clear the cache after editing: `php bin/console cache:clear --no-warmup`. If you change `crud_route_prefix`, update the matching entry in `config/routes.yaml` (or rerun the setup wizard). If you switch `storage` to `database`/`both`, run a Doctrine migration (`doctrine:migrations:diff` then `doctrine:migrations:migrate`).

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
