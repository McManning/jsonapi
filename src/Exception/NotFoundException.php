<?php

namespace McManning\JsonApi\Exception;

/**
 * Generic "Not Found" (404) exception
 */
class NotFoundException extends JsonApiException
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
        parent::__construct(404, 'Not Found', $message, $previous);
    }
}
