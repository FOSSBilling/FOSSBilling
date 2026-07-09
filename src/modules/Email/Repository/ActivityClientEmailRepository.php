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

use Box\Mod\Email\Entity\ActivityClientEmail;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ActivityClientEmailRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for the per-client email history listing.
     *
     * Accepted keys in `$data`:
     *  - `id`        (int)     exact id match
     *  - `client_id` (int)     filter to one client
     *  - `sender`    (string)  LIKE on sender
     *  - `recipient` (string)  LIKE on recipients
     *  - `subject`   (string)  LIKE on subject
     *  - `search`    (string)  LIKE on subject, sender, recipients, or content
     *  - `date_from` (string)  created_at lower bound
     *  - `date_to`   (string)  created_at upper bound
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        // Excludes the attachment_content blob: list responses only need has_attachment
        // (derived from attachmentName), and hydrating every PDF for a page of results
        // would pull megabytes into memory just to render a list.
        $qb = $this->createQueryBuilder('e')
            ->select('partial e.{id, clientId, sender, recipients, subject, contentHtml, contentText, attachmentName, attachmentMime, createdAt, updatedAt}')
            ->orderBy('e.id', 'DESC');

        if (!empty($data['id'])) {
            $qb->andWhere('e.id = :id')
                ->setParameter('id', (int) $data['id']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('e.clientId = :client_id')
                ->setParameter('client_id', (int) $data['client_id']);
        }

        if (!empty($data['sender'])) {
            $qb->andWhere('e.sender LIKE :sender')
                ->setParameter('sender', '%' . $data['sender'] . '%');
        }

        if (!empty($data['recipient'])) {
            $qb->andWhere('e.recipients LIKE :recipient')
                ->setParameter('recipient', '%' . $data['recipient'] . '%');
        }

        if (!empty($data['subject'])) {
            $qb->andWhere('e.subject LIKE :subject')
                ->setParameter('subject', '%' . $data['subject'] . '%');
        }

        if (!empty($data['search'])) {
            $qb->andWhere('(e.subject LIKE :search OR e.sender LIKE :search OR e.recipients LIKE :search OR e.contentText LIKE :search OR e.contentHtml LIKE :search)')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        if (!empty($data['date_from'])) {
            $qb->andWhere('e.createdAt >= :date_from')
                ->setParameter('date_from', new \DateTime($data['date_from'] . ' 00:00:00'));
        }

        if (!empty($data['date_to'])) {
            $qb->andWhere('e.createdAt <= :date_to')
                ->setParameter('date_to', new \DateTime($data['date_to'] . ' 23:59:59'));
        }

        return $qb;
    }

    /**
     * Find a single email owned by the given client.
     */
    public function findOneForClientById(int $clientId, int $id): ?ActivityClientEmail
    {
        return $this->findOneBy(['id' => $id, 'clientId' => $clientId]);
    }

    /**
     * Find a single email by id, throwing if it does not exist.
     */
    public function findOneByIdOrFail(int $id): ActivityClientEmail
    {
        $email = $this->find($id);
        if (!$email instanceof ActivityClientEmail) {
            throw new \FOSSBilling\InformationException('Email not found');
        }

        return $email;
    }

    /**
     * Find a single email owned by the given client, throwing if it does not exist.
     */
    public function findOneForClientByIdOrFail(int $clientId, int $id): ActivityClientEmail
    {
        $email = $this->findOneForClientById($clientId, $id);
        if (!$email instanceof ActivityClientEmail) {
            throw new \FOSSBilling\InformationException('Email not found');
        }

        return $email;
    }

    /**
     * @return ActivityClientEmail[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    /**
     * Delete every email log row belonging to a client in a single query.
     *
     * Returns the number of deleted rows.
     */
    public function deleteByClientId(int $clientId): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(ActivityClientEmail::class, 'e')
            ->where('e.clientId = :client_id')
            ->setParameter('client_id', $clientId)
            ->getQuery()
            ->execute();
    }
}
