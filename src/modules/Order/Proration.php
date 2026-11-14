<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order;

use FOSSBilling\Core\Exception\InformationException;

/**
 * Proration logic for service and hosting package upgrades and downgrades.
 * Pure logic class with no direct database dependencies.
 *
 * - Unused credit: order's price * days left / days in the period.
 * - Same billing cycle: new price for the days left, minus the credit; expiry date remains.
 * - Different billing cycle: new price for whole cycle, minus credit; new cycle starts today.
 * - Amount > 0: client pays invoice; Amount < 0: credited to client's account balance.
 */
final class Proration
{
    public const string UPGRADE = 'upgrade';
    public const string DOWNGRADE = 'downgrade';

    /** Average days per unit, used to compare prices across different cycle units. */
    private const array UNIT_DAYS = ['D' => 1.0, 'W' => 7.0, 'M' => 30.436875, 'Y' => 365.2425];

    /**
     * @return array{kind: string, same_cycle: bool, days_total: int, days_left: int, credit: float, charge: float, amount: float, period_start: string, expires_at: string, new_expires_at: string}
     */
    public static function quote(float $oldPrice, string $oldPeriod, string $expiresAt, float $newPrice, string $newPeriod, string $now, ?float $currentProductNewCyclePrice = null, ?string $anchor = null): array
    {
        if ($oldPrice < 0 || $newPrice < 0 || !is_finite($oldPrice) || !is_finite($newPrice) || ($currentProductNewCyclePrice !== null && ($currentProductNewCyclePrice < 0 || !is_finite($currentProductNewCyclePrice)))) {
            throw new InformationException('Prices cannot be negative.');
        }

        [$oldQty, $oldUnit] = self::parsePeriod($oldPeriod);
        [$newQty, $newUnit] = self::parsePeriod($newPeriod);
        $expiry = self::day($expiresAt, 'expiry date');
        $today = self::day($now, 'current date');

        $start = self::start($expiry, $oldQty, $oldUnit, $anchor);
        $daysTotal = max(1, self::daysBetween($start, $expiry));
        $daysLeft = max(0, self::daysBetween($today, $expiry));

        $sameCycle = $oldQty === $newQty && $oldUnit === $newUnit;
        $credit = self::money($oldPrice * $daysLeft / $daysTotal);

        if ($sameCycle) {
            $charge = self::money($newPrice * $daysLeft / $daysTotal);
            $newExpires = self::format(self::at($expiresAt));
        } else {
            $charge = self::money($newPrice);
            $newExpires = self::format(self::shift(self::at($now), $newQty, $newUnit, 1));
        }

        $amount = round($charge - $credit, 2);

        // Compare price rates to determine upgrade vs downgrade
        if ($currentProductNewCyclePrice !== null) {
            $oldRate = $currentProductNewCyclePrice;
            $newRate = $newPrice;
        } else {
            $oldRate = $oldPrice / ($oldQty * self::UNIT_DAYS[$oldUnit]);
            $newRate = $newPrice / ($newQty * self::UNIT_DAYS[$newUnit]);
        }

        $epsilon = 1e-9;
        if (abs($newRate - $oldRate) > $epsilon) {
            $kind = $newRate > $oldRate ? self::UPGRADE : self::DOWNGRADE;
        } else {
            $kind = $amount < 0 ? self::DOWNGRADE : self::UPGRADE;
        }

        return [
            'kind' => $kind,
            'same_cycle' => $sameCycle,
            'days_total' => $daysTotal,
            'days_left' => $daysLeft,
            'credit' => $credit,
            'charge' => $charge,
            'amount' => $amount == 0.0 ? 0.0 : $amount,
            'period_start' => $start->format('Y-m-d'),
            'expires_at' => self::format(self::at($expiresAt)),
            'new_expires_at' => $newExpires,
        ];
    }

    /**
     * Parses a period string (e.g. 1M, 3M, 1Y, 1W) into [quantity, unit].
     *
     * @return array{0: int, 1: string}
     */
    public static function parsePeriod(string $period): array
    {
        if (preg_match('/^([1-9][0-9]{0,2})([DWMY])$/', strtoupper(trim($period)), $m) !== 1) {
            throw new InformationException('The billing period :period is not valid.', [':period' => $period]);
        }

        return [(int) $m[1], $m[2]];
    }

    /**
     * Moves a date by a given number of periods ($sign: 1 forward, -1 backward).
     */
    public static function shift(\DateTimeImmutable $date, int $qty, string $unit, int $sign): \DateTimeImmutable
    {
        if ($unit === 'D' || $unit === 'W') {
            $days = $qty * ($unit === 'W' ? 7 : 1) * $sign;

            return $date->modify(($days >= 0 ? '+' : '') . $days . ' days');
        }

        $months = $qty * ($unit === 'Y' ? 12 : 1) * $sign;
        $total = (int) $date->format('Y') * 12 + (int) $date->format('n') - 1 + $months;
        $y = intdiv($total, 12);
        $m = $total % 12 + 1;
        $day = min((int) $date->format('j'), (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t'));

        return $date->setDate($y, $m, $day);
    }

    /**
     * Whole days from now to the expiry date.
     */
    public static function daysLeft(string $expiresAt, string $now): int
    {
        return max(0, self::daysBetween(self::day($now, 'current date'), self::day($expiresAt, 'expiry date')));
    }

    /**
     * Days in the period that ends on the expiry date.
     */
    public static function daysTotal(string $period, string $expiresAt, ?string $anchor = null): int
    {
        [$qty, $unit] = self::parsePeriod($period);
        $expiry = self::day($expiresAt, 'expiry date');

        return max(1, self::daysBetween(self::start($expiry, $qty, $unit, $anchor), $expiry));
    }

    private static function start(\DateTimeImmutable $expiry, int $qty, string $unit, ?string $anchor): \DateTimeImmutable
    {
        $start = self::shift($expiry, $qty, $unit, -1);
        if ($anchor === null || ($unit !== 'M' && $unit !== 'Y')) {
            return $start;
        }

        try {
            $from = self::day($anchor, 'period start');
        } catch (InformationException) {
            return $start;
        }

        $step = '+' . $qty . ($unit === 'M' ? ' months' : ' years');
        for ($i = 0; $i < 1200 && $from < $expiry; ++$i) {
            $next = $from->modify($step);
            if ($next == $expiry) {
                return $from;
            }
            $from = $next;
        }

        return $start;
    }

    private static function daysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->format('%r%a');
    }

    private static function money(float $value): float
    {
        return round($value + 1e-9, 2);
    }

    private static function day(string $value, string $what): \DateTimeImmutable
    {
        $at = self::at($value, $what);

        return new \DateTimeImmutable($at->format('Y-m-d'), new \DateTimeZone('UTC'));
    }

    private static function at(string $value, string $what = 'date'): \DateTimeImmutable
    {
        $value = trim($value);
        $at = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'))
            ?: \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));

        if ($at === false || $at->format(strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s') !== $value) {
            throw new InformationException('The :what is not valid.', [':what' => $what]);
        }

        return $at;
    }

    private static function format(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
