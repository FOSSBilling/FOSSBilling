<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Knowledge base categories hidden from clients (see
 * Box\Mod\Support\HiddenKbCategories). One row per hidden category.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Support\Repository\SupportKbCategoryHiddenRepository::class)]
#[ORM\Table(name: 'support_kb_category_hidden')]
class SupportKbCategoryHidden
{
    #[ORM\Id]
    #[ORM\Column(name: 'category_id', type: Types::BIGINT)]
    private ?int $categoryId = null;

    #[ORM\Column(name: 'hidden_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $hiddenAt = null;

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(?int $categoryId): self
    {
        $this->categoryId = $categoryId;

        return $this;
    }

    public function getHiddenAt(): ?\DateTimeImmutable
    {
        return $this->hiddenAt;
    }

    public function setHiddenAt(?\DateTimeImmutable $hiddenAt): self
    {
        $this->hiddenAt = $hiddenAt;

        return $this;
    }
}
