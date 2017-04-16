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
     * Create a new guzzle client.
     *
     * @param string|null $base
     * @param int         $connect
     * @param int         $timeout
     * @param int         $backoff
     *
     * @return \GuzzleHttp\Client
     */
    public static function make(string $base = null, int $connect = 10, int $timeout = 15, int $backoff = 1000)
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::retry(function ($retries, RequestInterface $request, ResponseInterface $response = null, TransferException $exception = null) {
            return $retries < 3 && ($exception instanceof ConnectException || ($response && $response->getStatusCode() >= 500));
        }, function ($retries) {
            return (int) pow(2, $retries) * $backoff;
        }));

        $options = ['handler' => $stack, 'connect_timeout' => $connect, 'timeout' => $timeout];

        if ($base) {
            $options['base_uri'] = $base;
        }

        return new Client($options);
    }
}
