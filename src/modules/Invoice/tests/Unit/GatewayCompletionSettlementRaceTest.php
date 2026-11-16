<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

class CompletionWaitInvoiceService extends Box\Mod\Invoice\Service
{
    public ?Closure $onCompletionWait = null;

    protected function withPaymentCompletionLock(int $transactionId, callable $operation): mixed
    {
        if ($this->onCompletionWait !== null) {
            $connection = $this->getDi()['em']->getConnection();
            $name = 'fb:gateway-completion:' . $transactionId;
            if (FOSSBilling\Core\Doctrine\NamedLock::acquire($connection, $name, 0)) {
                FOSSBilling\Core\Doctrine\NamedLock::release($connection, $name);

                throw new LogicException('Expected the older completion worker to own the mutex');
            }
            ($this->onCompletionWait)();
        }

        return parent::withPaymentCompletionLock($transactionId, $operation);
    }
}

test('an older partial completion cannot acknowledge a later settlement even if its worker crashes', function (bool $staffReconciliation): void {
    if (!function_exists('pcntl_fork')) {
        $this->markTestSkipped('Requires process control for coordinated concurrent workers.');
    }
    $filesystem = new Filesystem();
    $directory = Path::join(sys_get_temp_dir(), 'completion-settlement-' . bin2hex(random_bytes(8)));
    $filesystem->mkdir($directory);
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $directory . '/test.sqlite']);
    $child = null;
    $waitFor = static function (string $path) use ($filesystem): void {
        $deadline = microtime(true) + 10;
        while (!$filesystem->exists($path)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Worker barrier timed out: ' . $path);
            }
            usleep(1000);
        }
    };

    try {
        [, $em, $client, $gateway, $transaction] = receiptTestContext($connection);
        $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
        $em->persist($invoice);
        $em->flush();
        $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(100)->setQuantity(1));
        $transaction->setInvoice($invoice)->setAmount('40');
        $em->flush();
        $billing = gatewayDepositTestService($em);
        $billing->recordGatewayPayment($transaction, $client, 'pi_partial_race', 'Stripe transaction pi_partial_race', 'transaction', (string) $transaction->getId());
        // Funding arrives after the partial receipt's original no-op completion.
        $em->persist((new Transaction())->setInvoice($invoice)->setGateway($gateway)->setAmount('60')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSED));
        $transaction->setGatewayCompletionState(Transaction::GATEWAY_COMPLETION_RUNNING);
        $em->flush();
        $receiptId = (int) $connection->fetchOne('SELECT id FROM client_balance WHERE gateway_receipt_key IS NOT NULL');
        $id = $transaction->getId();
        $clientId = $client->getId();
        $child = pcntl_fork();
        if ($child === -1) {
            throw new RuntimeException('Could not fork settlement worker');
        }
        if ($child === 0) {
            try {
                $waitFor($directory . '/observed');
                $worker = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $directory . '/test.sqlite']), $em->getConfiguration());
                $service = gatewayDepositTestService($worker, CompletionWaitInvoiceService::class);
                $service->onCompletionWait = fn () => $filesystem->dumpFile($directory . '/waiting', '1');
                $service->shouldReceive('completeGatewayPayment')->once()->andThrow(new Error('stopped after settlement commit'));
                $tx = $worker->find(Transaction::class, $id);
                $buyer = $worker->find(Client::class, $clientId);

                try {
                    if ($staffReconciliation) {
                        $service->reconcileGatewayReceipt($receiptId, (int) $id, 'pi_partial_race');
                    } else {
                        $service->recordGatewayPayment($tx, $buyer, 'pi_partial_race', 'Stripe transaction pi_partial_race', 'transaction', (string) $id);
                    }

                    throw new RuntimeException('Expected interrupted completion');
                } catch (Error $error) {
                    if ($error->getMessage() !== 'stopped after settlement commit') {
                        throw $error;
                    }
                }
                exit(0);
            } catch (Throwable $error) {
                $filesystem->dumpFile($directory . '/error', (string) $error);
                exit(1);
            }
        }
        $observer = new class($directory, $waitFor, $filesystem, $connection) {
            private bool $paused = false;

            public function __construct(private readonly string $directory, private readonly Closure $waitFor, private readonly Filesystem $filesystem, private readonly Doctrine\DBAL\Connection $connection)
            {
            }

            public function postLoad(Doctrine\ORM\Event\PostLoadEventArgs $event): void
            {
                if ($this->paused || !$event->getObject() instanceof Invoice) {
                    return;
                }
                $this->paused = true;
                $this->filesystem->dumpFile($this->directory . '/observed', '1');
                ($this->waitFor)($this->directory . '/waiting');
                // The settlement worker must still be waiting for this completion owner.
                expect($this->connection->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_UNPAID);
            }
        };
        $em->getEventManager()->addEventListener(['postLoad'], $observer);
        $billing->completeGatewayPayment($transaction);
        $em->getEventManager()->removeEventListener(['postLoad'], $observer);
        pcntl_waitpid($child, $status);
        $child = null;
        expect($filesystem->exists($directory . '/observed'))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0, $filesystem->exists($directory . '/error') ? $filesystem->readFile($directory . '/error') : '')
            ->and($connection->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_PAID);
        $table = $connection->quoteSingleIdentifier('transaction');
        expect((int) $connection->fetchOne("SELECT gateway_completion_state FROM $table WHERE id = ?", [$id]))->toBe($staffReconciliation ? Transaction::GATEWAY_COMPLETION_PENDING : Transaction::GATEWAY_COMPLETION_RUNNING);
        $fresh = new EntityManager($connection, $em->getConfiguration());
        $billing = gatewayDepositTestService($fresh);
        $notified = 0;
        $events = new Symfony\Component\EventDispatcher\EventDispatcher();
        $events->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$notified): void { ++$notified; });
        $billing->getDi()['event_dispatcher'] = $events;
        $tx = $fresh->find(Transaction::class, $id);
        $billing->completeGatewayPayment($tx);
        $billing->completeGatewayPayment($tx);
        expect($notified)->toBe(1)->and($tx->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE)
            ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0);
    } finally {
        if (is_int($child) && $child > 0) {
            pcntl_waitpid($child, $status);
        }
        $connection->close();
        $filesystem->remove($directory);
    }
})->with([false, true]);
