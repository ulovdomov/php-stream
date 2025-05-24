# Php Stream

This package provides a PSR-7-compatible `StreamInterface` implementation,
along with an extended `UlovDomov\Stream\FileStream` class that adds more.

## Installation

Run:

```shell
composer require ulovdomov/php-stream
```

## Usage

### Create `UlovDomov\Stream\FileStream`

You can create stream from:
- `resource`
- `string`
- `int`
- `float`
- `bool`
- `\Psr\Http\Message\StreamInterface`
- `callable`
- `\Iterator`
- `null`

```php
$stream = \UlovDomov\Stream\FileStream::create('my stream content');
```

For `file path`:

```php
// stream for reading
$stream = \UlovDomov\Stream\FileStream::createForPath(__DIR__ . '/file.pdf');

// stream for writing
$stream = \UlovDomov\Stream\FileStream::createForPath(__DIR__ . '/file.pdf', 'w');
```

## Additional features

```php
/** @var UlovDomov\Stream\FileStream $stream */

// get mime type
$stream->getMimeType(); // string: text/plain

// save content as file
$stream->saveAs(__DIR__ . '/my-new-file.jpg');
```

## Utils

### Method `tryFopen`

Replacement for `fopen`, it returns a resource; throws a `StreamException` on error.

```php
/** @var resource $file */
$file = \UlovDomov\Stream\Utils::tryFopen(__DIR__ . '/my-file.pdf');
```

## Development

### First setup

1. Run for initialization
```shell
make init
```
2. Run composer install
```shell
make composer
```

Use tasks in Makefile:

- To log into container
```shell
make docker
```
- To run code sniffer fix
```shell
make cs-fix
```
- To run PhpStan
```shell
make phpstan
```
- To run tests
```shell
make phpunit
```