<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Unit\Box;

require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PeriodTest extends TestCase
{
    public static function validPeriodCodesProvider(): array
    {
        return [
            ['1D', 'D', 1, 1],
            ['7D', 'D', 7, 7],
            ['2W', 'W', 2, 15],   // (2/4) * 30 = 15 days
            ['1M', 'M', 1, 30],
            ['3M', 'M', 3, 90],
            ['1Y', 'Y', 1, 360],  // 12 * 30 = 360 days
            ['2Y', 'Y', 2, 720],  // 24 * 30 = 720 days
        ];
    }

    #[DataProvider('validPeriodCodesProvider')]
    public function testValidPeriodCodes(string $code, string $expectedUnit, int $expectedQty, int $expectedDays): void
    {
        $period = new \Box_Period($code);

        $this->assertEquals($expectedUnit, $period->getUnit());
        $this->assertEquals($expectedQty, $period->getQty());
        $this->assertEquals($expectedDays, $period->getDays());
        $this->assertEquals($code, $period->getCode());
    }

    public function testPeriodTitles(): void
    {
        $this->assertStringContainsString('1', (new \Box_Period('1D'))->getCode());
        $this->assertStringContainsString('7', (new \Box_Period('7D'))->getCode());
        $this->assertStringContainsString('1', (new \Box_Period('1W'))->getCode());
        $this->assertStringContainsString('1', (new \Box_Period('1M'))->getCode());
        $this->assertStringContainsString('1', (new \Box_Period('1Y'))->getCode());
    }

    public function testPeriodMonthsCalculation(): void
    {
        $this->assertEquals(1, (new \Box_Period('1M'))->getMonths());
        $this->assertEquals(3, (new \Box_Period('3M'))->getMonths());
        $this->assertEquals(6, (new \Box_Period('6M'))->getMonths());
        $this->assertEquals(12, (new \Box_Period('1Y'))->getMonths());
    }

    public function testExpirationTimeIsInFuture(): void
    {
        $now = time();
        $period = new \Box_Period('1M');

        $expiration = $period->getExpirationTime();
        $this->assertGreaterThanOrEqual($now, $expiration);
        $this->assertLessThan($now + 35 * 24 * 60 * 60, $expiration); // Should be less than 35 days
    }

    public function testInvalidPeriodCodeLength(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invalid period code. Period definition must be 2 chars length');

        new \Box_Period('1');
    }

    public function testInvalidPeriodUnit(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Period Error. Unit Z is not defined');

        new \Box_Period('1Z');
    }

    public function testEmptyPeriodCode(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invalid period code. Period definition must be 2 chars length');

        new \Box_Period('');
    }

    public function testPeriodCodeTooLong(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invalid period code. Period definition must be 2 chars length');

        new \Box_Period('123');
    }
}
