<?php

namespace Tests;

use Tests\Fixtures\BookstoreTestCase;

use McManning\JsonApi\RelationshipCollection;

class JsonApiBehaviorTest extends BookstoreTestCase
{
    public function testResourceIdentifier()
    {
        $book = new \Book();
        $book->setId(1);

        $this->assertEquals('book', $book->getJsonApiType());
        $this->assertEquals('1', $book->getJsonApiId());
    }

    public function testToJsonApiAttributes()
    {
        $b = new \Book();
        $b->setTitle('The Hitchhikers Guide To The Galaxy');
        $b->setISBN('ISBN-1');
        $b->setPrice(29.99);

        $expected = [
            'ISBN' => 'ISBN-1',
            'price' => 29.99,
            'title' => 'The Hitchhikers Guide To The Galaxy'
        ];

        $actual = $b->toJsonApiAttributes();

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function testSparseAttributes()
    {
        $b = new \Book();
        $b->setTitle('The Hitchhikers Guide To The Galaxy');
        $b->setISBN('ISBN-1');
        $b->setPrice(29.99);

        $expected = [
            'price' => 29.99,
            'title' => 'The Hitchhikers Guide To The Galaxy'
        ];

        $actual = $b->toJsonApiAttributes(['title', 'price']);

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function testFromJsonApiAttributes()
    {
        $b = new \Book();
        $b->fromJsonApiAttributes([
            'title' => 'The Hitchhikers Guide To The Galaxy',
            'ISBN' => 'ISBN-1',
            'price' => 29.99
        ]);

        $this->assertEquals('The Hitchhikers Guide To The Galaxy', $b->getTitle());
        $this->assertEquals('ISBN-1', $b->getISBN());
        $this->assertEquals(29.99, $b->getPrice());
    }

    /**
     * Ensure attributes specified in the `exclude_attributes` parameter
     * of the behavior are excluded in the serialization
     */
    public function testGetExcludedAttributes()
    {
        $a = new \Author();
        $a->setFirstName('Douglas');
        $a->SetLastName('Adams');
        $a->setEmail('adams@example.com');
        $a->setAge(42);
        $a->setAddress('123 Fake St');

        $expected = [
            'firstName' => 'Douglas',
            'lastName' => 'Adams',
            'email' => 'adams@example.com'
        ];

        $actual = $a->toJsonApiAttributes();

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * Ensure that attributes excluded via `exclude_attributes` cannot
     * be set through the deserializer
     */
    public function testSetExcludedAttributes()
    {
        $a = new \Author();
        $a->setFirstName('Douglas');
        $a->SetLastName('Adams');
        $a->setEmail('adams@example.com');
        $a->setAge(42);
        $a->setAddress('123 Fake St');

        $a->fromJsonApiAttributes([
            'email' => 'adams@replacement.com',
            'age' => 100,
            'address' => '456 Test St'
        ]);

        $this->assertEquals('adams@replacement.com', $a->getEmail());
        $this->assertEquals(42, $a->getAge());
        $this->assertEquals('123 Fake St', $a->getAddress());
    }

    public function testGetRelationships()
    {
        $a = new \Author();
        $a->setId(5);
        $a->setFirstName('Douglas');
        $a->SetLastName('Adams');

        $b1 = new \Book();
        $b1->setId(1);
        $a->addBook($b1);

        $relationships = $b1->getRelationships();

        $expected = ['author', 'publisher', 'bookClubLists', 'summary'];
        $actual = array_keys($relationships->jsonSerialize());
        $this->assertEqualsCanonicalizing($expected, $actual);


        // To-one relationships (from Book) should have a backref to-many to books
        $author = $relationships->get('author');
        $this->assertSame(\Author::class, $author->getModelName());
        $this->assertSame('books', $author->getInverseRelationship());
        $this->assertFalse($author->isToMany());

        $publisher = $relationships->get('publisher');
        $this->assertSame(\Publisher::class, $publisher->getModelName());
        $this->assertSame('books', $publisher->getInverseRelationship());
        $this->assertFalse($publisher->isToMany());

        // Many-to-many relationship should ignore the crossref table entirely
        $bookClubLists = $relationships->get('bookClubLists');
        $this->assertSame(\BookClubList::class, $bookClubLists->getModelName());
        $this->assertSame('books', $bookClubLists->getInverseRelationship());
        $this->assertTrue($bookClubLists->isToMany());

        // One-to-one relationship with a custom name on both ends
        $summary = $relationships->get('summary');
        $this->assertSame(\BookSummary::class, $summary->getModelName());
        $this->assertSame('summarizedBook', $summary->getInverseRelationship());
        $this->assertFalse($summary->isToMany());
    }

    /**
     * Ensure that a related model that does not have `jsonapi`
     * behavior is not made available to the API.
     */
    public function testPrivateRelationship()
    {
        $a = new \Author();
        $relationships = $a->getRelationships();

        $expected = ['books'];
        $actual = array_keys($relationships->jsonSerialize());

        $this->assertEqualsCanonicalizing($expected, $actual);
    }
}
