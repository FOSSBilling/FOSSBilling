<?php

declare(strict_types=1);

namespace Box\Mod\System\Repository;

use Box\Mod\System\Entity\Setting;
use Doctrine\ORM\EntityRepository;

class SettingRepository extends EntityRepository
{
    public function findOneByParam(string $param): ?Setting
    {
        $setting = $this->findOneBy(['param' => $param]);

        return $setting instanceof Setting ? $setting : null;
    }
}
