<?php

namespace BohanYang\BingWallpaper;

use DateTimeImmutable;
use DateTimeZone;
use function array_filter;

class CurrentTime
{
    /** @var DateTimeImmutable */
    private $now;

    /** @var DateTimeImmutable */
    private $theLaterDate;

    /**
     * @var int Time zone offset of the current natural date line,
     *  the meridian where the time is zero o'clock now
     */
    private $midnightOffset;

    public function __construct(DateTimeImmutable $now = null)
    {
        $utc = new DateTimeZone('UTC');

        $this->now = $now === null ? new DateTimeImmutable('now', $utc) : $now->setTimezone($utc);
        $this->setTheLaterDate();
        $this->setMidnightOffset();
    }

    private function setTheLaterDate()
    {
        if ((int) $this->now->format('G') < 12) {
            // For time zones later than UTC (have an earlier date),
            // the moment of date change is 00:00 on today's date of UTC
            $this->theLaterDate = $this->now->setTime(0, 0, 0);
        } else {
            // For time zones earlier than UTC (have a later date),
            // the moment of date change is 00:00 on tomorrow's date of UTC
            $this->theLaterDate = $this->now->modify('+1 day midnight');
        }
    }

    public function getTheLaterDate() : DateTimeImmutable
    {
        return $this->theLaterDate;
    }

    private function setMidnightOffset()
    {
        // General solution:
        // $now->diff($the_moment_of_date_change)
        // which is equivalent to
        // $the_moment_of_date_change - $now
        $this->midnightOffset = $this->theLaterDate->getTimestamp() - $this->now->getTimestamp();
    }

    public function hasBecomeTheLaterDate(DateTimeZone $tz) : bool
    {
        return (int) $this->now->setTimezone($tz)->format('Z') >= $this->midnightOffset;
    }

    public function getMarketsHaveBecomeTheLaterDate(array $markets) : array
    {
        return array_filter($markets, function (MarketTimeZone $market) {
            return $this->hasBecomeTheLaterDate($market->getTimeZone());
        });
    }
}
