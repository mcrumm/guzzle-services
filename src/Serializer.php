<?php
namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFieldLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFileLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;
use Psr\Http\Message\UriInterface;

/**
 * Serializes requests for a given command.
 */
class Serializer
{
    /** @var RequestLocationInterface[] */
    private $locations;

    /** @var DescriptionInterface */
    private $description;

    /**
     * @param DescriptionInterface       $description
     * @param RequestLocationInterface[] $requestLocations Extra request locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $requestLocations = []
    ) {
        static $defaultRequestLocations;
        if (!$defaultRequestLocations) {
            $defaultRequestLocations = [
                'body'      => new BodyLocation('body'),
                'query'     => new QueryLocation('query'),
                'header'    => new HeaderLocation('header'),
                'json'      => new JsonLocation('json'),
                'xml'       => new XmlLocation('xml'),
                'postField' => new PostFieldLocation('postField'),
                'postFile'  => new PostFileLocation('postFile')
            ];
        }

        $this->locations = $requestLocations + $defaultRequestLocations;
        $this->description = $description;
    }

    public function __invoke(CommandInterface $command)
    {
        $request = $this->createRequest($command);
        return $this->prepareRequest($command, $request);
    }

    /**
     * Prepares a request for sending using location visitors
     *
     * @param CommandInterface $command
     * @param RequestInterface $request Request being created
     * @return RequestInterface
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
        if ($additional = $operation->getAdditionalParameters()) {
            $visitedLocations[$additional->getLocation()] = true;
        }

        // Call the after() method for each visited location
        foreach (array_keys($visitedLocations) as $location) {
            $request = $this->locations[$location]->after($command, $request, $param);
        }

        return $request;
    }

    /**
     * Create a request for the command and operation
     *
     * @param CommandInterface $command
     *
     * @return RequestInterface
     * @throws \RuntimeException
     */
    protected function createRequest(CommandInterface $command)
    {
        $operation = $this->description->getOperation($command->getName());

        // If command does not specify a template, assume the client's base URL.
        if (null === ($uri = $operation->getUri())) {
            return new Request(
                $operation->getHttpMethod(),
                $this->description->getBaseUrl()
            );
        }

        return $this->createCommandWithUri($operation, $command);
    }

    /**
     * Create a request for an operation with a uri merged onto a base URI
     * @TODO fix
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
                        $variables[$name] = (string) $variables[$name];
                    }
                }
            }
        }

        // Expand the URI template.
        $uri = \GuzzleHttp\uri_template($operation->getUri(), $variables);

        return new Request(
            $operation->getHttpMethod(),
            $this->combineUris($uri)
        );
    }

    /**
     * @param string $template
     *
     * @return UriInterface
     */
    private function combineUris($template)
    {
        $uri = $this->description->getBaseUrl();

        $template = \GuzzleHttp\Psr7\uri_for($template);

        $parts = $template->getParts();

        if (isset($parts['scheme'])) {
            return clone $template;
        }

        if (isset($parts['query'])) {

        }

        return $uri;
    }
}
