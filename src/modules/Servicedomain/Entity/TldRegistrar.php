<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicedomain\Repository\TldRegistrarRepository::class)]
#[ORM\Table(name: 'tld_registrar')]
class TldRegistrar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $registrar = null;

    #[ORM\Column(name: 'test_mode', type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $testMode = false;

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

    public function getRegistrar(): ?string
    {
        return $this->registrar;
    }

    public function setRegistrar(?string $registrar): void
    {
        $this->registrar = $registrar;
    }

    public function isTestMode(): ?bool
    {
        return $this->testMode;
    }

    public function setTestMode(?bool $testMode): void
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
