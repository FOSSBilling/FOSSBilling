<?php

declare(strict_types=1);
/**
 * Copyright 2025-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Currency\Repository\CurrencyRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'currency')]
class Currency implements TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(options: ['default' => new \DateTimeImmutable()], insertable: false, updatable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => new \DateTimeImmutable()], insertable: false, updatable: true)]
    private ?\DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 6, options: ['default' => '1.000000'])]
    private string $conversionRate = '1.000000';

    #[ORM\Column(length: 50, nullable: true, options: ['default' => '${{price}}'])]
    private ?string $priceFormat = '${{price}}';

    #[ORM\Column(length: 3, unique: true)]
    private string $code;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $format;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getConversionRate(): float
    {
        return (float) $this->conversionRate;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getPriceFormat(): ?string
    {
        return $this->priceFormat;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    /**
     * Set the conversion rate.
     * Accepts both string and float values. The value is stored as a string
     * to preserve decimal precision in the database.
     *
     * @param string|float $conversionRate The new conversion rate
     */
    public function setConversionRate(string|float $conversionRate): void
    {
        // Use sprintf to ensure consistent decimal format (avoids scientific notation)
        // and matches the database column precision of 6 decimal places
        if (is_float($conversionRate)) {
            $this->conversionRate = sprintf('%.6f', $conversionRate);
        } else {
            $this->conversionRate = $conversionRate;
        }
    }

    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    public function setPriceFormat(string $priceFormat): void
    {
        $this->priceFormat = $priceFormat;
    }
}
