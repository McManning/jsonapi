<?php

namespace McManning\JsonApi\Exception;

use Slim\Http\Response;

/**
 * Generic JSON:API error event. May contain multiple errors in one exception.
 *
 * Used solely by the JsonApiController
 */
class JsonApiException extends \Exception
{
    /**
     * @var string
     */
    protected $detail;

    /**
     * Create a new exception
     *
     * @param int           $httpStatus Status code for API responses
     * @param string        $title      Short description of the error
     * @param string        $detail     Detailed technical description of the error
     * @param \Throwable    $previous   Previous throwable in the stack (if applicable)
     */
    public function __construct(
        int $httpStatus,
        string $title,
        string $detail = '',
        \Throwable $previous = null
    ) {
        parent::__construct($title, $httpStatus, $previous);
        $this->detail = $detail;
    }

    /**
     * Get a JSON:API error payload for this exception.
     *
     * @param bool $verbose Whether to include full stack traces in the error payloads
     *
     * @return array list of errors
     */
    public function getJsonApiErrors(bool $verbose): array
    {
        $error = [
            'status' => $this->getCode(),
            'title' => $this->getMessage(),
            'detail' => $this->detail
        ];

        if ($verbose) {
            $error['meta'] = [
                'line' => $this->getFile() . '@' . $this->getLine(),
                'trace' => explode("\n", $this->getTraceAsString())
            ];
        }

        return [$error];
    }
}
