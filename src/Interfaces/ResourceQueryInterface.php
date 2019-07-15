<?php

namespace McManning\JsonApi\Interfaces;

use McManning\JsonApi\AuthorizationRequest;

/**
 * Methods utilized by the JsonApiController for querying API resources.
 *
 * Resource instances do **not** need to implement this interface
 * (to support cases where they cannot, such as Propel ORM models).
 * This interface is not directly typehinted within the API codebase.
 *
 * If you write your own Non-Propel resources, it is **strongly recommended**
 * that you explicitly implement the interface below as the methods defined
 * by this interface are expected by the JsonApiController.
 *
 * For each class implementing ResourceInterface, there must be an equivalent
 * class implementing ResourceQueryInterface with a `Query` suffix in the class
 * name within the same namespace.
 *
 * For example:
 *
 * ```
 * namespace API\Resources;
 *
 * use McManning\JsonApi\Interfaces\ResourceInterface;
 * use McManning\JsonApi\Interfaces\ResourceQueryInterface;
 *
 * class MyFoo implements ResourceInterface { ... }
 *
 * class MyFooQuery implements ResourceQueryInterface { ... }
 * ```
 */
interface ResourceQueryInterface
{
    /**
     * Perform pre-query authorization to the resource query.
     *
     * Pre-query authorization SHOULD reduce the number of results to only those
     * that the authorization request has access to. In some scenarios, this MAY
     * throw an `AuthorizationRequestException` if the request cannot be fulfilled
     * on any resource.
     *
     * @param AuthorizationRequest $request to apply to the query
     *
     * @return void
     */
    public function filterByAuthorizationRequest(AuthorizationRequest $request);

    /**
     * Apply a filter to the query to only return resources matching the specified IDs
     *
     * @param string|array $id One or more IDs to filter by
     *
     * @return void
     */
    public function filterById($id);

    /**
     * TODO: Document. Bit complicated.
     *
     * @throws McManning\JsonApi\Exception\AuthorizationRequestException
     *
     * @return iterable collection of ResourceInterface instances
     */
    public function findAuthorized(array $ids = null): iterable;

    /**
     * TODO: Document. Bit complicated.
     *
     * @throws McManning\JsonApi\Exception\AuthorizationRequestException
     *
     * @return null|ResourceIterface
     */
    public function findOneAuthorized(string $id): ?ResourceInterface;

    // --------------------------------------------------------------
    // To support `filter[foo]=bar` query parameters in the API, each
    // filter (e.g. `foo`) requires a matching `filterByFoo` method
    // with the signature below. This may be applied to attributes
    // on the resource, relationships, or any arbitrary filtering mechanism.
    // --------------------------------------------------------------

    /**
     * Restrict the query to only resources that match the `foo` filter value
     *
     * @param mixed $value The value of `foo` to filter on
     *
     * @return void
     */
    // public function filterByFoo($value);


    // --------------------------------------------------------------
    // To support relationship identifier-based filtering `filter[relationshipName]=ID`
    // add a `filterBy[RelationshipName]Identifier` method that accepts the
    // related resource's ID. These relationship filters take priority over
    // the standard `filterByFoos` when a `filter[foos]` query is provided.
    // --------------------------------------------------------------

    // TODO: Not entirely a fan of this technique. But it's primarily to get around
    // Propel's default `filterBy[Related](RelatedModel|Collection)` methods.
    // A proper technique might be to override Propel's implementation to also
    // support passing in a string - or by avoiding the builtin filterBy methods altogether.

    /**
     * Restrict the query to resources related to the given `foos` ID
     *
     * @param mixed $value The value of `foo` to filter on
     *
     * @return void
     */
    // public function filterByFoosIdentifier($id);


    // --------------------------------------------------------------
    // To support full text search `q=my search term` query parameters
    // in the API, the following method signature must be included.
    // Implementation is entirely up to what your backend storage
    // solution may support in terms of full text searching resources.
    // E.g. you may support features such as wildcards, regex, etc.
    // --------------------------------------------------------------

    /**
     * Restrict the query to only resources that match a full text search.
     *
     * @param string $search The full text search string
     *
     * @return void
     */
    // public function filterByFullTextSearch(string $search);
}
