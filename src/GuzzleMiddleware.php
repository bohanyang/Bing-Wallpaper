<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GuzzleMiddleware
{
    public static function retry(callable $statusDecider = null, int $maxRetries = 3, callable $delay = null) : callable
    {
        if ($statusDecider === null) {
            $statusDecider = function (int $status) {
                return $status >= 500 || $status === 408;
            };
        }

        return Middleware::retry(
            function ($retries, $request, ?ResponseInterface $response, $reason) use (
                $maxRetries,
                $statusDecider
            ) : bool {
                if ($retries >= $maxRetries) {
                    return false;
                }

                if ($reason instanceof ConnectException) {
                    return true;
                }

                if ($response !== null) {
                    if ($statusDecider($response->getStatusCode())) {
                        return true;
                    }
                }

                return false;
            },
            $delay
        );
    }

    public static function ensure() : callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request) {
                        $status = $response->getStatusCode();

                        if ($status === 200) {
                            return $response;
                        }

                        throw RequestException::create($request, $response);
                    }
                );
            };
        };
    }
}
