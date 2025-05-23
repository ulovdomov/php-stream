<?php declare(strict_types = 1);

namespace Tests\Package;

use PHPUnit\Framework\TestCase;
use UlovDomov\Stream\Exception\StreamException;
use UlovDomov\Stream\FileStream;

final class FileStreamTest extends TestCase
{
    private const CONTENT = "test text\n";

    /**
     * @throws \RuntimeException
     */
    public function testFileStream(): void
    {
        $stream = FileStream::create(\fopen(__DIR__ . '/../data/test.txt', 'r'));
        self::assertStream($stream);

        $stream->close();
    }

    /**
     * @throws \RuntimeException
     */
    public function testSaveAs(): void
    {
        $path = __DIR__ . '/../data/test.txt.copy';

        @\unlink($path);

        $stream = FileStream::create(\fopen(__DIR__ . '/../data/test.txt', 'r'));
        self::assertSame(self::CONTENT, $stream->getContents());

        $stream->saveAs($path);
        self::assertSame(self::CONTENT, \file_get_contents($path));

        self::assertStream($stream);

        $stream->close();

        $streamCopy = FileStream::createForPath($path, 'r');
        self::assertStream($streamCopy);
    }

    /**
     * @throws StreamException
     */
    private static function assertStream(FileStream $stream): void
    {
        self::assertSame('text/plain', $stream->getMimeType());
        self::assertSame(self::CONTENT, $stream->getContents());
        self::assertSame(10, $stream->getSize());
    }
}
