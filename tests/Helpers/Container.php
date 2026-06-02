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
        $dbal->shouldReceive('fetchAssociative')->byDefault()->andReturn([]);
        $dbal->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
        $dbal->shouldReceive('fetchOne')->byDefault()->andReturn(null);
        $dbal->shouldReceive('executeStatement')->byDefault()->andReturn(1);

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
    $di['em'] = static function (): object {
        $extensionMetaRepository = \Mockery::mock(\Box\Mod\Extension\Repository\ExtensionMetaRepository::class)->shouldIgnoreMissing();
        $extensionMetaRepository->shouldReceive('findOneByExtensionAndScope')->byDefault()->andReturn(null);
        $extensionMetaRepository->shouldReceive('findByExtensionAndScope')->byDefault()->andReturn([]);
        $extensionMetaRepository->shouldReceive('deleteByExtensionAndScope')->byDefault()->andReturn(0);

        $em = \Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
        $em->shouldReceive('getRepository')->byDefault()->andReturn($extensionMetaRepository);

        return $em;
    };

    $staffService = \Mockery::mock(\Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffService->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturn(true);
    $di['mod_service'] = $di->protect(static function (string $name = '', string $sub = '') use ($staffService): object {
        if (strtolower($name) === 'staff') {
            return $staffService;
        }

        return \Mockery::mock()->shouldIgnoreMissing();
    });

    return $di;
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
