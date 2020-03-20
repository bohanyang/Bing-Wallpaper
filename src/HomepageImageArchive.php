<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function GuzzleHttp\choose_handler;
use function GuzzleHttp\Promise\unwrap;
use function Safe\json_decode;

final class HomepageImageArchive
{
    /** @var string */
    private $endpoint;

    /** @var Client */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        string $endpoint = 'https://global.bing.com/HPImageArchive.aspx',
        ?LoggerInterface $logger = null,
        ?callable $handler = null,
        ?MessageFormatter $formatter = null
    ) {
        $this->endpoint = $endpoint;
        $this->logger = $logger ?? new Logger(self::class, [new StreamHandler('php://stderr')]);
        $handler = $handler ?? choose_handler();
        $formatter = $formatter ?? new MessageFormatter();

        $handler = new HandlerStack($handler);
        $handler->push(Middleware::httpErrors(), 'http_errors');
        $handler->push(Middleware::redirect(), 'allow_redirects');
        $handler->push(GuzzleMiddleware::retry(), 'retry');
        $handler->push(Middleware::log($this->logger, $formatter, LogLevel::DEBUG));

        $this->client = new Client(['handler' => $handler]);
    }

    private function get(string $market, int $index = 0, int $n = 1) : PromiseInterface
    {
        return $this->client->getAsync(
            $this->endpoint,
            [
                'query' => [
                    'format' => 'js',
                    'idx' => (string) $index,
                    'n' => (string) $n,
                    'video' => '1',
                    'mkt' => $market,
                ],
            ]
        );
    }

    private function request(MarketDate $marketDate) : PromiseInterface
    {
        $offset = $marketDate->getOffset();

        if ($offset < 0 || $offset > 7) {
            return new RejectedPromise(
                new FetchArchiveFailure('Offset is out of the available range (0 to 7)', $marketDate)
            );
        }

        return $this->get($marketDate->getMarket(), $offset)->then(
            function (ResponseInterface $resp) use ($marketDate) {
                $resp = json_decode($resp->getBody()->__toString(), true);
                if (empty($resp['images'][0])) {
                    throw new FetchArchiveFailure('Got empty response', $marketDate);
                }

                try {
                    $resp = ResponseParser::parse($resp['images'][0], $marketDate->getMarket());
                } catch (Exception $e) {
                    throw new FetchArchiveFailure('Failed to parse response: ' . $e->getMessage(), $marketDate, null, $e);
                }

                if ($marketDate->isDateExpected($resp['date'])) {
                    throw new FetchArchiveFailure('Got unexpected date', $marketDate, $resp['date']);
                }

                if ($marketDate->isTimeZoneOffsetExpected($resp['date'])) {
                    $e = new FetchArchiveFailure('The actual time zone offset differs from expected', $marketDate, $resp['date']);
                    $this->logger->warning($e->getMessage());
                }

                return $resp;
            }
        );
    }

    public function fetch(string $market, DateTimeImmutable $date = null, DateTimeZone $tz = null)
    {
        return $this->request($market, $date, $tz)->wait();
    }

    public function batch(array $markets, DateTimeImmutable $date)
    {
        /** @var PromiseInterface[] $promises */
        $promises = [];

        foreach ($markets as $market => $tz) {
            $promises[$market] = $this->request($market, $date, $tz);
        }

        return unwrap($promises);
    }
}
