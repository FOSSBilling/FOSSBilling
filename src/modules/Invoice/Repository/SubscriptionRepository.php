<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Subscription;
use Doctrine\ORM\EntityRepository;

class SubscriptionRepository extends EntityRepository
{
    /**
     * Find a subscription by its gateway subscription id (`sid`).
     * Mirrors the legacy `findOne('Subscription', 'sid = :sid', ...)`.
     */
    public function findOneBySid(string $sid): ?Subscription
    {
        $subscription = $this->findOneBy(['sid' => $sid]);

        return $subscription instanceof Subscription ? $subscription : null;
    }
}
