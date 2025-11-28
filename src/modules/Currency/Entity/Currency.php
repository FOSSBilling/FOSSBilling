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

namespace Box\Mod\Currency\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Currency\Repository\CurrencyRepository::class)]
#[ORM\Table(name: "currency")]
#[ORM\HasLifecycleCallbacks]
class Currency implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: "string", length: 3, unique: true)]
    private string $code;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $isDefault = false;

    /**
     * Conversion rate stored as string to preserve database decimal precision (13,6).
     * Use getConversionRate() to access the cached float value for calculations.
     * Note: Float conversion may introduce small precision differences for values
     * beyond float's ~15-17 significant digits, but this is acceptable for currency
     * conversion rates which typically don't require such extreme precision.
     */
    #[ORM\Column(type: "decimal", precision: 13, scale: 6, options: ["default" => "1.000000"])]
    private string $conversionRate = "1.000000";

    /**
     * Cached float value of conversionRate to avoid repeated string-to-float conversions.
     * This is not persisted to the database.
     */
    private ?float $conversionRateFloat = null;

    #[ORM\Column(type: "string", length: 30, nullable: true)]
    private ?string $format = null;

    #[ORM\Column(type: "string", length: 50, nullable: true, options: ["default" => '${{price}}'])]
    private ?string $priceFormat = '${{price}}';

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct(string $code, string $format)
    {
        $this->code = $code;
        $this->format = $format;
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

    /**
     * Get the conversion rate as a float value.
     * The float value is cached to avoid repeated string-to-float conversions.
     *
     * Note: While the database stores the value as decimal(13,6) for precision,
     * PHP's float type is used for calculations. Float precision (~15-17 significant
     * digits) is sufficient for currency conversion rates in practical use cases.
     *
     * @return float The conversion rate
     */
    public function getConversionRate(): float
    {
        if ($this->conversionRateFloat === null) {
            $this->conversionRateFloat = (float) $this->conversionRate;
        }
        return $this->conversionRateFloat;
    }

    /**
     * Get the raw string value of the conversion rate.
     * Use this method when you need the exact decimal precision stored in the database,
     * for example when performing calculations that require arbitrary precision arithmetic.
     *
     * @return string The conversion rate as a string (e.g., "1.234567")
     */
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
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
     * @return self
     */
    public function setConversionRate(string|float $conversionRate): self
    {
        $this->conversionRate = (string) $conversionRate;
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

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
