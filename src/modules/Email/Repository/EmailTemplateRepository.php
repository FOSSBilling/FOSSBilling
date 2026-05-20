<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Repository;

use Box\Mod\Email\Entity\EmailTemplate;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class EmailTemplateRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.category', 'ASC')
            ->addOrderBy('t.actionCode', 'ASC');

        if (!empty($data['code'])) {
            $qb->andWhere('t.actionCode LIKE :code')
               ->setParameter('code', '%' . $data['code'] . '%');
        }

        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';
            $qb->andWhere(
                '(t.actionCode LIKE :search 
                OR COALESCE(t.subject, \'\') LIKE :search 
                OR COALESCE(t.content, \'\') LIKE :search 
                OR COALESCE(t.category, \'\') LIKE :search 
                OR COALESCE(t.description, \'\') LIKE :search)'
            )->setParameter('search', $search);
        }

        return $qb;
    }

    public function findOneByActionCode(string $code): ?EmailTemplate
    {
        return $this->findOneBy(['actionCode' => $code]);
    }

    public function setAllEnabled(bool $enabled): int
    {
        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.enabled', ':enabled')
            ->setParameter('enabled', $enabled)
            ->getQuery()
            ->execute();
    }
}
