<?php declare(strict_types = 1);

namespace Tests\Package;

use PHPUnit\Framework\TestCase;
use UlovDomov\Stream\FileStream;

final class FileStreamTest extends TestCase
{
    /**
     * @throws \RuntimeException
     */
    public function testFileStream(): void
    {
        $stream = FileStream::create(\fopen(__DIR__ . '/../data/test.txt', 'r'));
        self::assertSame('text/plain', $stream->getMimeType());
        self::assertSame("test text\n", $stream->getContents());

        $stream->close();
    }
}
