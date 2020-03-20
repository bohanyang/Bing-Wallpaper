<?php

namespace BohanYang\BingWallpaper;

use Assert\Assertion;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use function array_shift;
use function parse_str;
use function parse_url;
use function Safe\preg_match;
use function urldecode;
use const PHP_URL_QUERY;

class ResponseParser
{
    /** Parse "fullstartdate" string into DateTime with correct time zone of UTC offset type */
    public static function parseFullStartDate(string $fullStartDate) : DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('YmdHi', $fullStartDate, new DateTimeZone('UTC'));

        if ($d === false) {
            throw new InvalidArgumentException("Failed to parse full start date ${fullStartDate}");
        }

        if ((int) $d->format('G') < 12) {
            // The moment of date change is the new date's 00:00
            // and UTC is on the new date.
            // Therefore, the timezone just reached the new date's 00:00
            // (just changed its date / just becomes the next day)
            // is slower than UTC.
            $tz = '-' . $d->format('H:i');
        } else {
            // But when UTC becomes 12:00, all UTC -* timezones
            // (the west side of the prime meridian)
            // already changed their date.
            // The fastest UTC +12 becomes the next new date
            // (tomorrow's date of UTC).
            $d24 = $d->modify('+1 day midnight');
            $tz = $d->diff($d24, true)->format('%R%H:%I');
            $d = $d24;
        }

        return new DateTimeImmutable($d->format('Y-m-d'), new DateTimeZone($tz));
    }

    /** Parse an URL of web search engine and extract keyword from its query string */
    public static function extractKeyword(string $url) : ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (!$query) {
            return null;
        }

        parse_str($query, $query);

        $fields = ['q', 'wd'];

        foreach ($fields as $field) {
            if (isset($query[$field]) && $query[$field] !== '') {
                return urldecode($query[$field]);
            }
        }

        return null;
    }

    /**
     * Normalize "urlbase" and extract image name from it
     *
     * @param string $urlBase e.g.
     *  "/az/hprichbg/rb/BemarahaNP_JA-JP15337355971" or
     *  "/th?id=OHR.BemarahaNP_JA-JP15337355971"
     *
     * @return string[] e.g.
     *  [
     *      "BemarahaNP_JA-JP15337355971",
     *      "BemarahaNP",
     *      "JA-JP15337355971"
     *  ]
     */
    public static function parseUrlBase(string $urlBase)
    {
        $regex = '/(\w+)_((?:ROW|[A-Z]{2}-[A-Z]{2})\d+)/';
        $matches = [];

        if (preg_match($regex, $urlBase, $matches) !== 1) {
            throw new InvalidArgumentException("Failed to parse URL base ${urlBase}");
        }

        return $matches;
    }

    /**
     * Extract image description as well as the author and/or
     * the stock photo agency from "copyright" string
     *
     * @return string[] [$description, $copyright]
     */
    public static function parseCopyright(string $copyright)
    {
        $regex = '/(.+?)(?: |\x{3000})?(?:\(|\x{FF08})?\x{00A9}(?: |\x{3000})?(.+?)(?:\)|\x{FF09})?$/u';
        $matches = [];

        if (preg_match($regex, $copyright, $matches) !== 1) {
            throw new InvalidArgumentException("Failed to parse copyright string ${copyright}");
        }

        array_shift($matches);

        return $matches;
    }

    private const REQUIRED_FIELDS = [
        'fullstartdate',
        'urlbase',
        'copyright',
        'copyrightlink',
        'wp'
    ];

    /**
     * @return array Result structure:
     *  - market (required, string)
     *  - date (required, DateTimeImmutable)
     *  - description (required, string)
     *  - link (optional, string)
     *  - hotspots (optional)
     *  - messages (optional)
     *  - coverstory (optional)
     *  - image (required)
     *      - name (required, string)
     *      - urlbase (required, string, e.g. "/az/hprichbg/rb/BemarahaNP_JA-JP15337355971")
     *      - copyright (required, string)
     *      - wp (required, boolean)
     *      - vid (optional)
     */
    public static function parse(array $resp, string $market)
    {
        $result = [];
        $result['market'] = $market;

        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($resp[$field]) && $resp[$field] !== false) {
                throw new InvalidArgumentException("Required field ${field} is empty");
            }
        }

        $result['date'] = self::parseFullStartDate($resp['fullstartdate']);

        [$result['image']['urlbase'], $result['image']['name']] = self::parseUrlBase($resp['urlbase']);
        $result['image']['urlbase'] = '/az/hprichbg/rb/' . $result['image']['urlbase'];

        [$result['description'], $result['image']['copyright']] = self::parseCopyright($resp['copyright']);

        if ($resp['copyrightlink'] !== 'javascript:void(0)') {
            Assertion::url($resp['copyrightlink']);
            $result['link'] = $resp['copyrightlink'];
        }

        Assertion::boolean($resp['wp']);
        $result['image']['wp'] = $resp['wp'];

        if (!empty($resp['vid'])) {
            $result['image']['vid'] = $resp['vid'];
        }

        if (!empty($resp['hs'])) {
            $result['hotspots'] = $resp['hs'];
        }

        if (!empty($resp['msg'])) {
            $result['messages'] = $resp['msg'];
        }

        return $result;
    }
}
