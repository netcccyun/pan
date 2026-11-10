<?php

namespace GuzzleHttp\Command\Guzzle;

/**
 * Converts non-finite floats to the strings PHP coerces them to, as implicit
 * coercion of NAN emits a warning on PHP 8.5.
 *
 * @internal
 */
final class NonFiniteFloats
{
    private function __construct()
    {
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function normalize($value, ?string $context = null)
    {
        if (is_float($value) && !is_finite($value)) {
            if ($context !== null) {
                \trigger_deprecation(
                    'guzzlehttp/guzzle-services',
                    '1.7',
                    'Passing a non-finite float as %s is deprecated; guzzlehttp/guzzle-services 2.0 rejects non-finite floats.',
                    $context
                );
            }

            return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
        }

        return $value;
    }

    /**
     * @return array
     */
    public static function normalizeAll(array $values, ?string $context = null)
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::normalizeAll($value, $context);
            } else {
                $values[$key] = self::normalize($value, $context);
            }
        }

        return $values;
    }
}
