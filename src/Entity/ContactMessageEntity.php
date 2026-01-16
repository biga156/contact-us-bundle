<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Entity;

use Caeligo\ContactUsBundle\Model\ContactMessage as ContactMessageModel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for contact messages
 * This is optional and only used when storage mode includes 'database'
 */
#[ORM\Entity]
#[ORM\Table(name: 'contact_message')]
#[ORM\Index(columns: ['created_at'], name: 'idx_contact_created')]
#[ORM\Index(columns: ['verification_token'], name: 'idx_contact_verification')]
class ContactMessageEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $data = [];

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $verified = false;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, unique: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;
        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): self
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    /**
     * Convert to DTO model
     */
    public function toModel(): ContactMessageModel
    {
        $model = new ContactMessageModel();
        $model->setId($this->id);
        $model->setCreatedAt($this->createdAt);
        $model->setData($this->data);
        $model->setIpAddress($this->ipAddress);
        $model->setUserAgent($this->userAgent);
        $model->setVerified($this->verified);
        $model->setVerificationToken($this->verificationToken);
        $model->setVerifiedAt($this->verifiedAt);

        return $model;
    }

    /**
     * Create entity from DTO model
     */
    public static function fromModel(ContactMessageModel $model): self
    {
        $entity = new self();
        
        if ($model->getId()) {
            $entity->id = $model->getId();
        }
        
        $entity->setCreatedAt($model->getCreatedAt() ?? new \DateTimeImmutable());
        $entity->setData($model->getData());
        $entity->setIpAddress($model->getIpAddress());
        $entity->setUserAgent($model->getUserAgent());
        $entity->setVerified($model->isVerified());
        $entity->setVerificationToken($model->getVerificationToken());
        $entity->setVerifiedAt($model->getVerifiedAt());

        return $entity;
    }
}
