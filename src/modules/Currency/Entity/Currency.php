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
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Currency\Repository\CurrencyRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'currency')]
class Currency implements ApiArrayInterface, TimestampInterface
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

    private ?float $conversionRateFloat = null;

    #[ORM\Column(length: 50, nullable: true, options: ['default' => '${{price}}'])]
    private ?string $priceFormat = '${{price}}';

    public function __construct(
        #[ORM\Column(length: 3, unique: true)]
        private string $code,
        #[ORM\Column(length: 30, nullable: true)]
        private ?string $format,
    ) {
    }

    public function toApiArray(): array
    {
        return [
            'code' => $this->getCode(),
            'title' => $this->getTitle(),
            'conversion_rate' => $this->getConversionRate(),
            'format' => $this->getFormat(),
            'price_format' => $this->getPriceFormat(),
            'default' => $this->isDefault(),
        ];
    }

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
        if ($this->conversionRateFloat === null) {
            $this->conversionRateFloat = (float) $this->conversionRate;
        }

        return $this->conversionRateFloat;
    }

    public function getConversionRateRaw(): string
    {
        return $this->conversionRate;
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

    // --- Setters ---
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * Set the conversion rate.
     * Accepts both string and float values. The value is stored as a string
     * to preserve decimal precision in the database.
     *
     * @param string|float $conversionRate The new conversion rate
     */
    public function setConversionRate(string|float $conversionRate): self
    {
        // Use sprintf to ensure consistent decimal format (avoids scientific notation)
        // and matches the database column precision of 6 decimal places
        if (is_float($conversionRate)) {
            $this->conversionRate = sprintf('%.6f', $conversionRate);
        } else {
            $this->conversionRate = $conversionRate;
        }
        // Invalidate cached float value so it will be recalculated on next access
        $this->conversionRateFloat = null;

        return $this;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function setPriceFormat(?string $priceFormat): self
    {
        $this->priceFormat = $priceFormat;

        return $this;
    }
}
