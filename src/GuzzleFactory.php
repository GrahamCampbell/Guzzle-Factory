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
     * @param array      $options
     * @param int|null   $backoff
     * @param int[]|null $codes
     * @param int|null   $retries
     *
     * @return \GuzzleHttp\Client
     */
    public static function make(
        array $options = [],
        int $backoff = null,
        array $codes = null,
        int $retries = null
    ): Client {
        $config = array_merge([
            RequestOptions::CRYPTO_METHOD   => self::CRYPTO_METHOD,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            RequestOptions::TIMEOUT         => self::TIMEOUT,
        ], $options);

        $config['handler'] = self::handler($backoff, $codes, $retries, $options['handler'] ?? null);

        return new Client($config);
    }

    /**
     * Create a new retrying handler stack.
     *
     * @param int|null                      $backoff
     * @param int[]|null                    $codes
     * @param int|null                      $retries
     * @param \GuzzleHttp\HandlerStack|null $stack
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public static function handler(
        int $backoff = null,
        array $codes = null,
        int $retries = null,
        HandlerStack $stack = null
    ): HandlerStack {
        $stack = $stack ?? self::innerHandler();

        if ($retries === 0) {
            return $stack;
        }

        $stack->push(self::createRetryMiddleware($backoff ?? self::BACKOFF, $codes ?? self::CODES, $retries ?? self::RETRIES), 'retry');

        return $stack;
    }

    /**
     * Create a new handler stack.
     *
     * @param callable|null $handler
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public static function innerHandler(
        callable $handler = null
    ): HandlerStack {
        $stack = new HandlerStack($handler ?? Utils::chooseHandler());

        $stack->push(Middleware::httpErrors(new BodySummarizer(250)), 'http_errors');
        $stack->push(Middleware::redirect(), 'allow_redirects');
        $stack->push(Middleware::cookies(), 'cookies');
        $stack->push(Middleware::prepareBody(), 'prepare_body');

        return $stack;
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
        $decider = static function ($retries, RequestInterface $request, ResponseInterface $response = null, TransferException $exception = null) use ($codes, $maxRetries) {
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
