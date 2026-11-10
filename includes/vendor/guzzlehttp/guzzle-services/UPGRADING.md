Guzzle Services Upgrade Guide
=============================

1.0 from 0.6
------------

Guzzle Services 1.0 added support for Guzzle 6 and PSR-7. Applications that use
only service descriptions should usually need small changes. Applications with
custom request locations, response locations, or subscribers need closer review.

#### Dependencies

Guzzle Services 1.0 added support for Guzzle 6. It requires PHP 5.5 or higher,
`guzzlehttp/guzzle` 6.2 or higher, and `guzzlehttp/command` 1.x.

If your application still uses Guzzle 5, continue using Guzzle Services 0.6.

#### Service Description Base URI

Use `baseUri` instead of `baseUrl` in service descriptions.

```php
// 0.6
$description = new Description([
    'baseUrl' => 'https://api.example.com',
]);

// 1.0
$description = new Description([
    'baseUri' => 'https://api.example.com',
]);
```

#### Request Locations

The `postField` and `postFile` request locations were renamed to `formParam`
and `multipart`.

```php
// 0.6
[
    'parameters' => [
        'name' => [
            'type' => 'string',
            'location' => 'postField',
        ],
        'avatar' => [
            'type' => 'string',
            'location' => 'postFile',
        ],
    ],
]

// 1.0
[
    'parameters' => [
        'name' => [
            'type' => 'string',
            'location' => 'formParam',
        ],
        'avatar' => [
            'type' => 'string',
            'location' => 'multipart',
        ],
    ],
]
```

#### Custom Locations and Subscribers

Guzzle Services 1.0 serializes PSR-7 requests for Guzzle 6. Custom request or
response location implementations should use PSR-7 message interfaces and
return the modified message instead of mutating the message in place.

The old subscriber-based response processing and input validation hooks were
replaced by Guzzle Command handlers. Move custom validation or processing logic
to command handlers when upgrading.
