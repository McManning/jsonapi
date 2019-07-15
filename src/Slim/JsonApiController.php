<?php

namespace McManning\JsonApi\Slim;

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

use McManning\JsonApi\AuthorizationRequest;
use McManning\JsonApi\Middleware\ErrorResponseMiddleware;
use McManning\JsonApi\Exception\NotFoundException;
use McManning\JsonApi\Exception\BadRequestException;
use McManning\JsonApi\Exception\ConflictException;
use McManning\JsonApi\Exception\AuthorizationRequestException;
use McManning\JsonApi\Relationship;

/**
 * Expose ORM resource CRUD operations as JSON:API compliant endpoints
 */
class JsonApiController
{
    /**
     * Specifications version accepted/returned
     *
     * @var string
     */
    const JSONAPI_VERSION = '1.0';

    /**
     * Content type sent by response payloads
     *
     * @var string
     */
    const JSONAPI_CONTENT_TYPE = 'application/vnd.api+json';

    /**
     * Service name lookup for this controller instance
     */
    protected $containerName;

    /**
     * Namespaces for ORM query/model lookup
     *
     * @var array
     */
    protected $namespaces;

    /**
     * @var \Slim\Router
     */
    protected $router;

    /**
     * @param string $containerName Name of the Slim container service used for
     *                              retrieving an instance of this class in routing.
     * @param string[] $namespaces  Fully qualified namespaces that
     *                              models and queries are stored
     */
    public function __construct(string $containerName, array $namespaces)
    {
        $this->containerName = $containerName;
        $this->namespaces = $namespaces;
    }

    /**
     * Register API endpoints with a Slim app
     *
     * It is *strongly* recommended to register these under a route group to
     * prevent conflict with other endpoints your application may be providing.
     *
     * @param App $app to load the endpoints
     */
    public function registerEndpoints(App $app)
    {
        $this->router = $app->getContainer()->get('router');

        $me = $this->containerName;

        // Metadata
        $app->get('[/]', $me.':getRoot');

        // Search
        $app->get('/{type}', $me.':search');

        // Resource management (CRUD)
        $app->group(
            '/{type}',
            function () use ($me) {
                $this->post('', $me.':createResource')
                    ->setName($me.'-create');

                $this->get('/{id}', $me.':getResource')
                    ->setName($me.'-get');

                $this->patch('/{id}', $me.':updateResource')
                    ->setName($me.'-update');

                $this->delete('/{id}', $me.':deleteResource')
                    ->setName($me.'-delete');
            }
        );

        // Relationship management (CRUD)
        $app->get('/{type}/{id}/{relName}', $me.':getRelated')
            ->setName($me.'-related');

        $app->group(
            '/{type}/{id}/relationships/{relName}',
            function () use ($me) {
                $this->get('', $me.':getRelationship')
                    ->setName($me.'-self');

                $this->post('', $me.':addRelationships')
                    ->setName($me.'-add-related');

                $this->patch('', $me.':replaceRelationships')
                    ->setName($me.'-replace-related');

                $this->delete('', $me.':removeRelationships')
                    ->setName($me.'-delete-related');
            }
        );

        // Attach middleware to catch and re-throw all exceptions
        // in a JSON:API compliant way. TODO: Controllable verbosity
        $app->add(new ErrorResponseMiddleware($verbose = true));
    }

    /**
     * Retrieve metadata about the exposed API
     *
     * @param Request $req
     * @param Response $res
     *
     * @return Response
     */
    public function getRoot(Request $req, Response $res)
    {
        $json = [
            'jsonapi' => ['version' => self::JSONAPI_VERSION],
            'meta' => [
                'foo' => 'bar'
            ]
        ];

        return $res
            ->withJson($json)
            ->withHeader('Content-Type', self::JSONAPI_CONTENT_TYPE);
    }

    /**
     * Retrieve resource identifiers for a relationship
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException on an invalid relationship
     * @throws AuthorizationRequestException    if trying to view resources outside
     *                                          of current authorization
     *
     * @return Response JSON:API resource identifiers in the relationship
     */
    public function getRelationship(Request $req, Response $res)
    {
        // ------ Resolve Request to Models/Queries ---------
        $type = $req->getAttribute('type');
        $id = $req->getAttribute('id');
        $relName = $req->getAttribute('relName');

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // ------ Get Parent Model Instance ---------
        $viewAuth = new AuthorizationRequest();
        $viewAuth->setActions(AuthorizationRequest::VIEW);

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($viewAuth);

        $resource = $query->findOneAuthorized($id);

        $relationship = $resource->getRelationships()->get($relName);
        if (!$relationship) {
            throw new BadRequestException(sprintf(
                'Relationship `%s` does not exist on `%s`',
                $relName,
                $type
            ));
        }

        // ------ Get Related Model Instance(s) ---------
        $related = $this->queryRelatedResources($resource, $relationship);

        // ------ Compile Response ---------
        $data = $this->jsonApiResourceIdentifiersTransformer($related);
        $links = $this->createRelationshipLinks($resource, $relationship);

        return $this->createResponse($res, $data, null, $links);
    }

    /**
     * Add additional resources to a relationship.
     * If the relationship is to-one, this will replace the existing relationship.
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException on a malformed request body
     * @throws AuthorizationRequestException    if trying to modify resources outside
     *                                          of current authorization
     *
     * @return Response JSON:API resource identifiers in the updated
     *                  relationship after the requested additions.
     */
    public function addRelationships(Request $req, Response $res)
    {
        // ------ Resolve Request to Models/Queries ---------
        $type = $req->getAttribute('type');
        $id = $req->getAttribute('id');
        $relName = $req->getAttribute('relName');

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // ------ Get/Parse Request Payload --------
        $body = $req->getParsedBody();

        // ------ Get Model Instances --------- (cp jsonapi-self)
        $modifyAuth = new AuthorizationRequest();
        $modifyAuth->setActions(AuthorizationRequest::MODIFY_RELATIONSHIPS);
        $modifyAuth->setModifiedRelationships([$relName]);

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($modifyAuth);

        $resource = $query->findOneAuthorized($id);

        $relationship = $resource->getRelationships()->get($relName);
        if (!$relationship) {
            throw new BadRequestException(sprintf(
                'Relationship `%s` does not exist on `%s`',
                $relName,
                $type
            ));
        }

        // Ensure the request payload is valid before access
        $this->validateRelationshipsMutationBody($body, $relationship);

        // --------- Get Instances to Add ----------
        $idsToAdd = $this->extractIdsFromResourceLinkage($body['data']);

        // Setup a new auth request
        $modifyAuth = new AuthorizationRequest();
        $modifyAuth->setActions(AuthorizationRequest::MODIFY_RELATIONSHIPS);
        $modifyAuth->setModifiedRelationships([
            $relationship->getInverseRelationship()
        ]);

        $relatedModelFQN = $this->resolveModelFQN($relationship->getModelName());
        $relatedQueryFQN = $relatedModelFQN.'Query';

        if (!empty($idsToAdd)) {
            $query = new $relatedQueryFQN;
            $query->filterById($idsToAdd);
            $query->filterByAuthorizationRequest($modifyAuth);

            $relatedModelsToAdd = $query->findAuthorized($idsToAdd);
        }

        // TODO: There should be a check here to ensure we were authorized for
        // all the resources we requested. Otherwise, a partial add would happen silently.

        // --------- Perform Add Updates ----------

        if ($relationship->isToMany() && !empty($idsToAdd)) {
            $relationshipMethod = 'add'.$relName; // TODO: Secure/error proof

            // Add resource collection to a to-many relationship
            $resource->{$relationshipMethod}(
                $relatedModelsToAdd
            );
        } else if (!$relationship->isToMany()) {
            $relationshipMethod = 'set'.$relName;

            // Replacement of the to-one relationship with a new resource (or nothing)
            $resource->{$relationshipMethod}(
                !empty($idsToAdd) ? $relatedModelsToAdd[0] : null
            );
        }

        $resource->save();

        // ------ Get Related Model Instance(s) ---------
        $related = $this->queryRelatedResources($resource, $relationship);

        // ------ Compile Response ---------
        $data = $this->jsonApiResourceIdentifiersTransformer($related);
        $links = $this->createRelationshipLinks($resource, $relationship);

        return $this->createResponse($res, $data, null, $links);
    }

    /**
     * Replace all existing resources in a relationship
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws NotFoundException because it may never be implemented.
     */
    public function replaceRelationships(Request $req, Response $res)
    {
        throw new NotFoundException(
            'PATCH is not supported for relationship replacement. ' .
            'Use a combination of POST and DELETE requests instead.'
        );
    }

    /**
     * Unlink specified resource relationships
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException on a malformed request body
     * @throws AuthorizationRequestException    if trying to modify resources outside
     *                                          of current authorization
     *
     * @return Response JSON:API resource identifiers in the updated
     *                  relationship after the requested removals.
     */
    public function removeRelationships(Request $req, Response $res)
    {
        // ------ Resolve Request to Models/Queries --------- (cp jsonapi-add-related)
        $type = $req->getAttribute('type');
        $id = $req->getAttribute('id');
        $relName = $req->getAttribute('relName');

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';
        $relationshipRemover = 'remove'.$relName; // TODO: Error proof

        // ------ Get/Parse Request Payload -------- (cp jsonapi-add-related)
        $body = $req->getParsedBody();

        // ------ Get Model Instances --------- (cp jsonapi-add-related)
        $modifyAuth = new AuthorizationRequest();
        $modifyAuth->setActions(AuthorizationRequest::MODIFY_RELATIONSHIPS);
        $modifyAuth->setModifiedRelationships([$relName]);

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($modifyAuth);

        $resource = $query->findOneAuthorized($id);

        $relationship = $resource->getRelationships()->get($relName);
        if (!$relationship) {
            throw new BadRequestException(sprintf(
                'Relationship `%s` does not exist on `%s`',
                $relName,
                $type
            ));
        }

        // Ensure the request payload is valid before access
        $this->validateRelationshipsMutationBody($body, $relationship);

        // --------- Get Instances to Delete ----------
        $idsToRemove = $this->extractIdsFromResourceLinkage($body['data']);

        // Setup a new auth request
        $modifyAuth = new AuthorizationRequest();
        $modifyAuth->setActions(AuthorizationRequest::MODIFY_RELATIONSHIPS);
        $modifyAuth->setModifiedRelationships([
            $relationship->getInverseRelationship()
        ]);

        $relatedModelFQN = $this->resolveModelFQN($relationship->getModelName());
        $relatedQueryFQN = $relatedModelFQN.'Query';

        $query = new $relatedQueryFQN;
        $query->filterById($idsToRemove);
        $query->filterByAuthorizationRequest($modifyAuth);

        $relatedModelsToRemove = $query->findAuthorized($idsToRemove);

        // --------- Perform Deletes ----------
        $resource->{$relationshipRemover}($relatedModelsToRemove);
        $resource->save();

        // ------ Get Related Model Instance(s) ---------
        $related = $this->queryRelatedResources($resource, $relationship);

        // ------ Compile Response ---------
        $data = $this->jsonApiResourceIdentifiersTransformer($related);
        $links = $this->createRelationshipLinks($resource, $relationship);

        return $this->createResponse($res, $data, null, $links);
    }

    /**
     * Search for one or more instances of a type of resource
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException on invalid search parameters
     *
     * @return Response JSON:API serialized version of the resource
     */
    public function search(Request $req, Response $res)
    {
        $type = $req->getAttribute('type');
        $filters = $req->getParam('filter', []);
        $fields = $req->getParam('fields', []);
        $fullTextSearch = $req->getParam('q', '');
        $include = explode(',', $req->getParam('include', ''));

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // TODO: Ensure certain filters are required for certain
        // use cases (e.g. can't retrieve ALL ACTIONS EVER)

        // ------ Get Model Instances ---------
        $viewAuth = new AuthorizationRequest();
        $viewAuth->setActions(AuthorizationRequest::VIEW);

        $query = new $modelQueryFQN;
        $query->filterByAuthorizationRequest($viewAuth);

        // Process `filter` directives to the query
        foreach ($filters as $filter => $value) {
            $filterMethod = 'filterBy'.$filter;

            if (!method_exists($query, $filterMethod)) {
                throw new BadRequestException(sprintf(
                    'Unknown filter `%s`',
                    $filter
                ));
            }

            $query->{$filterMethod}($value);
        }

        // Process `q` full text search directive (JSON:API nonstandard feature)
        if (strlen($fullTextSearch) > 0) {
            if (!method_exists($query, 'filterByFullTextSearch')) {
                throw new BadRequestException(
                    'Resource does not support full text search queries'
                );
            }

            $query->filterByFullTextSearch($fullTextSearch);
        }

        $resources = $query->findAuthorized();

        // TODO: Support `include` requests

        // ------ Compile Response ---------
        $data = $this->jsonApiTransformer($resources, $fields);
        return $this->createResponse($res, $data);
    }

    /**
     * Retrieve a specific resource by type/id identifier
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws AuthorizationRequestException    if trying to view resources outside
     *                                          of current authorization
     *
     * @return Response JSON:API serialized version of the resource
     */
    public function getResource(Request $req, Response $res)
    {
        // ------ Resolve Request to Models/Queries --------- (cp jsonapi-related)
        $type = $req->getAttribute('type');
        $id = $req->getAttribute('id');
        $fields = $req->getParam('fields', []);

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // ------ Get Model Instances --------- (cp jsonapi-related)
        $viewAuth = new AuthorizationRequest();
        $viewAuth->setActions(AuthorizationRequest::VIEW);

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($viewAuth);

        $resource = $query->findOneAuthorized($id);

        // TODO: Support `include` requests

        // ------ Compile Response ---------
        $data = $this->jsonApiTransformer($resource, $fields);
        return $this->createResponse($res, $data);
    }

    /**
     * Retrieve resources associated via a relationship
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException  on an invalid relationship
     * @throws AuthorizationRequestException    if trying to view resources outside
     *                                          of current authorization
     *
     * @return Response JSON:API serialized version of the related resources
     */
    public function getRelated(Request $req, Response $res)
    {
        // ------ Resolve Request to Models/Queries ---------
        $type = $req->getAttribute('type');
        $fields = $req->getAttribute('fields', []);
        $id = $req->getAttribute('id');
        $relName = $req->getAttribute('relName');

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // ------ Get Parent Model Instance ---------
        $viewAuth = new AuthorizationRequest();
        $viewAuth->setActions(AuthorizationRequest::VIEW);

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($viewAuth);

        $resource = $query->findOneAuthorized($id);

        $relationship = $resource->getRelationships()->get($relName);
        if (!$relationship) {
            throw new BadRequestException(sprintf(
                'Relationship `%s` does not exist on `%s`',
                $relName,
                $type
            ));
        }

        // ------ Get Related Model Instance(s) ---------
        $related = $this->queryRelatedResources($resource, $relationship);

        // ------ Compile Response ---------
        $data = $this->jsonApiTransformer($related, $fields);
        return $this->createResponse($res, $data);
    }

    /**
     * Request to create a new resource by specifying initial attributes & relationships
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException  on a malformed request body
     * @throws AuthorizationRequestException    if not authorized to create a new resource
     *                                          with values in the request body
     *
     * @return Response JSON:API serialized version of the new resource
     */
    public function createResource(Request $req, Response $res)
    {
        // ------ Get/Parse Request Payload --------
        $type = $req->getAttribute('type');
        $body = $req->getParsedBody();

        // TODO: Validate body payload structure
        $attributes = $body['data']['attributes'];

        $relationships = [];
        if (isset($body['data']['relationships'])) {
            $relationships = $body['data']['relationships'];
        }

        // ------ Create New Resource --------

        $modelFQN = $this->resolveModelFQN($type);
        $resource = new $modelFQN;

        // Ensure the identity is authorized to create a new instance
        // with the specified attributes and relationships
        // TODO: The flags may not necessarily be correct. If they don't
        // specify relationships, the relationship flag shouldn't be in there.
        $createAuth = new AuthorizationRequest();
        $createAuth->setActions(
            AuthorizationRequest::CREATE |
            AuthorizationRequest::MODIFY_ATTRIBUTES |
            AuthorizationRequest::MODIFY_RELATIONSHIPS
        );

        $createAuth->setModifiedAttributes(array_keys($attributes));
        $createAuth->setModifiedRelationships(array_keys($relationships));

        $resource->testAuthorizationRequest($createAuth);

        // ------ Apply Request Payload to Resource --------

        // Load specified attributes
        $resource->fromJsonApiAttributes($attributes);

        // Attach specified relationships
        $relationshipInfo = $resource->getRelationships();
        foreach ($relationships as $relName => $relData) {
            $relationship = $relationshipInfo->get($relName);
            if (!$relationship) {
                throw new BadRequestException(sprintf(
                    'Relationship `%s` does not exist on `%s`',
                    $relName,
                    $type
                ));
            }

            if ($relationship->isToMany()) {
                // Array of resource links
                $relatedModelIds = [];
                foreach ($relData['data'] as $idx => $resourceLink) {
                    $relatedModelIds = $resourceLink['id'];
                }
            } else { // Single resource link
                $relatedModelIds = [$relData['data']['id']];
            }

            // Setup pre-query authorization and get results
            $modifyAuth = new AuthorizationRequest();
            $modifyAuth->setActions(AuthorizationRequest::MODIFY_RELATIONSHIPS);
            $modifyAuth->setModifiedRelationships([
                // Relationship on the related resource that will be
                // updated to include this newly created resource
                $relationship->getInverseRelationship()
            ]);

            // TODO: Incorrect. Calls setter instead of adder for to-many.

            $relatedModelFQN = $this->resolveModelFQN($relationship->getModelName());
            $relatedQueryFQN = $relatedModelFQN.'Query';
            $relationshipSetter = 'set'.$relName; // TODO: Error proof
            $relationshipAdder = 'add'.$relName;

            $query = new $relatedQueryFQN;
            $query->filterById($relatedModelIds);
            $query->filterByAuthorizationRequest($modifyAuth);

            $relatedModels = $query->findAuthorized($relatedModelIds);

            if (!$relationship->isToMany()) {
                $relatedModels = $relatedModels[0];
            }

            // Apply relationship onto the newly created resource
            $resource->{$relationshipSetter}($relatedModels);
        }

        $resource->save();

        // --------- Return Created Resource ----------
        $data = $this->jsonApiTransformer($resource);
        return $this->createResponse($res, $data)->withStatus(201);
    }

    /**
     * Request to update an existing resource
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException  on a malformed request body
     * @throws AuthorizationRequestException    if trying to modify a resource outside
     *                                          of current authorization
     *
     * @return Response JSON:API serialized version of the updated resource
     */
    public function updateResource(Request $req, Response $res)
    {
        // ------ Get/Parse Request Payload --------
        $type = $req->getAttribute('type');
        $id = $req->getAttribute('id');
        $body = $req->getParsedBody();

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // TODO: Validate body payload structure
        $attributes = $body['data']['attributes'];

        // TODO: Support patch on relationships? Maybe?
        if (isset($body['data']['relationships'])) {
            throw new BadRequestException('Relationship patching not yet supported. Use /relationships/ endpoints instead');
        }

        // ------ Get Model Instance ---------
        $updateAuth = new AuthorizationRequest();
        $updateAuth->setActions(AuthorizationRequest::MODIFY_ATTRIBUTES);
        $updateAuth->setModifiedAttributes(array_keys($attributes));

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($updateAuth);

        $resource = $query->findOneAuthorized($id);

        // ------ Apply Request Payload to Resource --------
        $resource->fromJsonApiAttributes($attributes);
        $resource->save();

        // ------ Compile Response ---------
        $data = $this->jsonApiTransformer($resource);
        return $this->createResponse($res, $data);
    }

    /**
     * Request to delete an existing resource
     *
     * @param Request $req
     * @param Response $res
     *
     * @throws BadRequestException  on a malformed request body
     * @throws AuthorizationRequestException    if trying to delete a resource outside
     *                                          of current authorization
     *
     * @return Response JSON:API GONE response
     */
    public function deleteResource(Request $req, Response $res)
    {
        // ------ Get/Parse Request Payload --------
        $type = $req->getAttribute('type');
        $id = $req->getAttribute('id');
        $body = $req->getParsedBody();

        $modelFQN = $this->resolveModelFQN($type);
        $modelQueryFQN = $modelFQN.'Query';

        // ------ Get Model Instance ---------
        $deleteAuth = new AuthorizationRequest();
        $deleteAuth->setActions(AuthorizationRequest::DELETE);

        $query = new $modelQueryFQN;
        $query->filterById($id);
        $query->filterByAuthorizationRequest($deleteAuth);

        $resource = $query->findOneAuthorized($id);

        // ------ Perform Deletion --------
        $resource->delete();

        // ------ Compile Response ---------
        $data = $this->jsonApiTransformer($resource);
        return $res
            ->withStatus(204)
            ->withHeader('Content-Type', self::JSONAPI_CONTENT_TYPE);
    }

    /**
     * Generate a JSON:API response payload with proper MIME type headers
     *
     * @return Response with updated payload and headers
     */
    private function createResponse(
        Response $res,
        ?array $data,
        array $included = null,
        array $links = null
    ): Response {
        $json = [
            'jsonapi' => ['version' => self::JSONAPI_VERSION],
            'data' => $data
        ];

        if ($included) {
            $json['included'] = $included;
        }

        if ($links) {
            $json['links'] = $links;
        }

        return $res
            ->withJson($json)
            ->withHeader('Content-Type', self::JSONAPI_CONTENT_TYPE);
    }

    /**
     * Transform model(s) into a JSON:API response payload of just resource identifiers
     *
     * @param mixed $resource Either an iterable, single model, or null value to transform.
     *
     * @return array|null
     */
    private function jsonApiResourceIdentifiersTransformer($resource): ?array {
        if ($resource === null) {
            return null;
        }

        if (is_iterable($resource)) {
            $data = [];
            foreach ($resource as $instance) {
                $data[] = $this->jsonApiResourceIdentifiersTransformer($instance);
            }

            return $data;
        }

        return [
            'type' => $resource->getJsonApiType(),
            'id' => $resource->getJsonApiId()
        ];
    }

    /**
     * Transform model(s) into a JSON:API response payload
     *
     * @param mixed $resource       Either an iterable, single model, or null value to transform.
     * @param array $allFieldsets   Hashmap of sparse fieldset requests for all resources
     *                              May include related. An empty array indicates no sparsify.
     * @return array|null
     */
    private function jsonApiTransformer($resource, array $allFieldsets = []): ?array {
        if ($resource === null) {
            return null;
        }

        if (is_iterable($resource)) {
            $data = [];
            foreach ($resource as $instance) {
                $data[] = $this->jsonApiTransformer($instance, $allFieldsets);
            }

            return $data;
        }

        $fieldset = null;
        if (array_key_exists($resource->getJsonApiType(), $allFieldsets)) {
            $fieldset = explode(',', $allFieldsets[$resource->getJsonApiType()]);
        }

        $attributes = $resource->toJsonApiAttributes($fieldset);
        ksort($attributes);

        $relationships = [];
        foreach ($resource->getRelationships() as $name => $relationship) {
            $related = [
                'links' => [
                    'self' => $this->router->pathFor($this->containerName.'-self', [
                        'type' => $resource->getJsonApiType(),
                        'id' => $resource->getJsonApiId(),
                        'relName' => $name
                    ]),
                    'related' => $this->router->pathFor($this->containerName.'-related', [
                        'type' => $resource->getJsonApiType(),
                        'id' => $resource->getJsonApiId(),
                        'relName' => $name
                    ])
                ]
            ];

            $data = $relationship->getData($resource);
            if ($data !== false) {
                $related['data'] = $data;
            }

            $relationships[$name] = $related;
        }

        ksort($relationships);

        $json = [
            'type' => $resource->getJsonApiType(),
            'id' => $resource->getJsonApiId(),
            'attributes' => $attributes
        ];

        if (!empty($relationships)) {
            $json['relationships'] = $relationships;
        }

        // Load any custom links, if specified
        $json['links'] = $resource->getJsonApiLinks();

        // Specify self link for this resource's API
        $json['links']['self'] = $this->router->pathFor($this->containerName.'-get', [
            'type' => $resource->getJsonApiType(),
            'id' => $resource->getJsonApiId()
        ]);

        return $json;
    }

    /**
     * Resolves a type string to an FQN of the model class
     *
     * @param string $type
     *
     * @throws NotFoundException if type does not have a loadable model and query class.
     *
     * @return string
     */
    private function resolveModelFQN(string $type): string
    {
        foreach ($this->namespaces as $namespace) {
            if (class_exists($namespace.$type) &&
                class_exists($namespace.$type.'Query')
            ) {
                return $namespace.$type;
            }
        }

        throw new NotFoundException(sprintf(
            'Cannot resolve model or query for type `%s` in namespaces: %s',
            $type,
            implode(',', $this->namespaces)
        ));
    }

    /**
     * @return iterator|object
     */
    private function queryRelatedResources($resource, Relationship $relationship)
    {
        // TODO: Auth passthrough/cloning so we can carry forward the proper identity/etc
        $viewAuth = new AuthorizationRequest();
        $viewAuth->setActions(AuthorizationRequest::VIEW);

        // NOTE: A query is done here to apply pre/post auth for the entire
        // set of related models to return - not just those added.
        $relatedModelFQN = $this->resolveModelFQN($relationship->getModelName());
        $relatedQueryFQN = $relatedModelFQN.'Query';
        $filterMethod = sprintf('filterBy%sRelationship', $relationship->getFilterName()); // TODO: Error proof

        $query = new $relatedQueryFQN;
        $query->{$filterMethod}($resource->getId());
        $query->filterByAuthorizationRequest($viewAuth);

        $related = $query->findAuthorized();

        if (!$relationship->isToMany()) {
            // TODO: Does isset() work for Propel Collections?
            if (isset($related[0])) {
                $related = $related[0];
            } else {
                $related = null;
            }
        }

        return $related;
    }

    private function extractIdsFromResourceLinkage(?array $data)
    {
        // Case: { "data": null }
        if ($data === null) {
            return [];
        }

        // Case: { "data": { "id": "string", "type": "string" } }
        if (isset($data['id'])) {
            return [$data['id']];
        }

        // Case: { "data": [{ "id": "string", "type": "string" }] }
        $ids = [];
        foreach ($data as $linkage) {
            $ids[] = $linkage['id'];
        }

        return $ids;
    }

    /**
     * Generate a top level `links` object for endpoints under /relationships
     *
     * @param mixed $resource ResourceInterface or Propel ORM model
     * @param string $relName Relationship name on the resource
     *
     * @return array
     */
    private function createRelationshipLinks($resource, Relationship $relationship): array
    {
        $links = [
            'self' => $this->router->pathFor($this->containerName.'-self', [
                'type' => $resource->getJsonApiType(),
                'id' => $resource->getJsonApiId(),
                'relName' => $relationship->getRelatedName()
            ]),
            'related' => $this->router->pathFor($this->containerName.'-related', [
                'type' => $resource->getJsonApiType(),
                'id' => $resource->getJsonApiId(),
                'relName' => $relationship->getRelatedName()
            ])
        ];

        return $links;
    }

    /**
     * Ensure that the given JSON:API body a well-formed relationship mutation request
     *
     * @param Relationship $relationship to be mutated
     *
     * @throws BadRequestException on a malformed body
     */
    private function validateRelationshipsMutationBody(array $body, Relationship $relationship)
    {
        // For a to-one, `data` needs to either be a null or a resource link
        if (!$relationship->isToMany()) {
            if ($body['data'] !== null && !isset($body['data']['id'])) {
                throw new BadRequestException(
                    'Expected single resource link or null in `data`'
                );
            }
        } else {
            // For to-many, `data` is an array with one or more records.
            // If PATCH was supported - could be 0 or more to indicate that they're removing
            // all existing relationships and not replacing them. But it isn't going to be supported (yet?)

            // There's not really a great way to detect sequential arrays in PHP
            // (as far as I can tell - TODO) so we'll just check for 0th index
            if (!isset($body['data'][0]['id']) || !isset($body['data'][0]['type'])) {
                throw new BadRequestException(
                    'Expected an array of at least one resource link in `data`'
                );
            }
        }

        // TODO: Type checking. We need the related model's API type, which
        // isn't currently stored on the Relationship class. Might move type
        // over to a class constant instead so we can access it out of an
        // instance scope.
    }
}
