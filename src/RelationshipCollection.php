<?php

namespace McManning\JsonApi;

use McManning\JsonApi\Relationship;

// TODO: Impl \ArrayAccess as well?
class RelationshipCollection implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @var Relationship[]
     */
    protected $related;

    public function __construct()
    {
        $this->related = [];
    }

    public function add(Relationship $related)
    {
        $this->related[$related->getRelatedName()] = $related;
    }

    public function get(string $relatedName): ?Relationship
    {
        if (!isset($this->related[$relatedName])) {
            return null;
        }

        return $this->related[$relatedName];
    }

    public function jsonSerialize()
    {
        $json = [];
        foreach ($this->related as $related) {
            $json[$related->getRelatedName()] = $related;
        }

        return $json;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->related);
    }

    public function count(): int
    {
        return count($this->related);
    }
}
