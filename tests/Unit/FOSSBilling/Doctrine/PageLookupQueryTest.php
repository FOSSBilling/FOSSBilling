<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Extension\Entity\Extension;
use Box\Mod\System\Entity\Setting;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;
use Psr\Log\AbstractLogger;

test('page lookups reuse queries across separately created module services', function (): void {
    $logger = new class extends AbstractLogger {
        public array $queries = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            if (isset($context['sql'])) {
                $this->queries[] = $context['sql'];
            }
        }
    };
    $configuration = new Configuration();
    $configuration->setMiddlewares([new Middleware($logger)]);
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    $em = EntityManagerFactory::create($connection);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Setting::class, Currency::class, Extension::class]));
    $em->persist((new Setting())->setParam('company_name')->setValue('Example Company'));
    $em->persist((new Setting())->setParam('hide_company_public')->setValue('0'));
    $em->persist((new Currency('GBP'))->setIsDefault(true));
    foreach (['redirect', 'news', 'cookieconsent'] as $name) {
        $em->persist((new Extension())->setType('mod')->setName($name)->setStatus(Extension::STATUS_INSTALLED));
    }
    $em->flush();
    $em->clear();

    // Use the real factory: each call creates a new service, but shares the EntityManager.
    $di = require PATH_ROOT . '/di.php';
    $di['em'] = $em;
    expect($di['mod_service']('system'))->not->toBe($di['mod_service']('system'));
    $logger->queries = [];

    for ($i = 0; $i < 2; ++$i) {
        expect($di['mod_service']('currency')->getCurrencyRepository()->findDefault()?->getCode())->toBe('GBP');
        expect($di['mod_service']('currency')->getCurrencyRepository()->findOneByCode('GBP')?->getCode())->toBe('GBP');
    }
    for ($i = 0; $i < 3; ++$i) {
        expect($di['mod_service']('system')->getCompany()['name'])->toBe('Example Company');
        expect($di['mod_service']('system')->getParamValue('hide_company_public'))->toBe('0');
    }
    expect($di['mod_service']('extension')->isExtensionActive('mod', 'redirect'))->toBeTrue();
    expect($di['mod_service']('extension')->getInstalledMods())->toContain('news');
    foreach (['news', 'cookieconsent', 'cookieconsent'] as $name) {
        expect($di['mod_service']('extension')->isExtensionActive('mod', $name))->toBeTrue();
    }

    expect($logger->queries)->toHaveCount(4);
});
