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
use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Repository\CannedResponseCategoryRepository;
use Box\Mod\Support\Repository\CannedResponseRepository;
use Box\Mod\Support\Repository\KbArticleCategoryRepository;
use Box\Mod\Support\Repository\KbArticleRepository;
use Box\Mod\Support\Repository\HelpdeskRepository;
use FOSSBilling\InformationException;
use FOSSBilling\Tools;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected KbArticleRepository $kbArticleRepository;
    protected KbArticleCategoryRepository $kbArticleCategoryRepository;
    protected CannedResponseRepository $cannedResponseRepository;
    protected CannedResponseCategoryRepository $cannedResponseCategoryRepository;
    protected HelpdeskRepository $helpdeskRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->kbArticleRepository = $this->di['em']->getRepository(KbArticle::class);
        $this->kbArticleCategoryRepository = $this->di['em']->getRepository(KbArticleCategory::class);
        $this->cannedResponseRepository = $this->di['em']->getRepository(CannedResponse::class);
        $this->cannedResponseCategoryRepository = $this->di['em']->getRepository(CannedResponseCategory::class);
        $this->helpdeskRepository = $this->di['em']->getRepository(Helpdesk::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getKbArticleRepository(): KbArticleRepository
    {
        return $this->kbArticleRepository;
    }

    public function getKbArticleCategoryRepository(): KbArticleCategoryRepository
    {
        return $this->kbArticleCategoryRepository;
    }

    public function getCannedResponseRepository(): CannedResponseRepository
    {
        return $this->cannedResponseRepository;
    }

    public function getCannedResponseCategoryRepository(): CannedResponseCategoryRepository
    {
        return $this->cannedResponseCategoryRepository;
    }

    public function getHelpdeskRepository(): HelpdeskRepository
    {
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
                $email['to'] = $ticketObj->author_email;
                $email['to_name'] = $ticketObj->author_name;
            } else {
                $email['to_client'] = $ticketObj->client_id;
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

    public function getTicketById(int $id): \Model_SupportTicket
    {
        return $this->di['db']->getExistingModelById('SupportTicket', $id, 'Ticket not found');
    }

    /**
     * Determine if the provided ticket is a guest ticket.
     *
     * @todo Doctrine: Move this to the Entity when migrating to Doctrine
     */
    public function isGuestTicket(\Model_SupportTicket $ticket): bool
    {
        return empty($ticket->client_id) && !empty($ticket->access_hash);
    }

    /**
     * Return array of ticket statuses.
     */
    public function getStatuses(): array
    {
        return [
            \Model_SupportTicket::OPENED => 'Open',
            \Model_SupportTicket::ONHOLD => 'On Hold',
            \Model_SupportTicket::CLOSED => 'Closed',
        ];
    }

    /**
     * Find ticket for client.
     */
    public function findOneByClient(\Model_Client $c, int $id): \Model_SupportTicket
    {
        $bindings = [
            ':id' => $id,
            ':client_id' => $c->id,
        ];

        $ticket = $this->di['db']->findOne('SupportTicket', 'id = :id AND client_id = :client_id', $bindings);

        if (!$ticket instanceof \Model_SupportTicket) {
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
            $bindings[':rel_type'] = \Model_SupportTicket::REL_TYPE_ORDER;
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
            \Model_SupportTicket::OPENED => $data[\Model_SupportTicket::OPENED] ?? 0,
            \Model_SupportTicket::CLOSED => $data[\Model_SupportTicket::CLOSED] ?? 0,
            \Model_SupportTicket::ONHOLD => $data[\Model_SupportTicket::ONHOLD] ?? 0,
        ];
    }

    public function getLatest(): array
    {
        return $this->di['db']->find('SupportTicket', 'ORDER BY id DESC LIMIT 10');
    }

    public function getExpired(): array
    {
        $bindings = [
            ':status' => \Model_SupportTicket::ONHOLD,
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
        $query = "SELECT COUNT(id) as counter FROM support_ticket
                WHERE rel_id = :order_id
                AND rel_type = 'order'
                AND (status = :status1 OR status = :status2)";

        $bindings = [
            ':order_id' => $model->id,
            ':status1' => \Model_SupportTicket::OPENED,
            ':status2' => \Model_SupportTicket::ONHOLD,
        ];

        return (int) $this->di['db']->getCell($query, $bindings);
    }

    public function checkIfTaskAlreadyExists(\Model_Client $client, int $rel_id, string $rel_type, string $rel_task): bool
    {
        $bindings = [
            ':client_id' => $client->id,
            ':rel_id' => $rel_id,
            ':rel_type' => $rel_type,
            ':rel_task' => $rel_task,
            ':rel_status' => \Model_SupportTicket::REL_STATUS_PENDING,
        ];

        $ticket = $this->di['db']->findOne(
            'SupportTicket',
            'client_id = :client_id
            AND rel_id = :rel_id
            AND rel_type = :rel_type
            AND rel_task = :rel_task
            AND rel_status = :rel_status',
            $bindings
        );

        return $ticket instanceof \Model_SupportTicket;
    }

    public function closeTicket(\Model_SupportTicket $ticket, \Model_Admin|\Model_Client|\Model_Guest $identity): bool
    {
        $ticket->status = \Model_SupportTicket::CLOSED;
        $ticket->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($ticket);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminCloseTicket', 'params' => ['id' => $ticket->id]]);
        } else {
            $this->di['events_manager']->fire(['event' => 'onAfterClientCloseTicket', 'params' => ['id' => $ticket->id]]);
        }

        $this->di['logger']->info('Closed ticket "%s"', $ticket->id);

        return true;
    }

    public function autoClose(\Model_SupportTicket $model): bool
    {
        $model->status = \Model_SupportTicket::CLOSED;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);
        $this->di['logger']->info('Ticket %s was closed', $model->id);

        return true;
    }

    public function canBeReopened(\Model_SupportTicket $model): bool
    {
        if ($model->status != \Model_SupportTicket::CLOSED) {
            return true;
        }

        $helpdesk = $this->getHelpdeskRepository()->find((int) $model->support_helpdesk_id);
        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\Exception('Helpdesk invalid');
        }

        return $helpdesk->canReopen();
    }

    /**
     * @return mixed[]
     */
    private function _getRelDetails(\Model_SupportTicket $model): array
    {
        $result = [
            'id' => $model->rel_id ?: null,
            'type' => $model->rel_type ?: null,
            'task' => $model->rel_task ?: null,
            'new_value' => $model->rel_new_value ?: null,
            'status' => $model->rel_status ?: null,
        ];

        if (!$model->rel_type || !$model->rel_id) {
            return $result;
        }

        $client = $this->di['db']->load('Client', $model->client_id);

        if ($model->rel_type == \Model_SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $o = $orderService->findForClientById($client, $model->rel_id);
            if ($o instanceof \Model_ClientOrder) {
                $result['order'] = $orderService->toApiArray($o, false);
            }
        }

        return $result;
    }

    public function rmByClient(\Model_Client $client): void
    {
        $clientTickets = $this->di['db']->find('SupportTicket', 'client_id = :client_id', [':client_id' => $client->id]);
        foreach ($clientTickets as $ticket) {
            $this->di['db']->trash($ticket);
        }
    }

    public function rm(\Model_SupportTicket $model): bool
    {
        $supportTicketNotes = $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :support_ticket_id', [':support_ticket_id' => $model->id]);
        foreach ($supportTicketNotes as $note) {
            $this->di['db']->trash($note);
        }

        $supportTicketMessages = $this->di['db']->find('SupportTicketMessage', 'support_ticket_id = :support_ticket_id', [':support_ticket_id' => $model->id]);
        foreach ($supportTicketMessages as $message) {
            $this->di['db']->trash($message);
        }

        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed ticket "%s"', $id);

        return true;
    }

    public function toApiArray(\Model_SupportTicket $model, bool $deep = true, \Model_Admin|\Model_Client|null $identity = null): array
    {
        $firstSupportTicketMessage = $this->di['db']->findOne('SupportTicketMessage', 'support_ticket_id = :support_ticket_id ORDER by id ASC LIMIT 1', [':support_ticket_id' => $model->id]);
        $helpdesk = $model->support_helpdesk_id ? $this->getHelpdeskRepository()->find((int) $model->support_helpdesk_id) : null;

        $data = $this->ticketToApiArray($this->di['db']->toArray($model), $identity);
        $data['replies'] = $this->messageGetRepliesCount($model);
        $data['first'] = $firstSupportTicketMessage instanceof \Model_SupportTicketMessage ? $this->messageToApiArray($firstSupportTicketMessage, true, $identity) : null;
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
            $data['priority'] = $model->priority;
            $data['notes'] = [];
            $supportTicketNotes = $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :support_ticket_id', [':support_ticket_id' => $model->id]);

            foreach ($supportTicketNotes as $note) {
                $data['notes'][] = $this->noteToApiArray($note);
            }
        }

        return $data;
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

    private function getClientApiArrayForTicket(\Model_SupportTicket $ticket, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($this->isGuestTicket($ticket)) {
            return [];
        }

        $client = $this->di['db']->load('Client', $ticket->client_id);

        if ($client instanceof \Model_Client) {
            return $this->clientToTicketApiArray($client, $identity);
        }
        $this->di['logger']->error('Missing client for ticket ' . $ticket->id);

        return [];
    }

    private function getTicketAuthor(\Model_SupportTicket $ticket, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($this->isGuestTicket($ticket)) {
            $author = [
                'name' => $ticket->author_name,
                'role' => 'guest',
            ];

            if ($identity instanceof \Model_Admin || $identity === null) {
                $author['email'] = $ticket->author_email;
            }

            return $author;
        }

        $client = $this->di['db']->load('Client', $ticket->client_id);

        if ($client instanceof \Model_Client) {
            return $this->clientToTicketAuthorArray($client);
        }
        $this->di['logger']->error('Missing client for ticket ' . $ticket->id);

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

    public function noteGetAuthorDetails(\Model_SupportTicketNote $model): array
    {
        $admin = $this->di['db']->load('Admin', $model->admin_id);

        return [
            'name' => $admin->getFullName(),
            'email' => $admin->email,
        ];
    }

    public function noteRm(\Model_SupportTicketNote $model): bool
    {
        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed note #%s', $id);

        return true;
    }

    public function noteToApiArray(\Model_SupportTicketNote $model, bool $deep = false, \Model_Admin|\Model_Client|null $identity = null): array
    {
        $data = $this->di['db']->toArray($model);
        $data['author'] = $this->noteGetAuthorDetails($model);

        return $data;
    }

    public function helpdeskGetSearchQuery(array $data): array
    {
        $query = 'SELECT * FROM support_helpdesk';

        $search = $data['search'] ?? null;

        $where = [];
        $bindings = [];

        if ($search) {
            $search = '%' . $search . '%';
            $where[] = '(name LIKE :name OR email LIKE :email OR signature LIKE :signature)';
            $bindings[':name'] = $search;
            $bindings[':email'] = $search;
            $bindings[':signature'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY id DESC';

        return [$query, $bindings];
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

    public function messageGetTicketMessages(\Model_SupportTicket $model): array
    {
        return $this->di['db']->find('supportTicketMessage', 'support_ticket_id = :support_ticket_id ORDER BY id ASC', [':support_ticket_id' => $model->id]);
    }

    public function messageGetRepliesCount(\Model_SupportTicket $model): int
    {
        $query = 'SELECT COUNT(id) as counter
                    FROM support_ticket_message
                    WHERE support_ticket_id = :support_ticket_id
                    GROUP BY support_ticket_id';

        $bindings = [
            ':support_ticket_id' => $model->id,
        ];

        return (int) $this->di['db']->getCell($query, $bindings);
    }

    public function messageGetAuthorDetails(\Model_SupportTicketMessage $model, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($model->admin_id) {
            $author = $this->di['db']->load('Admin', $model->admin_id);
            $role = 'admin';
        } elseif ($model->client_id) {
            $author = $this->di['db']->load('Client', $model->client_id);
            $role = 'client';
        } else {
            $ticket = $this->di['db']->load('SupportTicket', $model->support_ticket_id);

            if ($ticket instanceof \Model_SupportTicket && $this->isGuestTicket($ticket)) {
                return [
                    'name' => $ticket->author_name,
                    'email' => $ticket->author_email,
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

    public function messageToApiArray(\Model_SupportTicketMessage $model, bool $deep = true, \Model_Admin|\Model_Client|null $identity = null): array
    {
        if ($identity instanceof \Model_Admin) {
            $data = $this->di['db']->toArray($model);
        } else {
            $data = [
                'id' => $model->id,
                'content' => $model->content,
                'attachment' => $model->attachment,
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
            ];
        }

        $data['author'] = $this->messageGetAuthorDetails($model, $identity);

        return $data;
    }

    public function ticketUpdate(\Model_SupportTicket $model, array $data): bool
    {
        $model->support_helpdesk_id = $data['support_helpdesk_id'] ?? $model->support_helpdesk_id;
        $model->status = $data['status'] ?? $model->status;
        $model->subject = $data['subject'] ?? $model->subject;
        $model->priority = $data['priority'] ?? $model->priority;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated ticket #%s', $model->id);

        return true;
    }

    public function ticketMessageUpdate(\Model_SupportTicketMessage $model, string $content): bool
    {
        $model->content = $content;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @param \Model_Admin $identity
     */
    public function ticketReply(\Model_SupportTicket $ticket, \Model_Admin|\Model_Client|\Model_Guest $identity, string $content): int
    {
        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        if ($identity instanceof \Model_Admin) {
            $msg->admin_id = $identity->id;
        } elseif ($identity instanceof \Model_Client) {
            $msg->client_id = $identity->id;
        }
        $msg->content = $content;
        $msg->ip = $this->di['request']->getClientIp();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $msgId = $this->di['db']->store($msg);

        if ($identity instanceof \Model_Admin) {
            $ticket->status = \Model_SupportTicket::ONHOLD;
        } else {
            $ticket->status = \Model_SupportTicket::OPENED;
        }

        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminReplyTicket', 'params' => ['id' => $ticket->id]]);
        } else {
            $this->di['events_manager']->fire(['event' => 'onAfterClientReplyTicket', 'params' => ['id' => $ticket->id]]);
        }

        $this->di['logger']->info('Replied to ticket "%s"', $ticket->id);

        return $msgId;
    }

    public function ticketCreateForAdmin(\Model_Client $client, Helpdesk $helpdesk, array $data, \Model_Admin $identity): int
    {
        $status = $data['status'] ?? \Model_SupportTicket::ONHOLD;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOpenTicket', 'params' => $data]);

        $ticket = $this->di['db']->dispense('SupportTicket');
        $ticket->client_id = $client->id;
        $ticket->status = $status;
        $ticket->subject = $data['subject'];
        $ticket->support_helpdesk_id = $helpdesk->getId();
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');
        $ticketId = $this->di['db']->store($ticket);

        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->admin_id = $identity->id;
        $msg->support_ticket_id = $ticketId;
        $msg->content = $data['content'];
        $msg->ip = $this->di['request']->getClientIp();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOpenTicket', 'params' => ['id' => $ticketId]]);

        $this->di['logger']->info('Admin opened new ticket "%s"', $ticketId);

        return (int) $ticketId;
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

        $helpdesk = isset($data['support_helpdesk_id']) ? $this->getHelpdeskRepository()->find((int) $data['support_helpdesk_id']) : $this->getDefaultHelpdesk();
        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\Exception('Helpdesk invalid');
        }

        $ticket = $this->di['db']->dispense('SupportTicket');
        $ticket->access_hash = bin2hex(random_bytes(random_int(15, 30)));
        $ticket->support_helpdesk_id = $helpdesk->getId();
        $ticket->author_name = $data['name'];
        $ticket->author_email = $data['email'];
        $ticket->subject = $subject;
        $ticket->status = $status;
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');
        $ticketId = $this->di['db']->store($ticket);

        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        $msg->content = $message;
        $msg->ip = $this->di['request']->getClientIp();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(['event' => 'onAfterClientOpenTicket', 'params' => ['id' => $ticketId]]);

        $this->di['logger']->info('"%s" opened guest ticket "%s"', $ticket->author_email, $ticketId);

        return $ticket->access_hash;
    }

    private function getDefaultHelpdesk(): Helpdesk
    {
        $helpdesk = $this->getHelpdeskRepository()->findOneBy([], ['id' => 'ASC']);
        if ($helpdesk instanceof Helpdesk) {
            return $helpdesk;
        }

        $helpdesk = (new Helpdesk())
            ->setName('General')
            ->setCloseAfter(24)
            ->setCanReopen(false);
        $this->di['em']->persist($helpdesk);
        $this->di['em']->flush();

        return $helpdesk;
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
        if (!$lastTicket instanceof \Model_SupportTicket) {
            return true;
        }

        $timeSinceLast = round(abs(strtotime($lastTicket->created_at) - strtotime(date('Y-m-d H:i:s'))) / 3600, 0);

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
        $rel_status = isset($data['rel_task']) ? \Model_SupportTicket::REL_STATUS_PENDING : \Model_SupportTicket::REL_STATUS_COMPLETE;

        $order = null;
        if ($rel_id !== null && $rel_type === \Model_SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $order = $orderService->findForClientById($client, $rel_id);
            if (!$order instanceof \Model_ClientOrder) {
                throw new \FOSSBilling\Exception('You do not have permission to reference this order.');
            }
        }

        if ($rel_task === \Model_SupportTicket::REL_TASK_UPGRADE) {
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

        $ticket = $this->di['db']->dispense('SupportTicket');
        $ticket->client_id = $client->id;
        $ticket->subject = $data['subject'];
        $ticket->support_helpdesk_id = $helpdesk->getId();
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');

        // related task with ticket
        $ticket->rel_id = $rel_id;
        $ticket->rel_type = $rel_type;
        $ticket->rel_task = $rel_task;
        $ticket->rel_new_value = $rel_new_value;
        $ticket->rel_status = $rel_status;

        $ticketId = $this->di['db']->store($ticket);

        $this->messageCreateForTicket($ticket, $client, $data['content']);

        $this->di['events_manager']->fire(['event' => 'onAfterClientOpenTicket', 'params' => ['id' => $ticket->id]]);

        if (
            isset($config['autorespond_enable'])
            && $config['autorespond_enable']
            && isset($config['autorespond_message_id'])
            && !empty($config['autorespond_message_id'])
        ) {
            $this->sendAutoresponderCannedReply($ticket, $config['autorespond_message_id']);
        }

        $this->di['logger']->info('Submitted new ticket "%s"', $ticketId);

        return (int) $ticketId;
    }

    private function sendAutoresponderCannedReply(\Model_SupportTicket $ticket, $cannedId): void
    {
        try {
            $canned = $this->getCannedResponseRepository()->find((int) $cannedId)?->toApiArray() ?? [];
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
    public function messageCreateForTicket(\Model_SupportTicket $ticket, \Model_Admin|\Model_Client $identity, string $content): int
    {
        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        if ($identity instanceof \Model_Admin) {
            $msg->admin_id = $identity->id;
        } elseif ($identity instanceof \Model_Client) {
            $msg->client_id = $identity->id;
        } else {
            throw new \FOSSBilling\Exception('Identity is invalid');
        }
        $msg->content = $content;
        $msg->ip = $this->di['request']->getClientIp();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');

        return $this->di['db']->store($msg);
    }

    public function findOneByHash(string $hash): \Model_SupportTicket
    {
        $guestTicket = $this->di['db']->findOne('SupportTicket', 'access_hash = :hash AND client_id IS NULL', [':hash' => $hash]);
        if (!$guestTicket instanceof \Model_SupportTicket) {
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

    public function noteCreate(\Model_SupportTicket $ticket, \Model_Admin $identity, string $note): int
    {
        $model = $this->di['db']->dispense('SupportTicketNote');
        $model->support_ticket_id = $ticket->id;
        $model->admin_id = $identity->id;
        $model->note = $note;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Added note to ticket #%s', $id);

        return $id;
    }

    public function ticketTaskComplete(\Model_SupportTicket $model): bool
    {
        $model->rel_status = \Model_SupportTicket::REL_STATUS_COMPLETE;
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Marked ticket #%s task as complete', $id);

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
