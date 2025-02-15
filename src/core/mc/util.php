<?php

namespace mc;

class util {
    public static function size_bytes_to_readable(int $sizeBytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $sizeBytes > 1024; $i++) {
            $sizeBytes /= 1024;
        }
        return round($sizeBytes, 2) . ' ' . $units[$i];
    }
}