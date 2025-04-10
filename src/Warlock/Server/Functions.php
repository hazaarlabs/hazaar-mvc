<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

class Functions
{
    /**
     * Rotate a log file.
     *
     * This function will rename a log file with a sequential suffix up to a certain number.  So a file name server.log will become
     * server.log.1, if server.log.1 exists it will be renamed to server.log.2, and so on.
     *
     * @param string $file      The file to rename
     * @param int    $fileCount The maximum number of files.  Default: 1
     * @param int    $offset    Rotation offset number to start from.  Also used as an internal counter when being called recursively.
     */
    public static function rotateLogFile(string $file, int $fileCount = 1, int $offset = 0): bool
    {
        $c = $file.(($offset > 0) ? '.'.$offset : '');
        if (!\file_exists($c)) {
            return false;
        }
        if ($offset < $fileCount) {
            self::rotateLogFile($file, $fileCount, ++$offset);
        }
        rename($c, $file.'.'.$offset);

        return true;
    }
}
