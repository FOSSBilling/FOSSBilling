<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use Doctrine\DBAL\ArrayParameterType;
use FOSSBilling\Environment;
use FOSSBilling\InformationException;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    private const string FILTER_CLIENT_STATUS = 'client_status';
    private const string FILTER_CLIENT_GROUPS = 'client_groups';
    private const string FILTER_HAS_ORDER = 'has_order';
    private const string FILTER_HAS_ORDER_WITH_STATUS = 'has_order_with_status';
    private const array FILTER_KEYS = [
        self::FILTER_CLIENT_STATUS,
        self::FILTER_CLIENT_GROUPS,
        self::FILTER_HAS_ORDER,
        self::FILTER_HAS_ORDER_WITH_STATUS,
    ];
    private const array CLIENT_STATUSES = [
        \Model_Client::ACTIVE,
        \Model_Client::SUSPENDED,
        \Model_Client::CANCELED,
    ];
    private const array ORDER_STATUSES = [
        \Model_ClientOrder::STATUS_PENDING_SETUP,
        \Model_ClientOrder::STATUS_FAILED_SETUP,
        \Model_ClientOrder::STATUS_FAILED_RENEW,
        \Model_ClientOrder::STATUS_ACTIVE,
        \Model_ClientOrder::STATUS_CANCELED,
        \Model_ClientOrder::STATUS_SUSPENDED,
    ];

    protected ?\Pimple\Container $di = null;
    protected ?MassmailerMessageRepository $messageRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->messageRepository = $this->di['em']->getRepository(MassmailerMessage::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getMessageRepository(): MassmailerMessageRepository
    {
        if ($this->messageRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->messageRepository = $this->di['em']->getRepository(MassmailerMessage::class);
        }

        return $this->messageRepository;
    }

    public function install(): void
    {
        $extensionService = $this->di['mod_service']('extension');

        $sql = '
        CREATE TABLE IF NOT EXISTS `mod_massmailer` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `from_email` varchar(255) DEFAULT NULL,
        `from_name` varchar(255) DEFAULT NULL,
        `subject` varchar(255) DEFAULT NULL,
        `content` text DEFAULT NULL,
        `filter` text DEFAULT NULL,
        `status` varchar(255) DEFAULT NULL,
        `sent_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ';
        $this->di['db']->exec($sql);

        // default config values
        $extensionService->setConfig(['ext' => 'mod_massmailer', 'limit' => '2', 'interval' => '10', 'test_client_id' => 1]);
    }

    public function getSearchQueryBuilder(array $data): \Doctrine\ORM\QueryBuilder
    {
        return $this->getMessageRepository()->getSearchQueryBuilder($data);
    }

    public function getMessageReceivers(MassmailerMessage $model): array
    {
        $filter = $this->normalizeFilter($model->getFilter(), true);

        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('DISTINCT c.id')
            ->from('client', 'c')
            ->leftJoin('c', 'client_order', 'co', 'co.client_id = c.id')
            ->orderBy('c.id', 'DESC');

        $this->appendInCondition($query, 'c.status', 'client_status', $filter[self::FILTER_CLIENT_STATUS] ?? [], ArrayParameterType::STRING);
        $this->appendInCondition($query, 'c.client_group_id', 'client_groups', $filter[self::FILTER_CLIENT_GROUPS] ?? [], ArrayParameterType::INTEGER);
        $this->appendInCondition($query, 'co.product_id', 'has_order', $filter[self::FILTER_HAS_ORDER] ?? [], ArrayParameterType::INTEGER);
        $this->appendInCondition($query, 'co.status', 'has_order_with_status', $filter[self::FILTER_HAS_ORDER_WITH_STATUS] ?? [], ArrayParameterType::STRING);

        return $query->executeQuery()->fetchAllAssociative();
    }

    public function normalizeFilter(mixed $filter, bool $strict = false): array
    {
        if (is_string($filter)) {
            $filter = trim($filter);
            if ($filter === '') {
                return [];
            }

            try {
                $filter = json_decode($filter, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $this->handleInvalidFilter('filter', $strict);
            }
        }

        if ($filter === null) {
            return [];
        }

        if (!is_array($filter)) {
            return $this->handleInvalidFilter('filter', $strict);
        }

        $unknownKeys = array_diff(array_keys($filter), self::FILTER_KEYS);
        if ($strict && $unknownKeys !== []) {
            return $this->handleInvalidFilter((string) reset($unknownKeys), $strict);
        }

        $normalized = [];
        $normalized[self::FILTER_CLIENT_STATUS] = $this->normalizeEnumFilterValues(
            $filter[self::FILTER_CLIENT_STATUS] ?? [],
            self::CLIENT_STATUSES,
            self::FILTER_CLIENT_STATUS,
            $strict
        );
        $normalized[self::FILTER_CLIENT_GROUPS] = $this->normalizeIdFilterValues(
            $filter[self::FILTER_CLIENT_GROUPS] ?? [],
            'client_group',
            self::FILTER_CLIENT_GROUPS,
            $strict
        );
        $normalized[self::FILTER_HAS_ORDER] = $this->normalizeIdFilterValues(
            $filter[self::FILTER_HAS_ORDER] ?? [],
            'product',
            self::FILTER_HAS_ORDER,
            $strict
        );
        $normalized[self::FILTER_HAS_ORDER_WITH_STATUS] = $this->normalizeEnumFilterValues(
            $filter[self::FILTER_HAS_ORDER_WITH_STATUS] ?? [],
            self::ORDER_STATUSES,
            self::FILTER_HAS_ORDER_WITH_STATUS,
            $strict
        );

        return array_filter($normalized, static fn (array $values): bool => $values !== []);
    }

    public function serializeFilter(mixed $filter): string
    {
        return json_encode($this->normalizeFilter($filter, true), JSON_THROW_ON_ERROR);
    }

    public function getParsed(MassmailerMessage $model, int $client_id): array
    {
        $clientService = $this->di['mod_service']('client');
        $systemService = $this->di['mod_service']('system');

        $client = $clientService->get(['id' => $client_id]);
        $clientArr = $clientService->toApiArray($client, true, null);

        $vars = [];
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $model->getSubject();
        $ps = $systemService->renderString($vars['_tpl'], false, $vars);

        $vars = [];
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $model->getContent();
        $pc = $systemService->renderString($vars['_tpl'], false, $vars);

        return [$ps, $pc];
    }

    public function sendMessage(MassmailerMessage $model, int $client_id, bool $sendNow = false): bool
    {
        [$ps, $pc] = $this->getParsed($model, $client_id);

        $clientService = $this->di['mod_service']('client');

        $client = $clientService->get(['id' => $client_id]);

        $data = [
            'to' => $client->email,
            'to_name' => $client->first_name . ' ' . $client->last_name,
            'from' => $model->getFromEmail(),
            'from_name' => $model->getFromName(),
            'subject' => $ps,
            'content' => $pc,
            'client_id' => $client_id,
        ];

        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            throw new InformationException('Disabled for security reasons (Demo mode enabled)');
        }

        if (!Environment::isProduction()) {
            if (DEBUG) {
                error_log('Skip email sending. Application ENV: ' . Environment::getCurrentEnvironment());
            }

            return true;
        }

        $emailService = $this->di['mod_service']('email');
        $emailService->sendMail($data['to'], $data['from'], $data['subject'], $data['content'], $data['to_name'], $data['from_name'], $data['client_id'], null, $sendNow);

        return true;
    }

    public function toApiArray(MassmailerMessage|array $row): array
    {
        if ($row instanceof MassmailerMessage) {
            $row = $row->toApiArray();
        }

        $row['filter'] = $this->normalizeFilter($row['filter'] ?? null);

        return $row;
    }

    public function sendMail(array $params): void
    {
        $model = $this->getMessageRepository()->find((int) $params['msg_id']);
        if (!$model instanceof MassmailerMessage) {
            throw new \Exception('Mass mail message not found');
        }
        $this->sendMessage($model, $params['client_id']);
    }

    private function normalizeEnumFilterValues(mixed $values, array $allowedValues, string $field, bool $strict): array
    {
        if ($values === null || $values === []) {
            return [];
        }

        if (!is_array($values)) {
            return $this->handleInvalidFilter($field, $strict);
        }

        $selectedValues = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                return $this->handleInvalidFilter($field, $strict);
            }

            $value = trim((string) $value);
            if ($value === '' || !in_array($value, $allowedValues, true)) {
                return $this->handleInvalidFilter($field, $strict);
            }

            $selectedValues[$value] = true;
        }

        $normalized = [];
        foreach ($allowedValues as $allowedValue) {
            if (isset($selectedValues[$allowedValue])) {
                $normalized[] = $allowedValue;
            }
        }

        return $normalized;
    }

    private function normalizeIdFilterValues(mixed $values, string $table, string $field, bool $strict): array
    {
        if ($values === null || $values === []) {
            return [];
        }

        if (!is_array($values)) {
            return $this->handleInvalidFilter($field, $strict);
        }

        $normalized = [];
        foreach ($values as $value) {
            if (is_int($value)) {
            } elseif (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            } else {
                return $this->handleInvalidFilter($field, $strict);
            }

            if ($value < 1) {
                return $this->handleInvalidFilter($field, $strict);
            }

            $normalized[$value] = $value;
        }

        $normalized = array_values($normalized);
        sort($normalized);

        if ($normalized === []) {
            return [];
        }

        $existingIds = $this->getExistingIds($table, $normalized);
        if (count($existingIds) !== count($normalized)) {
            return $this->handleInvalidFilter($field, $strict);
        }

        return $existingIds;
    }

    private function getExistingIds(string $table, array $ids): array
    {
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('id')
            ->from($table)
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        $rows = $query->executeQuery()->fetchAllAssociative();
        $existingIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        sort($existingIds);

        return array_values(array_unique($existingIds));
    }

    private function appendInCondition(\Doctrine\DBAL\Query\QueryBuilder $query, string $column, string $parameterName, array $filterValues, ArrayParameterType $parameterType): void
    {
        if ($filterValues === []) {
            return;
        }

        $query
            ->andWhere(sprintf('%s IN (:%s)', $column, $parameterName))
            ->setParameter($parameterName, $filterValues, $parameterType);
    }

    private function handleInvalidFilter(string $field, bool $strict): array
    {
        if ($strict) {
            throw new InformationException(sprintf('Mass mail filter contains invalid values for "%s"', $field));
        }

        return [];
    }
}
