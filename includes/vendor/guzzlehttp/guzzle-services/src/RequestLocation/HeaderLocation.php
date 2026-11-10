<?php

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\NonFiniteFloats;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Request header location
 */
class HeaderLocation extends AbstractLocation
{
    /**
     * Set the name of the location
     *
     * @param string $locationName
     */
    public function __construct($locationName = 'header')
    {
        parent::__construct($locationName);
    }

    /**
     * @return MessageInterface
     */
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ) {
        $value = $command[$param->getName()];
        $prepared = self::prepareHeaderValue($param->filter($value));
        if ($prepared === []) {
            return $request;
        }

        return $request->withHeader($param->getWireName(), $prepared);
    }

    /**
     * @return RequestInterface
     */
    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation
    ) {
        /** @var Parameter $additional */
        $additional = $operation->getAdditionalParameters();
        if ($additional && ($additional->getLocation() === $this->locationName)) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $prepared = self::prepareHeaderValue($additional->filter($value));
                    if ($prepared === []) {
                        continue;
                    }

                    $request = $request->withHeader($key, $prepared);
                }
            }
        }

        return $request;
    }

    /**
     * @param mixed $value
     *
     * @return string|string[]
     */
    private static function prepareHeaderValue($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            \trigger_deprecation(
                'guzzlehttp/guzzle-services',
                '1.6',
                'Passing %s as a header location value is deprecated; guzzlehttp/guzzle-services 2.0 requires string|string[].',
                get_debug_type($value)
            );

            return (string) NonFiniteFloats::normalize($value);
        }

        if (is_array($value)) {
            if ($value === []) {
                \trigger_deprecation(
                    'guzzlehttp/guzzle-services',
                    '1.6',
                    'Passing an empty array as a header location value is deprecated; guzzlehttp/guzzle-services 2.0 requires string or a non-empty array of strings.'
                );

                return $value;
            }

            foreach ($value as $key => $item) {
                if (is_string($item)) {
                    continue;
                }

                if (is_scalar($item) || $item === null) {
                    \trigger_deprecation(
                        'guzzlehttp/guzzle-services',
                        '1.6',
                        'Passing %s inside a header location value array is deprecated; guzzlehttp/guzzle-services 2.0 requires string|string[].',
                        get_debug_type($item)
                    );

                    $value[$key] = (string) NonFiniteFloats::normalize($item);

                    continue;
                }

                throw new \InvalidArgumentException('Header location values must be scalar or an array of scalars.');
            }

            return $value;
        }

        throw new \InvalidArgumentException('Header location values must be scalar or an array of scalars.');
    }
}
