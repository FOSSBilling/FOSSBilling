<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Api;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\Identity\Guest;

final readonly class Identity
{
    private readonly Role $role;

    public function __construct(private readonly object $identity)
    {
        $this->role = self::typeFromObject($identity);
    }

    public static function typeFromObject(object $identity): Role
    {
        return match (true) {
            $identity instanceof Guest => Role::Guest,
            $identity instanceof Client => Role::Client,
            $identity instanceof Admin => Role::Admin,
            default => throw new \InvalidArgumentException(sprintf('Unsupported API identity: %s', $identity::class)),
        };
    }

    public function getIdentity(): object
    {
        return $this->identity;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * @deprecated Use getRole() instead — kept for backwards compatibility, returns Role::value.
     */
    public function getType(): string
    {
        return $this->role->value;
    }
}
