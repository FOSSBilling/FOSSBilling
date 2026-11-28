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
 * Help desks hidden from clients (see Box\Mod\Support\HiddenHelpdesks).
 * One row per hidden help desk.
 */
#[ORM\Entity]
#[ORM\Table(name: 'support_helpdesk_hidden')]
class SupportHelpdeskHidden
{
    #[ORM\Id]
    #[ORM\Column(name: 'helpdesk_id', type: Types::BIGINT)]
    private ?int $helpdeskId = null;

    #[ORM\Column(name: 'hidden_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $hiddenAt = null;

    public function getHelpdeskId(): ?int
    {
        return $this->helpdeskId;
    }

    public function setHelpdeskId(?int $helpdeskId): self
    {
        $this->helpdeskId = $helpdeskId;

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
