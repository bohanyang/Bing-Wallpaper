<?php

namespace BohanYang\BingWallpaper;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class MarketDate
{
    /** @var MarketTimeZone */
    private $market;

    /** @var DateTimeImmutable */
    private $date;

    /** @var int */
    private $offset;

    private function __construct() {}

    public static function createToday(MarketTimeZone $market, DateTimeImmutable $today = null) : self
    {
        $instance = new self();
        $instance->market = $market;
        $instance->date = $market->getToday($today);
        $instance->offset = 0;

        return $instance;
    }

    public static function create(MarketTimeZone $market, DateTimeInterface $date, DateTimeImmutable $today = null) : self
    {
        $instance = new self();
        $instance->market = $market;
        $instance->date = $market->getDate($date);
        $instance->offset = $instance->getDaysAgo($today);

        return $instance;
    }

    public static function createFromOffset(MarketTimeZone $market, int $offset, DateTimeImmutable $today = null) : self
    {
        $instance = new self();
        $instance->market = $market;
        $instance->date = $market->getDateBefore($offset, $today);
        $instance->offset = $offset;

        return $instance;
    }

    /** Get how many days ago was "$date" */
    public function getDaysAgo(DateTimeImmutable $today = null) : int
    {
        $today = $this->market->getToday($today);
        $diff = $this->date->diff($today, false);

        return (int) $diff->format('%r%a');
    }

    public function isDateExpected(DateTimeInterface $date) : bool
    {
        return $date->format('Y-m-d') === $this->date->format('Y-m-d');
    }

    public function isTimeZoneOffsetExpected(DateTimeInterface $date) : bool
    {
        return $date->format('Z') === $this->date->format('Z');
    }

    public function getMarketTimeZone() : MarketTimeZone
    {
        return $this->market;
    }

    public function getMarket() : string
    {
        return $this->market->getMarket();
    }

    public function getTimeZone() : DateTimeZone
    {
        return $this->market->getTimeZone();
    }

    public function getOffset() : int
    {
        return $this->offset;
    }

    public function getDate() : DateTimeImmutable
    {
        return $this->date;
    }
}
