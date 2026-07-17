<?php

declare(strict_types=1);

namespace Box\Mod\Serviceapikey\Repository;

use Box\Mod\Serviceapikey\Entity\ServiceApiKey;
use Doctrine\ORM\EntityRepository;

class ServiceApiKeyRepository extends EntityRepository
{
    public function findOneByApiKey(string $apiKey): ?ServiceApiKey
    {
        $result = $this->findOneBy(['apiKey' => $apiKey]);

        return $result instanceof ServiceApiKey ? $result : null;
    }
}
