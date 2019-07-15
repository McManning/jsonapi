<?php

namespace Tests;

use Tests\Fixtures\SlimTestCase;

/**
 * Quick test to ensure SlimTestCase is functioning as expected
 */
class SlimTestCaseTest extends SlimTestCase
{
    public function testGet()
    {
        $expected = [ 'method' => 'get', 'query' => [], 'body' => null ];
        $actual = $this->request('get', '/test');

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testPost()
    {
        $expected = [ 'method' => 'post', 'query' => [], 'body' => null ];
        $actual = $this->request('post', '/test');

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testPatch()
    {
        $expected = [ 'method' => 'patch', 'query' => [], 'body' => null ];
        $actual = $this->request('patch', '/test');

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testDelete()
    {
        $expected = [ 'method' => 'delete', 'query' => [], 'body' => null ];
        $actual = $this->request('delete', '/test');

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testNotFound()
    {
        $this->request('get', '/invalid-page');
        $this->assertStatusCode(404);
    }

    public function testQueryParams()
    {
        $expected = [
            'method' => 'get',
            'query' => [
                'foo' => 'bar',
                'fizz' => 'buzz'
            ],
            'body' => null
        ];

        $actual = $this->request('get', '/test', [
            'foo' => 'bar',
            'fizz' => 'buzz'
        ]);

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testBody()
    {
        $expected = [
            'method' => 'post',
            'query' => [],
            'body' => [
                'foo' => 'bar',
                'fizz' => 'buzz'
            ]
        ];

        $actual = $this->request('post', '/test', [], [
            'foo' => 'bar',
            'fizz' => 'buzz'
        ]);

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }
}
