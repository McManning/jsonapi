<?php

namespace McManning\JsonApi\Exception;

/**
 * Generic "Conflict" (409) exception
 */
class ConflictException extends JsonApiException
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
        parent::__construct(409, 'Conflict', $message, $previous);
    }
}
