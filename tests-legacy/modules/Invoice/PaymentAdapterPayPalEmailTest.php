<?php

declare(strict_types=1);

use Box\Mod\Invoice\Service;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class PaymentAdapterPayPalEmailTest extends BBTestCase
{
    public function testProcessTransactionHandlesSubscriptionPaymentByGeneratingRenewalInvoice(): void
    {
        $adapter = new Payment_Adapter_PayPalEmail([
            'email' => 'merchant@example.com',
            'test_mode' => true,
        ]);

        $renewalInvoice = new Model_Invoice();
        $renewalInvoice->loadBean(new DummyBean());
        $renewalInvoice->id = 55;
        $renewalInvoice->approved = false;

        $originalInvoice = new Model_Invoice();
        $originalInvoice->loadBean(new DummyBean());
        $originalInvoice->id = 10;
        $originalInvoice->approved = true;

        $invoiceService = $this->getMockBuilder(Service::class)
            ->onlyMethods(['generateRenewalInvoiceForSubscriptionPayment', 'isInvoiceTypeDeposit', 'approveInvoice', 'getTotalWithTax'])
            ->getMock();
        $invoiceService->expects($this->once())
            ->method('generateRenewalInvoiceForSubscriptionPayment')
            ->with('SUB-123', 7)
            ->willReturn($renewalInvoice);
        $invoiceService->expects($this->once())
            ->method('isInvoiceTypeDeposit')
            ->with($renewalInvoice)
            ->willReturn(false);
        $invoiceService->expects($this->once())
            ->method('approveInvoice')
            ->with($renewalInvoice, ['use_credits' => false]);
        $invoiceService->expects($this->once())
            ->method('getTotalWithTax')
            ->with($originalInvoice)
            ->willReturn(14.99);

        $dbMock = $this->createMock(Box_Database::class);
        $dbMock->expects($this->once())
            ->method('load')
            ->with('Invoice', 10)
            ->willReturn($originalInvoice);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function (string $service) use ($invoiceService) {
            if ($service === 'Invoice') {
                return $invoiceService;
            }

            throw new RuntimeException('Unexpected service request: ' . $service);
        });
        $adapter->setDi($di);

        $apiAdmin = new class {
            public array $updates = [];
            public array $funds = [];
            public array $paid = [];
            private int $txReads = 0;

            public function invoice_transaction_get(array $data): array
            {
                ++$this->txReads;

                return match ($this->txReads) {
                    1 => [
                        'invoice_id' => 10,
                        'type' => 'subscr_payment',
                        'txn_id' => null,
                        'txn_status' => 'Pending',
                        'amount' => 0,
                        'currency' => null,
                        'status' => 'received',
                    ],
                    2 => [
                        'invoice_id' => 10,
                        'type' => 'subscr_payment',
                        'txn_id' => 'PAY-123',
                        'txn_status' => 'Pending',
                        'amount' => 0,
                        'currency' => 'USD',
                        'status' => Model_Transaction::STATUS_PROCESSING,
                    ],
                    default => throw new RuntimeException('Unexpected transaction read'),
                };
            }

            public function invoice_get(array $data): array
            {
                return [
                    'id' => 10,
                    'client' => ['id' => 7],
                ];
            }

            public function invoice_transaction_claim_for_processing(array $data): bool
            {
                return true;
            }

            public function invoice_transaction_update(array $data): void
            {
                $this->updates[] = $data;
            }

            public function client_balance_add_funds(array $data): void
            {
                $this->funds[] = $data;
            }

            public function invoice_pay_with_credits(array $data): void
            {
                $this->paid[] = $data;
            }
        };

        $adapter->processTransaction($apiAdmin, 1, [
            'get' => ['invoice_id' => 10],
            'post' => [
                'txn_type' => 'subscr_payment',
                'payment_status' => 'Completed',
                'txn_id' => 'PAY-123',
                'mc_gross' => '14.99',
                'mc_currency' => 'USD',
                'subscr_id' => 'SUB-123',
            ],
        ], 2);

        $this->assertContains(['id' => 1, 'txn_id' => 'PAY-123'], $apiAdmin->updates);
        $this->assertContains(['id' => 1, 'amount' => '14.99'], $apiAdmin->updates);
        $this->assertContains(['id' => 1, 'currency' => 'USD'], $apiAdmin->updates);
        $this->assertContains(['id' => 1, 'txn_status' => 'Completed'], $apiAdmin->updates);
        $this->assertContains(['id' => 1, 'invoice_id' => 55], $apiAdmin->updates);
        $this->assertSame(1, count($apiAdmin->funds));
        $this->assertSame([
            'id' => 7,
            'amount' => '14.99',
            'description' => 'PayPal transaction PAY-123',
            'type' => 'PayPal',
            'rel_id' => 'PAY-123',
        ], $apiAdmin->funds[0]);
        $this->assertSame([['id' => 55]], $apiAdmin->paid);
        $this->assertSame(Model_Transaction::STATUS_PROCESSED, $apiAdmin->updates[array_key_last($apiAdmin->updates)]['status']);
    }

    public function testProcessTransactionRejectsAmountMismatch(): void
    {
        $adapter = new Payment_Adapter_PayPalEmail([
            'email' => 'merchant@example.com',
            'test_mode' => true,
        ]);

        $originalInvoice = new Model_Invoice();
        $originalInvoice->loadBean(new DummyBean());
        $originalInvoice->id = 10;
        $originalInvoice->approved = true;

        $invoiceService = $this->getMockBuilder(Service::class)
            ->onlyMethods(['isInvoiceTypeDeposit', 'getTotalWithTax'])
            ->getMock();
        $invoiceService->expects($this->never())
            ->method('isInvoiceTypeDeposit');
        $invoiceService->expects($this->once())
            ->method('getTotalWithTax')
            ->with($originalInvoice)
            ->willReturn(100.00);

        $dbMock = $this->createMock(Box_Database::class);
        $dbMock->expects($this->once())
            ->method('load')
            ->with('Invoice', 10)
            ->willReturn($originalInvoice);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function (string $service) use ($invoiceService) {
            if ($service === 'Invoice') {
                return $invoiceService;
            }

            throw new RuntimeException('Unexpected service request: ' . $service);
        });
        $adapter->setDi($di);

        $apiAdmin = new class {
            private int $txReads = 0;

            public function invoice_transaction_get(array $data): array
            {
                ++$this->txReads;

                return match ($this->txReads) {
                    1 => [
                        'invoice_id' => 10,
                        'type' => 'web_accept',
                        'txn_id' => null,
                        'txn_status' => 'Pending',
                        'amount' => 0,
                        'currency' => null,
                        'status' => 'received',
                    ],
                    2 => [
                        'invoice_id' => 10,
                        'type' => 'web_accept',
                        'txn_id' => 'PAY-456',
                        'txn_status' => 'Pending',
                        'amount' => 0,
                        'currency' => 'USD',
                        'status' => Model_Transaction::STATUS_PROCESSING,
                    ],
                    default => throw new RuntimeException('Unexpected transaction read'),
                };
            }

            public function invoice_get(array $data): array
            {
                return [
                    'id' => 10,
                    'client' => ['id' => 7],
                ];
            }

            public function invoice_transaction_claim_for_processing(array $data): bool
            {
                return true;
            }

            public function invoice_transaction_update(array $data): void
            {
            }

            public function client_balance_add_funds(array $data): void
            {
            }

            public function invoice_pay_with_credits(array $data): void
            {
            }
        };

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Payment amount does not match the expected invoice total');

        $adapter->processTransaction($apiAdmin, 1, [
            'get' => ['invoice_id' => 10],
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'PAY-456',
                'mc_gross' => '95.00',
                'mc_currency' => 'USD',
            ],
        ], 2);
    }
}
