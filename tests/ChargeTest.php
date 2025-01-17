<?php

namespace Invoiced\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Invoiced\Charge;
use Invoiced\Client;
use PHPUnit_Framework_TestCase;

class ChargeTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    public static $invoiced;

    /**
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $mock = new MockHandler([
            new Response(201, [], '{"id":123,"amount":100}'),
            new Response(201, [], '{"id":456,"amount":50,"object":"refund"}'),
        ]);

        self::$invoiced = new Client('API_KEY', false, null, $mock);
    }

    /**
     * @return void
     */
    public function testGetEndpoint()
    {
        $charge = new Charge(self::$invoiced, 123);
        $this->assertEquals('/charges/123', $charge->getEndpoint());
    }

    /**
     * @return void
     */
    public function testCreate()
    {
        $charge = self::$invoiced->Charge->create(['customer' => 123]);

        $this->assertInstanceOf('Invoiced\\Charge', $charge);
        $this->assertEquals(123, $charge->id);
        $this->assertEquals(100, $charge->amount);
    }
}
