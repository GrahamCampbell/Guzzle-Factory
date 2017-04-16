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
        $this->assertInstanceOf(Client::class, GuzzleFactory::make('https://example.com'));
    }
}
