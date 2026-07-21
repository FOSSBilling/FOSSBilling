<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable\Repository;

use Doctrine\ORM\EntityRepository;

class ServiceDownloadableFileRepository extends EntityRepository
{
    public function isStoredFilenameReferenced(string $storedFilename): bool
    {
        return (int) $this->createQueryBuilder('file')
            ->select('COUNT(file.id)')
            ->where('file.storedFilename = :storedFilename')
            ->setParameter('storedFilename', $storedFilename)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
