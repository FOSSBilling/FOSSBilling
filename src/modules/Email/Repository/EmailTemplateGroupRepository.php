<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Repository;

use Box\Mod\Email\Entity\EmailTemplateGroup;
use Doctrine\ORM\EntityRepository;

class EmailTemplateGroupRepository extends EntityRepository
{
    /**
     * @return int[]
     */
    public function getGroupIdsForTemplate(int $templateId): array
    {
        return array_map(intval(...), $this->createQueryBuilder('g')
            ->select('g.adminGroupId')
            ->andWhere('g.emailTemplate = :template_id')
            ->setParameter('template_id', $templateId)
            ->orderBy('g.adminGroupId', 'ASC')
            ->getQuery()
            ->getSingleColumnResult());
    }

    public function findAssociation(int $templateId, int $groupId): ?EmailTemplateGroup
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.emailTemplate = :template_id')
            ->andWhere('g.adminGroupId = :group_id')
            ->setParameter('template_id', $templateId)
            ->setParameter('group_id', $groupId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteAssociationsForTemplate(int $templateId): int
    {
        return (int) $this->getEntityManager()->getConnection()->delete('email_template_group', ['email_template_id' => $templateId]);
    }

    public function countTemplatesUsingGroup(int $groupId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT email_template_id) FROM email_template_group WHERE admin_group_id = :group_id',
            ['group_id' => $groupId],
        );
    }
}
