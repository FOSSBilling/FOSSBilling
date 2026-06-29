<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

use Box\Mod\Support\Entity\CannedResponse;
use Box\Mod\Support\Entity\CannedResponseCategory;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Entity\SupportTicketNote;
use Box\Mod\Support\Repository\CannedResponseCategoryRepository;
use Box\Mod\Support\Repository\CannedResponseRepository;
use Box\Mod\Support\Repository\HelpdeskRepository;
use Box\Mod\Support\Repository\KbArticleCategoryRepository;
use Box\Mod\Support\Repository\KbArticleRepository;
use Box\Mod\Support\Repository\SupportTicketMessageRepository;
use Box\Mod\Support\Repository\SupportTicketNoteRepository;
use Box\Mod\Support\Repository\SupportTicketRepository;
use FOSSBilling\InformationException;
use FOSSBilling\Tools;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected ?KbArticleRepository $kbArticleRepository = null;
    protected ?KbArticleCategoryRepository $kbArticleCategoryRepository = null;
    protected ?CannedResponseRepository $cannedResponseRepository = null;
    protected ?CannedResponseCategoryRepository $cannedResponseCategoryRepository = null;
    protected ?HelpdeskRepository $helpdeskRepository = null;
    protected ?SupportTicketRepository $supportTicketRepository = null;
    protected ?SupportTicketMessageRepository $supportTicketMessageRepository = null;
    protected ?SupportTicketNoteRepository $supportTicketNoteRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getKbArticleRepository(): KbArticleRepository
    {
        if ($this->kbArticleRepository === null) {
            $this->kbArticleRepository = $this->di['em']->getRepository(KbArticle::class);
        }

        return $this->kbArticleRepository;
    }

    public function getKbArticleCategoryRepository(): KbArticleCategoryRepository
    {
        if ($this->kbArticleCategoryRepository === null) {
            $this->kbArticleCategoryRepository = $this->di['em']->getRepository(KbArticleCategory::class);
        }

        return $this->kbArticleCategoryRepository;
    }

    public function getCannedResponseRepository(): CannedResponseRepository
    {
        if ($this->cannedResponseRepository === null) {
            $this->cannedResponseRepository = $this->di['em']->getRepository(CannedResponse::class);
        }

        return $this->cannedResponseRepository;
    }

    public function getCannedResponseCategoryRepository(): CannedResponseCategoryRepository
    {
        if ($this->cannedResponseCategoryRepository === null) {
            $this->cannedResponseCategoryRepository = $this->di['em']->getRepository(CannedResponseCategory::class);
        }

        return $this->cannedResponseCategoryRepository;
    }

    public function getSupportTicketRepository(): SupportTicketRepository
    {
        if ($this->supportTicketRepository === null) {
            $this->supportTicketRepository = $this->di['em']->getRepository(SupportTicket::class);
        }

        return $this->supportTicketRepository;
    }

    public function getSupportTicketMessageRepository(): SupportTicketMessageRepository
    {
        if ($this->supportTicketMessageRepository === null) {
            $this->supportTicketMessageRepository = $this->di['em']->getRepository(SupportTicketMessage::class);
        }

        return $this->supportTicketMessageRepository;
    }

    public function getSupportTicketNoteRepository(): SupportTicketNoteRepository
    {
        if ($this->supportTicketNoteRepository === null) {
            $this->supportTicketNoteRepository = $this->di['em']->getRepository(SupportTicketNote::class);
        }

        return $this->supportTicketNoteRepository;
    }

    public function getHelpdeskRepository(): HelpdeskRepository
    {
        if ($this->helpdeskRepository === null) {
            $this->helpdeskRepository = $this->di['em']->getRepository(Helpdesk::class);
        }

        return $this->helpdeskRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View support tickets'),
                'description' => __trans('Allows the staff member to view tickets, inquiries, helpdesks, canned responses, and knowledge base articles.'),
            ],
            'manage_tickets' => [
                'type' => 'bool',
                'display_name' => __trans('Manage tickets'),
                'description' => __trans('Allows the staff member to create, update, reply to, close, and delete tickets and inquiries.'),
            ],
            'manage_helpdesk' => [
                'type' => 'bool',
                'display_name' => __trans('Manage helpdesks'),
                'description' => __trans('Allows the staff member to create, update, and delete helpdesks.'),
            ],
            'manage_canned' => [
                'type' => 'bool',
                'display_name' => __trans('Manage canned responses'),
                'description' => __trans('Allows the staff member to create, update, and delete canned responses and categories.'),
            ],
            'manage_kb' => [
                'type' => 'bool',
                'display_name' => __trans('Manage knowledge base'),
                'description' => __trans('Allows the staff member to create, update, and delete knowledge base articles and categories.'),
            ],
            'manage_settings' => [],
        ];
    }

    public static function onAfterClientOpenTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');

        try {
            $ticketObj = $supportService->getTicketById((int) $params['id']);
            $isGuestTicket = $supportService->isGuestTicket($ticketObj);
            $identity = $isGuestTicket ? null : $di['loggedin_client'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            if ($isGuestTicket) {
                $email['to'] = $ticketObj->getAuthorEmail();
                $email['to_name'] = $ticketObj->getAuthorName();
            } else {
                $email['to_client'] = $ticketObj->getClientId();
            }
            $email['code'] = 'mod_support_ticket_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send ticket open email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminOpenTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getTicketById((int) $params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            if ($supportService->isGuestTicket($ticketObj)) {
                $email['to'] = $ticketObj->author_email;
                $email['to_name'] = $ticketObj->author_name;
            } else {
                $email['to_client'] = $ticketObj->client_id;
            }
            $email['code'] = 'mod_support_ticket_staff_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send admin ticket open email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminCloseTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $identity = $di['loggedin_admin'];
            $ticketObj = $supportService->getTicketById((int) $params['id']);
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            if ($supportService->isGuestTicket($ticketObj)) {
                $email['to'] = $ticketObj->author_email;
                $email['to_name'] = $ticketObj->author_name;
            } else {
                $email['to_client'] = $ticketObj->client_id;
            }
            $email['code'] = 'mod_support_ticket_staff_close';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send ticket close email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminReplyTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getTicketById((int) $params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            if ($supportService->isGuestTicket($ticketObj)) {
                $email['to'] = $ticketObj->author_email;
                $email['to_name'] = $ticketObj->author_name;
            } else {
                $email['to_client'] = $ticketObj->client_id;
            }
            $email['code'] = 'mod_support_ticket_staff_reply';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send ticket reply email', ['exception' => $exc->getMessage()]);
        }
    }

    public function getTicketById(int $id): SupportTicket
    {
        $ticket = $this->di['em']->find(SupportTicket::class, $id);
        if (!$ticket instanceof SupportTicket) {
            throw new \FOSSBilling\Exception('Ticket not found');
        }

        return $ticket;
    }

    public function isGuestTicket(SupportTicket $ticket): bool
    {
        return $ticket->getClientId() === null && $ticket->getAccessHash() !== null;
    }

    /**
     * Return array of ticket statuses.
     */
    public function getStatuses(): array
    {
        return [
            SupportTicket::STATUS_OPEN => 'Open',
            SupportTicket::STATUS_ONHOLD => 'On Hold',
            SupportTicket::STATUS_CLOSED => 'Closed',
        ];
    }

    /**
     * Find ticket for client.
     */
    public function findOneByClient(\Model_Client $c, int $id): SupportTicket
    {
        $ticket = $this->getSupportTicketRepository()->findOneByClient((int) $c->id, $id);
        if (!$ticket instanceof SupportTicket) {
            throw new \FOSSBilling\Exception('Ticket not found');
        }

        return $ticket;
    }

    public function getSearchQuery(array $data): array
    {
        $query = 'SELECT st.*
                FROM support_ticket st
                JOIN support_ticket_message stm ON stm.support_ticket_id = st.id
                LEFT JOIN client c ON st.client_id = c.id';

        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        $client = $data['client'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $order_id = $data['order_id'] ?? null;
        $subject = $data['subject'] ?? null;
        $content = $data['content'] ?? null;
        $helpdesk = $data['support_helpdesk_id'] ?? null;
        $created_at = $data['created_at'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $priority = $data['priority'] ?? null;
        $auth = $data['auth'] ?? null;
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;

        $where = [];
        $bindings = [];

        if ($id) {
            $where[] = 'st.id = :ticket_id';
            $bindings[':ticket_id'] = $id;
        }

        if ($priority) {
            $where[] = 'st.priority = :priority';
            $bindings[':priority'] = $priority;
        }

        if ($helpdesk) {
            $where[] = 'st.support_helpdesk_id = :support_helpdesk_id';
            $bindings[':support_helpdesk_id'] = $helpdesk;
        }

        if ($client) {
            $where[] = 'c.first_name LIKE :first_name';
            $where[] = 'c.last_name LIKE :last_name';
            $bindings[':first_name'] = '%' . $client . '%';
            $bindings[':last_name'] = '%' . $client . '%';
        }

        if ($client_id) {
            $where[] = 'c.id = :client_id';
            $bindings[':client_id'] = $client_id;
        }

        if ($auth === 'guest') {
            $where[] = 'st.client_id IS NULL AND st.access_hash IS NOT NULL';
        } elseif ($auth === 'client') {
            $where[] = 'st.client_id IS NOT NULL';
        }

        if ($name) {
            $where[] = 'st.author_name LIKE :filter_author_name';
            $bindings[':filter_author_name'] = '%' . $name . '%';
        }

        if ($email) {
            $where[] = 'st.author_email LIKE :filter_author_email';
            $bindings[':filter_author_email'] = '%' . $email . '%';
        }

        if ($content) {
            $where[] = 'stm.content LIKE :content';
            $bindings[':content'] = '%' . $content . '%';
        }

        if ($subject) {
            $where[] = 'st.subject LIKE :subject';
            $bindings[':subject'] = '%' . $subject . '%';
        }

        if ($status) {
            $where[] = 'st.status = :status';
            $bindings[':status'] = $status;
        }

        if ($order_id) {
            $where[] = 'st.rel_type = :rel_type AND st.rel_id = :rel_id';
            $bindings[':rel_type'] = SupportTicket::REL_TYPE_ORDER;
            $bindings[':rel_id'] = $order_id;
        }

        if ($created_at) {
            $where[] = "DATE_FORMAT(st.created_at, '%Y-%m-%d') = :created_at";
            $bindings[':created_at'] = date('Y-m-d', strtotime((string) $created_at));
        }

        if ($date_from) {
            $where[] = 'UNIX_TIMESTAMP(st.created_at) >= :date_from';
            $bindings[':date_from'] = strtotime((string) $date_from);
        }

        if ($date_to) {
            $where[] = 'UNIX_TIMESTAMP(st.created_at) <= :date_to';
            $bindings[':date_to'] = strtotime((string) $date_to);
        }
        // smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[] = 'st.id = :ticket_id';
                $bindings[':ticket_id'] = $search;
            } else {
                $search = '%' . $search . '%';
                $where[] = '(stm.content LIKE :content OR st.subject LIKE :subject OR st.author_name LIKE :author_name OR st.author_email LIKE :author_email)';
                $bindings[':content'] = $search;
                $bindings[':subject'] = $search;
                $bindings[':author_name'] = $search;
                $bindings[':author_email'] = $search;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' GROUP BY st.id ORDER BY st.priority ASC, st.id DESC';

        return [$query, $bindings];
    }

    public function counter(): array
    {
        $query = 'SELECT status, COUNT(id) as counter
                    FROM support_ticket
                    GROUP BY status';

        $data = $this->di['db']->getAssoc($query);

        return [
            'total' => array_sum($data),
            SupportTicket::STATUS_OPEN => $data[SupportTicket::STATUS_OPEN] ?? 0,
            SupportTicket::STATUS_CLOSED => $data[SupportTicket::STATUS_CLOSED] ?? 0,
            SupportTicket::STATUS_ONHOLD => $data[SupportTicket::STATUS_ONHOLD] ?? 0,
        ];
    }

    public function getLatest(): array
    {
        return $this->di['db']->find('SupportTicket', 'ORDER BY id DESC LIMIT 10');
    }

    public function getExpired(): array
    {
        $bindings = [
            ':status' => SupportTicket::STATUS_ONHOLD,
        ];

        $sql = 'SELECT st.*
                FROM support_ticket as st
                    LEFT JOIN support_helpdesk sh ON sh.id = st.support_helpdesk_id
                WHERE st.status = :status
                AND DATE_ADD(st.updated_at, INTERVAL sh.close_after HOUR) < NOW()
                ORDER BY st.id ASC';

        return $this->di['db']->getAll($sql, $bindings);
    }

    public function countByStatus(string $status): int
    {
        $query = 'SELECT COUNT(id) as counter FROM support_ticket
                WHERE status = :status GROUP BY status LIMIT 1';

        return (int) $this->di['db']->getCell($query, [':status' => $status]);
    }

    public function getActiveTicketsCountForOrder(\Model_ClientOrder $model): int
    {
        return $this->getSupportTicketRepository()->countActiveTicketsForOrder((int) $model->id);
    }

    public function checkIfTaskAlreadyExists(\Model_Client $client, int $rel_id, string $rel_type, string $rel_task): bool
    {
        return $this->getSupportTicketRepository()->findOneBy([
            'clientId' => (int) $client->id,
            'relId' => $rel_id,
            'relType' => $rel_type,
            'relTask' => $rel_task,
            'relStatus' => SupportTicket::REL_STATUS_PENDING,
        ]) instanceof SupportTicket;
    }

    public function closeTicket(SupportTicket $ticket, \Model_Admin|\Model_Client|\Model_Guest $identity): bool
    {
        $ticket->setStatus(SupportTicket::STATUS_CLOSED);
        $this->di['em']->flush();

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminCloseTicket', 'params' => ['id' => $ticket->getId()]]);
        } else {
            $this->di['events_manager']->fire(['event' => 'onAfterClientCloseTicket', 'params' => ['id' => $ticket->getId()]]);
        }

        $this->di['logger']->info('Closed ticket "%s"', $ticket->getId());

        return true;
    }

    public function autoClose(SupportTicket $model): bool
    {
        $model->setStatus(SupportTicket::STATUS_CLOSED);
        $this->di['em']->flush();
        $this->di['logger']->info('Ticket %s was closed', $model->getId());

        return true;
    }

    public function canBeReopened(SupportTicket $model): bool
    {
        if ($model->getStatus() !== SupportTicket::STATUS_CLOSED) {
            return true;
        }

        $helpdeskId = $model->getSupportHelpdeskId();
        $helpdesk = $helpdeskId !== null ? $this->getHelpdeskRepository()->find($helpdeskId) : null;
        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\Exception('Helpdesk invalid');
        }

        return $helpdesk->canReopen();
    }

    /**
     * @return mixed[]
     */
    private function _getRelDetails(SupportTicket $model): array
    {
        $result = [
            'id' => $model->getRelId() ?: null,
            'type' => $model->getRelType() ?: null,
            'task' => $model->getRelTask() ?: null,
            'new_value' => $model->getRelNewValue() ?: null,
            'status' => $model->getRelStatus() ?: null,
        ];

        if (!$model->getRelType() || !$model->getRelId()) {
            return $result;
        }

        if ($model->getRelType() === SupportTicket::REL_TYPE_ORDER) {
            $clientId = $model->getClientId();
            $client = $clientId !== null ? $this->di['db']->load('Client', $clientId) : null;
            $orderService = $this->di['mod_service']('order');
            $o = $client instanceof \Model_Client ? $orderService->findForClientById($client, $model->getRelId()) : null;
            if ($o instanceof \Model_ClientOrder) {
                $result['order'] = $orderService->toApiArray($o, false);
            }
        }

        return $result;
    }

    public function rmByClient(\Model_Client $client): void
    {
        $em = $this->di['em'];
        foreach ($this->getSupportTicketRepository()->findBy(['clientId' => (int) $client->id]) as $ticket) {
            $em->remove($ticket);
        }
        $em->flush();
    }

    public function rm(SupportTicket $model): bool
    {
        $em = $this->di['em'];
        $id = $model->getId();

        foreach ($this->getSupportTicketNoteRepository()->findByTicketId($id ?? 0) as $note) {
            $em->remove($note);
        }
        foreach ($this->getSupportTicketMessageRepository()->findByTicketId($id ?? 0) as $message) {
            $em->remove($message);
        }

        $em->remove($model);
        $em->flush();

        $this->di['logger']->info('Removed ticket "%s"', $id);

        return true;
    }

    public function toApiArray(SupportTicket $model, bool $deep = true, \Model_Admin|\Model_Client|null $identity = null): array
    {
        $firstSupportTicketMessage = $this->getSupportTicketMessageRepository()->findFirstByTicketId($model->getId() ?? 0);
        $helpdeskId = $model->getSupportHelpdeskId();
        $helpdesk = $helpdeskId !== null ? $this->getHelpdeskRepository()->find($helpdeskId) : null;

        $data = $this->ticketToApiArray($this->entityToArray($model), $identity);
        $data['replies'] = $this->messageGetRepliesCount($model);
        $data['first'] = $firstSupportTicketMessage instanceof SupportTicketMessage ? $this->messageToApiArray($firstSupportTicketMessage, true, $identity) : null;
        $data['helpdesk'] = $helpdesk instanceof Helpdesk ? $helpdesk->toApiArray($identity) : null;
        $data['author'] = $this->getTicketAuthor($model, $identity);

        // @deprecated 0.9.0 Use author instead.
        $data['client'] = $this->getClientApiArrayForTicket($model, $identity);

        if ($deep) {
            $messages = $this->messageGetTicketMessages($model);
            foreach ($messages as $msg) {
                $data['messages'][] = $this->messageToApiArray($msg, true, $identity);
            }
        }

        if ($identity instanceof \Model_Admin) {
            $data['rel'] = $this->_getRelDetails($model);
            $data['priority'] = $model->getPriority();
            $data['notes'] = [];
            $supportTicketNotes = $this->getSupportTicketNoteRepository()->findByTicketId($model->getId() ?? 0);

            foreach ($supportTicketNotes as $note) {
                $data['notes'][] = $this->noteToApiArray($note);
            }
        }

        return $data;
    }

    private function entityToArray(SupportTicket $model): array
    {
        return [
            'id' => $model->getId(),
            'support_helpdesk_id' => $model->getSupportHelpdeskId(),
            'client_id' => $model->getClientId(),
            'author_name' => $model->getAuthorName(),
            'author_email' => $model->getAuthorEmail(),
            'subject' => $model->getSubject(),
            'status' => $model->getStatus(),
            'priority' => $model->getPriority(),
            'access_hash' => $model->getAccessHash(),
            'rel_type' => $model->getRelType(),
            'rel_id' => $model->getRelId(),
            'rel_task' => $model->getRelTask(),
            'rel_new_value' => $model->getRelNewValue(),
            'rel_status' => $model->getRelStatus(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function ticketToApiArray(array $data, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if (!empty($data['access_hash'])) {
            $data['hash'] = $data['access_hash'];
        }

        if ($identity instanceof \Model_Admin) {
            return $data;
        }

        // @deprecated 0.9.0 Use author.id/name/email instead of client_id/author_name/author_email.

        unset(
            $data['support_helpdesk_id'],
            $data['client_id'],
            $data['priority'],
            $data['access_hash'],
            $data['rel_type'],
            $data['rel_id'],
            $data['rel_task'],
            $data['rel_new_value'],
            $data['rel_status']
        );

        return $data;
    }

    /**
     * Get multiple tickets in a batch for API response.
     *
     * @param array                           $ids      Array of ticket IDs to fetch
     * @param bool                            $deep     Whether to include full message history
     * @param \Model_Admin|\Model_Client|null $identity The requesting identity
     *
     * @return array Array of ticket API arrays. Missing IDs are silently skipped.
     */
    public function getBatchForApi(array $ids, bool $deep = false, $identity = null): array
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return [];
        }

        if ($deep || $identity instanceof \Model_Admin) {
            return $this->getBatchForApiWithModels($ids, $deep, $identity);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $tickets = $this->di['db']->getAll("SELECT * FROM support_ticket WHERE id IN ($placeholders)", $ids);
        if (empty($tickets)) {
            return [];
        }

        $tickets = $this->orderRowsByIds($tickets, $ids);
        $ticketIds = array_column($tickets, 'id');
        $helpdeskIds = $this->normalizeIds(array_column($tickets, 'support_helpdesk_id'));
        $clientIds = $this->normalizeIds(array_column($tickets, 'client_id'));

        $replyCounts = [];
        if (!empty($ticketIds)) {
            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
            $countRows = $this->di['db']->getAll(
                "SELECT support_ticket_id, COUNT(id) as counter
                FROM support_ticket_message
                WHERE support_ticket_id IN ($placeholders)
                GROUP BY support_ticket_id",
                $ticketIds
            );
            foreach ($countRows as $row) {
                $replyCounts[$row['support_ticket_id']] = (int) $row['counter'];
            }
        }

        $firstMessages = [];
        if (!empty($ticketIds)) {
            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
            $rows = $this->di['db']->getAll(
                "SELECT support_ticket_id, MIN(id) as message_id
                FROM support_ticket_message
                WHERE support_ticket_id IN ($placeholders)
                GROUP BY support_ticket_id",
                $ticketIds
            );
            $messageIds = array_column($rows, 'message_id');
            if (!empty($messageIds)) {
                $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
                $messages = $this->di['db']->find('SupportTicketMessage', "id IN ($placeholders)", $messageIds);
                foreach ($messages as $message) {
                    $firstMessages[$message->support_ticket_id] = $message;
                }
            }
        }

        $helpdesks = [];
        if (!empty($helpdeskIds)) {
            $helpdeskModels = $this->getHelpdeskRepository()->findBy(['id' => $helpdeskIds]);
            foreach ($helpdeskModels as $helpdesk) {
                $helpdesks[$helpdesk->getId()] = $helpdesk;
            }
        }

        $clients = [];
        $clientAuthors = [];
        if (!empty($clientIds)) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $clientModels = $this->di['db']->find('Client', "id IN ($placeholders)", $clientIds);
            foreach ($clientModels as $client) {
                $clients[$client->id] = $this->clientToTicketApiArray($client, $identity);
                $clientAuthors[$client->id] = $this->clientToTicketAuthorArray($client);
            }
        }

        $result = [];
        foreach ($tickets as $ticket) {
            $data = $this->ticketToApiArray($ticket, $identity);
            $data['replies'] = $replyCounts[$ticket['id']] ?? 0;
            $data['first'] = isset($firstMessages[$ticket['id']]) ? $this->messageToApiArray($firstMessages[$ticket['id']], true, $identity) : null;

            $helpdesk = $helpdesks[$ticket['support_helpdesk_id']] ?? null;
            $data['helpdesk'] = $helpdesk instanceof Helpdesk ? $helpdesk->toApiArray($identity) : null;

            if (empty($ticket['client_id']) && !empty($ticket['access_hash'])) {
                $data['author'] = [
                    'name' => $ticket['author_name'],
                    'email' => $ticket['author_email'],
                    'role' => 'guest',
                ];
                // @deprecated 0.9.0 Use author instead.
                $data['client'] = [];
            } elseif (!isset($clients[$ticket['client_id']])) {
                $this->di['logger']->error('Missing client for ticket ' . $ticket['id']);
                $data['author'] = [];
                // @deprecated 0.9.0 Use author instead.
                $data['client'] = [];
            } else {
                $data['author'] = $clientAuthors[$ticket['client_id']];
                // @deprecated 0.9.0 Use author instead.
                $data['client'] = $clients[$ticket['client_id']];
            }

            $result[] = $data;
        }

        return $result;
    }

    private function getBatchForApiWithModels(array $ids, bool $deep, $identity): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $tickets = $this->di['db']->find('SupportTicket', "id IN ($placeholders)", $ids);
        if (empty($tickets)) {
            return [];
        }

        $ticketsById = [];
        foreach ($tickets as $ticket) {
            $ticketsById[$ticket->id] = $ticket;
        }

        $result = [];
        foreach ($ids as $id) {
            if (isset($ticketsById[$id])) {
                $result[] = $this->toApiArray($ticketsById[$id], $deep, $identity);
            }
        }

        return $result;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map(intval(...), array_filter($ids, is_numeric(...)))));
    }

    private function orderRowsByIds(array $rows, array $ids): array
    {
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($rowsById[$id])) {
                $ordered[] = $rowsById[$id];
            }
        }

        return $ordered;
    }

    private function getClientApiArrayForTicket(SupportTicket $ticket, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($this->isGuestTicket($ticket)) {
            return [];
        }

        $clientId = $ticket->getClientId();
        $client = $clientId !== null ? $this->di['db']->load('Client', $clientId) : null;

        if ($client instanceof \Model_Client) {
            return $this->clientToTicketApiArray($client, $identity);
        }
        $this->di['logger']->error('Missing client for ticket ' . $ticket->getId());

        return [];
    }

    private function getTicketAuthor(SupportTicket $ticket, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($this->isGuestTicket($ticket)) {
            $author = [
                'name' => $ticket->getAuthorName(),
                'role' => 'guest',
            ];

            if ($identity instanceof \Model_Admin || $identity === null) {
                $author['email'] = $ticket->getAuthorEmail();
            }

            return $author;
        }

        $clientId = $ticket->getClientId();
        $client = $clientId !== null ? $this->di['db']->load('Client', $clientId) : null;

        if ($client instanceof \Model_Client) {
            return $this->clientToTicketAuthorArray($client);
        }
        $this->di['logger']->error('Missing client for ticket ' . $ticket->getId());

        return [];
    }

    private function clientToTicketAuthorArray(\Model_Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->getFullName(),
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'role' => 'client',
        ];
    }

    private function clientToTicketApiArray(\Model_Client $client, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($identity instanceof \Model_Admin) {
            $clientService = $this->di['mod_service']('client');

            return $clientService->toApiArray($client, false, $identity);
        }

        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
        ];
    }

    public function noteGetAuthorDetails(SupportTicketNote $model): array
    {
        $adminId = $model->getAdminId();
        $admin = $adminId !== null ? $this->di['db']->load('Admin', $adminId) : null;

        return [
            'name' => $admin instanceof \Model_Admin ? $admin->getFullName() : null,
            'email' => $admin instanceof \Model_Admin ? $admin->email : null,
        ];
    }

    public function noteRm(SupportTicketNote $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed note #%s', $id);

        return true;
    }

    public function noteToApiArray(SupportTicketNote $model, bool $deep = false, \Model_Admin|\Model_Client|null $identity = null): array
    {
        $data = [
            'id' => $model->getId(),
            'support_ticket_id' => $model->getSupportTicket()?->getId(),
            'admin_id' => $model->getAdminId(),
            'note' => $model->getNote(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $data['author'] = $this->noteGetAuthorDetails($model);

        return $data;
    }

    public function helpdeskRm(Helpdesk $model): bool
    {
        $id = $model->getId();

        if ($id !== null && $this->getHelpdeskRepository()->countTickets($id) > 0) {
            throw new InformationException('Cannot remove helpdesk which has tickets');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted helpdesk #%s', $id);

        return true;
    }

    public function messageGetTicketMessages(SupportTicket $model): array
    {
        return $this->getSupportTicketMessageRepository()->findByTicketId($model->getId() ?? 0);
    }

    public function messageGetRepliesCount(SupportTicket $model): int
    {
        return $this->getSupportTicketMessageRepository()->countByTicketId($model->getId() ?? 0);
    }

    public function messageGetAuthorDetails(SupportTicketMessage $model, \Model_Admin|\Model_Client|null $identity = null): array
    {
        $adminId = $model->getAdminId();
        $clientId = $model->getClientId();
        $ticket = $model->getSupportTicket();

        if ($adminId) {
            $author = $this->di['db']->load('Admin', $adminId);
            $role = 'admin';
        } elseif ($clientId) {
            $author = $this->di['db']->load('Client', $clientId);
            $role = 'client';
        } else {
            if ($ticket instanceof SupportTicket && $this->isGuestTicket($ticket)) {
                return [
                    'name' => $ticket->getAuthorName(),
                    'email' => $ticket->getAuthorEmail(),
                    'role' => 'guest',
                ];
            }

            return [];
        }

        if (!$author) {
            return [];
        }

        $result = [
            'name' => $author->getFullName(),
            'role' => $role,
        ];

        if ($identity instanceof \Model_Admin) {
            $result['email'] = $author->email;
        }

        return $result;
    }

    public function messageToApiArray(SupportTicketMessage $model, bool $deep = true, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($identity instanceof \Model_Admin) {
            $data = [
                'id' => $model->getId(),
                'support_ticket_id' => $model->getSupportTicket()?->getId(),
                'client_id' => $model->getClientId(),
                'admin_id' => $model->getAdminId(),
                'content' => $model->getContent(),
                'attachment' => $model->getAttachment(),
                'ip' => $model->getIp(),
                'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        } else {
            $data = [
                'id' => $model->getId(),
                'content' => $model->getContent(),
                'attachment' => $model->getAttachment(),
                'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $data['author'] = $this->messageGetAuthorDetails($model, $identity);

        return $data;
    }

    public function ticketUpdate(SupportTicket $model, array $data): bool
    {
        if (isset($data['support_helpdesk_id'])) {
            $helpdesk = $this->getHelpdeskRepository()->find((int) $data['support_helpdesk_id']);
            if ($helpdesk instanceof Helpdesk) {
                $model->setSupportHelpdesk($helpdesk);
            }
        }
        if (isset($data['status'])) {
            $model->setStatus($data['status']);
        }
        if (isset($data['subject'])) {
            $model->setSubject($data['subject']);
        }
        if (isset($data['priority'])) {
            $model->setPriority((int) $data['priority']);
        }
        $model->setUpdatedAt(new \DateTime());

        $this->di['em']->flush();

        $this->di['logger']->info('Updated ticket #%s', $model->getId());

        return true;
    }

    public function ticketMessageUpdate(SupportTicketMessage $model, string $content): bool
    {
        $model->setContent($content);
        $model->setUpdatedAt(new \DateTime());

        $this->di['em']->flush();

        return true;
    }

    /**
     * @param \Model_Admin $identity
     */
    public function ticketReply(SupportTicket $ticket, \Model_Admin|\Model_Client|\Model_Guest $identity, string $content): int
    {
        $em = $this->di['em'];
        $msg = new SupportTicketMessage();
        $msg->setSupportTicket($ticket);
        if ($identity instanceof \Model_Admin) {
            $msg->setAdminId((int) $identity->id);
        } elseif ($identity instanceof \Model_Client) {
            $msg->setClientId((int) $identity->id);
        }
        $msg->setContent($content);
        $msg->setIp($this->di['request']->getClientIp());
        $em->persist($msg);
        $em->flush();

        if ($identity instanceof \Model_Admin) {
            $ticket->setStatus(SupportTicket::STATUS_ONHOLD);
        } else {
            $ticket->setStatus(SupportTicket::STATUS_OPEN);
        }
        $ticket->setUpdatedAt(new \DateTime());
        $em->flush();

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminReplyTicket', 'params' => ['id' => $ticket->getId()]]);
        } else {
            $this->di['events_manager']->fire(['event' => 'onAfterClientReplyTicket', 'params' => ['id' => $ticket->getId()]]);
        }

        $this->di['logger']->info('Replied to ticket "%s"', $ticket->getId());

        return (int) $msg->getId();
    }

    public function ticketCreateForAdmin(\Model_Client $client, Helpdesk $helpdesk, array $data, \Model_Admin $identity): int
    {
        $status = $data['status'] ?? SupportTicket::STATUS_ONHOLD;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOpenTicket', 'params' => $data]);

        $em = $this->di['em'];
        $ticket = new SupportTicket();
        $ticket->setClientId((int) $client->id);
        $ticket->setStatus($status);
        $ticket->setSubject($data['subject']);
        $ticket->setSupportHelpdesk($helpdesk);
        $em->persist($ticket);
        $em->flush();

        $msg = new SupportTicketMessage();
        $msg->setAdminId((int) $identity->id);
        $msg->setSupportTicket($ticket);
        $msg->setContent($data['content']);
        $msg->setIp($this->di['request']->getClientIp());
        $em->persist($msg);
        $em->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOpenTicket', 'params' => ['id' => $ticket->getId()]]);

        $this->di['logger']->info('Admin opened new ticket "%s"', $ticket->getId());

        return (int) $ticket->getId();
    }

    public function ticketCreateForGuest(array $data): string
    {
        if (!$this->guestTicketsEnabled()) {
            throw new InformationException("We currently aren't accepting support tickets from unregistered users. Please use another contact method.");
        }

        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);
        $data['content'] ??= $data['message'] ?? null;

        SupportTicketValidator::validateTicketCreation($data);

        $event_params = $data;
        $event_params['author_role'] = 'guest';
        $event_params['ip'] = $this->di['request']->getClientIp();
        $altered = $this->di['events_manager']->fire(['event' => 'onBeforeClientOpenTicket', 'params' => $event_params]);

        $status = 'open';
        $subject = $data['subject'] ?? null;
        $message = $data['content'] ?? null;

        if (is_array($altered)) {
            $status = $altered['status'] ?? null;
            $subject = $altered['subject'] ?? null;
            $message = $altered['content'] ?? $altered['message'] ?? null;
        }

        $helpdesk = isset($data['support_helpdesk_id'])
            ? $this->getHelpdeskRepository()->find((int) $data['support_helpdesk_id'])
            : $this->getHelpdeskRepository()->getDefault();

        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\Exception('Helpdesk invalid');
        }

        $em = $this->di['em'];
        $ticket = new SupportTicket();
        $ticket->setAccessHash(bin2hex(random_bytes(random_int(15, 30))));
        $ticket->setSupportHelpdesk($helpdesk);
        $ticket->setAuthorName($data['name']);
        $ticket->setAuthorEmail($data['email']);
        $ticket->setSubject($subject);
        $ticket->setStatus($status);
        $em->persist($ticket);
        $em->flush();

        $msg = new SupportTicketMessage();
        $msg->setSupportTicket($ticket);
        $msg->setContent($message);
        $msg->setIp($this->di['request']->getClientIp());
        $em->persist($msg);
        $em->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterClientOpenTicket', 'params' => ['id' => $ticket->getId()]]);

        $this->di['logger']->info('"%s" opened guest ticket "%s"', $ticket->getAuthorEmail(), $ticket->getId());

        return $ticket->getAccessHash();
    }

    public function guestTicketsEnabled(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        // @deprecated 0.9.0 Use disable_guest_tickets instead.
        $disableGuestTickets = $config['disable_guest_tickets'] ?? $config['disable_public_tickets'] ?? false;

        return !$disableGuestTickets;
    }

    public function canClientSubmitNewTicket(\Model_Client $client, array $config): bool
    {
        $hours = $config['wait_hours'];

        $lastTicket = $this->di['db']->findOne('SupportTicket', 'client_id = :client_id ORDER BY created_at DESC', [':client_id' => $client->id]);
        if (!$lastTicket instanceof SupportTicket) {
            return true;
        }

        $createdAt = $lastTicket->getCreatedAt();
        $createdAtStr = $createdAt?->format('Y-m-d H:i:s');
        $timeSinceLast = $createdAtStr !== null ? round(abs(strtotime($createdAtStr) - strtotime(date('Y-m-d H:i:s'))) / 3600, 0) : 0;

        if ($timeSinceLast < $hours) {
            throw new InformationException(sprintf('You can submit one ticket per %s hours. %s hours left', $hours, $hours - $timeSinceLast));
        }

        return true;
    }

    public function ticketCreateForClient(\Model_Client $client, Helpdesk $helpdesk, array $data): int
    {
        SupportTicketValidator::validateTicketCreation($data);

        if (isset($data['rel_id'])) {
            if (filter_var($data['rel_id'], FILTER_VALIDATE_INT) === false) {
                throw new \FOSSBilling\Exception('rel_id must be a valid integer, received: :value', [':value' => $data['rel_id']]);
            }
            $rel_id = (int) $data['rel_id'];
        } else {
            $rel_id = null;
        }

        $rel_type = $data['rel_type'] ?? null;

        $rel_task = $data['rel_task'] ?? null;
        $rel_new_value = $data['rel_new_value'] ?? null;
        $rel_status = isset($data['rel_task']) ? SupportTicket::REL_STATUS_PENDING : SupportTicket::REL_STATUS_COMPLETE;

        $order = null;
        if ($rel_id !== null && $rel_type === SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $order = $orderService->findForClientById($client, $rel_id);
            if (!$order instanceof \Model_ClientOrder) {
                throw new \FOSSBilling\Exception('You do not have permission to reference this order.');
            }
        }

        if ($rel_task === SupportTicket::REL_TASK_UPGRADE) {
            if (!$order instanceof \Model_ClientOrder) {
                throw new \FOSSBilling\Exception('You must provide both an order ID and a new product ID in order to request an upgrade.');
            }

            if (filter_var($rel_new_value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new \FOSSBilling\Exception('rel_new_value must be a valid positive integer product ID, received: :value', [':value' => $rel_new_value]);
            }

            $productService = $this->di['mod_service']('product');
            $productService->assertUpgradeAllowedByIds((int) $order->product_id, (int) $rel_new_value);
        }

        // check if support ticket with same uncompleted task already exists
        if ($rel_id && $rel_type && $rel_task && $this->checkIfTaskAlreadyExists($client, $rel_id, $rel_type, $rel_task)) {
            throw new InformationException('We have already received this request.');
        }

        $mod = $this->di['mod']('support');
        $config = $mod->getConfig();

        if (isset($config['wait_hours']) && is_numeric($config['wait_hours'])) {
            $this->canClientSubmitNewTicket($client, $config);
        }

        $event_params = $data;
        $event_params['author_role'] = 'client';
        $event_params['client_id'] = $client->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeClientOpenTicket', 'params' => $event_params]);

        $ticket = new SupportTicket();
        $ticket->setClientId((int) $client->id);
        $ticket->setSubject($data['subject']);
        $ticket->setSupportHelpdesk($helpdesk);

        // related task with ticket
        $ticket->setRelId($rel_id);
        $ticket->setRelType($rel_type);
        $ticket->setRelTask($rel_task);
        $ticket->setRelNewValue($rel_new_value);
        $ticket->setRelStatus($rel_status);

        $this->di['em']->persist($ticket);
        $this->di['em']->flush();

        $this->messageCreateForTicket($ticket, $client, $data['content']);

        $this->di['events_manager']->fire(['event' => 'onAfterClientOpenTicket', 'params' => ['id' => $ticket->getId()]]);

        if (
            isset($config['autorespond_enable'])
            && $config['autorespond_enable']
            && isset($config['autorespond_message_id'])
            && !empty($config['autorespond_message_id'])
        ) {
            $this->sendAutoresponderCannedReply($ticket, $config['autorespond_message_id']);
        }

        $this->di['logger']->info('Submitted new ticket "%s"', $ticket->getId());

        return (int) $ticket->getId();
    }

    private function sendAutoresponderCannedReply(SupportTicket $ticket, $cannedId): void
    {
        try {
            $cannedResponse = $this->getCannedResponseRepository()->find((int) $cannedId);

            if (!$cannedResponse instanceof CannedResponse) {
                $this->di['logger']->warning('Autoresponder: canned response #%s not found, skipping reply for ticket #%s', $cannedId, $ticket->getId());

                return;
            }

            $canned = $cannedResponse->toApiArray();

            $staffService = $this->di['mod_service']('staff');
            $admin = $staffService->getCronAdmin();

            if (isset($canned['content']) && $admin instanceof \Model_Admin) {
                $this->ticketReply($ticket, $admin, $canned['content']);
            }
        } catch (\Exception $e) {
            $this->di['logger']->error('Autoresponder canned reply failed: %s', $e->getMessage());
        }
    }

    /**
     * @param \Model_Client $identity
     */
    public function messageCreateForTicket(SupportTicket $ticket, \Model_Admin|\Model_Client $identity, string $content): int
    {
        $em = $this->di['em'];
        $msg = new SupportTicketMessage();
        $msg->setSupportTicket($ticket);
        if ($identity instanceof \Model_Admin) {
            $msg->setAdminId((int) $identity->id);
        } elseif ($identity instanceof \Model_Client) {
            $msg->setClientId((int) $identity->id);
        } else {
            throw new \FOSSBilling\Exception('Identity is invalid');
        }
        $msg->setContent($content);
        $msg->setIp($this->di['request']->getClientIp());
        $em->persist($msg);
        $em->flush();

        return (int) $msg->getId();
    }

    public function findOneByHash(string $hash): SupportTicket
    {
        $guestTicket = $this->getSupportTicketRepository()->findOneByAccessHash($hash);
        if (!$guestTicket instanceof SupportTicket) {
            throw new \FOSSBilling\Exception('Guest ticket not found');
        }

        return $guestTicket;
    }

    public function helpdeskUpdate(Helpdesk $model, array $data): bool
    {
        if (array_key_exists('name', $data)) {
            $model->setName($data['name']);
        }
        if (array_key_exists('email', $data)) {
            $model->setEmail($data['email']);
        }
        if (array_key_exists('can_reopen', $data)) {
            $model->setCanReopen($data['can_reopen']);
        }
        if (array_key_exists('close_after', $data)) {
            $model->setCloseAfter($data['close_after']);
        }
        if (array_key_exists('signature', $data)) {
            $model->setSignature($data['signature']);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated helpdesk #%s', $model->getId());

        return true;
    }

    public function helpdeskCreate(array $data): int
    {
        $model = (new Helpdesk())
            ->setName($data['name'])
            ->setEmail($data['email'] ?? null)
            ->setCanReopen($data['can_reopen'] ?? null)
            ->setCloseAfter($data['close_after'] ?? null)
            ->setSignature($data['signature'] ?? null);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();

        $this->di['logger']->info('Created helpdesk #%s', $id);

        return $id;
    }

    public function cannedRm(CannedResponse $model): bool
    {
        $id = $model->getId();

        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Deleted canned response #%s', $id);

        return true;
    }

    public function cannedCategoryRm(CannedResponseCategory $model): bool
    {
        $id = $model->getId();
        $responsesCount = $id !== null ? $this->getCannedResponseRepository()->countByCategoryId($id) : 0;

        if ($responsesCount > 0) {
            throw new InformationException('Cannot remove category which has canned responses');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted canned response category #%s', $id);

        return true;
    }

    public function cannedCreate(string $title, int $categoryId, ?string $content = null): int
    {
        $category = $this->getCannedResponseCategoryRepository()->find($categoryId);
        if (!$category instanceof CannedResponseCategory) {
            throw new \FOSSBilling\Exception('Canned category not found');
        }

        $model = (new CannedResponse())
            ->setCategory($category)
            ->setTitle($title)
            ->setContent($content);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();

        $this->di['logger']->info('Created new canned response #%s', $id);

        return $id;
    }

    public function cannedUpdate(CannedResponse $model, array $data): bool
    {
        if (isset($data['category_id'])) {
            $category = $this->getCannedResponseCategoryRepository()->find((int) $data['category_id']);
            if (!$category instanceof CannedResponseCategory) {
                throw new \FOSSBilling\Exception('Canned category not found');
            }

            $model->setCategory($category);
        }

        if (isset($data['title'])) {
            $model->setTitle($data['title']);
        }

        if (array_key_exists('content', $data)) {
            $model->setContent($data['content']);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated canned response #%s', $model->getId());

        return true;
    }

    public function cannedCategoryCreate(string $title): int
    {
        $model = (new CannedResponseCategory())
            ->setTitle($title);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();

        $this->di['logger']->info('Created new canned response category #%s', $id);

        return $id;
    }

    public function cannedCategoryUpdate(CannedResponseCategory $model, ?string $title = null): bool
    {
        if (isset($title)) {
            $model->setTitle($title);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated canned response category #%s', $model->getId());

        return true;
    }

    public function noteCreate(SupportTicket $ticket, \Model_Admin $identity, string $note): int
    {
        $em = $this->di['em'];
        $model = new SupportTicketNote();
        $model->setSupportTicket($ticket);
        $model->setAdminId((int) $identity->id);
        $model->setNote($note);
        $em->persist($model);
        $em->flush();

        $id = (int) $model->getId();
        $this->di['logger']->info('Added note to ticket #%s', $id);

        return $id;
    }

    public function ticketTaskComplete(SupportTicket $model): bool
    {
        $model->setRelStatus(SupportTicket::REL_STATUS_COMPLETE);
        $model->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();

        $this->di['logger']->info('Marked ticket #%s task as complete', $model->getId());

        return true;
    }

    /*
     * Knowledge Base Functions.
     */

    public function kbEnabled(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return Tools::normalizeBoolean($config['kb_enable'] ?? true, true);
    }

    public function kbArticleViewsEnabled(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return Tools::normalizeBoolean($config['kb_article_views_enable'] ?? true, true);
    }

    public function kbSuggestionsEnabled(string $area): bool
    {
        if (!$this->kbEnabled()) {
            return false;
        }

        $key = match ($area) {
            'contact' => 'kb_suggestions_contact',
            'ticket' => 'kb_suggestions_ticket',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return Tools::normalizeBoolean($config[$key] ?? false);
    }

    public function kbRm(KbArticle $model): void
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted Knowledge Base article #%s', $id);
    }

    public function kbCreateArticle(int $articleCategoryId, string $title, ?string $status = null, ?string $content = null): int
    {
        $status = $this->normalizeKbArticleStatus($status ?? KbArticle::DRAFT);
        $category = $this->getKbArticleCategoryRepository()->find($articleCategoryId);
        if (!$category instanceof KbArticleCategory) {
            throw new \FOSSBilling\Exception('Knowledge Base category not found');
        }

        $model = (new KbArticle())
            ->setCategory($category)
            ->setTitle($title)
            ->setSlug($this->di['tools']->slug($title))
            ->setStatus($status)
            ->setContent($content);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();
        $this->di['logger']->info('Created new knowledge base article #%s', $id);

        return $id;
    }

    public function kbUpdateArticle(int $id, ?int $articleCategoryId = null, ?string $title = null, ?string $slug = null, ?string $status = null, ?string $content = null, ?int $views = null): bool
    {
        $status = $status !== null ? $this->normalizeKbArticleStatus($status) : null;
        $model = $this->getKbArticleRepository()->find($id);

        if (!$model instanceof KbArticle) {
            throw new \FOSSBilling\Exception('Article not found');
        }

        if (isset($articleCategoryId)) {
            $category = $this->getKbArticleCategoryRepository()->find($articleCategoryId);
            if (!$category instanceof KbArticleCategory) {
                throw new \FOSSBilling\Exception('Knowledge Base category not found');
            }

            $model->setCategory($category);
        }

        if (isset($title)) {
            $model->setTitle($title);
        }

        if (isset($slug)) {
            $model->setSlug($slug);
        }

        if (isset($status)) {
            $model->setStatus($status);
        }

        if (isset($content)) {
            $model->setContent($content);
        }

        if (isset($views)) {
            $model->setViews($views);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated knowledge base article #%s', $id);

        return true;
    }

    private function normalizeKbArticleStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, [KbArticle::ACTIVE, KbArticle::DRAFT], true)) {
            throw new \FOSSBilling\Exception('Invalid knowledge base article status: :status', [':status' => $status]);
        }

        return $status;
    }

    public function kbCategoryRm(KbArticleCategory $model): bool
    {
        $id = $model->getId();
        $articlesCount = $id !== null ? $this->getKbArticleRepository()->countByCategoryId($id) : 0;

        if ($articlesCount > 0) {
            throw new InformationException('Cannot remove category which has articles');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Deleted knowledge base category #%s', $id);

        return true;
    }

    public function kbCreateCategory(string $title, ?string $description = null): int
    {
        $model = (new KbArticleCategory())
            ->setTitle($title)
            ->setDescription($description)
            ->setSlug($this->di['tools']->slug($title));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();
        $this->di['logger']->info('Created new knowledge base category #%s', $id);

        return $id;
    }

    public function kbUpdateCategory(KbArticleCategory $model, ?string $title = null, ?string $slug = null, ?string $description = null): bool
    {
        if (isset($title)) {
            $model->setTitle($title);
        }

        if (isset($slug)) {
            $model->setSlug($slug);
        }

        if (isset($description)) {
            $model->setDescription($description);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated Knowledge Base category #%s', $model->getId());

        return true;
    }
}
