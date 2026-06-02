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

    public function testGetConfigDeclaresConditionalRequiredWhenRules(): void
    {
        $config = Payment_Adapter_Stripe::getConfig();

        $this->assertArrayHasKey('form', $config);
        $this->assertArrayHasKey('pub_key', $config['form']);
        $this->assertArrayHasKey('api_key', $config['form']);
        $this->assertArrayHasKey('test_pub_key', $config['form']);
        $this->assertArrayHasKey('test_api_key', $config['form']);

        $this->assertSame(
            ['enabled' => true, 'test_mode' => false],
            $config['form']['pub_key'][1]['required_when'],
        );
        $this->assertSame(
            ['enabled' => true, 'test_mode' => false],
            $config['form']['api_key'][1]['required_when'],
        );
        $this->assertSame(
            ['enabled' => true, 'test_mode' => true],
            $config['form']['test_pub_key'][1]['required_when'],
        );
        $this->assertSame(
            ['enabled' => true, 'test_mode' => true],
            $config['form']['test_api_key'][1]['required_when'],
        );
    }

    public function testConstructorRejectsMissingLiveKeysWhenLiveMode(): void
    {
        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('not fully configured');

        new Payment_Adapter_Stripe([
            'test_mode' => false,
        ]);
    }

    public function testConstructorRejectsMissingTestKeysWhenTestMode(): void
    {
        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('not fully configured');

        new Payment_Adapter_Stripe([
            'test_mode' => true,
        ]);
    }
}
