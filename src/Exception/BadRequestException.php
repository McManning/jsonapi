<?php

namespace McManning\JsonApi\Exception;

/**
 * Generic "Bad Request" (400) exception
 */
class BadRequestException extends JsonApiException
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
        parent::__construct(400, 'Bad Request', $message, $previous);
    }
}
