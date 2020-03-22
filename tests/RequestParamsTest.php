<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper\Tests;

use BohanYang\BingWallpaper\CurrentTime;
use BohanYang\BingWallpaper\RequestParams;
use BohanYang\BingWallpaper\Market;
use Safe\DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class RequestParamsTest extends TestCase
{
    public function testDaysAgo() : void
    {
        foreach ([
            [
                '2019-05-31 23:59:59 Asia/Shanghai',
                '2019-07-23 15:04:05 Asia/Shanghai',
                53,
            ],
            [
                '2019-07-08 23:59:59 Asia/Shanghai',
                '2019-07-23 00:00:00 Asia/Tokyo',
                14,
            ],
            [
                '2019-10-27 00:00:00 Europe/London',
                '2019-10-26 23:59:59 UTC',
                0,
            ],
            [
                '2019-04-04 00:00:00 Europe/London',
                '2019-04-01 23:59:59 UTC',
                -2,
            ],
        ] as [$date, $today, $expected]) {
            $date  = DateTimeImmutable::createFromFormat('Y-m-d H:i:s e', $date);
            $market = new Market('', $date->getTimezone());
            $today = DateTimeImmutable::createFromFormat('Y-m-d H:i:s e', $today);
            $date = RequestParams::create($market, $date, $today);

            $this->assertSame($expected, $date->getOffset());
        }
    }

    public function testDateBefore() : void
    {
        foreach ([
            [
                53,
                new DateTimeZone('Asia/Shanghai'),
                '2019-07-23 15:04:05 Asia/Shanghai',
                '2019-05-31 00:00:00',
            ],
            [
                14,
                new DateTimeZone('Asia/Shanghai'),
                '2019-07-23 00:00:00 Asia/Tokyo',
                '2019-07-08 00:00:00',
            ],
            [
                0,
                new DateTimeZone('Europe/London'),
                '2019-10-26 23:59:59 UTC',
                '2019-10-27 00:00:00',
            ],
            [
                -2,
                new DateTimeZone('Europe/London'),
                '2019-04-01 23:59:59 UTC',
                '2019-04-04 00:00:00',
            ],
        ] as [$offset, $tz, $today, $expected]) {
            /** @var DateTimeZone $tz */
            $market = new Market('', $tz);
            $today = DateTimeImmutable::createFromFormat('Y-m-d H:i:s e', $today);
            $date = RequestParams::createFromOffset($market, $offset, $today)->getDate();
            $this->assertSame("${expected} {$tz->getName()}", $date->format('Y-m-d H:i:s e'));
        }
    }

    public function testHasBecomeTheLaterDate() : void
    {
        foreach ([
            [
                new DateTimeZone('Australia/Sydney'),
                new DateTimeImmutable('2020-03-16 22:00:00', new DateTimeZone('Asia/Tokyo')),
                true
            ],
            [
                new DateTimeZone('Australia/Sydney'),
                new DateTimeImmutable('2020-03-16 21:59:59', new DateTimeZone('Asia/Tokyo')),
                false
            ],
            [
                new DateTimeZone('Asia/Shanghai'),
                new DateTimeImmutable('2020-03-17 01:00:00', new DateTimeZone('Asia/Tokyo')),
                true
            ],
            [
                new DateTimeZone('Asia/Shanghai'),
                new DateTimeImmutable('2020-03-17 00:59:59', new DateTimeZone('Asia/Tokyo')),
                false
            ]
        ] as [$tz, $now, $expected]) {
            /** @var DateTimeZone $tz */
            $now = new CurrentTime($now);
            $this->assertSame($expected, $now->hasBecomeTheLaterDate($tz));
        }
    }
}
