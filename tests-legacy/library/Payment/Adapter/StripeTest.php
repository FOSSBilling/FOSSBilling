<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Payment_Adapter_StripeTest extends BBTestCase
{
    public static function minorUnitAmounts(): array
    {
        return [
            'USD has two decimals' => [12345, 'usd', 123.45],
            'JPY has no decimals' => [1000, 'jpy', 1000.0],
            'BHD has three decimals' => [12345, 'bhd', 12.345],
        ];
    }

    #[DataProvider('minorUnitAmounts')]
    public function testAmountFromMinorUnitsUsesCurrencyFractionDigits(int $amount, string $currency, float $expected): void
    {
        $adapter = new Payment_Adapter_Stripe([
            'test_mode' => true,
            'test_api_key' => 'sk_test_key',
            'test_pub_key' => 'pk_test_key',
        ]);

        $method = new ReflectionMethod($adapter, 'getAmountFromMinorUnits');

        $this->assertSame($expected, $method->invoke($adapter, $amount, $currency));
    }
}
