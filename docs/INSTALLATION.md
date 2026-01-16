# Installation Guide

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.x
- Composer

**Optional dependencies**:
- Doctrine ORM (for database storage)
- Symfony UX LiveComponent (for LiveComponent UI - planned for future release)

## Installation Steps

### 1. Install the Bundle

```bash
composer require caeligo/contact-us-bundle
```

If you're not using Symfony Flex, manually register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Caeligo\ContactUsBundle\ContactUsBundle::class => ['all' => true],
];
```

### 2. Configure the Bundle

Create configuration file `config/packages/contact_us.yaml`:

```yaml
contact_us:
    recipients: ['%env(CONTACT_EMAIL)%']
```

Add to your `.env` file:

```bash
CONTACT_EMAIL=admin@example.com
```

### 3. Import Routes

Add to `config/routes.yaml`:

```yaml
contact_us:
    resource: '@ContactUsBundle/config/routes.php'
```

This will register the `/contact` route.

### 4. (Optional) Set Up Database Storage

If you want to save contact messages to database:

```yaml
# config/packages/contact_us.yaml
contact_us:
    storage: database  # or 'both' for email + database
```

Create migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### 5. (Optional) Configure AssetMapper

If using Symfony AssetMapper (6.4+), assets will be auto-discovered. To customize:

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            - vendor/caeligo/contact-us-bundle/assets
```

### 6. Clear Cache

```bash
php bin/console cache:clear
```

## Verify Installation

Visit `/contact` in your browser. You should see the contact form.

## Next Steps

- [Configure spam protection](SPAM_PROTECTION.md)
- [Customize the form](CUSTOMIZATION.md)
- [Handle events](EVENTS.md)
- [Use the REST API](API.md)
