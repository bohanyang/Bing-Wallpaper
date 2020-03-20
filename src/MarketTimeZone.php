<?php

namespace BohanYang\BingWallpaper;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

class MarketTimeZone
{
    public const MAPPINGS = [
        'ROW' => 'America/Los_Angeles',   // UTC -8 / UTC -7
        'en-US' => 'America/Los_Angeles',
        'pt-BR' => 'America/Los_Angeles',
        'en-CA' => 'America/Toronto',    // UTC -5 / UTC -4
        'fr-CA' => 'America/Toronto',
        'en-GB' => 'Europe/London',      // UTC +0 / UTC +1
        'fr-FR' => 'Europe/Paris',       // UTC +1 / UTC +2
        'de-DE' => 'Europe/Berlin',
        'en-IN' => 'Asia/Kolkata',       // UTC +5:30
        'zh-CN' => 'Asia/Shanghai',      // UTC +8
        'ja-JP' => 'Asia/Tokyo',         // UTC +9
        'en-AU' => 'Australia/Sydney',   // UTC +10 / UTC +11
    ];

    /** @var string */
    private $market;

    /** @var DateTimeZone */
    private $tz;

    public function __construct(string $market, DateTimeZone $tz = null)
    {
        if ($tz === null) {
            if (!isset(self::MAPPINGS[$market])) {
                return new InvalidArgumentException("Market ${market} is unknown and no time zone provided");
            }

            $tz = new DateTimeZone(self::MAPPINGS[$market]);
        }

        $this->market = $market;
        $this->tz = $tz;
    }

    public function getToday(DateTimeImmutable $today = null) : DateTimeImmutable
    {
        if ($today === null) {
            $today = new DateTimeImmutable('today', $this->tz);
        } else {
            $today = $today->setTimezone($this->tz)->setTime(0, 0, 0);
        }

        return $today;
    }

    public function getDate(DateTimeInterface $date) : DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d'), $this->tz);
    }

    /** Get the date "$offset" days before today */
    public function getDateBefore(int $offset, DateTimeImmutable $today = null) : DateTimeImmutable
    {
        $today = $this->getToday($today);
        $invert = $offset < 0 ? 1 : 0;
        $offset = (string) abs($offset);
        $interval = new DateInterval("P${offset}D");
        $interval->invert = $invert;

        return $today->sub($interval);
    }

    public function getMarket() : string
    {
        return $this->market;
    }

    public function getTimeZone() : DateTimeZone
    {
        return $this->tz;
    }
}
