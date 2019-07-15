<?php

namespace McManning\JsonApi;

use McManning\JsonApi\Exception\AuthorizationRequestException;
use McManning\JsonApi\Interfaces\ResourceInterface;

/**
 * Temporary trait to apply generic AuthorizationRequest filtering/finding
 * support onto Propel Query models - until it's baked into the behavior.
 */
trait AuthorizationRequestTrait
{
    /**
     * @var bool
     */
    protected $authRequest;

    public function filterByAuthorizationRequest(AuthorizationRequest $req): self
    {
        $this->authRequest = $req;
        return $this;
    }

    /**
     * @param string[] $expectedIds List of resource IDs that *must* come back,
     *                              otherwise the request is considered an authorization
     *                              failure (as certain expected results did not pass
     *                              the pre-query authorization filters). If not supplied,
     *                              IDs will not be checked and only post-query authorization
     *                              tests will be used to determine failures for each resource
     *
     * @throws AuthorizationRequestException if there is a mismatch between
     *                                       expected IDs and find results
     */
    public function findAuthorized(array $expectedIds = null): iterable
    {
        $results = $this->find();

        foreach ($results as $result) {
            // Execute post-query auth per returned instance
            $result->testAuthorizationRequest($this->authRequest);

            if ($expectedIds !== null) {
                $idx = array_search($result->getJsonApiId(), $expectedIds);
                if ($idx !== false) {
                    unset($expectedIds[$idx]);
                }
            }
        }

        if ($expectedIds !== null && !empty($expectedIds)) {
            throw new AuthorizationRequestException(
                $this->authRequest,
                sprintf(
                    'Unauthorized to access IDs `%s`',
                    implode('`, `', $expectedIds)
                )
            );
        }

        // TODO: Deal with extra results

        return $results;
    }

    public function findOneAuthorized(string $expectedId): ?ResourceInterface
    {
        $results = $this->findAuthorized([$expectedId]);
        if (empty($results)) {
            return null;
        }

        return $results[0];
    }
}
