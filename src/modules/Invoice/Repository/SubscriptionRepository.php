<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Subscription;
use Doctrine\ORM\EntityRepository;

class SubscriptionRepository extends EntityRepository
{
    public function findBySId(string $sId): ?Subscription
    {
        $subscription = $this->findOneBy(['sid' => $sId]);

        return $subscription instanceof Subscription ? $subscription : null;
    }

    /**
     * @return Subscription[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }
}
