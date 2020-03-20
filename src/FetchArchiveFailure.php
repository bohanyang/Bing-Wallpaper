<?php

namespace BohanYang\BingWallpaper;

use DateTimeInterface;
use Exception;
use Symfony\Component\VarDumper\Caster\DateCaster;
use Throwable;

use function rtrim;
use function Safe\sprintf;
use function str_pad;
use function strlen;

class FetchArchiveFailure extends Exception
{
    public function __construct(
        string $message,
        MarketDate $marketDate,
        DateTimeInterface $responseDate = null,
        Throwable $previous = null,
        int $code = 0
    ) {
        $message .= ". Market: {$marketDate->getMarket()}";
        $message .= ', Date: ' . self::formatDateTime($marketDate->getDate());
        $message .= ", Offset: {$marketDate->getOffset()}";

        if ($responseDate !== null) {
            $message .= ', Response Date: ' . self::formatDateTime($responseDate);
        }

        parent::__construct($message, $code, $previous);
    }

    /** @see DateCaster::formatDateTime() */
    private static function formatDateTime(DateTimeInterface $d) : string
    {
        $tz = $d->format('e');
        $offset = $d->format('P');
        $tz = $tz === $offset ? " ${tz}" : " ${tz} (${offset})";

        return $d->format('Y-m-d H:i:' . self::formatSeconds($d->format('s'), $d->format('u'))) . $tz;
    }

    /** @see DateCaster::formatSeconds() */
    private static function formatSeconds(string $s, string $us) : string
    {
        return sprintf('%02d.%s', $s, 0 === ($len = strlen($t = rtrim($us, '0'))) ? '0' : ($len <= 3 ? str_pad($t, 3, '0') : $us));
    }
}
