<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core;

use FOSSBilling\Core\Exception\BaseException as Exception;

class Period
{
    final public const string UNIT_DAY = 'D';
    final public const string UNIT_WEEK = 'W';
    final public const string UNIT_MONTH = 'M';
    final public const string UNIT_YEAR = 'Y';

    final public const string PERIOD_WEEK = '1W';
    final public const string PERIOD_MONTH = '1M';
    final public const string PERIOD_QUARTER = '3M';
    final public const string PERIOD_BIANNUAL = '6M';
    final public const string PERIOD_ANNUAL = '1Y';
    final public const string PERIOD_BIENNIAL = '2Y';
    final public const string PERIOD_TRIENNIAL = '3Y';
    final public const string PERIOD_QUADRENNIAL = '4Y';
    final public const string PERIOD_QUINQUENNIAL = '5Y';

    private const array UNIT_RANGES = [
        self::UNIT_DAY => [1, 90],
        self::UNIT_WEEK => [1, 52],
        self::UNIT_MONTH => [1, 24],
        self::UNIT_YEAR => [1, 5],
    ];

    private readonly string $unit;
    private readonly int $qty;

    public function __construct(string $code)
    {
        // A period code is a quantity followed by a single unit letter (e.g. "1M", "45D",
        // "24M"). Quantities are not limited to a single digit, so the code as a whole is
        // not a fixed length; UNIT_RANGES enforces the actual allowed ranges.
        if (!preg_match('/^(\d+)([A-Za-z])$/', $code, $matches)) {
            throw new Exception('Invalid period code. Period definition must be a quantity followed by a unit letter');
        }

        [, $qty, $unit] = $matches;
        $qty = (int) $qty;
        $unit = strtoupper($unit);
        $range = self::UNIT_RANGES[$unit] ?? null;

        if ($range === null) {
            throw new Exception('Period Error. Unit :unit is not defined', [':unit' => $unit]);
        }

        if ($qty < $range[0] || $qty > $range[1]) {
            throw new Exception('Invalid period quantity :qty for unit :unit. Allowed range is from :from to :to', [':qty' => $qty, ':unit' => $unit, ':from' => $range[0], ':to' => $range[1]]);
        }

        $this->unit = $unit;
        $this->qty = $qty;
    }

    public static function getPredefined(bool $simple = true): array
    {
        $periods = [
            self::PERIOD_WEEK => ['rec_qty' => 1, 'title' => __trans('Every Week'), 'code' => self::PERIOD_WEEK, 'rec_unit' => self::UNIT_WEEK],
            self::PERIOD_MONTH => ['rec_qty' => 1, 'title' => __trans('Every Month'), 'code' => self::PERIOD_MONTH, 'rec_unit' => self::UNIT_MONTH],
            self::PERIOD_QUARTER => ['rec_qty' => 3, 'title' => __trans('Every 3 Months'), 'code' => self::PERIOD_QUARTER, 'rec_unit' => self::UNIT_MONTH],
            self::PERIOD_BIANNUAL => ['rec_qty' => 6, 'title' => __trans('Every 6 Months'), 'code' => self::PERIOD_BIANNUAL, 'rec_unit' => self::UNIT_MONTH],
            self::PERIOD_ANNUAL => ['rec_qty' => 1, 'title' => __trans('Every Year'), 'code' => self::PERIOD_ANNUAL, 'rec_unit' => self::UNIT_YEAR],
            self::PERIOD_BIENNIAL => ['rec_qty' => 2, 'title' => __trans('Every 2 Years'), 'code' => self::PERIOD_BIENNIAL, 'rec_unit' => self::UNIT_YEAR],
            self::PERIOD_TRIENNIAL => ['rec_qty' => 3, 'title' => __trans('Every 3 Years'), 'code' => self::PERIOD_TRIENNIAL, 'rec_unit' => self::UNIT_YEAR],
            self::PERIOD_QUADRENNIAL => ['rec_qty' => 4, 'title' => __trans('Every 4 Years'), 'code' => self::PERIOD_QUADRENNIAL, 'rec_unit' => self::UNIT_YEAR],
            self::PERIOD_QUINQUENNIAL => ['rec_qty' => 5, 'title' => __trans('Every 5 Years'), 'code' => self::PERIOD_QUINQUENNIAL, 'rec_unit' => self::UNIT_YEAR],
        ];

        if (!$simple) {
            return $periods;
        }

        $simplePeriods = [];
        foreach ($periods as $period) {
            $simplePeriods[$period['code']] = $period['title'];
        }

        return $simplePeriods;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function getCode(): string
    {
        return $this->qty . $this->unit;
    }

    public function getTitle(): string
    {
        $placeholders = [':number' => $this->qty];

        return match ($this->unit) {
            self::UNIT_DAY => __pluralTrans('Every :number day', 'Every :number days', $this->qty, $placeholders),
            self::UNIT_WEEK => __pluralTrans('Every :number week', 'Every :number weeks', $this->qty, $placeholders),
            self::UNIT_MONTH => __pluralTrans('Every :number month', 'Every :number months', $this->qty, $placeholders),
            self::UNIT_YEAR => __pluralTrans('Every :number year', 'Every :number years', $this->qty, $placeholders),
            default => throw new Exception('Unit not defined'),
        };
    }

    public function getDays(): float|int
    {
        return $this->getMonths() * 30;
    }

    /**
     * How many months the period consists of.
     */
    public function getMonths(): float|int
    {
        return match ($this->unit) {
            self::UNIT_DAY => $this->qty / 30,
            self::UNIT_WEEK => $this->qty / 4,
            self::UNIT_MONTH => $this->qty,
            self::UNIT_YEAR => $this->qty * 12,
            default => throw new Exception('Unable to get the number of months for :unit', [':unit' => $this->unit]),
        };
    }

    public function getExpirationTime(?int $now = null): int|false
    {
        $now ??= time();

        $shift = match ($this->unit) {
            self::UNIT_DAY => 'days',
            self::UNIT_WEEK => 'weeks',
            self::UNIT_MONTH => 'months',
            self::UNIT_YEAR => 'years',
            default => throw new Exception('Unit not defined'),
        };

        return strtotime("+$this->qty $shift", $now);
    }
}
