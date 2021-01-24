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

namespace GrahamCampbell\Tests\GuzzleFactory;

use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * This is the guzzle factory test class.
 *
 * @author Graham Campbell <graham@alt-three.com>
 */
class GuzzleFactoryTest extends TestCase
{
    public function testMake()
    {
        $this->assertInstanceOf(Client::class, GuzzleFactory::make());
    }

    public function testHandler()
    {
        $this->assertInstanceOf(HandlerStack::class, GuzzleFactory::handler());
    }

    public function testRetries()
    {
        $increment = function () use (&$totalRequests) {
            $totalRequests += 1;
        };

        $totalRequests = 0;
        $handler = new MockHandler([new Response(500), new Response(500), new Response(500), new Response(500)], $increment);
        $stack = new HandlerStack($handler);
        $client = GuzzleFactory::make(['handler' => $stack], 0, [500]);
        $client->sendRequest(new Request('GET', 'http://test.com'));
        self::assertEquals(4, $totalRequests);

        $totalRequests = 0;
        $handler = new MockHandler([new Response(500), new Response(500)], $increment);
        $stack = new HandlerStack($handler);
        $client = GuzzleFactory::make(['handler' => $stack], 0, [500], 1);
        $client->sendRequest(new Request('GET', 'http://test.com'));
        self::assertEquals(2, $totalRequests);

        $totalRequests = 0;
        $handler = new MockHandler([new Response(500)], $increment);
        $stack = new HandlerStack($handler);
        $client = GuzzleFactory::make(['handler' => $stack], 0, [500], 0);
        $client->sendRequest(new Request('GET', 'http://test.com'));
        self::assertEquals(1, $totalRequests);
    }
}
