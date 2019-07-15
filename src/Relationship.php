<?php

namespace McManning\JsonApi;

class Relationship
{
    /**
     * @var string
     */
    protected $relatedName;

    /**
     * @var callable
     */
    protected $resolver;

    /**
     * @var bool
     */
    protected $toMany;

    /**
     * @var string
     */
    protected $inverseRelationship;

    public function __construct(string $relatedName)
    {
        $this->relatedName = $relatedName;
        $this->resolver = null;
        $this->toMany = false;
        $this->inverseRelationship = null;
    }

    public function getRelatedName(): string
    {
        return $this->relatedName;
    }

    public function setModelName(string $modelName)
    {
        // If it's a FQN, strip down to classname only
        $pos = strrpos($modelName, '\\');
        if ($pos !== false) {
            $modelName = substr($modelName, $pos + 1);
        }

        $this->modelName = $modelName;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function setInverseRelationship(string $relName)
    {
        $this->inverseRelationship = $relName;
    }

    /**
     * Return the filterBy clause to retrieve related resources from the parent.
     *
     * If the inverse relationship is set, it'll be based on that value.
     * Otherwise, it is based on the relationship name.
     *
     * It is expected that whatever gets returned from this function matches
     * a `filterBy[FilterName]Relationship` method in the parent resource's Query class.
     *
     * @return string
     */
    public function getFilterName(): string
    {
        if ($this->inverseRelationship) {
            return $this->inverseRelationship;
        }

        return $this->relatedName;
    }

    public function getInverseRelationship(): ?string
    {
        // TODO: This error message is SUPER unhelpful.
        if (!$this->inverseRelationship) {
            throw new \Exception(sprintf(
                'No inverse relationship on `%s` for model `%s`',
                $this->relatedName,
                $this->modelName
            ));
        }

        return $this->inverseRelationship;
    }

    public function setToMany(bool $toMany)
    {
        $this->toMany = $toMany;
    }

    public function isToMany(): bool
    {
        return $this->toMany;
    }

    public function setDataResolver(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @return false - no resolver attached, therefore do not display `data`
     *         array - Resource linkage to zero or more related resources
     *         null - Resource linkage to an unset to-one relationship
     */
    public function getData($resource)
    {
        $resolver = $this->resolver;
        if (!$resolver) {
            return false;
        }

        return $resolver($resource);
    }
}
