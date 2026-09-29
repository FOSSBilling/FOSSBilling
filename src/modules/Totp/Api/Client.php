<?php

declare(strict_types=1);

namespace Box\Mod\Totp\Api;

use Box\Mod\Client\Entity\Client as ClientEntity;
use FOSSBilling\InformationException;
use FOSSBilling\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Api\AbstractApi
{
    private function client(): ClientEntity
    {
        $identity = $this->getIdentity();
        if (!$identity instanceof ClientEntity) {
            throw new InformationException('Client identity not found.');
        }

return $identity;
    }

    public function status(): array
    {
        return $this->getService()->status('client', (int) $this->client()->getId());
    }

    public function setup(): array
    {
        return $this->getService()->setup('client', (int) $this->client()->getId(), (string) $this->client()->getEmail());
    }

    #[RequiredParams(['code' => 'Verification code required'])]
    public function enable($data): array
    {
        return $this->getService()->enable('client', (int) $this->client()->getId(), (string) $data['code']);
    }

    #[RequiredParams(['code' => 'Verification code required'])]
    public function disable($data): bool
    {
        return $this->getService()->disable('client', (int) $this->client()->getId(), (string) $data['code']);
    }

    #[RequiredParams(['code' => 'Verification code required'])]
    public function regenerate_recovery_codes($data): array
    {
        return $this->getService()->regenerateRecoveryCodes('client', (int) $this->client()->getId(), (string) $data['code']);
    }
}
