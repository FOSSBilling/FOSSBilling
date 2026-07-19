<?php

declare(strict_types=1);

namespace FOSSBilling\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'session')]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $id = '';

    #[ORM\Column(name: 'modified_at', type: Types::INTEGER, nullable: true)]
    private ?int $modifiedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::INTEGER, nullable: true)]
    private ?int $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fingerprint = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getModifiedAt(): ?int
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(?int $modifiedAt): void
    {
        $this->modifiedAt = $modifiedAt;
    }

    public function getCreatedAt(): ?int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }
}
