<?php

declare(strict_types=1);

namespace Box\Mod\Servicedownloadable\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicedownloadable\Repository\ServiceDownloadableRepository::class)]
#[ORM\Table(name: 'service_downloadable')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\HasLifecycleCallbacks]
class ServiceDownloadable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(name: 'stored_filename', type: Types::STRING, length: 100, nullable: true)]
    private ?string $storedFilename = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $downloads = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(?int $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->filename = $filename;
    }

    public function getStoredFilename(): ?string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(?string $storedFilename): void
    {
        $this->storedFilename = $storedFilename;
    }

    public function getDownloads(): ?int
    {
        return $this->downloads;
    }

    public function setDownloads(?int $downloads): void
    {
        $this->downloads = $downloads;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
