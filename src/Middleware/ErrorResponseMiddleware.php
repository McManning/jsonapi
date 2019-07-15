<?php

namespace McManning\JsonApi\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use McManning\JsonApi\Exception\JsonApiException;

/**
 * Middleware to clean up throwables within routes and transform them
 * into proper JSON:API error payloads.
 *
 * If verbose reporting is enabled, each throwable in the stack will be
 * reported on (most recent first) as well as a stack trace for each.
 *
 * Verbose exceptions are **not** recommended for production.
 */
class ErrorResponseMiddleware
{
    /**
     * @var bool
     */
    protected $verbose;

    /**
     * @param bool $verbose Whether to output verbose errors (full stack traces and previous exceptions)
     */
    public function __construct(bool $verbose)
    {
        // TODO: Optional logger dependency to dump errors into a log stream?
        $this->verbose = $verbose;
    }

    /**
     * Middleware invocation
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): ResponseInterface {
        try {
            return $next($request, $response);
        } catch (\Throwable $e) {
            return $this->createErrorResponse($e, $response);
        }
    }

    protected function toJsonApiErrors(\Throwable $e): array
    {
        $errors = [];
        if ($e instanceof JsonApiException) {
            $errors = $e->getJsonApiErrors($this->verbose);
        } else {
            // Convert internal throwable into something we can display
            $error = [
                'status' => 500,
                'title' => get_class($e),
                'detail' => $e->getMessage()
            ];

            if ($this->verbose) {
                $error['meta'] = [
                    'line' => $e->getFile() . '@' . $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ];
            }

            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * Convert a throwable to a PSR-7 Response in JSON:API format
     *
     * @return ResponseInterface
     */
    protected function createErrorResponse(\Throwable $e, ResponseInterface $res): ResponseInterface
    {
        $errors = [];

        // If a JsonApiException is caught, a custom HTTP status code was supplied with it.
        $httpStatusCode = 500;
        if ($e instanceof JsonApiException) {
            $httpStatusCode = $e->getCode();
        }

        // Walk throwables in the stack and report on all of them
        // if in verbose mode. Otherwise, we just report the first one.
        $prev = $e;
        do {
            $errors = array_merge($errors, $this->toJsonApiErrors($prev));
            $prev = $prev->getPrevious();
        } while ($prev && $this->verbose);

        return $res
            ->withStatus($httpStatusCode)
            ->withJson([ 'errors' => $errors ])
            ->withHeader('Content-Type', 'application/vnd.api+json');
    }
}
