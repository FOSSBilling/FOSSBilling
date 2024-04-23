<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Period
{
    final public const UNIT_DAY = 'D';
    final public const UNIT_WEEK = 'W';
    final public const UNIT_MONTH = 'M';
    final public const UNIT_YEAR = 'Y';

    final public const PERIOD_WEEK = '1W';
    final public const PERIOD_MONTH = '1M';
    final public const PERIOD_QUARTER = '3M';
    final public const PERIOD_BIANNUAL = '6M';
    final public const PERIOD_ANNUAL = '1Y';
    final public const PERIOD_BIENNIAL = '2Y';
    final public const PERIOD_TRIENNIAL = '3Y';

    /**
     * Predefined periods.
     */
    protected $_multiplier = [
        self::PERIOD_MONTH => 1,
        self::PERIOD_QUARTER => 3,
        self::PERIOD_BIANNUAL => 6,
        self::PERIOD_ANNUAL => 12,
        self::PERIOD_BIENNIAL => 24,
        self::PERIOD_TRIENNIAL => 36,
    ];

    private readonly string $unit;
    private int $qty;

    public function __construct($code)
    {
        if (strlen($code) != 2) {
            throw new FOSSBilling\Exception('Invalid period code. Period definition must be 2 chars length');
        }

        [$qty, $unit] = str_split($code);

        $units = $this->getUnits();
        $qty = (int) $qty;
        $unit = strtoupper($unit);
        if (!array_key_exists($unit, $units)) {
            throw new FOSSBilling\Exception('Period Error. Unit :unit is not defined', [':unit' => $unit]);
        }

        if ($qty < $units[$unit][0] || $qty > $units[$unit][1]) {
            $d = [
                ':qty' => $qty,
                ':unit' => $unit,
                ':from' => $units[$unit][0],
                ':to' => $units[$unit][1],
            ];

            throw new FOSSBilling\Exception('Invalid period quantity :qty for unit :unit. Allowed range is from :from to :to', $d);
        }

        $this->unit = $unit;
        $this->qty = $qty;
    }

    private function getUnits()
    {
        return [
            self::UNIT_DAY => [1, 90],
            self::UNIT_WEEK => [1, 52],
            self::UNIT_MONTH => [1, 24],
            self::UNIT_YEAR => [1, 5],
        ];
    }

    public static function getPredefined($simple = true): array
    {
        $periods = [
            self::PERIOD_WEEK => ['rec_qty' => 1, 'title' => __trans('Every week'), 'code' => self::PERIOD_WEEK, 'rec_unit' => self::UNIT_WEEK],
            self::PERIOD_MONTH => ['rec_qty' => 1, 'title' => __trans('Every month'), 'code' => self::PERIOD_MONTH, 'rec_unit' => self::UNIT_MONTH],
            self::PERIOD_QUARTER => ['rec_qty' => 3, 'title' => __trans('Every 3 months'), 'code' => self::PERIOD_QUARTER, 'rec_unit' => self::UNIT_MONTH],
            self::PERIOD_BIANNUAL => ['rec_qty' => 6, 'title' => __trans('Every 6 months'), 'code' => self::PERIOD_BIANNUAL, 'rec_unit' => self::UNIT_MONTH],
            self::PERIOD_ANNUAL => ['rec_qty' => 1, 'title' => __trans('Every year'), 'code' => self::PERIOD_ANNUAL, 'rec_unit' => self::UNIT_YEAR],
            self::PERIOD_BIENNIAL => ['rec_qty' => 2, 'title' => __trans('Every 2 years'), 'code' => self::PERIOD_BIENNIAL, 'rec_unit' => self::UNIT_YEAR],
            self::PERIOD_TRIENNIAL => ['rec_qty' => 3, 'title' => __trans('Every 3 years'), 'code' => self::PERIOD_TRIENNIAL, 'rec_unit' => self::UNIT_YEAR],
        ];

        if ($simple) {
            $new = [];
            foreach ($periods as $pp) {
                $new[$pp['code']] = $pp['title'];
            }
            $periods = $new;
        }

        return $periods;
    }

    public function getUnit()
    {
        return $this->unit;
    }

    public function getQty()
    {
        return $this->qty;
    }

    public function getCode()
    {
        return $this->qty . $this->unit;
    }

    public function getTitle()
    {
        $qty = $this->qty;
        $placeholders = [':number' => $qty];

        return match ($this->unit) {
            self::UNIT_DAY => __pluralTrans('Every :number day', 'Every :number days', $qty, $placeholders),
            self::UNIT_WEEK => __pluralTrans('Every :number week', 'Every :number weeks', $qty, $placeholders),
            self::UNIT_MONTH => __pluralTrans('Every :number month', 'Every :number months', $qty, $placeholders),
            self::UNIT_YEAR => __pluralTrans('Every :number year', 'Every :number years', $qty, $placeholders),
            default => throw new FOSSBilling\Exception('Unit not defined'),
        };
    }

    public function getDays()
    {
        return $this->getMonths() * 30;
    }

    /**
     * How many months $this->unit consists of.
     *
     * @return int
     */
    public function getMonths()
    {
        return match ($this->unit) {
            self::UNIT_DAY => $this->qty / 30,
            self::UNIT_WEEK => $this->qty / 4,
            self::UNIT_MONTH => $this->qty,
            self::UNIT_YEAR => $this->qty * 12,
            default => throw new FOSSBilling\Exception('Unable to get the number of months for :unit', [':unit' => $this->unit]),
        };
    }

    public function getExpirationTime($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $shift = match ($this->unit) {
            self::UNIT_DAY => 'days',
            self::UNIT_WEEK => 'weeks',
            self::UNIT_MONTH => 'months',
            self::UNIT_YEAR => 'years',
            default => throw new FOSSBilling\Exception('Unit not defined'),
        };

        return strtotime("+$this->qty $shift", $now);
    }
}
