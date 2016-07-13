<?php
namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\BodyLocation;
use GuzzleHttp\Psr7\Response;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\BodyLocation
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\AbstractLocation
 */
class BodyLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new BodyLocation('body');
        $command = new Command('foo', []);
        $parameter = new Parameter([
            'name'    => 'val',
            'filters' => ['strtoupper']
        ]);
        $response = new Response(200, [], 'foo');
        $result = [];
        $l->visit($command, $response, $parameter, $result);
        $this->assertEquals('FOO', $result['val']);
    }
}
