# Guzzle Commands

This library uses Guzzle and provides the foundations to create fully-featured
web service clients by abstracting Guzzle HTTP *requests* and *responses* into
higher-level *commands* and *results*. A *middleware* system, analogous to, but
separate from, the one in the HTTP layer may be used to customize client
behavior when preparing commands into requests and processing responses into
results.

### Commands

Key-value pair objects representing an operation of a web service. Commands
have a name and a set of parameters.

### Results

Key-value pair objects representing the processed result of executing an
operation of a web service.

## Installing

This project can be installed using [Composer](https://getcomposer.org/):

```
composer require guzzlehttp/command
```

## Service Clients

Service Clients are web service clients that implement the
`GuzzleHttp\Command\ServiceClientInterface` and use an underlying Guzzle HTTP
client (`GuzzleHttp\ClientInterface`) to communicate with the service. Service
clients create and execute *commands* (`GuzzleHttp\Command\CommandInterface`),
which encapsulate operations within the web service, including the operation
name and parameters. This library provides a generic implementation of a service
client: the `GuzzleHttp\Command\ServiceClient` class.

## Instantiating a Service Client

The provided service client implementation (`GuzzleHttp\Command\ServiceClient`)
can be instantiated by providing the following arguments:

1. A fully-configured Guzzle HTTP client that will be used to perform the
   underlying HTTP requests. That is, an instance of an object implementing
   `GuzzleHttp\ClientInterface` such as `new GuzzleHttp\Client()`.
1. A callable that transforms a Command into a Request. The function should
   accept a `GuzzleHttp\Command\CommandInterface` object and return a
   `Psr\Http\Message\RequestInterface` object.
1. A callable that transforms a Response into a Result. The function should
   accept a `Psr\Http\Message\ResponseInterface` object and optionally a
   `Psr\Http\Message\RequestInterface` object, and return a
   `GuzzleHttp\Command\ResultInterface` object.
1. Optionally, a Guzzle HandlerStack (`GuzzleHttp\HandlerStack`), which can be
   used to add command-level middleware to the service client.

Below is an example configured to send and receive JSON payloads:

```php
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\UriTemplate\UriTemplate;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$client = new ServiceClient(
    new HttpClient(['base_uri' => 'https://api.example.com']),
    function (CommandInterface $command): RequestInterface {
        return new Request(
            'POST',
            UriTemplate::expand('/{command}', ['command' => $command->getName()]),
            ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::jsonEncode($command->toArray())
        );
    },
    function (
        ResponseInterface $response,
        RequestInterface $request,
        CommandInterface $command
    ): ResultInterface {
        return new Result(
            Utils::jsonDecode((string) $response->getBody(), true)
        );
    }
);
```

## Executing Commands

Service clients create command objects using the ``getCommand()`` method.

```php
$commandName = 'foo';
$arguments = ['baz' => 'bar'];
$command = $client->getCommand($commandName, $arguments);
```

After creating a command, you may execute the command using the `execute()`
method of the client.

```php
$result = $client->execute($command);
```

The result of executing a command will be an instance of an object implementing
`GuzzleHttp\Command\ResultInterface`. Result objects are `ArrayAccess`-ible and
contain the data parsed from HTTP response.

Service clients have magic methods that act as shortcuts to executing commands
by name without having to create the ``Command`` object in a separate step
before executing it.

```php
$result = $client->foo(['baz' => 'bar']);
```

### Per-command HTTP options

`GuzzleHttp\Command\ServiceClient` reserves the `@http` command parameter for
per-command Guzzle request options. When a command is executed, the service
client reads `$command['@http']`, removes it from the command, transforms the
remaining command data into a PSR-7 request, and passes the `@http` array to the
underlying Guzzle HTTP client.

This is intended for trusted application code that needs to adjust transport
behavior for a single command, such as setting a shorter timeout. Treat `@http`
as a reserved control key, not as an operation parameter. Do not pass untrusted
input directly into command arguments without filtering it first. If external
input can include `@http`, that input may be able to influence the underlying
HTTP request or transfer depending on the configured Guzzle client and handler.
The `@http` value must be an array of Guzzle request options. Be especially
careful with options that affect the target URI, proxy, TLS verification,
headers, body, response sink, redirects, or timeouts.

Build command arguments from an allowlist of expected operation parameters, or
explicitly reject reserved keys such as `@http` before creating commands:

```php
if (array_key_exists('@http', $input)) {
    throw new InvalidArgumentException('"@http" is reserved.');
}

$command = $client->getCommand('foo', [
    'baz' => (string) $input['baz'],
]);
```

When setting per-command HTTP options intentionally, only expose and validate the
specific options your application needs:

```php
use GuzzleHttp\RequestOptions;

$command = $client->getCommand('foo', [
    'baz' => 'bar',
    '@http' => [
        RequestOptions::CONNECT_TIMEOUT => 1.0,
        RequestOptions::TIMEOUT => 2.0,
    ],
]);

$result = $client->execute($command);
```

Because `@http` is removed during execution, create a new command if you need to
execute the same operation again with the same per-command HTTP options.

## Asynchronous Commands

Commands can be executed asynchronously using `executeAsync()`. This method
returns a `GuzzleHttp\Promise\PromiseInterface` that resolves to a
`GuzzleHttp\Command\ResultInterface`.

```php
use GuzzleHttp\Command\ResultInterface;

// Create and execute an asynchronous command.
$command = $client->getCommand('foo', ['baz' => 'bar']);
$promise = $client->executeAsync($command);

$promise->then(function (ResultInterface $result) {
    echo $result['fizz']; //> 'buzz'
})->wait();
```

Synchronous execution is equivalent to waiting on the asynchronous operation:

```php
$result = $promise->wait();

echo $result['fizz']; //> 'buzz'
```

Magic methods may also be used asynchronously by appending `Async` to the
operation name. For example, `fooAsync()` creates a `foo` command and executes it
asynchronously:

```php
$promise = $client->fooAsync(['baz' => 'bar']);
$result = $promise->wait();
```

If execution fails, the promise is rejected with a
`GuzzleHttp\Command\Exception\CommandException`. When HTTP errors are enabled,
4xx and 5xx responses are represented by `CommandClientException` and
`CommandServerException`, respectively, when the underlying Guzzle exception
contains a response.

## Concurrent Requests

Use `executeAll()` or `executeAllAsync()` to execute multiple commands with a
fixed concurrency limit. Both methods accept an array or iterator that yields
`CommandInterface` objects.

`executeAll()` waits for the pool to finish and returns an array keyed like the
input commands. Successful entries contain results. Failed entries contain the
rejection reason, typically a `CommandException`.

```php
use GuzzleHttp\Command\ResultInterface;

$commands = [
    'first' => $client->getCommand('foo', ['baz' => 'bar']),
    'second' => $client->getCommand('foo', ['baz' => 'qux']),
];

$results = $client->executeAll($commands, [
    'concurrency' => 10,
    'fulfilled' => function (ResultInterface $result, $key) {
        // Called when one command succeeds.
    },
    'rejected' => function ($reason, $key) {
        // Called when one command fails.
    },
]);
```

`executeAllAsync()` returns a promise for the command pool instead of waiting for
it immediately. The same options are supported:

```php
$promise = $client->executeAllAsync($commands, [
    'concurrency' => 10,
]);

$promise->wait();
```

The supported options are:

* `concurrency`: Maximum number of commands to execute at the same time. The
  default is `25`.
* `fulfilled`: Callable invoked as `fulfilled($result, $key)` when an individual
  command succeeds.
* `rejected`: Callable invoked as `rejected($reason, $key)` when an individual
  command fails.

Choose a concurrency value that is appropriate for the remote service and your
application. Very large command lists should generally be streamed with an
iterator rather than built eagerly as a large array.

## Middleware: Extending the Client

Middleware can be added to the service client or underlying HTTP client to
implement additional behavior and customize the ``Command``-to-``Result`` and
``Request``-to-``Response`` lifecycles, respectively.

Command middleware is added to the service client's handler stack and wraps
commands before they are transformed into HTTP requests. HTTP middleware should
be configured on the underlying Guzzle HTTP client instead.

```php
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\RequestOptions;

$client->getHandlerStack()->push(function (callable $handler) {
    return function (CommandInterface $command) use ($handler) {
        $http = $command['@http'] ?: [];
        $http[RequestOptions::TIMEOUT] = 2.0;
        $command['@http'] = $http;

        return $handler($command);
    };
});
```

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/command/security/policy) for more information.

## License

Guzzle is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-command?utm_source=packagist-guzzlehttp-command&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
