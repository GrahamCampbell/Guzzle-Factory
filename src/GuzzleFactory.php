<?php

declare(strict_types=1);

/*
 * This file is part of Guzzle Factory.
 *
 * (c) Graham Campbell <graham@alt-three.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GrahamCampbell\GuzzleFactory;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This is the guzzle factory class.
 *
 * @author Graham Campbell <graham@alt-three.com>
 */
final class GuzzleFactory
{
    /**
     * The default connect timeout.
     *
     * @var int
     */
    const CONNECT_TIMEOUT = 10;

    /**
     * The default connect timeout.
     *
     * @var int
     */
    const TIMEOUT = 15;

    /**
     * The default backoff multiplier.
     *
     * @var int
     */
    const BACKOFF = 1000;

    /**
     * The default 4xx retry codes.
     *
     * @var int[]
     */
    const CODES = [429];

    /**
     * Create a new guzzle client.
     *
     * @param array      $options
     * @param int|null   $backoff
     * @param int[]|null $codes
     *
     * @return \GuzzleHttp\Client
     */
    public static function make(array $options = [], int $backoff = null, array $codes = null)
    {
        return new Client(array_merge(['handler' => self::handler($backoff, $codes), 'connect_timeout' => self::CONNECT_TIMEOUT, 'timeout' => self::TIMEOUT], $options));
    }

    /**
     * Create a new guzzle handler stack.
     *
     * @param int|null   $backoff
     * @param int[]|null $codes
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public static function handler(int $backoff = null, array $codes = null)
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::retry(function ($retries, RequestInterface $request, ResponseInterface $response = null, TransferException $exception = null) use ($codes) {
            return $retries < 3 && ($exception instanceof ConnectException || ($response && ($response->getStatusCode() >= 500 || in_array($response->getStatusCode(), $codes === null ? self::CODES : $codes, true))));
        }, function ($retries) use ($backoff) {
            return (int) pow(2, $retries) * ($backoff === null ? self::BACKOFF : $backoff);
        }));

        return $stack;
    }
}
