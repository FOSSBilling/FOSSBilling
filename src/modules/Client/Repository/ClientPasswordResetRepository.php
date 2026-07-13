<?php

declare(strict_types=1);

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\ClientPasswordReset;
use Doctrine\ORM\EntityRepository;

class ClientPasswordResetRepository extends EntityRepository
{
    public function findOneByHash(string $hash): ?ClientPasswordReset
    {
        $reset = $this->findOneBy(['hash' => $hash]);

        return $reset instanceof ClientPasswordReset ? $reset : null;
    }
}
