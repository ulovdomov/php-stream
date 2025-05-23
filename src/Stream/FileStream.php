<?php declare(strict_types = 1);

namespace UlovDomov\Stream;

use Psr\Http\Message\StreamInterface;
use Stringable;
use UlovDomov\Stream\Exception\StreamException;

class FileStream implements StreamInterface, Stringable
{
    public function __construct(
        private StreamInterface $stream,
        private readonly string|null $mimeType = null,
    ) {
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
     * @param array{size?: int, metadata?: array<mixed>} $options  Additional options
     *
     * @throws StreamException if the $resource arg is not valid.
     */
    public static function create($resource = '', array $options = []): FileStream
    {
        if ($resource instanceof self) {
            return $resource;
        }
        return new self(Stream::create($resource, $options));
    }

    /**
     * @param array{size?: int, metadata?: array<mixed>} $options  Additional options
     * @throws StreamException if the $resource arg is not valid.
     */
    public static function createForPath(string $path, string $mode, array $options = []): StreamInterface
    {
        return self::create(Utils::tryFopen($path, $mode), $options);
    }

    /**
     * @throws StreamException
     */
    public function saveAs(string $path): void
    {
        $file = Utils::tryFopen($path, 'w+');

        $resource = null;
        try {
            $this->rewind();

            $resource = $this->detach();

            if ($resource === null) {
                throw new StreamException('Can not detach stream from body');
            }

            $copied = \stream_copy_to_stream($resource, $file);

            if ($copied === false) {
                throw new StreamException(\sprintf('Can not create %s file', $path));
            }
        } finally {
            \fclose($file);

            if ($resource !== null) {
                $this->stream = Stream::create($resource);
            }
        }
    }

    /**
     * @throws StreamException
     */
    public function getMimeType(): string
    {
        if ($this->mimeType !== null && $this->mimeType !== 'application/octet-stream') {
            return $this->mimeType;
        }

        $uri = $this->getMetadata('uri');

        if (\is_string($uri) && \file_exists($uri)) {
            $type = (new \finfo(\FILEINFO_MIME_TYPE))->file($uri);

            if ($type !== false) {
                return $type;
            }
        }

        $pos = $this->tell();
        $this->rewind();
        $buffer = $this->read(4096);
        $this->seek($pos);

        $type = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($buffer);

        if ($type !== false) {
            return $type;
        }

        throw new StreamException('Unable to determine MIME type');
    }

    public function __toString(): string
    {
        return $this->stream->__toString();
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function detach()
    {
        return $this->stream->detach();
    }

    public function getSize(): int|null
    {
        return $this->stream->getSize();
    }

    /**
     * @throws StreamException
     */
    public function tell(): int
    {
        try {
            return $this->stream->tell();
        } catch (\Throwable $e) {
            throw new StreamException('Unable to determine stream position', 0, $e);
        }
    }

    public function eof(): bool
    {
        return $this->stream->eof();
    }

    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    /**
     * @throws StreamException
     */
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        try {
            $this->stream->seek($offset, $whence);
        } catch (\Throwable $e) {
            throw new StreamException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws StreamException
     */
    public function rewind(): void
    {
        try {
            $this->stream->rewind();
        } catch (\Throwable $e) {
            throw new StreamException('Unable to rewind stream', 0, $e);
        }
    }

    public function isWritable(): bool
    {
        return $this->stream->isWritable();
    }

    public function write(string $string): int
    {
        return $this->stream->write($string);
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    /**
     * @throws StreamException
     */
    public function read(int $length): string
    {
        try {
            return $this->stream->read($length);
        } catch (\Throwable $e) {
            throw new StreamException('Unable to read from stream', 0, $e);
        }
    }

    public function getContents(): string
    {
        return $this->stream->getContents();
    }

    public function getMetadata(string|null $key = null)
    {
        return $this->stream->getMetadata($key);
    }
}
