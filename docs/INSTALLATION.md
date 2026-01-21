# Installation Guide

## Table of Contents

1. [Requirements](#requirements)
2. [Installation Steps](#installation-steps)
   - [Install the Bundle](#1-install-the-bundle)
   - [Configure the Bundle](#2-configure-the-bundle)
   - [Import Routes](#3-import-routes)
   - [Set Up Database Storage (Optional)](#4-optional-set-up-database-storage)
   - [Configure AssetMapper (Optional)](#5-optional-configure-assetmapper)
   - [Clear Cache](#6-clear-cache)
3. [Verify Installation](#verify-installation)
4. [Next Steps](#next-steps)

---

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
    storage: email  # email|database|both
    recipients: ['%env(CONTACT_EMAIL)%']

# Recipients are only required when storage is email or both.
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
    type: php
```

This will register the `/contact` route.

**Optional: Route Prefix**

If you need to avoid route conflicts (e.g., if your application already has contact-related routes), you can add a prefix:

```yaml
contact_us:
    resource: '@ContactUsBundle/config/routes.php'
    type: php
    prefix: /contact-form  # or any prefix you prefer
```

### 4. (Optional) Set Up Database Storage

If you want to save contact messages to database:

```yaml
# config/packages/contact_us.yaml
contact_us:
    storage: database  # or 'both' for email + database
    recipients: []      # recipients not needed for database-only
```

Create migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

If you use the bundle's entity, the setup wizard will import an admin CRUD at `/contact/messages`. If you select your own existing entity, no CRUD routes are imported—wire your own controller or extend the bundle's abstract CRUD controller if desired.

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

- [Customize the form](CUSTOMIZATION.md)
- [Configure translations](TRANSLATION.md)
- [Write tests](TESTING.md)
- [View all configuration options](CONFIGURATION.md)
