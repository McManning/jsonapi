<?php

namespace McManning\JsonApi;

/**
 * Encapsulated information about a request to perform some action(s) on an API resource
 *
 * An authorization request may carry along with it an application-specific identity
 * as well as information about what the API user is trying to do to a specific resource.
 *
 * A copy of the request is used in two places:
 *
 * - Query class for a resource to filter down results based on an identity's level of
 *      access - as well as early-quit the query if the identity does not have access
 *      to perform the requested actions on *any* resources
 * - Resource instance class to test the authorization after data has been
 *      hydrated from the backend storage solution. If access controls are based on
 *      attributes and relationships of a particular resource, then the authorization
 *      check steps will happen within the resource itself.
 */
class AuthorizationRequest
{
    // Action flags ORed together in getActions()
    const CREATE = 1;
    const VIEW = 2;
    const DELETE = 4;
    const MODIFY_ATTRIBUTES = 8;
    const MODIFY_RELATIONSHIPS = 16;

    /** @var mixed */
    protected $identity;

    /** @var int */
    protected $actions;

    /** @var string[] */
    protected $attributes;

    /** @var string[] */
    protected $relationships;

    public function __construct()
    {
        $this->actions = 0;
        $this->attributes = [];
        $this->relationships = [];
    }

    public function getIdentity()
    {
        return $this->identity;
    }

    public function setIdentity($identity)
    {
        $this->identity = $identity;
    }

    public function getActions(): int
    {
        return $this->actions;
    }

    /**
     * Non-bitwise method for testing for an action
     */
    public function hasAction(int $action)
    {
        return $this->actions & $action;
    }

    public function setActions(int $actions)
    {
        $this->actions = $actions;
    }

    public function getModifiedAttributes(): array
    {
        return $this->attributes;
    }

    public function setModifiedAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function getModifiedRelationships(): array
    {
        return $this->relationships;
    }

    public function setModifiedRelationships(array $relationships)
    {
        $this->relationships = $relationships;
    }

    /**
     * Create a human-readable version of this request.
     *
     * Will be returned by the API upon request failures.
     */
    public function __toString()
    {
        $actions = [];

        // TODO: This is dumb. Improve.
        if ($this->actions & self::CREATE) {
            $actions[] = sprintf('[%s].create', 'resource');
        }

        if ($this->actions & self::VIEW) {
            $actions[] = sprintf('[%s].view', 'resource');
        }

        if ($this->actions & self::DELETE) {
            $actions[] = sprintf('[%s].delete', 'resource');
        }

        if ($this->actions & self::MODIFY_ATTRIBUTES) {
            $actions[] = sprintf(
                '[%s].modify_attributes[%s]',
                'resource',
                implode(', ', $this->attributes)
            );
        }

        if ($this->actions & self::MODIFY_ATTRIBUTES) {
            $actions[] = sprintf(
                '[%s].modify_relationships[%s]',
                'resource',
                implode(', ', $this->relationships)
            );
        }

        return 'Authorization Request: ' . implode(', ', $actions);
    }
}
