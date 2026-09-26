<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Entity\ProductPayment;
use Box\Mod\Product\Entity\ProductPaymentPeriod;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as DBALLoggingMiddleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\AbstractLogger;
use Symfony\Component\Filesystem\Path;

function productSearchEntityManager(?AbstractLogger $logger = null): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    $connectionConfig = new DBALConfiguration();
    if ($logger !== null) {
        $connectionConfig->setMiddlewares([new DBALLoggingMiddleware($logger)]);
    }

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $connectionConfig), $config);
}

test('getSearchQueryBuilder orders by priority ascending with no filters', function (): void {
    $dql = productSearchEntityManager()->getRepository(Product::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toContain('ORDER BY p.priority ASC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = productSearchEntityManager()->getRepository(Product::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', p.id');
    } else {
        expect($dql)->not->toContain(', p.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY p.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY p.id DESC', false],
    'title' => [['sort' => 'title'], 'ORDER BY p.title ASC, p.id ASC', true],
    'slug' => [['sort' => 'slug', 'direction' => 'desc'], 'ORDER BY p.slug DESC, p.id DESC', true],
    'status' => [['sort' => 'status'], 'ORDER BY p.status ASC, p.id ASC', true],
    'type' => [['sort' => 'type'], 'ORDER BY p.type ASC, p.id ASC', true],
    'priority' => [['sort' => 'priority'], 'ORDER BY p.priority ASC, p.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY p.createdAt ASC, p.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY p.updatedAt ASC, p.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'p.id; DROP TABLE product'], 'ORDER BY p.priority ASC', false],
    'invalid direction falls back to ascending' => [['sort' => 'title', 'direction' => 'sideways'], 'ORDER BY p.title ASC, p.id ASC', true],
]);

test('paginated product pricing fetches payments and periods without per-product queries', function (): void {
    $logger = new class extends AbstractLogger {
        /** @var list<string> */
        public array $queries = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            if (isset($context['sql'])) {
                $this->queries[] = $context['sql'];
            }
        }
    };

    $entityManager = productSearchEntityManager($logger);
    (new SchemaTool($entityManager))->createSchema(array_map(
        $entityManager->getClassMetadata(...),
        [Product::class, ProductCategory::class, ProductPayment::class, ProductPaymentPeriod::class],
    ));

    foreach (range(1, 5) as $productNumber) {
        $payment = (new ProductPayment())->setType(ProductPayment::RECURRENT);
        foreach (['1M', '1Y'] as $periodNumber => $code) {
            $payment->addPeriod(
                (new ProductPaymentPeriod())
                    ->setCode($code)
                    ->setPrice($productNumber * 10 + $periodNumber)
                    ->setSortOrder($periodNumber),
            );
        }

        $product = (new Product())
            ->setTitle("Product {$productNumber}")
            ->setPriority($productNumber)
            ->setProductPayment($payment);

        $entityManager->persist($payment);
        $entityManager->persist($product);
    }
    $entityManager->persist((new Product())->setTitle('Product 6')->setPriority(6));
    $entityManager->flush();
    $entityManager->clear();
    $logger->queries = [];

    $repository = $entityManager->getRepository(Product::class);
    $page = (new OffsetPaginator(true))->paginate(
        $repository->getSearchQueryBuilder(['status' => 'enabled', 'show_hidden' => false]),
        Window::fromPageNumberAndSize(2, 2),
    );
    $products = $page->getItems();
    $queriesAfterPagination = count($logger->queries);

    expect($page->getTotalCount())->toBe(6)
        ->and($products)->toHaveCount(2)
        ->and(array_map(static fn (Product $product): ?string => $product->getTitle(), $products))
        ->toBe(['Product 3', 'Product 4']);

    foreach ($products as $product) {
        $payment = $product->getProductPayment();
        $periodCodes = $payment instanceof ProductPayment
            ? array_map(
                static fn (ProductPaymentPeriod $period): string => $period->getCode(),
                $payment->getPeriods()->toArray(),
            )
            : [];

        expect($payment)->toBeInstanceOf(ProductPayment::class)
            ->and($periodCodes)->toBe(['1M', '1Y']);
    }

    expect($logger->queries)->toHaveCount($queriesAfterPagination);

    $lastPage = (new OffsetPaginator(true))->paginate(
        $repository->getSearchQueryBuilder(['status' => 'enabled', 'show_hidden' => false]),
        Window::fromPageNumberAndSize(3, 2),
    );
    expect($lastPage->getTotalCount())->toBe(6)
        ->and($lastPage->getItems())->toHaveCount(2)
        ->and($lastPage->getItems()[1]->getTitle())->toBe('Product 6')
        ->and($lastPage->getItems()[1]->getProductPayment())->toBeNull();
});
