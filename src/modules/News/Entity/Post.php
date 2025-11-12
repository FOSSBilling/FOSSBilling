<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\News\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\News\Repository\PostRepository::class)]
#[ORM\Table(name: 'post')]
#[ORM\HasLifecycleCallbacks]
class Post implements ApiArrayInterface, TimestampInterface
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAFT = 'draft';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $admin_id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $section = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $publishAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $publishedAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $createdAt;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $updatedAt;

    /** @todo Temporary until Admin entity is migrated */
    private array $adminData = [];

    public function __construct(
        /** @todo Doctrine: Replace with actual Admin entity once it's migrated to Doctrine. */
        #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
        private string $title,
        #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
        private string $slug,
    ) {
    }

    /** @todo Temporary until Admin entity is migrated */
    public function setAdminData(array $adminData): self
    {
        $this->adminData = $adminData;

        return $this;
    }

    public function toApiArray(): array
    {
        // Remove <!--more--> from content
        $contentRaw = $this->getContent() ?? '';
        $content = str_replace('<!--more-->', '', $contentRaw);
        $pos = strpos($contentRaw, '<!--more-->');
        $excerpt = $pos !== false ? substr($contentRaw, 0, $pos) : null;

        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'content' => $content,
            'status' => $this->getStatus(),
            'slug' => $this->getSlug(),
            'image' => $this->getImage(),
            'section' => $this->getSection(),
            'publish_at' => $this->getPublishAt()?->format('Y-m-d H:i:s'),
            'published_at' => $this->getPublishedAt()?->format('Y-m-d H:i:s'),
            'expires_at' => $this->getExpiresAt()?->format('Y-m-d H:i:s'),
            'created_at' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'author' => $this->adminData, /* @todo Doctrine: Replace with actual Admin entity and remove $adminData once it's migrated to Doctrine. */
            'excerpt' => $excerpt,
        ];
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // --- Getters ---
    public function getId(): int
    {
        return $this->id;
    }

    public function getAdminId(): int
    {
        return $this->admin_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getPublishAt(): ?\DateTime
    {
        return $this->publishAt;
    }

    public function getPublishedAt(): ?\DateTime
    {
        return $this->publishedAt;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    // --- Setters ---
    public function setAdminId(int $adminId): self
    {
        $this->admin_id = $adminId;

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function setSection(?string $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $allowedStatuses = [self::STATUS_ACTIVE, self::STATUS_DRAFT];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status "%s". Allowed values: %s', $status, implode(', ', $allowedStatuses)));
        }

        $this->status = $status;

        return $this;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function setPublishAt(?\DateTime $publishAt): self
    {
        $this->publishAt = $publishAt;

        return $this;
    }

    public function setPublishedAt(?\DateTime $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function setExpiresAt(?\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
