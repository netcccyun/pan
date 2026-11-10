<?php

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\FormParamLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\MultiPartLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\UriTemplate\UriTemplate;
use Psr\Http\Message\RequestInterface;

/**
 * Serializes requests for a given command.
 */
class Serializer
{
    /** @var RequestLocationInterface[] */
    private $locations;

    /** @var RequestLocationInterface[] */
    private $customRequestLocations;

    /** @var DescriptionInterface */
    private $description;

    /**
     * @param RequestLocationInterface[] $requestLocations Extra request locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $requestLocations = []
    ) {
        $this->customRequestLocations = $requestLocations;
        $this->resetDefaultRequestLocations();
        $this->description = $description;
    }

    /**
     * @return RequestInterface
     */
    public function __invoke(CommandInterface $command)
    {
        $request = $this->createRequest($command);

        try {
            return $this->prepareRequest($command, $request);
        } catch (\Throwable $e) {
            $this->resetDefaultRequestLocations();

            throw $e;
        }
    }

    private function resetDefaultRequestLocations()
    {
        $this->locations = $this->customRequestLocations + [
            'body' => new BodyLocation(),
            'query' => new QueryLocation(),
            'header' => new HeaderLocation(),
            'json' => new JsonLocation(),
            'xml' => new XmlLocation(),
            'formParam' => new FormParamLocation(),
            'multipart' => new MultiPartLocation(),
        ];
    }

    /**
     * Prepares a request for sending using location visitors
     *
     * @param RequestInterface $request Request being created
     *
     * @return RequestInterface
     *
     * @throws \RuntimeException If a location cannot be handled
     */
    protected function prepareRequest(
        CommandInterface $command,
        RequestInterface $request
    ) {
        $visitedLocations = [];
        $operation = $this->description->getOperation($command->getName());

        // Visit each actual parameter
        foreach ($operation->getParams() as $name => $param) {
            /* @var Parameter $param */
            $location = $param->getLocation();
            // Skip parameters that have not been set or are URI location
            if ($location == 'uri' || !$command->hasParam($name)) {
                continue;
            }
            if (!isset($this->locations[$location])) {
                throw new \RuntimeException("No location registered for $name");
            }
            $visitedLocations[$location] = true;
            $request = $this->locations[$location]->visit($command, $request, $param);
        }

        // Ensure that the after() method is invoked for additionalParameters
        /** @var Parameter $additional */
        if ($additional = $operation->getAdditionalParameters()) {
            if ($location = $additional->getLocation()) {
                if (!isset($this->locations[$location])) {
                    throw new \RuntimeException('No location registered for additionalParameters');
                }
                $visitedLocations[$location] = true;
            }
        }

        // Call the after() method for each visited location
        foreach (array_keys($visitedLocations) as $location) {
            $request = $this->locations[$location]->after($command, $request, $operation);
        }

        return $request;
    }

    /**
     * Create a request for the command and operation
     *
     * @return RequestInterface
     *
     * @throws \RuntimeException
     */
    protected function createRequest(CommandInterface $command)
    {
        $operation = $this->description->getOperation($command->getName());

        // If command does not specify a template, assume the client's base URL.
        if (null === $operation->getUri()) {
            /** @var mixed $method */
            $method = $operation->getHttpMethod() ?: 'GET';
            if (is_string($method)) {
                $normalizedMethod = Utils::asciiToUpper($method);
                if ($method !== $normalizedMethod) {
                    \trigger_deprecation(
                        'guzzlehttp/guzzle-services',
                        '1.6',
                        'Passing a non-uppercase operation "httpMethod" value to Serializer::createRequest() is deprecated; guzzlehttp/guzzle-services 2.0 will preserve HTTP method casing. Pass an uppercase method explicitly if uppercase is required.'
                    );
                    $method = $normalizedMethod;
                }
            }

            return new Request(
                $method,
                $this->description->getBaseUri()
            );
        }

        return $this->createCommandWithUri($operation, $command);
    }

    /**
     * Create a request for an operation with a uri merged onto a base URI
     *
     * @return Request
     */
    private function createCommandWithUri(
        Operation $operation,
        CommandInterface $command
    ) {
        // Get the path values and use the client config settings
        $variables = [];
        foreach ($operation->getParams() as $name => $arg) {
            /* @var Parameter $arg */
            if ($arg->getLocation() == 'uri') {
                if (isset($command[$name])) {
                    $variables[$name] = $arg->filter($command[$name]);
                    if (!is_array($variables[$name])) {
                        $variables[$name] = (string) NonFiniteFloats::normalize($variables[$name], 'a uri location value');
                    }
                }
            }
        }

        // Expand the URI template.
        $uri = new Uri(UriTemplate::expand($operation->getUri(), $variables));
        /** @var mixed $method */
        $method = $operation->getHttpMethod() ?: 'GET';
        if (is_string($method)) {
            $normalizedMethod = Utils::asciiToUpper($method);
            if ($method !== $normalizedMethod) {
                \trigger_deprecation(
                    'guzzlehttp/guzzle-services',
                    '1.6',
                    'Passing a non-uppercase operation "httpMethod" value to Serializer::createCommandWithUri() is deprecated; guzzlehttp/guzzle-services 2.0 will preserve HTTP method casing. Pass an uppercase method explicitly if uppercase is required.'
                );
                $method = $normalizedMethod;
            }
        }

        return new Request(
            $method,
            UriResolver::resolve($this->description->getBaseUri(), $uri)
        );
    }
}
