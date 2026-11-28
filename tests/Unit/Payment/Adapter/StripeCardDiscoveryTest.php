<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Box\Mod\Invoice\ServiceSavedCards;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Stripe\StripeClient;

function stripeCardDiscoveryContext(bool $testMode): array
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
    $config->setUseSchemaEditorApi(true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('StripeCardDiscoveryProxies');
    $em = new EntityManager($connection, $config);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Client::class, PayGateway::class, PayGatewayCustomer::class]));
    $client = (new Client())->setEmail('victim@example.com')->setCurrency('USD');
    $gateway = (new PayGateway())->setGateway('Stripe')->setEnabled(true);
    $em->persist($client);
    $em->persist($gateway);
    $em->flush();
    $customers = Mockery::mock();
    $methods = Mockery::mock();
    $intents = Mockery::mock();
    $intents->shouldNotReceive('create');
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->customers = $customers;
    $stripe->paymentMethods = $methods;
    $stripe->paymentIntents = $intents;
    $adapter = new Payment_Adapter_Stripe([
        'gateway_id' => $gateway->getId(), 'test_mode' => $testMode,
        'api_key' => 'sk_live_dummy', 'pub_key' => 'pk_live_dummy',
        'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy',
        'notify_url' => 'https://billing.example/ipn.php',
    ]);
    (new ReflectionProperty($adapter, 'stripe'))->setValue($adapter, $stripe);
    $di = Tests\Helpers\container();
    $di['em'] = $em;
    $saved = new ServiceSavedCards();
    $saved->setDi($di);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $saved]));
    $adapter->setDi($di);

    return compact('connection', 'em', 'client', 'gateway', 'customers', 'methods', 'adapter', 'saved');
}

test('Stripe discovery cannot adopt a customer through email or a subscription cache', function (string $state, bool $testMode): void {
    $c = stripeCardDiscoveryContext($testMode);

    try {
        $c['client']->setEmailApproved($state === 'verified email');
        if (in_array($state, ['subscription cache', 'other mode', 'other gateway'], true)) {
            $gateway = $c['gateway'];
            if ($state === 'other gateway') {
                $gateway = (new PayGateway())->setGateway('Stripe')->setEnabled(true);
                $c['em']->persist($gateway);
            }
            $row = (new PayGatewayCustomer())->setPayGateway($gateway)->setClientId($c['client']->getId())
                ->setTestMode($state === 'other mode' ? !$testMode : $testMode)
                ->setExternalCustomerId('cus_victim')->setSavedCards($state !== 'subscription cache');
            $c['em']->persist($row);
        }
        $c['em']->flush();
        $before = $c['connection']->fetchAllAssociative('SELECT * FROM pay_gateway_customer');
        // A matching email and unscoped metadata must never prove card ownership.
        $victim = Stripe\Customer::constructFrom([
            'id' => 'cus_victim', 'email' => 'victim@example.com',
            'metadata' => ['client_id' => (string) $c['client']->getId()],
            'invoice_settings' => ['default_payment_method' => 'pm_victim'],
        ]);
        $c['customers']->shouldReceive('search')->byDefault()->andReturn((object) ['data' => [$victim]]);
        $c['customers']->shouldReceive('retrieve')->byDefault()->andReturn($victim);
        $c['methods']->shouldReceive('retrieve')->byDefault()->andReturn(Stripe\PaymentMethod::constructFrom([
            'id' => 'pm_victim', 'customer' => 'cus_victim', 'type' => 'card',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030],
        ]));

        $result = $c['adapter']->findCards(0, 25);

        expect($c['saved']->saved($c['gateway']->getId(), $testMode, $c['client']->getId()))->toBeNull()
            ->and($result)->toBe(['checked' => 1, 'found' => 0, 'next' => null])
            ->and($c['connection']->fetchAllAssociative('SELECT * FROM pay_gateway_customer'))->toBe($before);
        $c['customers']->shouldNotHaveReceived('search');
        $c['customers']->shouldNotHaveReceived('retrieve');
    } finally {
        $c['connection']->close();
    }
})->with(['unverified email', 'verified email', 'subscription cache', 'other mode', 'other gateway'])->with([false, true]);

test('Stripe discovery is read only and an owned card requires explicit selection for auto pay', function (bool $hasDefault, bool $testMode): void {
    $c = stripeCardDiscoveryContext($testMode);

    try {
        $row = (new PayGatewayCustomer())->setPayGateway($c['gateway'])->setClientId($c['client']->getId())
            ->setTestMode($testMode)->setExternalCustomerId('cus_owned')->setSavedCards(true);
        $c['em']->persist($row);
        $c['em']->flush();
        $before = $c['connection']->fetchAllAssociative('SELECT * FROM pay_gateway_customer');
        $c['customers']->shouldNotReceive('search');
        $c['customers']->shouldReceive('retrieve')->once()->with('cus_owned', [])->andReturn(Stripe\Customer::constructFrom([
            'id' => 'cus_owned', 'invoice_settings' => ['default_payment_method' => $hasDefault ? 'pm_owned' : null],
        ]));
        if (!$hasDefault) {
            $c['customers']->shouldReceive('allPaymentMethods')->once()->with('cus_owned', ['type' => 'card', 'limit' => 1])
                ->andReturn((object) ['data' => [(object) ['id' => 'pm_owned']]]);
        }
        $c['methods']->shouldReceive('retrieve')->byDefault()->with('pm_owned', [])->andReturn(Stripe\PaymentMethod::constructFrom([
            'id' => 'pm_owned', 'customer' => 'cus_owned', 'type' => 'card',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030],
        ]));

        expect($c['adapter']->findCards(0, 1))->toBe(['checked' => 1, 'found' => 1, 'next' => $c['client']->getId()])
            ->and($c['adapter']->findCards($c['client']->getId(), 1))->toBe(['checked' => 0, 'found' => 0, 'next' => null])
            ->and($c['connection']->fetchAllAssociative('SELECT * FROM pay_gateway_customer'))->toBe($before)
            ->and($c['saved']->saved($c['gateway']->getId(), $testMode, $c['client']->getId()))->toBeNull();

        $c['adapter']->cardDefault($c['client']->getId(), 'pm_owned');

        expect($c['saved']->saved($c['gateway']->getId(), $testMode, $c['client']->getId())['payment_method'])->toBe('pm_owned')
            ->and($row->getAutoPayAt())->toBeInstanceOf(DateTimeImmutable::class);
    } finally {
        $c['connection']->close();
    }
})->with([false, true])->with([false, true]);
