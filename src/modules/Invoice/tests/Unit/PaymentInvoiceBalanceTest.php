<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('legacy payment DTO carries the payable balance and aggregate invoice tax', function (float $paid, bool $subscribe): void {
    $invoice = createEntity(Invoice::class, ['id' => 42]);
    $api = ['id' => 42, 'nr' => '42', 'serie' => 'INV', 'currency' => 'USD', 'tax' => 10.01,
        'amount_paid' => $paid, 'balance_due' => max(0.0, round(110.04 - $paid, 2)),
        'lines' => [
            ['id' => 1, 'title' => 'Taxable service', 'price' => 33.33, 'tax' => 0, 'quantity' => 3],
            ['id' => 2, 'title' => 'Untaxed service', 'price' => 0.04, 'tax' => 0, 'quantity' => 1],
        ]];
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->once()->with($invoice)->andReturn($api);
    $service->shouldReceive('getBuyer')->once()->andReturn(array_fill_keys(['email', 'first_name', 'last_name', 'company', 'address', 'city', 'state', 'zip', 'phone', 'phone_cc', 'country'], ''));
    $settings = Mockery::mock();
    $settings->shouldReceive('getParamValue')->with('invoice_number_padding')->andReturn(5);
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('isSubscribable')->zeroOrMoreTimes()->andReturn(false);
    $di = container();
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'system' ? $settings : $subscriptions);
    $service->setDi($di);
    $dto = $service->getPaymentInvoice($invoice, $subscribe);
    expect(round($dto->getTotalWithTax(), 2))->toBe($subscribe ? 110.04 : $api['balance_due'])
        ->and($dto->getCurrency())->toBe('USD')->and($dto->getId())->toBe(42);
    if (!$subscribe && $paid > 0) {
        expect($dto->getItems())->toHaveCount(1)->and($dto->getTax())->toEqual(0);
    } else {
        expect($dto->getItems()[0]->getTitle())->toBe('Taxable service')->and($dto->getItems()[0]->getQuantity())->toBe(3)
            ->and(round($dto->getTax(), 2))->toBe(10.01);
    }
})->with([0.0, 40.0, 110.04, 150.0])->with([false, true]);
