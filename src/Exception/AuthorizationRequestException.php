<?php

namespace McManning\JsonApi\Exception;

use McManning\JsonApi\AuthorizationRequest;

/**
 * Exception to indicate that an authorization request has failed
 */
class AuthorizationRequestException extends JsonApiException
{
    /**
     * Create a new exception
     *
     * @param string     $message  Exception message
     * @param \Throwable $previous Previous exception in the stack (if applicable)
     */
    public function __construct(
        AuthorizationRequest $req,
        string $message = '',
        \Throwable $previous = null
    ) {
        parent::__construct(401, $message, (string)$req, $previous);
    }
}
