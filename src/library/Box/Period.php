<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_Period
{
    const UNIT_DAY          = 'D';
    const UNIT_WEEK         = 'W';
    const UNIT_MONTH        = 'M';
    const UNIT_YEAR         = 'Y';

    const PERIOD_WEEK       = '1W';
    const PERIOD_MONTH      = '1M';
    const PERIOD_QUARTER    = '3M';
    const PERIOD_BIANNUAL   = '6M';
    const PERIOD_ANNUAL     = '1Y';
    const PERIOD_BIENNIAL	= '2Y';
    const PERIOD_TRIENNIAL	= '3Y';

    /**
     * Predefined periods
     */
    protected $_multiplier = array(
        self::PERIOD_MONTH      =>  1,
        self::PERIOD_QUARTER    =>  3,
        self::PERIOD_BIANNUAL   =>  6,
        self::PERIOD_ANNUAL     =>  12,
        self::PERIOD_BIENNIAL	=>	24,
        self::PERIOD_TRIENNIAL	=>	36,
    );

    private $unit;
    private $qty;

    public function __construct($code)
    {
        if(strlen($code) != 2) {
            throw new \Box_Exception("Invalid period code. Period definition must be 2 chars length");
        }

        list($qty, $unit) = str_split($code);

        $units = $this->getUnits();
        $qty = (int)$qty;
        $unit = strtoupper($unit);
        if(!array_key_exists($unit, $units)) {
            throw new \Box_Exception("Period Error. Unit :unit is not defined", array(':unit'=>$unit));
        }

        if($qty < $units[$unit][0] || $qty > $units[$unit][1]) {
            $d = array(
                ':qty'  =>  $qty,
                ':unit'  =>  $unit,
                ':from'  =>  $units[$unit][0],
                ':to'  =>  $units[$unit][1],
            );
            throw new \Box_Exception("Invalid period quantity :qty for unit :unit. Allowed range is from :from to :to", $d);
        }

        $this->unit = $unit;
        $this->qty = $qty;
    }

    private function getUnits()
    {
        return array(
            self::UNIT_DAY      => array(1, 90),
            self::UNIT_WEEK     => array(1, 52),
            self::UNIT_MONTH    => array(1, 24),
            self::UNIT_YEAR     => array(1, 5),
        );
    }

    public static function getPredefined($simple = true)
    {
        $periods = array(
            self::PERIOD_WEEK       =>  array('rec_qty'=>1, 'title'=>__('Every week'), 'code'=>self::PERIOD_WEEK, 'rec_unit'=>self::UNIT_WEEK),
            self::PERIOD_MONTH      =>  array('rec_qty'=>1, 'title'=>__('Every month'), 'code'=>self::PERIOD_MONTH, 'rec_unit'=>self::UNIT_MONTH),
            self::PERIOD_QUARTER    =>  array('rec_qty'=>3, 'title'=>__('Every 3 months'), 'code'=>self::PERIOD_QUARTER, 'rec_unit'=>self::UNIT_MONTH),
            self::PERIOD_BIANNUAL   =>  array('rec_qty'=>6, 'title'=>__('Every 6 months'), 'code'=>self::PERIOD_BIANNUAL, 'rec_unit'=>self::UNIT_MONTH),
            self::PERIOD_ANNUAL     =>  array('rec_qty'=>1, 'title'=>__('Every year'), 'code'=>self::PERIOD_ANNUAL, 'rec_unit'=>self::UNIT_YEAR),
            self::PERIOD_BIENNIAL	=>	array('rec_qty'=>2, 'title'=>__('Every 2 years'), 'code'=>self::PERIOD_BIENNIAL, 'rec_unit'=>self::UNIT_YEAR),
            self::PERIOD_TRIENNIAL	=>	array('rec_qty'=>3, 'title'=>__('Every 3 years'), 'code'=>self::PERIOD_TRIENNIAL, 'rec_unit'=>self::UNIT_YEAR),
        );

        if($simple) {
            $new = array();
            foreach($periods as $pp) {
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
        return $this->qty.$this->unit;
    }

    public function getTitle()
    {
        switch ($this->unit) {
            case self::UNIT_DAY:
                $shift = __('Every :number day', array(':number'=>$this->qty));
                break;
            case self::UNIT_WEEK:
                $shift = __('Every :number week(s)', array(':number'=>$this->qty));
                break;
            case self::UNIT_MONTH:
                $shift = __('Every :number months', array(':number'=>$this->qty));
                break;
            case self::UNIT_YEAR:
                $shift = __('Every :number years', array(':number'=>$this->qty));
                break;
            default:
                throw new \Box_Exception('Unit not defined');
        }

        return $shift;
    }

    public function getDays()
    {
        return $this->getMonths() * 30;
    }

    /**
     * How many months $this->unit consists of
     * @return int
     */
    public function getMonths()
    {
        $qty = 0;

        switch ($this->unit) {
            case self::UNIT_DAY:
                $qty = $this->qty / 30;
                break;
            case self::UNIT_WEEK:
                $qty = $this->qty / 4;
                break;
            case self::UNIT_MONTH:
                $qty = $this->qty;
                break;
            case self::UNIT_YEAR:
                $qty = $this->qty * 12;
                break;
            default:
                throw new \Box_Exception('Can not determine months amount from unit');
        }

        return $qty;
    }

    public function getExpirationTime($now = NULL)
    {
        if(NULL === $now) {
            $now = time();
        }

        switch ($this->unit) {
            case self::UNIT_DAY:
                $shift = 'days';
                break;
            case self::UNIT_WEEK:
                $shift = 'weeks';
                break;
            case self::UNIT_MONTH:
                $shift = 'months';
                break;
            case self::UNIT_YEAR:
                $shift = 'years';
                break;
            default:
                throw new \Box_Exception('Unit not defined');
        }
        return strtotime("+$this->qty $shift", $now);
    }
}