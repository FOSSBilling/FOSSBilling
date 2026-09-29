<?php

declare(strict_types=1);

namespace Box\Mod\Totp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'totp_credential')]
#[ORM\UniqueConstraint(name: 'totp_credential_owner_unique', columns: ['owner_type', 'owner_id'])]
class TotpCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_type', type: Types::STRING, length: 16)]
    private string $ownerType = '';

    #[ORM\Column(name: 'owner_id', type: Types::BIGINT)]
    private int $ownerId = 0;

    #[ORM\Column(type: Types::TEXT)]
    private string $secret = '';

    #[ORM\Column(name: 'recovery_codes', type: Types::TEXT)]
    private string $recoveryCodes = '[]';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = false;

    public function getId(): ?int { return $this->id; }
    public function getOwnerType(): string { return $this->ownerType; }
    public function setOwnerType(string $ownerType): self { $this->ownerType = $ownerType; return $this; }
    public function getOwnerId(): int { return $this->ownerId; }
    public function setOwnerId(int $ownerId): self { $this->ownerId = $ownerId; return $this; }
    public function getSecret(): string { return $this->secret; }
    public function setSecret(string $secret): self { $this->secret = $secret; return $this; }
    public function getRecoveryCodes(): array
    {
        $codes = json_decode($this->recoveryCodes, true);
        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }
    public function setRecoveryCodes(array $codes): self
    {
        $this->recoveryCodes = json_encode(array_values($codes), JSON_THROW_ON_ERROR);
        return $this;
    }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
}
