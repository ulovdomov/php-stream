<?php declare(strict_types = 1);

namespace Tests\Stream;

use PHPUnit\Framework\TestCase;
use UlovDomov\Stream\Utils;

final class UtilsTest extends TestCase
{
    /**
     * @dataProvider mimeProvider
     */
    public function testMimetypeToExtension(string $mimetype, string|null $expected): void
    {
        self::assertSame($expected, Utils::mimetypeToExtension($mimetype));
    }

    /**
     * @dataProvider mimeOctetProvider
     */
    public function testIsOctetStream(string $mimetype, bool $expected): void
    {
        self::assertSame($expected, Utils::isOctetStream($mimetype));
    }

    /** @return array<array{string, string|null}> */
    public static function mimeProvider(): array
    {
        return [
            ['image/jpeg', 'jpeg'],
            ['image/png', 'png'],
            ['image/webp', 'webp'],
            ['application/pdf', 'pdf'],
            ['application/octet-stream', null],
            ['unknown/type', null],
        ];
    }

    /** @return array<array{string, bool}> */
    public static function mimeOctetProvider(): array
    {
        return [
            ['image/jpeg', false],
            ['image/png', false],
            ['application/pdf', false],
            ['application/octet-stream', true],
            ['unknown/type', false],
        ];
    }
}
