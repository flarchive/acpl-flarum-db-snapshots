<?php

namespace Acpl\FlarumDbSnapshots\Helpers;

class Format
{
    public static function humanReadableSize(int $sizeInBytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $sizeInBytes ? floor(log($sizeInBytes, 1024)) : 1;

        return round($sizeInBytes / pow(1024, $i), 2).' '.$units[$i];
    }
}
