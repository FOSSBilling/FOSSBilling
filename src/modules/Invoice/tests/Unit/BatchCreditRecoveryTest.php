<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('batch credit recovery reloads later invoices after a currency rejection closes the manager', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoices = [];
    foreach ([['EUR', 100], ['USD', 50], ['USD', 80], ['USD', 999]] as [$currency,$amount]) {
        $invoice = (new Invoice())->setClientId($client->getId())->setCurrency($currency)->setStatus('unpaid')->setIssued(true);
        $em->persist($invoice);
        $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice($amount)->setQuantity(1)->setCharged(false)->setStatus('pending_payment'));
        $invoices[] = $invoice;
    }
    $em->persist((new Box\Mod\Client\Entity\ClientBalance())->setClient($client)->setType('credit')->setAmount('130'));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('findAllUnpaid')->once()->andReturn(array_map(fn (Invoice $invoice): array => ['id' => $invoice->getId()], $invoices));
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('createEntityManager')->once()->andReturnUsing(fn (): Doctrine\ORM\EntityManager => new Doctrine\ORM\EntityManager($em->getConnection(), $em->getConfiguration()));
    expect($service->doBatchPayWithCredits([]))->toBeTrue();
    $manager = $service->getDi()['em'];
    expect($em->isOpen())->toBeFalse()->and($manager->isOpen())->toBeTrue()->and($manager->contains($invoices[1]))->toBeFalse();
    expect($manager->getConnection()->fetchFirstColumn('SELECT status FROM invoice ORDER BY id'))->toBe(['unpaid', 'paid', 'paid', 'unpaid'])
        ->and((float) $manager->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
        ->and((int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(3);
});

test('ClientBalance acknowledges its transaction after the batch replaces the manager', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setGateway('ClientBalance');
    $invoices = [];
    foreach ([['USD', 10], ['EUR', 100], ['USD', 15]] as [$currency,$amount]) {
        $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency($currency)->setStatus('unpaid')->setIssued(true);
        $em->persist($invoice);
        $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setStatus('pending_payment')->setPrice($amount)->setQuantity(1)->setCharged(false));
        $invoices[] = $invoice;
    }
    $transaction->setInvoice($invoices[0])->setAmount(null);
    $em->persist((new Box\Mod\Client\Entity\ClientBalance())->setClient($client)->setType('credit')->setAmount('25'));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('findAllUnpaid')->once()->andReturn([['id' => $invoices[1]->getId()], ['id' => $invoices[2]->getId()]]);
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('createEntityManager')->once()->andReturnUsing(fn (): Doctrine\ORM\EntityManager => new Doctrine\ORM\EntityManager($em->getConnection(), $em->getConfiguration()));
    $di = $service->getDi();
    $di['loggedin_client'] = $client;
    $auth = Mockery::mock();
    $auth->shouldReceive('isClientLoggedIn')->once()->andReturn(true);
    $di['auth'] = $auth;
    $adapter = new Payment_Adapter_ClientBalance();
    $adapter->setDi($di);
    expect($adapter->processTransaction(null, $transaction->getId(), [], $gateway->getId()))->toBeTrue();
    $connection = $di['em']->getConnection();
    expect($em->isOpen())->toBeFalse()
        ->and($connection->fetchFirstColumn('SELECT status FROM invoice ORDER BY id'))->toBe(['paid', 'unpaid', 'paid'])
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$transaction->getId()]))->toBe('processed')
        ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0);
});
