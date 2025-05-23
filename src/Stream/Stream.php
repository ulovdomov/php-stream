<?php declare(strict_types = 1);

namespace UlovDomov\Stream;

use Psr\Http\Message\StreamInterface;
use UlovDomov\Stream\Exception\StreamException;

/**
 * PHP stream implementation.
 */
class Stream implements StreamInterface
{
    /**
     * @see https://www.php.net/manual/en/function.fopen.php
     * @see https://www.php.net/manual/en/function.gzopen.php
     */
    private const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';
    private const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    private int|null $size = null;

    private bool $seekable;

    private bool $readable;

    private bool $writable;

    private string|null $uri = null;

    /**
     * @var array<mixed>
     */
    private array $customMetadata;

    /**
     * This constructor accepts an associative array of options.
     *
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknowledge, then you can
     *   provide that size, in bytes.
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     *
     * @param resource                            $stream  Stream resource to wrap.
     * @param array{size?: int, metadata?: array<mixed>} $options Associative array of options.
     *
     * @throws StreamException if the stream is not a stream resource
     */
    public function __construct(private $stream, array $options = [])
    {
        if (!\is_resource($stream)) {
            throw new StreamException('Stream must be a resource');
        }

        if (isset($options['size'])) {
            $this->size = $options['size'];
        }

        $this->customMetadata = $options['metadata'] ?? [];
        $meta = \stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = (bool) \preg_match(self::READABLE_MODES, $meta['mode']);
        $this->writable = (bool) \preg_match(self::WRITABLE_MODES, $meta['mode']);
        $this->uri = $this->getMetadata('uri');
    }

    /**
     * Create a new stream based on the input type.
     *
     * Options is an associative array that can contain the following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * This method accepts the following `$resource` types:
     * - `Psr\Http\Message\StreamInterface`: Returns the value as-is.
     * - `string`: Creates a stream object that uses the given string as the contents.
     * - `resource`: Creates a stream object that wraps the given PHP stream resource.
     * - `Iterator`: If the provided value implements `Iterator`, then a read-only
     *   stream object will be created that wraps the given iterable. Each time the
     *   stream is read from, data from the iterator will fill a buffer and will be
     *   continuously called until the buffer is equal to the requested read size.
     *   Subsequent read calls will first read from the buffer and then call `next`
     *   on the underlying iterator until it is exhausted.
     * - `object` with `__toString()`: If the object has the `__toString()` method,
     *   the object will be cast to a string and then a stream will be returned that
     *   uses the string value.
     * - `NULL`: When `null` is passed, an empty stream object is returned.
     * - `callable` When a callable is passed, a read-only stream object will be
     *   created that invokes the given callable. The callable is invoked with the
     *   number of suggested bytes to read. The callable can return any number of
     *   bytes, but MUST return `false` when there is no more data to return. The
     *   stream object that wraps the callable will invoke the callable until the
     *   number of requested bytes are available. Any additional bytes will be
     *   buffered and used in subsequent reads.
     *
     * @param resource|string|int|float|bool|StreamInterface|callable|\Iterator|null $resource Entity body data
     * @param array{size?: int, metadata?: array}                                    $options  Additional options
     *
     * @throws StreamException if the $resource arg is not valid.
     */
    public static function create($resource = '', array $options = []): StreamInterface
    {
        if (\is_scalar($resource)) {
            $stream = Utils::tryFopen('php://temp', 'r+');

            if ($resource !== '') {
                \fwrite($stream, (string) $resource);
                \fseek($stream, 0);
            }

            return new self($stream, $options);
        }

        switch (\gettype($resource)) {
            case 'resource':
                /*
                 * The 'php://input' is a special stream with quirks and inconsistencies.
                 * We avoid using that stream by reading it into php://temp
                 */

                if ((\stream_get_meta_data($resource)['uri'] ?? '') === 'php://input') {
                    $stream = Utils::tryFopen('php://temp', 'w+');
                    \stream_copy_to_stream($resource, $stream);
                    \fseek($stream, 0);
                    $resource = $stream;
                }

                return new Stream($resource, $options);
            case 'object':
                if ($resource instanceof StreamInterface) {
                    return $resource;
                } elseif ($resource instanceof \Iterator) {
                    return new PumpStream(static function () use ($resource) {
                        if (!$resource->valid()) {
                            return false;
                        }

                        $result = $resource->current();
                        $resource->next();

                        return $result;
                    }, $options);
                } elseif (\method_exists($resource, '__toString')) {
                    return self::create((string) $resource, $options);
                }

                break;
            case 'NULL':
                return new Stream(Utils::tryFopen('php://temp', 'r+'), $options);
        }

        if (\is_callable($resource)) {
            return new PumpStream($resource, $options);
        }

        throw new StreamException('Invalid resource type: '.\gettype($resource));
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }

            return $this->getContents();
        } catch (\Throwable $e) {
            if (\PHP_VERSION_ID >= 70400) {
                throw $e;
            }

            \trigger_error(\sprintf('%s::__toString exception: %s', self::class, (string) $e), \E_USER_ERROR);

            return '';
        }
    }

    /**
     * @throws StreamException
     */
    public function getContents(): string
    {
        if (!isset($this->stream)) {
            throw new StreamException('Stream is detached');
        }

        if (!$this->readable) {
            throw new StreamException('Cannot read from non-readable stream');
        }

        return self::tryGetContents($this->stream);
    }

    public function close(): void
    {
        if (isset($this->stream)) {
            if (\is_resource($this->stream)) {
                \fclose($this->stream);
            }

            $this->detach();
        }
    }

    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        unset($this->stream);
        $this->size = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    public function getSize(): int|null
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            \clearstatcache(true, $this->uri);
        }

        $stats = \fstat($this->stream);

        if (\is_array($stats)) {
            $this->size = $stats['size'];

            return $this->size;
        }

        return null;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function eof(): bool
    {
        if (!isset($this->stream)) {
            throw new StreamException('Stream is detached');
        }

        return \feof($this->stream);
    }

    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new StreamException('Stream is detached');
        }

        $result = \ftell($this->stream);

        if ($result === false) {
            throw new StreamException('Unable to determine stream position');
        }

        return $result;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek($offset, int $whence = \SEEK_SET): void
    {
        if (!isset($this->stream)) {
            throw new StreamException('Stream is detached');
        }

        if (!$this->seekable) {
            throw new StreamException('Stream is not seekable');
        }

        if (\fseek($this->stream, $offset, $whence) === -1) {
            throw new StreamException('Unable to seek to stream position '
                . $offset . ' with whence ' . \var_export($whence, true));
        }
    }

    public function read($length): string
    {
        if (!isset($this->stream)) {
            throw new StreamException('Stream is detached');
        }

        if (!$this->readable) {
            throw new StreamException('Cannot read from non-readable stream');
        }

        if ($length < 0) {
            throw new StreamException('Length parameter cannot be negative');
        }

        if ($length === 0) {
            return '';
        }

        try {
            $string = \fread($this->stream, $length);
        } catch (\Throwable $e) {
            throw new StreamException('Unable to read from stream', 0, $e);
        }

        if ($string === false) {
            throw new StreamException('Unable to read from stream');
        }

        return $string;
    }

    public function write($string): int
    {
        if (!isset($this->stream)) {
            throw new StreamException('Stream is detached');
        }

        if (!$this->writable) {
            throw new StreamException('Cannot write to a non-writable stream');
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result = \fwrite($this->stream, $string);

        if ($result === false) {
            throw new StreamException('Unable to write to stream');
        }

        return $result;
    }

    public function getMetadata($key = null): mixed
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        } elseif (!$key) {
            return $this->customMetadata + \stream_get_meta_data($this->stream);
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }

        $meta = \stream_get_meta_data($this->stream);

        return $meta[$key] ?? null;
    }

    /**
     * @param resource $stream
     *
     * @throws StreamException
     */
    public static function tryGetContents($stream): string
    {
        $ex = null;
        \set_error_handler(static function (int $errno, string $errstr) use (&$ex): bool {
            $ex = new StreamException(\sprintf(
                'Unable to read stream contents: %s',
                $errstr,
            ));

            return true;
        });

        try {
            /** @var string|false $contents */
            $contents = \stream_get_contents($stream);

            if ($contents === false) {
                $ex = new StreamException('Unable to read stream contents');
            }
        } catch (\Throwable $e) {
            $ex = new StreamException(\sprintf(
                'Unable to read stream contents: %s',
                $e->getMessage(),
            ), 0, $e);
        }

        \restore_error_handler();

        if ($ex) {
            throw $ex;
        }

        return $contents;
    }
}
