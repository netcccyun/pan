<?php

namespace GuzzleHttp\Command\Guzzle\QuerySerializer;

use GuzzleHttp\Command\Guzzle\NonFiniteFloats;

class Rfc3986Serializer implements QuerySerializerInterface
{
    /**
     * @var bool
     */
    private $removeNumericIndices;

    /**
     * @param bool $removeNumericIndices
     */
    public function __construct($removeNumericIndices = false)
    {
        $this->removeNumericIndices = $removeNumericIndices;
    }

    /**
     * {@inheritDoc}
     */
    public function aggregate(array $queryParams)
    {
        $queryString = http_build_query(NonFiniteFloats::normalizeAll($queryParams, 'a query location value'), '', '&', PHP_QUERY_RFC3986);

        if ($this->removeNumericIndices) {
            $queryString = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $queryString);

            if ($queryString === null) {
                throw new \RuntimeException('Unable to normalize query string: '.preg_last_error_msg());
            }
        }

        return $queryString;
    }
}
