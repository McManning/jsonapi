<?php

namespace Tests;

use Tests\Fixtures\SlimTestCase;

/**
 * Tests for GET routes in the Slim Controller
 */
class ControllerGetTest extends SlimTestCase
{
    private function jsonFixture(string $name)
    {
        $path = sprintf('%s/Fixtures/%s.json', __DIR__, $name);
        return json_decode(file_get_contents($path), true);
    }

    public function testGetAll()
    {
        $expected = $this->jsonFixture('book');
        $actual = $this->request('get', '/api/book');

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testGetInstance()
    {
        $expected = $this->jsonFixture('book-1');
        $actual = $this->request('get', '/api/book/1');

        \file_put_contents('.tmp/get-book.json', json_encode($actual, JSON_PRETTY_PRINT));

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    public function testFilterByAttribute()
    {
        $expected = $this->jsonFixture('book-filter-isbn');
        $actual = $this->request('get', '/api/book', [
            'filter[ISBN]' => 'ISBN-1'
        ]);

        $this->assertStatusCode(200);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Complex filter test where we filter results based on
     * a related resource of a related resource.
     */
    public function testNestedRelationshipFilter()
    {
        // author?filter[books.bookClubLists]=1
        $query = new \AuthorQuery();
        $query
            ->useBookQuery()
                ->useQuery('BookClubList', '\BookClubListQuery')
                    ->filterByTheme('scifi')
                ->endUse()
            ->endUse();

        // Can't nest a useXQuery(), so now what?

        $books = $query->find();

        $this->assertEquals(1, $books->count());
        $this->assertEquals(1, $books[0]->getId());
    }

    // FAIL: Unknown column books in model Author
    // public function testGetRelated()
    // {
    //     $expected = $this->jsonFixture('book-1-author');
    //     $actual = $this->request('get', '/api/book/1/author');

    //     \file_put_contents('.tmp/get-book-author.json', json_encode($actual, JSON_PRETTY_PRINT));

    //     $this->assertStatusCode(200);
    //     $this->assertEquals($expected, $actual);
    // }

    // FAIL: Unknown column books in model Author
    // public function testGetRelationship()
    // {
    //     $expected = $this->jsonFixture('book');
    //     $actual = $this->request('get', '/api/book/1/relationships/author');

    //     $this->assertStatusCode(200);
    //     $this->assertEquals($expected, $actual);
    // }

    // testFilterByRelationship

    // testFilterByFullTextSearch
}
