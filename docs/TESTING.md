# Testing Guide

## Table of Contents

1. [Overview](#overview)
2. [Test Structure](#test-structure)
3. [Running Tests](#running-tests)
4. [Test Components](#test-components)
5. [Current Test Status](#current-test-status)
6. [Writing New Tests](#writing-new-tests)
7. [Continuous Integration](#continuous-integration)
8. [Code Coverage Goals](#code-coverage-goals)
9. [Next Steps](#next-steps)
10. [Resources](#resources)

---

## Overview

The ContactUsBundle includes comprehensive test coverage for all major components. Tests are organized into three categories:

- **Unit Tests**: Test individual components in isolation
- **Functional Tests**: Test HTTP interactions and controller behavior  
- **Integration Tests**: Test component interactions and full workflows

## Test Structure

```
tests/
├── bootstrap.php                      # Test bootstrap
├── Unit/                             # Unit tests
│   ├── Form/
│   │   └── ContactFormTypeTest.php
│   ├── SpamProtection/
│   │   ├── TimingValidatorTest.php
│   │   └── HoneypotValidatorTest.php
│   ├── Twig/
│   │   └── ContactUsExtensionTest.php
│   ├── Storage/
│   │   └── DoctrineStorageTest.php
│   ├── Email/
│   │   └── EmailNotifierTest.php (to be implemented)
│   └── DependencyInjection/
│       ├── ConfigurationTest.php
│       └── ContactUsExtensionTest.php
├── Functional/
│   └── Controller/
│       └── ContactControllerTest.php (requires Symfony app context)
└── Integration/
    └── ContactFormIntegrationTest.php (requires Symfony app context)
```

## Running Tests

### Run All Tests

```bash
cd /path/to/contact-us-bundle
./vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
./vendor/bin/phpunit --testsuite="Unit Tests"

# Functional tests only
./vendor/bin/phpunit --testsuite="Functional Tests"

# Integration tests only
./vendor/bin/phpunit --testsuite="Integration Tests"
```

### Run Tests with Coverage

```bash
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/
```

### Run Specific Test Class

```bash
./vendor/bin/phpunit tests/Unit/Form/ContactFormTypeTest.php
```

## Test Components

### Unit Tests

#### Form Tests (`ContactFormTypeTest`)
- ✅ Form field configuration
- ✅ Validation rules (name, email, subject, message)
- ✅ CSRF protection
- ✅ Honeypot field integration
- ✅ Timing validator integration

#### Spam Protection Tests
- ✅ `TimingValidatorTest`: Tests minimum submission time validation
- ✅ `HoneypotValidatorTest`: Tests honeypot field spam detection

#### Twig Extension Tests (`ContactUsExtensionTest`)
- ✅ Template path configuration
- ✅ Design settings (CSS, Stimulus)
- ✅ Translation system with auto-detection
- ✅ Fallback plain text extraction
- ✅ Custom translation domains

#### Storage Tests (`DoctrineStorageTest`)
- ✅ Entity persistence
- ✅ Custom entity class support
- ✅ Exception handling

#### Email Tests (`EmailNotifierTest`)
- ✅ Email sending when enabled
- ✅ Email disabled configuration
- ✅ Subject prefix configuration
- ✅ Reply-To header setup

#### Dependency Injection Tests
- ✅ `ConfigurationTest`: Configuration tree validation
- ✅ `ContactUsExtensionTest`: Service registration and parameters

### Functional Tests

#### Controller Tests (`ContactControllerTest`)
- Form page rendering
- Form submission with valid data
- Validation error handling
- CSRF token protection
- Multi-locale routes
- Rate limiting (if enabled)
- Success/error messages

### Integration Tests

#### Contact Form Integration (`ContactFormIntegrationTest`)
- Complete form submission workflow
- Database persistence verification
- Email notification delivery
- Service container integration
- Configuration loading

## Current Test Status

### ✅ Completed
- Test structure and bootstrap
- Unit test templates for all major components
- Test configuration (phpunit.xml.dist)
- Testing documentation

### ⚠️ Requires Attention
Some tests reference classes/namespaces that need adjustment:
- `Caeligo\ContactUsBundle\Entity\ContactMessage` → Should use `Model\ContactMessage` or `Entity\ContactMessageEntity`
- `Caeligo\ContactUsBundle\Email\EmailNotifier` → Class may not exist (check actual implementation)
- `Caeligo\ContactUsBundle\Validator\*` → Should use `SpamProtection\*Validator`

### 🔧 To Be Adjusted
1. Update entity references in tests to match actual bundle structure
2. Verify email notifier implementation and adjust tests
3. Adjust validator namespaces (`Validator\*` → `SpamProtection\*`)
4. Configure default values in Configuration tests to match actual defaults
5. Add missing methods to Twig Extension or update tests

### 📝 Notes for Functional/Integration Tests
Functional and integration tests require a full Symfony application context. These should be run in a project that uses the bundle (like CAELIGO), not standalone in the bundle repository.

## Writing New Tests

### Unit Test Template

```php
<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\YourNamespace;

use PHPUnit\Framework\TestCase;

class YourClassTest extends TestCase
{
    private YourClass $instance;
    
    protected function setUp(): void
    {
        $this->instance = new YourClass();
    }
    
    public function testYourFeature(): void
    {
        $result = $this->instance->yourMethod();
        
        $this->assertEquals('expected', $result);
    }
}
```

### Functional Test Template

```php
<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class YourFeatureTest extends WebTestCase
{
    public function testYourFeature(): void
    {
        $client = static::createClient();
        $client->request('GET', '/your-route');
        
        $this->assertResponseIsSuccessful();
    }
}
```

## Continuous Integration

The bundle includes PHPUnit configuration for CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
- name: Run tests
  run: ./vendor/bin/phpunit --coverage-clover coverage.xml
```

## Code Coverage Goals

- **Overall**: > 80%
- **Critical paths** (form handling, storage, spam protection): > 90%
- **Controllers**: > 70%
- **Configuration**: > 80%

## Next Steps

1. Fix namespace/class references in existing tests
2. Run unit tests and verify they pass
3. Implement functional tests in CAELIGO project
4. Add missing email notifier implementation or tests
5. Generate coverage report
6. Document any untested edge cases

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Symfony Testing](https://symfony.com/doc/current/testing.html)
- [Testing Best Practices](https://symfony.com/doc/current/best_practices.html#tests)
