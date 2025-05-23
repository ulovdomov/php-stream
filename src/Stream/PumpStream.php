<?php declare(strict_types = 1);

namespace UlovDomov\Stream;

use Psr\Http\Message\StreamInterface;
use UlovDomov\Stream\Exception\StreamException;

final class PumpStream implements StreamInterface
{
    /**
     * @var callable(int): (string|false|null)|null
     */
    private $source;

    private int|null $size;

    private int $tellPos = 0;

    /** @var array<mixed> */
    private array $metadata;

    private BufferStream $buffer;

    /**
     * @param callable(int): (string|false|null)  $source  Source of the stream data. The callable MAY
     *                                                     accept an integer argument used to control the
     *                                                     amount of data to return. The callable MUST
     *                                                     return a string when called, or false|null on error
     *                                                     or EOF.
     * @param array{size?: int, metadata?: array<mixed>} $options Stream options:
     *                                                     - metadata: Hash of metadata to use with stream.
     *                                                     - size: Size of the stream, if known.
     */
    public function __construct(callable $source, array $options = [])
    {
        $this->source = $source;
        $this->size = $options['size'] ?? null;
        $this->metadata = $options['metadata'] ?? [];
        $this->buffer = new BufferStream();
    }

    public function __toString(): string
    {
        try {
            return self::copyToString($this);
        } catch (\Throwable $e) {
            if (\PHP_VERSION_ID >= 70400) {
                throw $e;
            }

            \trigger_error(\sprintf('%s::__toString exception: %s', self::class, (string) $e), \E_USER_ERROR);

            return '';
        }
    }

    public function close(): void
    {
        $this->detach();
    }

    public function detach()
    {
        $this->tellPos = 0;
        $this->source = null;

        return null;
    }

    public function getSize(): int|null
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->tellPos;
    }

    public function eof(): bool
    {
        return $this->source === null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = \SEEK_SET): void
    {
        throw new StreamException('Cannot seek a PumpStream');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new StreamException('Cannot write to a PumpStream');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read($length): string
    {
        $data = $this->buffer->read($length);
        $readLen = \strlen($data);
        $this->tellPos += $readLen;
        $remaining = $length - $readLen;

        if ($remaining) {
            $this->pump($remaining);
            $data .= $this->buffer->read($remaining);
            $this->tellPos += \strlen($data) - $readLen;
        }

        return $data;
    }

    public function getContents(): string
    {
        $result = '';

        while (!$this->eof()) {
            $result .= $this->read(1000000);
        }

        return $result;
    }

    public function getMetadata($key = null): mixed
    {
        if (!$key) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * @throws \RuntimeException
     */
    private function pump(int $length): void
    {
        if ($this->source !== null) {
            do {
                $data = ($this->source)($length);

                if ($data === false || $data === null) {
                    $this->source = null;

                    return;
                }

                $this->buffer->write($data);
                $length -= \strlen($data);
            } while ($length > 0);
        }
    }

    /**
     * Copy the contents of a stream into a string until the given number of
     * bytes have been read.
     *
     * @param StreamInterface $stream Stream to read
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copyToString(StreamInterface $stream, int $maxLen = -1): string
    {
        $buffer = '';

        if ($maxLen === -1) {
            while (!$stream->eof()) {
                $buf = $stream->read(1048576);

                if ($buf === '') {
                    break;
                }

                $buffer .= $buf;
            }

            return $buffer;
        }

        $len = 0;
        while (!$stream->eof() && $len < $maxLen) {
            $buf = $stream->read($maxLen - $len);

            if ($buf === '') {
                break;
            }

            $buffer .= $buf;
            $len = \strlen($buffer);
        }

        return $buffer;
    }
}
