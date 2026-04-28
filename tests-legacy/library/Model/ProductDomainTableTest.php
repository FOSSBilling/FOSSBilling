<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ProductDomainTableTest extends BBTestCase
{
    private ?Model_ProductDomainTable $table = null;

    protected function setUp(): void
    {
        $this->table = new Model_ProductDomainTable();
    }

    public function testGetOrderLineConfigUsesRegistrationAndRenewalPricing(): void
    {
        $tld = new Model_Tld();
        $tld->loadBean(new DummyBean());
        $tld->tld = '.com';
        $tld->price_registration = 13;
        $tld->price_renew = 20;
        $tld->price_transfer = 15;

        $tldService = $this->getMockBuilder(\Box\Mod\Servicedomain\ServiceTld::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneByTld'])
            ->getMock();
        $tldService->expects($this->atLeastOnce())
            ->method('findOneByTld')
            ->with('.com')
            ->willReturn($tld);

        $di = $this->getDi();
        $di['period'] = $di->protect(fn (string $period): Box_Period => new Box_Period($period));
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $tldService);
        $this->table->setDi($di);

        $product = new Model_Product();
        $product->loadBean(new DummyBean());

        $line = $this->table->getOrderLineConfig($product, [
            'action' => 'register',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ]);

        $renewal = $this->table->getRenewalLineConfig($product, [
            'action' => 'register',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ]);

        $this->assertSame(['price' => 33.0, 'quantity' => 1, 'setup_price' => 0.0], $line);
        $this->assertSame(['price' => 20.0, 'quantity' => 2], $renewal);
    }

    public function testGetRelatedDiscountUsesRenewalPricingForFreeMultiYearDomain(): void
    {
        $tld = new Model_Tld();
        $tld->loadBean(new DummyBean());
        $tld->tld = '.com';
        $tld->price_registration = 13;
        $tld->price_renew = 20;
        $tld->price_transfer = 15;

        $tldService = $this->getMockBuilder(\Box\Mod\Servicedomain\ServiceTld::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneByTld'])
            ->getMock();
        $tldService->expects($this->once())
            ->method('findOneByTld')
            ->with('.com')
            ->willReturn($tld);

        $di = $this->getDi();
        $di['period'] = $di->protect(fn (string $period): Box_Period => new Box_Period($period));
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $tldService);
        $this->table->setDi($di);

        $product = new Model_Product();
        $product->loadBean(new DummyBean());

        $discount = $this->table->getRelatedDiscount([
            [
                'config' => [
                    'action' => 'register',
                    'domain' => [
                        'action' => 'register',
                        'register_sld' => 'example',
                        'register_tld' => '.com',
                    ],
                    'free_domain' => true,
                    'free_domain_periods' => ['2Y'],
                    'period' => '2Y',
                ],
            ],
        ], $product, [
            'action' => 'register',
            'register_sld' => 'example',
            'register_tld' => '.com',
            'period' => '2Y',
        ]);

        $this->assertSame(33.0, $discount);
    }
}
