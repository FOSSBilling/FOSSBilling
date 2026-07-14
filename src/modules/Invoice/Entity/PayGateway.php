<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Invoice\Repository\PayGatewayRepository::class)]
#[ORM\Table(name: 'pay_gateway')]
class PayGateway
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $gateway = null;

    #[ORM\Column(name: 'accepted_currencies', type: Types::TEXT, nullable: true)]
    private ?string $acceptedCurrencies = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'allow_single', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $allowSingle = true;

    #[ORM\Column(name: 'allow_recurrent', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $allowRecurrent = true;

    #[ORM\Column(name: 'test_mode', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $testMode = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $config = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getGateway(): ?string
    {
        return $this->gateway;
    }

    public function setGateway(?string $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function getAcceptedCurrencies(): ?string
    {
        return $this->acceptedCurrencies;
    }

    public function setAcceptedCurrencies(?string $acceptedCurrencies): void
    {
        $this->acceptedCurrencies = $acceptedCurrencies;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isAllowSingle(): bool
    {
        return $this->allowSingle;
    }

    public function setAllowSingle(bool $allowSingle): void
    {
        $this->allowSingle = $allowSingle;
    }

    public function isAllowRecurrent(): bool
    {
        return $this->allowRecurrent;
    }

    public function setAllowRecurrent(bool $allowRecurrent): void
    {
        $this->allowRecurrent = $allowRecurrent;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
    }
}
