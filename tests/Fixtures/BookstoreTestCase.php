<?php

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Util\QuickBuilder;

/**
 * Uses `bookstore.schema.xml` and generates a handful of test fixtures
 */
abstract class BookstoreTestCase extends TestCase
{
    public function setUp()
    {
        if (!class_exists('Book')) {
            $schema = file_get_contents(__DIR__ . '/bookstore.schema.xml');

            $builder = new QuickBuilder();
            $builder->setSchema($schema);
            $builder->build();

            // Dump the generated code locally to inspect behavior methods
            $tempFile = (new \ReflectionClass('Book'))->getFileName();
            copy($tempFile, __DIR__ . '/generated.php');

            $this->addFixtures();
        }
    }

    private function addFixtures()
    {
        $p = new \Publisher();
        $p->setName('Pan Books Ltd.');

        $a = new \Author();
        $a->setFirstName('Douglas');
        $a->setLastName('Adams');
        $a->setEmail('adams@example.com');
        $a->setAge(42);

        $b1 = new \Book();
        $b1->setTitle('The Hitchhikers Guide To The Galaxy');
        $b1->setISBN('ISBN-1');
        $b1->setPrice(29.99);
        $b1->setPublisher($p);

        $a->addBook($b1);
        $a->save();

        $b2 = new \Book();
        $b2->setTitle('The Restaurant At The End Of The Universe');
        $b2->setISBN('ISBN-2');
        $b2->setPrice(29.99);
        $b2->setPublisher($p);
        $b2->setAuthor($a);

        $b2->save();
    }
}
