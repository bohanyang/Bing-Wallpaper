<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

use function GuzzleHttp\choose_handler;
use function GuzzleHttp\Promise\unwrap;
use function Safe\json_decode;

final class Client
{
    /** @var string */
    private $endpoint;

    /** @var HttpClient */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        string $endpoint = 'https://global.bing.com/HPImageArchive.aspx',
        LoggerInterface $logger = null,
        callable $handler = null,
        MessageFormatter $formatter = null
    ) {
        $this->endpoint = $endpoint;
        $handler = $handler ?? choose_handler();
        $handler = new HandlerStack($handler);
        $handler->push(Middleware::httpErrors(), 'http_errors');
        $handler->push(Middleware::redirect(), 'allow_redirects');
        $handler->push(GuzzleMiddleware::retry(), 'retry');

        if ($logger === null){
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
            $handler->push(
                Middleware::log(
                    $logger,
                    $formatter ?? new MessageFormatter(),
                    LogLevel::DEBUG
                )
            );
        }

        $this->client = new HttpClient(['handler' => $handler]);
    }

    private function get(RequestParams $params) : PromiseInterface
    {
        return $this->client->getAsync(
            $this->endpoint,
            [
                'query' => [
                    'format' => 'js',
                    'idx' => (string) $params->getOffset(),
                    'n' => '1',
                    'video' => '1',
                    'mkt' => $params->getMarket(),
                ],
            ]
        );
    }

    private function request(RequestParams $params) : PromiseInterface
    {
        $offset = $params->getOffset();

        if ($offset < 0 || $offset > 7) {
            return new RejectedPromise(
                new RequestException('Offset is out of the available range (0 to 7)', $params)
            );
        }

        return $this->get($params)->then(
            function (ResponseInterface $response) use ($params) {
                $response = json_decode($response->getBody()->__toString(), true);
                if (empty($response['images'][0])) {
                    throw new RequestException('Got empty response', $params);
                }

                try {
                    $response = ResponseParser::parse($response['images'][0], $params->getMarket());
                } catch (Exception $e) {
                    throw new RequestException('Failed to parse response: ' . $e->getMessage(), $params, null, $e);
                }

                if (!$params->isDateExpected($response['date'])) {
                    throw new RequestException('Got unexpected date', $params, $response['date']);
                }

                if (!$params->isTimeZoneOffsetExpected($response['date'])) {
                    $e = new RequestException('The actual time zone offset differs from expected', $params, $response['date']);
                    $this->logger->warning($e->getMessage());
                }

                return $response;
            }
        );
    }

    public function fetch(RequestParams $params)
    {
        return $this->request($params)->wait();
    }

    public function batch(array $requests)
    {
        foreach ($requests as $i => $params) {
            $requests[$i] = $this->request($params);
        }

        return unwrap($requests);
    }
}
