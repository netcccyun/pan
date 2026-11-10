# CHANGELOG

## 1.7.4 - 2026-08-24

* Add support for PHP 8.6
* Replace the deprecated is_integer() in schema validation for PHP 8.6
* Require `guzzlehttp/guzzle` ^7.15.5 and `guzzlehttp/psr7` ^2.13.1
* Require `guzzlehttp/command` ^1.5.4 and `guzzlehttp/uri-template` ^1.0.11

## 1.7.3 - 2026-07-17

* Require `guzzlehttp/guzzle` ^7.15 and `guzzlehttp/psr7` ^2.13
* Require `guzzlehttp/command` ^1.5.3 and `guzzlehttp/uri-template` ^1.0.10
* Replace deprecated Guzzle JSON utility methods with native JSON functions
* Normalize operation HTTP methods with locale-independent ASCII uppercasing
* Capitalize magic command names with locale-independent ASCII folding

## 1.7.2 - 2026-07-08

* Require `guzzlehttp/guzzle` ^7.13.3 and `guzzlehttp/psr7` ^2.12.4
* Require `guzzlehttp/command` ^1.5.2 and `guzzlehttp/uri-template` ^1.0.9
* Pass explicit trim characters ahead of the PHP 8.6 trim default change

## 1.7.1 - 2026-06-23

* Require `guzzlehttp/guzzle` ^7.12.3 and `guzzlehttp/psr7` ^2.12.3
* Require `guzzlehttp/command` ^1.5.1 and `guzzlehttp/uri-template` ^1.0.8
* Fixed XML response parsing for default-namespace `sentAs` object properties
* Report PCRE errors separately from ordinary schema pattern mismatches

## 1.7.0 - 2026-06-16

* Require `guzzlehttp/guzzle` ^7.12, `guzzlehttp/psr7` ^2.12, and `guzzlehttp/uri-template` ^1.0.7
* Deprecate invalid `GuzzleClient` configuration option values
* Deprecate non-finite float location values; 2.0 rejects them

## 1.6.2 - 2026-06-12

* Fixed non-finite float values emitting coercion warnings on PHP 8.5

## 1.6.1 - 2026-06-04

* Allow deprecated `null` header location values to normalize to empty strings

## 1.6.0 - 2026-06-02

* Require `guzzlehttp/guzzle` ^7.11 and `guzzlehttp/psr7` ^2.11
* Require `guzzlehttp/command` ^1.5 and `guzzlehttp/uri-template` ^1.0.6
* Deprecate the legacy `baseUrl` service description option; use `baseUri` instead
* Deprecate the legacy `responseClass` operation option; use `responseModel` instead
* Deprecate non-uppercase operation `httpMethod` values; 2.0 preserves method casing
* Deprecate non-string and empty-array header location values
* Deprecate loose parameter schema values and reject values that cannot be safely normalized

## 1.5.4 - 2026-06-02

* Prevent XML CDATA injection during request serialization (GHSA-q8r6-5hfw-5jff)

## 1.5.3 - 2026-06-02

* Harden request serialization state after failures
* Preserve explicit `null` JSON response properties
* Validate `ToArrayInterface` object properties after conversion
* Enforce zero-valued schema validation bounds

## 1.5.2 - 2026-05-22

* Fix request serialization for validation-only `additionalParameters`

## 1.5.1 - 2026-05-20

* Replace deprecated Guzzle JSON helper functions
* Serialize and validate header location values

## 1.5.0 - 2026-05-18

* Add PHP 8.5 support
* Require `guzzlehttp/guzzle` ^7.10, `guzzlehttp/psr7` ^2.8, and `guzzlehttp/command` ^1.4
* Fix recursive parameter model inheritance

## 1.4.3 - 2026-05-18

* Handle malformed XML responses safely
* Fix multipart request `Content-Type` header

## 1.4.2 - 2025-02-04

* Add PHP 8.4 support

## 1.4.1 - 2023-12-03

* Add PHP 8.3 support

## 1.4.0 - 2023-05-21

* Add PHP 8.2 support
* Fix PHP 8.1 deprecation notices
* Bump minimum Guzzle dependency versions

## 1.3.2 - 2022-03-03

* Fix `http_build_query` on PHP 8.1
* Bump minimum Guzzle dependency versions

## 1.3.1 - 2021-10-07

* Restore lower minimum dependency versions

## 1.3.0 - 2021-08-14

* Restore PHP 7.2 support
* Add PHP 8.1 support
* Add support for PSR-7 2.x
* Add support for `guzzlehttp/uri-template` 1.x
* Add missing fallback to GET

## 1.2.0 - 2020-11-13

* Update to Guzzle 7
* Raise the minimum PHP version to 7.3
* Add `guzzlehttp/uri-template` dependency

## 1.1.3 - 2017-10-06

* Use wire names when deserializing named JSON arrays
* Improve invalid parameter error messages

## 1.1.2 - 2017-05-19

* Restore default values during validation
* Fix inherited operation parameters

## 1.1.1 - 2017-05-15

* Fix duplicate filter application
* Avoid mutating commands during validation
* Add response model filter support
* Improve JSON array response handling

## 1.1.0 - 2017-01-31

* Add configurable query parameter serialization
* Format values before validation
* Improve nested JSON array handling

## 1.0.1 - 2017-01-13

* Fix query parameter serialization
* Fix multipart request serialization
* Name the validation middleware in the handler stack

## 1.0.0 - 2016-11-24

* Add Guzzle 6 support
* Add PSR-7 request and response support
* Replace event subscribers with middleware-compatible handlers
* Rename `postField` and `postFile` request locations to `formParam` and `multipart`
