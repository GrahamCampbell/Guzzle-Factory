<?php

declare(strict_types=1);

/*
 * This file is part of Guzzle Factory.
 *
 * (c) Graham Campbell <hello@gjcampbell.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GrahamCampbell\GuzzleFactory;

use Closure;
use GuzzleHttp\BodySummarizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\CurlShare;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\RetryMiddleware;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This is the guzzle factory class.
 *
 * @author Graham Campbell <hello@gjcampbell.co.uk>
 */
final class GuzzleFactory
{
    /**
     * The default crypto method.
     *
     * @var int
     */
    private const CRYPTO_METHOD = \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

    /**
     * The default connect timeout.
     *
     * @var int
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * The default transport timeout.
     *
     * @var int
     */
    private const TIMEOUT = 15;

    /**
     * The default backoff multiplier.
     *
     * @var int
     */
    private const BACKOFF = 1000;

    /**
     * The default 4xx retry codes.
     *
     * @var int[]
     */
    private const CODES = [429];

    /**
     * The default amount of retries.
     */
    private const RETRIES = 3;

    /**
     * Create a new guzzle client.
     *
     * @param array                               $options
     * @param (callable(HandlerStack): void)|null $configure
     * @param CurlShare::*|null                   $curlShare
     * @param int|null                            $backoff
     * @param int[]|null                          $codes
     * @param int|null                            $retries
     *
     * @return \GuzzleHttp\Client
     */
    public static function make(
        array $options = [],
        ?callable $configure = null,
        ?string $curlShare = null,
        ?int $backoff = null,
        ?array $codes = null,
        ?int $retries = null
    ): Client {
        if (\array_key_exists('handler', $options)) {
            throw new \InvalidArgumentException('Use the configure callback to customize the handler stack; passing a handler in the client options array is not supported.');
        }

        if (\array_key_exists('curl_share', $options)) {
            throw new \InvalidArgumentException('Pass cURL sharing mode with the curlShare argument, not the client options array.');
        }

        $config = array_merge([
            RequestOptions::CRYPTO_METHOD   => self::CRYPTO_METHOD,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            RequestOptions::TIMEOUT         => self::TIMEOUT,
        ], $options);

        $config['handler'] = self::handler($configure, $curlShare, $backoff, $codes, $retries);

        return new Client($config);
    }

    /**
     * Create a new retrying handler stack.
     *
     * @param (callable(HandlerStack): void)|null $configure
     * @param CurlShare::*|null                   $curlShare
     * @param int|null                            $backoff
     * @param int[]|null                          $codes
     * @param int|null                            $retries
     *
     * @return \GuzzleHttp\HandlerStack
     */
    private static function handler(
        ?callable $configure = null,
        ?string $curlShare = null,
        ?int $backoff = null,
        ?array $codes = null,
        ?int $retries = null
    ): HandlerStack {
        $curlShare = self::normalizeCurlShare($curlShare);
        $handlerOptions = self::curlSharingEnabled($curlShare) ? ['share' => $curlShare] : [];
        $stack = new HandlerStack(Utils::chooseHandler($handlerOptions));

        $stack->push(Middleware::httpErrors(new BodySummarizer(250)), 'http_errors');
        $stack->push(Middleware::redirect(), 'allow_redirects');
        $stack->push(Middleware::cookies(), 'cookies');
        $stack->push(Middleware::prepareBody(), 'prepare_body');

        if ($configure !== null) {
            $configure($stack);
        }

        if ($retries === 0) {
            return $stack;
        }

        $stack->push(self::createRetryMiddleware($backoff ?? self::BACKOFF, $codes ?? self::CODES, $retries ?? self::RETRIES), 'retry');

        return $stack;
    }

    /**
     * @param CurlShare::*|null $curlShare
     *
     * @return CurlShare::*|null
     */
    private static function normalizeCurlShare(?string $curlShare): ?string
    {
        if ($curlShare === null || $curlShare === CurlShare::NONE || $curlShare === CurlShare::HANDLER) {
            return $curlShare;
        }

        throw new \TypeError('The curlShare argument must be null, CurlShare::NONE, or CurlShare::HANDLER.');
    }

    /**
     * @param CurlShare::*|null $curlShare
     */
    private static function curlSharingEnabled(?string $curlShare): bool
    {
        return $curlShare !== null && $curlShare !== CurlShare::NONE;
    }

    /**
     * Create a new retry middleware.
     *
     * @param int   $backoff
     * @param int[] $codes
     * @param int   $maxRetries
     *
     * @return Closure
     */
    private static function createRetryMiddleware(
        int $backoff,
        array $codes,
        int $maxRetries
    ): Closure {
        $decider = static function ($retries, RequestInterface $request, ?ResponseInterface $response = null, ?TransferException $exception = null) use ($codes, $maxRetries) {
            return $retries < $maxRetries && ($exception instanceof ConnectException || ($response && ($response->getStatusCode() >= 500 || in_array($response->getStatusCode(), $codes, true))));
        };

        $delay = static function ($retries) use ($backoff) {
            return (int) pow(2, $retries) * $backoff;
        };

        return static function (callable $handler) use ($decider, $delay): RetryMiddleware {
            return new RetryMiddleware($decider, $handler, $delay);
        };
    }
}
