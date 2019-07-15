<?php

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

use Slim\Http\Environment;
use Slim\Http\Uri;
use Slim\Http\Headers;
use Slim\Http\RequestBody;
use Slim\Http\Request;
use Slim\Http\Response;

use McManning\JsonApi\Slim\JsonApiController;

/**
 * Runs the Slim Framework in a mock environment for testing.
 *
 * Uses `bookstore.schema.xml` for models exposed to the API
 */
abstract class SlimTestCase extends BookstoreTestCase
{
    /**
     * Maximum bytes to display for the body payload within assertion messages.
     *
     * @var int
     */
    const BODY_ASSERTION_SIZE = 1024;

    /** @var \Slim\Http\Request */
    protected $request;

    /** @var \Slim\Http\Response */
    protected $response;

    /** @var string */
    protected $stdout;

    public function setUp()
    {
        parent::setUp();

        $container = new \Slim\Container();
        $container['Api'] = function () {
            return new JsonApiController('Api', ['\\']);
        };

        $app = new \Slim\App($container);

        // Endpoint for SlimTestCaseTest
        $app->map(['get', 'post', 'patch', 'delete'], '/test', function ($req, $res) {
            return $res->withJson([
                'method' => strtolower($req->getMethod()),
                'query' => $req->getQueryParams(),
                'body' => $req->getParsedBody()
            ]);
        });

        // Endpoint group for SlimController tests
        $app->group('/api', function () {
            $this->getContainer()->get('Api')->registerEndpoints($this);
        });

        $this->app = $app;
    }

    /**
     * Wrapper for performing HTTP requests against the Slim application.
     *
     * @param string $method
     * @param string $path
     * @param string[] $query Hash map of keys to values
     * @param array $body JSON payload to be serialized
     * @param
     *
     * @return stdout from the request. Use $this->response for actual payload.
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        array $body = null,
        array $additionalHeaders = []
    ) {
        $envHeaders = [
            'REQUEST_URI' => $path,
            'REQUEST_METHOD' => strtolower($method),
            'QUERY_STRING' => http_build_query($query)
        ];

        // Prepare request and response objects
        $env = Environment::mock($envHeaders);

        $uri = Uri::createFromEnvironment($env);
        $requestHeaders = Headers::createFromEnvironment($env);

        foreach ($additionalHeaders as $k => $v) {
            $requestHeaders->set($k, $v);
        }

        $requestBody = new RequestBody();
        if ($body !== null) {
            $requestBody->write(json_encode($body));
            $requestHeaders->set('Content-Type', 'application/json');
        }

        $cookies = [];
        $serverParams = $env->all();

        $req = new Request(
            $method,
            $uri,
            $requestHeaders,
            $cookies,
            $serverParams,
            $requestBody
        );

        $res = new Response();

        // Setup underlying application

        // Invoke app. We buffer stdout here to ensure it can
        // be returned by this method. But we also catch and
        // rethrow exceptions *outside* buffering due to
        // a bug in PHPUnit (https://github.com/sebastianbergmann/phpunit/issues/1832)
        try {
            $this->request = $req;

            ob_start();
            $this->app->getContainer()['request'] = $req;
            $this->response = $this->app->run(true);
            $this->stdout = ob_get_clean();
        } catch (\Exception $e) {
            $this->stdout = ob_get_clean();
            throw $e;
        }

        // Assume a JSON body has been returned that can be parsed
        return json_decode($this->response->getBody(), true);
    }

    /**
     * Custom assertion that checks the status code of `$this->response`.
     *
     * If the status code does not match the expected, the response
     * body is dumped as part of the assertion error message for debugging.
     *
     * @param int $expected status code (e.g. 200)
     */
    protected function assertStatusCode(int $expected)
    {
        $actual = $this->response->getStatusCode();
        return $this->assertEquals($expected, $actual,
            "Unexpected status code. Expected $expected but got $actual. Body: \n" .
            $this->getBodySummary()
        );
    }

    /**
     * Get a byte-reduced version of the response body for test output
     *
     * @return string
     */
    protected function getBodySummary(): string
    {
        $body = (string)$this->response->getBody();
        return $this->trimString($body);
    }

    /**
     * Trim down a long string into a summarized version
     *
     * @param string $value to trim
     *
     * @return string
     */
    protected function trimString(string $value): string
    {
        $len = strlen($value);
        if ($len < self::BODY_ASSERTION_SIZE) {
            return $value;
        }

        $half = self::BODY_ASSERTION_SIZE / 2;
        $omitted = $len - $half * 2;
        return substr($value, 0, $half) .
            "\n\033[1;30m ... $omitted bytes omitted ... \033[0m\n" .
            substr($value, $len - $half);
    }
}
