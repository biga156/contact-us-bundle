# Practical Examples

A collection of common customization scenarios with copy‑pasteable snippets.

Contents

1. Integrate the form into your theme
2. Customize bundle templates (form, emails)
3. Change form fields (add/remove/change type)
4. Customize CRUD (logic, filters, routes)
5. Customize submit action (events, short‑circuit)
6. BOTH mode with custom entity: what to consider

---

## 1) Integrate the Form Into Your Theme

Option A — Configure the bundle to use your base layout:

```yaml
# config/packages/contact_us.yaml
contact_us:
  templates:
    base: 'base.html.twig'
```

Option B — Override the form template and extend your layout:

```twig
{# templates/bundles/ContactUsBundle/contact/form.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ 'contact.title'|trans }}{% endblock %}
{% block body %}
  <div class="contact-form-container">
    {% include '@!ContactUs/contact/_form.html.twig' %}
  </div>
{% endblock %}
```

Tip: Use `@!ContactUs/...` when extending/including original bundle templates to avoid infinite loops.

---

## 2) Customize Bundle Templates (Form, Emails)

Override any template by placing a file under `templates/bundles/ContactUsBundle/...`:

- Form page: `templates/bundles/ContactUsBundle/contact/form.html.twig`
- Form partial: `templates/bundles/ContactUsBundle/contact/_form.html.twig`
- Email HTML: `templates/bundles/ContactUsBundle/email/contact_notification.html.twig`
- Email TXT: `templates/bundles/ContactUsBundle/email/contact_notification.txt.twig`
- (Database mode) Admin CRUD views: `templates/bundles/ContactUsBundle/admin/contact/*.html.twig`

Example — tweak email subject area:

```twig
{# templates/bundles/ContactUsBundle/email/contact_notification.html.twig #}
{% extends '@!ContactUs/email/contact_notification.html.twig' %}

{% block subject_line %}
  [MyBrand] {{ parent() }}
{% endblock %}
```

---

## 3) Change Form Fields (Add/Remove/Change Type)

Fields are defined in configuration. Common tweaks:

```yaml
# config/packages/contact_us.yaml
contact_us:
  storage: email  # or database/both
  fields:
    name:
      label: 'Your name'
    email:
      type: email
      constraints:
        - NotBlank: ~
        - Email: ~
    subject:
      type: choice
      options:
        choices:
          'General request': general
          'Support': support
          'Billing': billing
    phone:
      type: tel
      required: false
      constraints:
        - Length: { max: 30 }
    message:
      type: textarea
      constraints:
        - Length: { min: 10, max: 5000 }
```

Notes:
- You can add any field supported by Symfony forms (text, email, textarea, tel, url, number, choice, ...).
- Use `constraints` for validation.
- Use translation keys in `label` if you have translations.

---

## 4) Customize CRUD (Logic, Filters, Routes)

Database storage with the bundle entity ships with a default CRUD. You can customize it by extending the base controller or by swapping the CRUD manager service.

Extend the controller to tweak behavior or templates:

```php
// src/Controller/Admin/ContactMessageCrudController.php
namespace App\Controller\Admin;

use Caeligo\ContactUsBundle\Controller\Admin\AbstractContactCrudController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactMessageCrudController extends AbstractContactCrudController
{
    // Example: add simple filtering by query param
    public function index(Request $request): Response
    {
        $response = parent::index($request);
        // You can override render() call or pass extra vars to templates via events.
        return $response;
    }
}
```

Point routes to your controller (copy from the bundle’s routes and adjust):

```yaml
# config/routes.yaml
contact_us_admin:
  resource: '@ContactUsBundle/config/routes_crud.php'
  type: php
  prefix:
    fr: '/admin/contact'
    en: '/en/admin/contact'
    hu: '/hu/admin/contact'
  controller: App\Controller\Admin\ContactMessageCrudController
```

Swap the CRUD manager to change data access without touching controllers:

```php
// src/Contact/MyCrudManager.php
namespace App\Contact;

use App\Entity\ContactMessageEntity; // your entity
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

class MyCrudManager implements CrudManagerInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function list(int $page = 1, int $limit = 20): iterable
    {
        return $this->em->getRepository(ContactMessageEntity::class)
            ->createQueryBuilder('c')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function count(): int
    {
        return (int) $this->em->getRepository(ContactMessageEntity::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function find(int $id): ?ContactMessage
    {
        $entity = $this->em->find(ContactMessageEntity::class, $id);
        return $entity?->toModel(); // implement toModel() on your entity, or map manually
    }

    public function delete(ContactMessage $message): void
    {
        $entity = $this->em->find(ContactMessageEntity::class, $message->getId());
        if ($entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }
}
```

Wire it:

```yaml
# config/services.yaml
services:
  App\Contact\MyCrudManager: ~
  Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface: '@App\Contact\MyCrudManager'
```

Notes:
- Expose a `toModel()` helper on your entity (or map to `ContactMessage` manually).
- You can add filters, sorting, or security checks inside `list/find/delete`.

---

## 5) Customize Submit Action (Events, Short‑Circuit)

Hook into the submission pipeline via events dispatched by the bundle:

- `contact_us.submitted` — before processing; call `$event->preventProcessing()` to stop.
- `contact_us.persisted` — after saved to storage.
- `contact_us.email_sent` — after email sent; inspect recipients.
- `contact_us.verified` — after sender verification (BOTH mode).

Example — block messages with forbidden words:

```php
// src/EventSubscriber/ContactSubmittedSubscriber.php
namespace App\EventSubscriber;

use Caeligo\ContactUsBundle\Event\ContactSubmittedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContactSubmittedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ContactSubmittedEvent::NAME => 'onSubmitted'];
    }

    public function onSubmitted(ContactSubmittedEvent $event): void
    {
        $data = $event->getMessage()->getData();
        if (isset($data['message']) && str_contains(strtolower((string) $data['message']), 'forbidden')) {
            $event->preventProcessing();
        }
    }
}
```

Example — add BCC after email is sent:

```php
// src/EventSubscriber/ContactEmailSentSubscriber.php
namespace App\EventSubscriber;

use Caeligo\ContactUsBundle\Event\ContactEmailSentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContactEmailSentSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ContactEmailSentEvent::NAME => 'onEmailSent'];
    }

    public function onEmailSent(ContactEmailSentEvent $event): void
    {
        // Inspect $event->getRecipients() or trigger additional notifications
    }
}
```

Register subscribers as services (Symfony autoconfiguration usually does it automatically).

---

## 6) BOTH Mode with a Custom Entity — What to Consider

When you choose BOTH mode and select a custom entity in the setup wizard:

- Ensure your entity can map to the bundle model. Doctrine storage maps via `setData()/getData()` when available; otherwise it tries common getters/setters (`getTitle/getEmail/getSubtitle/getBody/getPhone`, etc.).
- If you enable email verification, your entity should have fields to store verification data (e.g., `verificationToken`, `verified`, `verifiedAt`). If you don’t have them, disable email verification or extend your entity accordingly.
- Implement an identifier getter (`getId()`); it’s used to round‑trip model IDs.
- Ensure your repository can find by verification token if you plan to use verification.
- Migrations: create/adjust your schema accordingly; the wizard only generates migrations for the bundle entity.

### Recommended minimal properties (works with default form fields)
- `id` (int, identifier)
- `data` (json/array) — recommended; stores all dynamic form fields
- `name`, `email`, `subject`, `message`, `phone` (string, nullable) — only needed if you don’t keep a `data` blob; setters/getters should match your form keys
- `ipAddress` (string, nullable)
- `userAgent` (string, nullable)
- `verified` (bool)
- `verificationToken` (string, nullable, unique if used)
- `verifiedAt` (datetime_immutable, nullable)

### Quick-start: copy/pasteable skeleton
```php
/** @Entity */
class MyContactMessage
{
    /** @Id @GeneratedValue @Column(type="integer") */
    private ?int $id = null;

    /** @Column(type="json") */
    private array $data = [];

    /** @Column(type="string", length=255, nullable=true) */
    private ?string $ipAddress = null;

    /** @Column(type="string", length=512, nullable=true) */
    private ?string $userAgent = null;

    /** @Column(type="boolean") */
    private bool $verified = false;

    /** @Column(type="string", length=128, nullable=true, unique=true) */
    private ?string $verificationToken = null;

    /** @Column(type="datetime_immutable", nullable=true) */
    private ?\DateTimeImmutable $verifiedAt = null;

    // Optional explicit fields if you prefer not to rely solely on `data`
    /** @Column(type="string", length=180, nullable=true) */
    private ?string $name = null;
    /** @Column(type="string", length=180, nullable=true) */
    private ?string $email = null;
    /** @Column(type="string", length=200, nullable=true) */
    private ?string $subject = null;
    /** @Column(type="text", nullable=true) */
    private ?string $message = null;
    /** @Column(type="string", length=50, nullable=true) */
    private ?string $phone = null;

    // getters/setters ... including getId(), getData()/setData() (recommended)
}
```

### Optional helper trait (copy into your project)
Use this if you want a minimal drop-in to reduce boilerplate (adjust namespaces/attributes as needed):

```php
trait ContactMessageFields
{
    /** @Id @GeneratedValue @Column(type="integer") */
    private ?int $id = null;
    /** @Column(type="json") */
    private array $data = [];
    /** @Column(type="string", length=255, nullable=true) */
    private ?string $ipAddress = null;
    /** @Column(type="string", length=512, nullable=true) */
    private ?string $userAgent = null;
    /** @Column(type="boolean") */
    private bool $verified = false;
    /** @Column(type="string", length=128, nullable=true, unique=true) */
    private ?string $verificationToken = null;
    /** @Column(type="datetime_immutable", nullable=true) */
    private ?\DateTimeImmutable $verifiedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getData(): array { return $this->data; }
    public function setData(array $data): self { $this->data = $data; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): self { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): self { $this->userAgent = $ua; return $this; }
    public function isVerified(): bool { return $this->verified; }
    public function setVerified(bool $v): self { $this->verified = $v; return $this; }
    public function getVerificationToken(): ?string { return $this->verificationToken; }
    public function setVerificationToken(?string $t): self { $this->verificationToken = $t; return $this; }
    public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
    public function setVerifiedAt(?\DateTimeImmutable $at): self { $this->verifiedAt = $at; return $this; }
}
```

If you prefer not to implement `getData()/setData()`, Doctrine storage will try to map individual fields using setters that match your form field keys.

---

Need more examples? Open an issue or ask in your project’s docs channel.
