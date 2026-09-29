<?php

declare(strict_types=1);

namespace Box\Mod\Totp\Api;

use Box\Mod\Staff\Entity\Admin as AdminEntity;
use FOSSBilling\InformationException;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    private function admin(): AdminEntity
    {
        $identity = $this->getIdentity();
        if (!$identity instanceof AdminEntity) {
            throw new InformationException('Staff identity not found.');
        }

        return $identity;
    }

    public function status(): array
    {
        return $this->getService()->status('admin', (int) $this->admin()->getId());
    }

    public function setup(): array
    {
        return $this->getService()->setup('admin', (int) $this->admin()->getId(), (string) $this->admin()->getEmail());
    }

    #[RequiredParams(['code' => 'Verification code required'])]
    public function enable($data): array
    {
        return $this->getService()->enable('admin', (int) $this->admin()->getId(), (string) $data['code']);
    }

    #[RequiredParams(['code' => 'Verification code required'])]
    public function disable($data): bool
    {
        return $this->getService()->disable('admin', (int) $this->admin()->getId(), (string) $data['code']);
    }

    #[RequiredParams(['code' => 'Verification code required'])]
    public function regenerate_recovery_codes($data): array
    {
        return $this->getService()->regenerateRecoveryCodes('admin', (int) $this->admin()->getId(), (string) $data['code']);
    }

    #[RequiredParams(['id' => 'Client ID required'])]
    public function client_status($data): array
    {
        $this->checkPermissions('totp', 'manage');

        return $this->getService()->status('client', (int) $data['id']);
    }

    #[RequiredParams(['id' => 'Client ID required'])]
    public function client_disable($data): bool
    {
        $this->checkPermissions('totp', 'manage');

        return $this->getService()->adminDisable('client', (int) $data['id']);
    }
}
