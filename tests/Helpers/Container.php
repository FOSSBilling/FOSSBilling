<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Pimple\Container;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * Create a minimal DI container for testing.
 */
function container(): Container
{
    $di = new Container();
    $di['config'] = [
        'salt' => 'test_salt_' . uniqid(),
        'url' => 'http://localhost/',
    ];
    $di['validator'] = fn (): \FOSSBilling\Validate => new \FOSSBilling\Validate();
    $di['tools'] = fn (): \FOSSBilling\Tools => new \FOSSBilling\Tools();
    $di['filesystem'] = fn (): \Symfony\Component\Filesystem\Filesystem => new \Symfony\Component\Filesystem\Filesystem();
    $di['logger'] = fn (): \Psr\Log\LoggerInterface => new class extends AbstractLogger {
        public array $calls = [];

        public function log($level, string|\Stringable $message, array $context = []): void
        {
            $this->calls[] = ['method' => $level, 'params' => [$message, $context]];
        }

        public function setChannel(string $channel): self
        {
            $this->calls[] = ['method' => 'setChannel', 'params' => [$channel]];

            return $this;
        }
    };
    $di['request'] = fn (): Request => Request::create('http://localhost/');
    $di['filesystem'] = fn (): \Symfony\Component\Filesystem\Filesystem => new \Symfony\Component\Filesystem\Filesystem();
    $di['session'] = static function (): object {
        $session = \Mockery::mock(\FOSSBilling\Session::class)->shouldIgnoreMissing();
        $session->shouldReceive('regenerateId')->byDefault()->andReturnNull();
        $session->shouldReceive('set')->byDefault()->andReturnNull();
        $session->shouldReceive('get')->byDefault()->andReturnNull();
        $session->shouldReceive('delete')->byDefault()->andReturnNull();

        return $session;
    };
    $di['db'] = static function (): object {
        $db = \Mockery::mock(\Box_Database::class)->shouldIgnoreMissing();
        $db->shouldReceive('find')->byDefault()->andReturn([]);
        $db->shouldReceive('getAll')->byDefault()->andReturn([]);
        $db->shouldReceive('getAssoc')->byDefault()->andReturn([]);
        $db->shouldReceive('toArray')->byDefault()->andReturn([]);
        $db->shouldReceive('exec')->byDefault()->andReturn(1);
        $db->shouldReceive('transaction')->byDefault()->andReturnUsing(static fn (callable $callback): mixed => $callback());

        return $db;
    };
    $di['dbal'] = static function (): object {
        $dbal = \Mockery::mock(\Doctrine\DBAL\Connection::class)->shouldIgnoreMissing();
        $result = \Mockery::mock(\Doctrine\DBAL\Result::class)->shouldIgnoreMissing();
        $result->shouldReceive('fetchAssociative')->byDefault()->andReturn(false);
        $result->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
        $result->shouldReceive('fetchOne')->byDefault()->andReturn(false);

        $queryBuilder = \Mockery::mock(\Doctrine\DBAL\Query\QueryBuilder::class)->shouldIgnoreMissing();
        foreach ([
            'select',
            'addSelect',
            'from',
            'leftJoin',
            'innerJoin',
            'where',
            'andWhere',
            'orWhere',
            'orderBy',
            'addOrderBy',
            'setFirstResult',
            'setMaxResults',
            'setParameter',
            'setParameters',
            'insert',
            'update',
            'delete',
            'values',
            'setValue',
            'set',
        ] as $method) {
            $queryBuilder->shouldReceive($method)->byDefault()->andReturn($queryBuilder);
        }
        $queryBuilder->shouldReceive('executeQuery')->byDefault()->andReturn($result);
        $queryBuilder->shouldReceive('executeStatement')->byDefault()->andReturn(1);
        $queryBuilder->shouldReceive('fetchAssociative')->byDefault()->andReturn(false);
        $queryBuilder->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
        $queryBuilder->shouldReceive('fetchOne')->byDefault()->andReturn(false);

        $dbal->shouldReceive('fetchAssociative')->byDefault()->andReturn([]);
        $dbal->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
        $dbal->shouldReceive('fetchOne')->byDefault()->andReturn(null);
        $dbal->shouldReceive('executeQuery')->byDefault()->andReturn($result);
        $dbal->shouldReceive('executeStatement')->byDefault()->andReturn(1);
        $dbal->shouldReceive('insert')->byDefault()->andReturn(1);
        $dbal->shouldReceive('update')->byDefault()->andReturn(1);
        $dbal->shouldReceive('lastInsertId')->byDefault()->andReturn(1);
        $dbal->shouldReceive('createQueryBuilder')->byDefault()->andReturn($queryBuilder);

        return $dbal;
    };
    $di['events_manager'] = fn (): object => new class {
        public array $events = [];

        public function fire(array|string $event): void
        {
            $this->events[] = $event;
        }
    };
    $di['auth'] = fn (): object => \Mockery::mock()->shouldIgnoreMissing();
    $di['pager'] = fn (): object => new class {
        public function getDefaultPerPage(): int
        {
            return 20;
        }

        public function getAdvancedSearch(array $data, array $fields = [], string $table = ''): array
        {
            return ['', []];
        }

        public function getSimpleResultSet(string $query, array $params = [], int $perPage = 20): array
        {
            return ['list' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => $perPage];
        }

        public function getPaginatedResultSet(string $query, array $params = [], mixed $pagination = null): array
        {
            return ['list' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => 20];
        }
    };
    $di['rate_limiter'] = fn (): object => new class {
        public function consume(string $policyName, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
        {
            return new \FOSSBilling\Security\RateLimitResult($policyName, false, null, null);
        }

        public function consumeOrThrow(string $policyName, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
        {
            return $this->consume($policyName, $subject, $tokens);
        }
    };
    $di['mod_config'] = $di->protect(fn (string $name): array => []);
    $di['cookie_queue'] = fn (): \FOSSBilling\Http\CookieQueue => new \FOSSBilling\Http\CookieQueue();
    $di['em'] = static function (): object {
        $adminRepository = \Mockery::mock(\Box\Mod\Staff\Repository\AdminRepository::class)->shouldIgnoreMissing();
        $adminGroupRepository = \Mockery::mock(\Box\Mod\Staff\Repository\AdminGroupRepository::class)->shouldIgnoreMissing();
        $adminGroupMemberRepository = \Mockery::mock(\Box\Mod\Staff\Repository\AdminGroupMemberRepository::class)->shouldIgnoreMissing();

        $clientRepository = \Mockery::mock(\Box\Mod\Client\Repository\ClientRepository::class)->shouldIgnoreMissing();
        $clientRepository->shouldReceive('find')->byDefault()->andReturnUsing(static function (int $id): ?object {
            $client = new \Box\Mod\Client\Entity\Client();
            $prop = new \ReflectionProperty($client, 'id');
            $prop->setValue($client, $id);

            return $client;
        });
        $clientRepository->shouldReceive('findOneByEmail')->byDefault()->andReturnUsing(static function (string $email): ?object {
            $client = new \Box\Mod\Client\Entity\Client();
            $prop = new \ReflectionProperty($client, 'id');
            $prop->setValue($client, 1);

            return $client;
        });
        $clientRepository->shouldReceive('findOneByEmailAndActive')->byDefault()->andReturnUsing(static function (string $email): ?object {
            $client = new \Box\Mod\Client\Entity\Client();
            $prop = new \ReflectionProperty($client, 'id');
            $prop->setValue($client, 1);
            $statusProp = new \ReflectionProperty($client, 'status');
            $statusProp->setValue($client, 'active');

            return $client;
        });
        $clientRepository->shouldReceive('getIdNamePairs')->byDefault()->andReturn([]);
        $clientRepository->shouldReceive('getStatusCounts')->byDefault()->andReturn(['active' => 1, 'suspended' => 0, 'canceled' => 0]);
        $clientGroupRepository = \Mockery::mock(\Box\Mod\Client\Repository\ClientGroupRepository::class)->shouldIgnoreMissing();
        $clientGroupRepository->shouldReceive('getIdTitlePairs')->byDefault()->andReturn([]);
        $clientGroupRepository->shouldReceive('find')->byDefault()->andReturnUsing(static function (int $id): ?object {
            $group = new \Box\Mod\Client\Entity\ClientGroup();
            $prop = new \ReflectionProperty($group, 'id');
            $prop->setValue($group, $id);

            return $group;
        });
        $clientBalanceRepository = \Mockery::mock(\Box\Mod\Client\Repository\ClientBalanceRepository::class)->shouldIgnoreMissing();
        $clientBalanceRepository->shouldReceive('getClientBalanceSum')->byDefault()->andReturn(0.0);
        $clientBalanceRepository->shouldReceive('find')->byDefault()->andReturnUsing(static function (int $id): ?object {
            $balance = new \Box\Mod\Client\Entity\ClientBalance();
            $prop = new \ReflectionProperty($balance, 'id');
            $prop->setValue($balance, $id);

            return $balance;
        });
        $clientPasswordResetRepository = \Mockery::mock(\Box\Mod\Client\Repository\ClientPasswordResetRepository::class)->shouldIgnoreMissing();
        $clientPasswordResetRepository->shouldReceive('findOneByHash')->byDefault()->andReturn(null);
        $clientPasswordResetRepository->shouldReceive('findOneBy')->byDefault()->andReturn(null);
        $clientPasswordResetRepository->shouldReceive('findBy')->byDefault()->andReturn([]);
        $clientPasswordResetRepository->shouldReceive('createQueryBuilder')->byDefault()->andReturnUsing(static function (): object {
            $qb = \Mockery::mock(\Doctrine\ORM\QueryBuilder::class)->shouldIgnoreMissing();
            $query = \Mockery::mock(\Doctrine\ORM\Query::class)->shouldIgnoreMissing();
            $query->shouldReceive('getResult')->byDefault()->andReturn([]);
            $qb->shouldReceive('getQuery')->byDefault()->andReturn($query);

            return $qb;
        });

        $invoiceRepository = \Mockery::mock(\Box\Mod\Invoice\Repository\InvoiceRepository::class)->shouldIgnoreMissing();
        $invoiceRepository->shouldReceive('find')->byDefault()->andReturnUsing(static function (?int $id): ?object {
            if ($id === null) {
                return null;
            }
            $invoice = new \Box\Mod\Invoice\Entity\Invoice();
            $prop = new \ReflectionProperty($invoice, 'id');
            $prop->setValue($invoice, $id);

            return $invoice;
        });
        $invoiceRepository->shouldReceive('getSearchQueryBuilder')->byDefault()->andReturnUsing(static function (): object {
            $qb = \Mockery::mock(\Doctrine\ORM\QueryBuilder::class)->shouldIgnoreMissing();
            $query = \Mockery::mock(\Doctrine\ORM\Query::class)->shouldIgnoreMissing();
            $query->shouldReceive('getSingleScalarResult')->byDefault()->andReturn(0);
            $query->shouldReceive('getResult')->byDefault()->andReturn([]);
            $query->shouldReceive('getArrayResult')->byDefault()->andReturn([]);
            $qb->shouldReceive('getQuery')->byDefault()->andReturn($query);
            $qb->shouldReceive('expr->in')->byDefault()->andReturn('');
            $qb->shouldReceive('expr->neq')->byDefault()->andReturn('');

            return $qb;
        });
        $invoiceRepository->shouldReceive('getStatusCounts')->byDefault()->andReturn(['active' => 0, 'paid' => 1, 'unpaid' => 0, 'refunded' => 0, 'canceled' => 0]);
        $invoiceRepository->shouldReceive('findByHash')->byDefault()->andReturn(null);
        $invoiceRepository->shouldReceive('findUnpaid')->byDefault()->andReturn([]);
        $invoiceRepository->shouldReceive('findPaid')->byDefault()->andReturn([]);
        $invoiceRepository->shouldReceive('getNextInvoiceNumber')->byDefault()->andReturn(null);
        $invoiceRepository->shouldReceive('findByClientId')->byDefault()->andReturn([]);
        $invoiceRepository->shouldReceive('findPaidByClientId')->byDefault()->andReturn([]);
        $invoiceRepository->shouldReceive('findApprovedUnpaid')->byDefault()->andReturn([]);

        $invoiceItemRepository = \Mockery::mock(\Box\Mod\Invoice\Repository\InvoiceItemRepository::class)->shouldIgnoreMissing();
        $invoiceItemRepository->shouldReceive('findByInvoiceId')->byDefault()->andReturn([]);
        $invoiceItemRepository->shouldReceive('findPendingRenewal')->byDefault()->andReturn([]);

        $transactionRepository = \Mockery::mock(\Box\Mod\Invoice\Repository\TransactionRepository::class)->shouldIgnoreMissing();
        $transactionRepository->shouldReceive('findByInvoiceId')->byDefault()->andReturn([]);
        $transactionRepository->shouldReceive('findByTxnId')->byDefault()->andReturn(null);
        $transactionRepository->shouldReceive('findBySId')->byDefault()->andReturn(null);

        $subscriptionRepository = \Mockery::mock(\Box\Mod\Invoice\Repository\SubscriptionRepository::class)->shouldIgnoreMissing();
        $subscriptionRepository->shouldReceive('findBySId')->byDefault()->andReturn(null);
        $subscriptionRepository->shouldReceive('findByClientId')->byDefault()->andReturn([]);

        $payGatewayRepository = \Mockery::mock(\Box\Mod\Invoice\Repository\PayGatewayRepository::class)->shouldIgnoreMissing();
        $payGatewayRepository->shouldReceive('findEnabled')->byDefault()->andReturn([]);

        $taxRepository = \Mockery::mock(\Box\Mod\Invoice\Repository\TaxRepository::class)->shouldIgnoreMissing();

        $orderRepository = \Mockery::mock(\Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
        $orderRepository->shouldReceive('find')->byDefault()->andReturnUsing(static function (?int $id): ?object {
            if ($id === null) {
                return null;
            }
            $order = new \Box\Mod\Order\Entity\Order();
            $prop = new \ReflectionProperty($order, 'id');
            $prop->setValue($order, $id);

            return $order;
        });
        $orderRepository->shouldReceive('findByClientId')->byDefault()->andReturn([]);
        $orderRepository->shouldReceive('findForClientById')->byDefault()->andReturn(null);
        $orderRepository->shouldReceive('findOneByProductId')->byDefault()->andReturn(null);
        $orderRepository->shouldReceive('getSoonExpiringActiveOrders')->byDefault()->andReturn([]);
        $orderRepository->shouldReceive('getExpired')->byDefault()->andReturn([]);
        $orderRepository->shouldReceive('findAddons')->byDefault()->andReturn([]);

        $orderMetaRepository = \Mockery::mock(\Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
        $orderMetaRepository->shouldReceive('getPairsForOrder')->byDefault()->andReturn([]);
        $orderMetaRepository->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
        $orderMetaRepository->shouldReceive('deleteByOrderId')->byDefault()->andReturn(0);

        $orderStatusRepository = \Mockery::mock(\Box\Mod\Order\Repository\OrderStatusRepository::class)->shouldIgnoreMissing();
        $orderStatusRepository->shouldReceive('findByOrderId')->byDefault()->andReturn([]);
        $orderStatusRepository->shouldReceive('rmByOrderId')->byDefault()->andReturn(0);

        $extensionMetaRepository = \Mockery::mock(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)->shouldIgnoreMissing();
        $extensionMetaRepository->shouldReceive('findOneByExtensionAndScope')->byDefault()->andReturn(null);
        $extensionMetaRepository->shouldReceive('findByExtensionAndScope')->byDefault()->andReturn([]);
        $extensionMetaRepository->shouldReceive('deleteByExtensionAndScope')->byDefault()->andReturn(0);

        $emailTemplateRepository = \Mockery::mock(\Box\Mod\Email\Repository\EmailTemplateRepository::class)->shouldIgnoreMissing();
        $templateQueryBuilder = \Mockery::mock(\Doctrine\ORM\QueryBuilder::class)->shouldIgnoreMissing();
        $emailTemplateRepository->shouldReceive('find')->byDefault()->andReturn(null);
        $emailTemplateRepository->shouldReceive('findOneByActionCode')->byDefault()->andReturn(null);
        $emailTemplateRepository->shouldReceive('getSearchQueryBuilder')->byDefault()->andReturn($templateQueryBuilder);

        $emailTemplateGroupRepository = \Mockery::mock(\Box\Mod\Email\Repository\EmailTemplateGroupRepository::class)->shouldIgnoreMissing();
        $emailTemplateGroupRepository->shouldReceive('getGroupIdsForTemplate')->byDefault()->andReturn([]);
        $emailTemplateGroupRepository->shouldReceive('countTemplatesUsingGroup')->byDefault()->andReturn(0);

        $activityClientEmailRepository = \Mockery::mock(\Box\Mod\Email\Repository\ActivityClientEmailRepository::class)->shouldIgnoreMissing();
        $queuedEmailRepository = \Mockery::mock(\Box\Mod\Email\Repository\QueuedEmailRepository::class)->shouldIgnoreMissing();

        $kbArticleRepository = \Mockery::mock(\Box\Mod\Support\Repository\KbArticleRepository::class)->shouldIgnoreMissing();
        $kbArticleCategoryRepository = \Mockery::mock(\Box\Mod\Support\Repository\KbArticleCategoryRepository::class)->shouldIgnoreMissing();
        $cannedResponseRepository = \Mockery::mock(\Box\Mod\Support\Repository\CannedResponseRepository::class)->shouldIgnoreMissing();
        $cannedResponseCategoryRepository = \Mockery::mock(\Box\Mod\Support\Repository\CannedResponseCategoryRepository::class)->shouldIgnoreMissing();
        $helpdeskRepository = \Mockery::mock(\Box\Mod\Support\Repository\HelpdeskRepository::class)->shouldIgnoreMissing();
        $supportTicketRepository = \Mockery::mock(\Box\Mod\Support\Repository\SupportTicketRepository::class)->shouldIgnoreMissing();
        $supportTicketMessageRepository = \Mockery::mock(\Box\Mod\Support\Repository\SupportTicketMessageRepository::class)->shouldIgnoreMissing();
        $supportTicketNoteRepository = \Mockery::mock(\Box\Mod\Support\Repository\SupportTicketNoteRepository::class)->shouldIgnoreMissing();
        $supportTicketMessageHistoryRepository = \Mockery::mock(\Box\Mod\Support\Repository\SupportTicketMessageHistoryRepository::class)->shouldIgnoreMissing();

        $em = \Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
        $em->shouldReceive('getRepository')->byDefault()->andReturnUsing(static fn (string $class): object => match ($class) {
            \Box\Mod\Staff\Entity\Admin::class => $adminRepository,
            \Box\Mod\Staff\Entity\AdminGroup::class => $adminGroupRepository,
            \Box\Mod\Staff\Entity\AdminGroupMember::class => $adminGroupMemberRepository,
            \Box\Mod\Client\Entity\Client::class => $clientRepository,
            \Box\Mod\Client\Entity\ClientGroup::class => $clientGroupRepository,
            \Box\Mod\Client\Entity\ClientBalance::class => $clientBalanceRepository,
            \Box\Mod\Client\Entity\ClientPasswordReset::class => $clientPasswordResetRepository,
            \Box\Mod\Email\Entity\EmailTemplate::class => $emailTemplateRepository,
            \Box\Mod\Email\Entity\EmailTemplateGroup::class => $emailTemplateGroupRepository,
            \Box\Mod\Email\Entity\ActivityClientEmail::class => $activityClientEmailRepository,
            \Box\Mod\Email\Entity\QueuedEmail::class => $queuedEmailRepository,
            \Box\Mod\Support\Entity\KbArticle::class => $kbArticleRepository,
            \Box\Mod\Support\Entity\KbArticleCategory::class => $kbArticleCategoryRepository,
            \Box\Mod\Support\Entity\CannedResponse::class => $cannedResponseRepository,
            \Box\Mod\Support\Entity\CannedResponseCategory::class => $cannedResponseCategoryRepository,
            \Box\Mod\Support\Entity\Helpdesk::class => $helpdeskRepository,
            \Box\Mod\Support\Entity\SupportTicket::class => $supportTicketRepository,
            \Box\Mod\Support\Entity\SupportTicketMessage::class => $supportTicketMessageRepository,
            \Box\Mod\Support\Entity\SupportTicketNote::class => $supportTicketNoteRepository,
            \Box\Mod\Support\Entity\SupportTicketMessageHistory::class => $supportTicketMessageHistoryRepository,
            \Box\Mod\Invoice\Entity\Invoice::class => $invoiceRepository,
            \Box\Mod\Invoice\Entity\InvoiceItem::class => $invoiceItemRepository,
            \Box\Mod\Invoice\Entity\Transaction::class => $transactionRepository,
            \Box\Mod\Invoice\Entity\Subscription::class => $subscriptionRepository,
            \Box\Mod\Invoice\Entity\PayGateway::class => $payGatewayRepository,
            \Box\Mod\Invoice\Entity\Tax::class => $taxRepository,
            \Box\Mod\Order\Entity\Order::class => $orderRepository,
            \Box\Mod\Order\Entity\OrderMeta::class => $orderMetaRepository,
            \Box\Mod\Order\Entity\OrderStatus::class => $orderStatusRepository,
            \Box\Mod\Extension\Entity\Extension::class => \Mockery::mock(\Box\Mod\Extension\Repository\ExtensionRepository::class)->shouldIgnoreMissing(),
            default => $extensionMetaRepository,
        });

        return $em;
    };

    $staffService = \Mockery::mock(\Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffService->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturn(true);

    $emailService = \Mockery::mock(\Box\Mod\Email\Service::class)->shouldIgnoreMissing();
    $emailTemplateGroupRepositoryDefault = \Mockery::mock(\Box\Mod\Email\Repository\EmailTemplateGroupRepository::class)->shouldIgnoreMissing();
    $emailTemplateGroupRepositoryDefault->shouldReceive('countTemplatesUsingGroup')->byDefault()->andReturn(0);
    $emailService->shouldReceive('getTemplateGroupRepository')->byDefault()->andReturn($emailTemplateGroupRepositoryDefault);

    $di['mod_service'] = $di->protect(static function (string $name = '', string $sub = '') use ($staffService, $emailService): object {
        if (strtolower($name) === 'staff') {
            return $staffService;
        }

        if (strtolower($name) === 'email') {
            return $emailService;
        }

        return \Mockery::mock()->shouldIgnoreMissing();
    });

    return $di;
}

/**
 * Create a module service resolver with staff permissions enabled by default.
 *
 * @param array<string, object> $services
 */
function moduleService(array $services = []): \Closure
{
    $staffService = $services['staff'] ?? \Mockery::mock(\Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffService->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturn(true);

    if (!isset($services['email'])) {
        $emailTemplateGroupRepositoryDefault = \Mockery::mock(\Box\Mod\Email\Repository\EmailTemplateGroupRepository::class)->shouldIgnoreMissing();
        $emailTemplateGroupRepositoryDefault->shouldReceive('countTemplatesUsingGroup')->byDefault()->andReturn(0);
        $services['email'] = \Mockery::mock(\Box\Mod\Email\Service::class)->shouldIgnoreMissing();
        $services['email']->shouldReceive('getTemplateGroupRepository')->byDefault()->andReturn($emailTemplateGroupRepositoryDefault);
    }

    return static function (string $name = '', string $sub = '') use ($services, $staffService): object {
        if (strtolower($name) === 'staff') {
            return $staffService;
        }

        $module = strtolower($name);
        $moduleWithSub = $sub === '' ? $module : $module . ':' . strtolower($sub);

        return $services[$moduleWithSub] ?? $services[$module] ?? \Mockery::mock()->shouldIgnoreMissing();
    };
}

/**
 * Access a private property on an object using reflection.
 *
 * @return mixed The property value when getting, null when setting
 */
function accessPrivate(object $instance, string $property, mixed $value = null): mixed
{
    $refl = new \ReflectionClass($instance);
    $prop = null;

    if ($refl->hasProperty($property)) {
        $prop = $refl->getProperty($property);
    } else {
        $parentClass = $refl->getParentClass();
        if ($parentClass && $parentClass->hasProperty($property)) {
            $prop = $parentClass->getProperty($property);
        }
    }

    if ($prop === null || $prop->isStatic()) {
        return null;
    }

    // If value is provided, set the property
    if (func_num_args() > 2) {
        $prop->setValue($instance, $value);

        return null;
    }

    // Otherwise, get the property value
    return $prop->getValue($instance);
}

/**
 * Inject a mock filesystem into a service that has a private filesystem property.
 */
function injectMockFilesystem(object $service, \Mockery\MockInterface $filesystemMock): void
{
    $refl = new \ReflectionClass($service);
    while (!$refl->hasProperty('filesystem')) {
        $parent = $refl->getParentClass();
        if ($parent === false) {
            throw new \ReflectionException(sprintf('Property %s::$filesystem does not exist', $service::class));
        }

        $refl = $parent;
    }

    $prop = $refl->getProperty('filesystem');
    $prop->setValue($service, $filesystemMock);
}

/**
 * Create an EM mock that assigns auto-increment IDs to entities on flush.
 * Inherits repository mocks from the container's default EM.
 */
function entityManagerWithIds(Container $di): object
{
    $factory = $di->raw('em');
    $defaultEm = $factory();
    $persisted = [];
    $nextId = 1;
    $emMock = \Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->andReturnUsing(static fn (string $class) => $defaultEm->getRepository($class));
    $emMock->shouldReceive('persist')->andReturnUsing(static function (object $entity) use (&$persisted): void {
        $persisted[] = $entity;
    });
    $emMock->shouldReceive('flush')->andReturnUsing(static function () use (&$persisted, &$nextId): void {
        foreach ($persisted as $entity) {
            $refl = new \ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                $prop->setValue($entity, $nextId++);
            }
        }
        $persisted = [];
    });
    $emMock->shouldReceive('remove')->andReturnNull();
    $emMock->shouldIgnoreMissing();

    return $emMock;
}
