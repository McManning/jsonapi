<?php

namespace McManning\JsonApi\Exception;

/**
 * Generic "Unauthorized" (401) exception
 */
class UnauthorizedException extends JsonApiException
{
    /**
     * Create a new exception
     *
     * @param string     $message  Exception message
     * @param \Exception $previous Previous exception in the stack (if applicable)
     */
    public function __construct(
        string $message = '',
        \Exception $previous = null
    ) {
        parent::__construct(401, 'Unauthorized', $message, $previous);
    }
}
