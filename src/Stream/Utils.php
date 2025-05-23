<?php declare(strict_types = 1);

namespace UlovDomov\Stream;

use UlovDomov\Stream\Exception\StreamException;

/**
 * PHP stream implementation.
 */
class Utils
{
    /**
     * Safely opens a PHP stream resource using a filename.
     *
     * When fopen fails, PHP normally raises a warning. This function adds an
     * error handler that checks for errors and throws an exception instead.
     *
     * @param string $filename File to open
     * @param string $mode     Mode used to open the file
     *
     * @return resource
     *
     * @throws StreamException if the file cannot be opened
     */
    public static function tryFopen(string $filename, string $mode)
    {
        /** @var StreamException|null $ex */
        $ex = null;
        \set_error_handler(static function (int $errno, string $errstr) use ($filename, $mode, &$ex): bool {
            $ex = new StreamException(\sprintf(
                'Unable to open "%s" using mode "%s": %s',
                $filename,
                $mode,
                $errstr,
            ));

            return true;
        });

        try {
            /** @var resource $handle */
            $handle = \fopen($filename, $mode);
        } catch (\Throwable $e) {
            $ex = new StreamException(\sprintf(
                'Unable to open "%s" using mode "%s": %s',
                $filename,
                $mode,
                $e->getMessage(),
            ), 0, $e);
        }

        \restore_error_handler();

        if ($ex !== null) {
            throw $ex;
        }

        return $handle;
    }
}
