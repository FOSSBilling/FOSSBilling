<?php

declare(strict_types=1);

namespace Box\Mod\Totp\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    #[RequiredParams(['code' => 'Verification code required'])]
    public function challenge($data): bool
    {
        return $this->getService()->completeChallenge((string) $data['code']);
    }
}
