<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Product\Repository\ProductPaymentRepository::class)]
#[ORM\Table(name: 'product_payment')]
class ProductPayment
{
    final public const string FREE = 'free';
    final public const string ONCE = 'once';
    final public const string RECURRENT = 'recurrent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 30, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'once_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $oncePrice = '0.00';

    #[ORM\Column(name: 'once_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $onceSetupPrice = '0.00';

    #[ORM\Column(name: 'w_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $wPrice = '0.00';

    #[ORM\Column(name: 'm_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $mPrice = '0.00';

    #[ORM\Column(name: 'q_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $qPrice = '0.00';

    #[ORM\Column(name: 'b_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $bPrice = '0.00';

    #[ORM\Column(name: 'a_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $aPrice = '0.00';

    #[ORM\Column(name: 'bia_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $biaPrice = '0.00';

    #[ORM\Column(name: 'tria_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $triaPrice = '0.00';

    #[ORM\Column(name: 'w_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $wSetupPrice = '0.00';

    #[ORM\Column(name: 'm_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $mSetupPrice = '0.00';

    #[ORM\Column(name: 'q_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $qSetupPrice = '0.00';

    #[ORM\Column(name: 'b_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $bSetupPrice = '0.00';

    #[ORM\Column(name: 'a_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $aSetupPrice = '0.00';

    #[ORM\Column(name: 'bia_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $biaSetupPrice = '0.00';

    #[ORM\Column(name: 'tria_setup_price', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $triaSetupPrice = '0.00';

    #[ORM\Column(name: 'w_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $wEnabled = true;

    #[ORM\Column(name: 'm_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $mEnabled = true;

    #[ORM\Column(name: 'q_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $qEnabled = true;

    #[ORM\Column(name: 'b_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $bEnabled = true;

    #[ORM\Column(name: 'a_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $aEnabled = true;

    #[ORM\Column(name: 'bia_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $biaEnabled = true;

    #[ORM\Column(name: 'tria_enabled', type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => true])]
    private bool $triaEnabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOncePrice(): float
    {
        return (float) $this->oncePrice;
    }

    public function setOncePrice(float|int|string $price): self
    {
        $this->oncePrice = sprintf('%.2f', (float) $price);

        return $this;
    }

    public function getOnceSetupPrice(): float
    {
        return (float) $this->onceSetupPrice;
    }

    public function setOnceSetupPrice(float|int|string $price): self
    {
        $this->onceSetupPrice = sprintf('%.2f', (float) $price);

        return $this;
    }

    public function getPeriodPrice(string $prefix): float
    {
        $property = $prefix . 'Price';

        return (float) $this->{$property};
    }

    public function getPeriodSetupPrice(string $prefix): float
    {
        $property = $prefix . 'SetupPrice';

        return (float) $this->{$property};
    }

    public function isPeriodEnabled(string $prefix): bool
    {
        $property = $prefix . 'Enabled';

        return (bool) $this->{$property};
    }

    public function setPeriodPricing(string $prefix, float|int|string $price, float|int|string $setupPrice, bool|int|string $enabled): self
    {
        $priceProperty = $prefix . 'Price';
        $setupProperty = $prefix . 'SetupPrice';
        $enabledProperty = $prefix . 'Enabled';

        $this->{$priceProperty} = sprintf('%.2f', (float) $price);
        $this->{$setupProperty} = sprintf('%.2f', (float) $setupPrice);
        $this->{$enabledProperty} = (bool) $enabled;

        return $this;
    }
}
