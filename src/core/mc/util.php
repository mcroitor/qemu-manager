<?php

namespace mc;

class util
{
    public static function size_bytes_to_readable(int $sizeBytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $sizeBytes > 1024; $i++) {
            $sizeBytes /= 1024;
        }
        return round($sizeBytes, 2) . ' ' . $units[$i];
    }

    public static function sausage(
        string $sausage,
        string $ext = "",
        string $parent = ""
    ): string {
        $sep = \config::sep;
        $path = str_replace(['/', '.', '\\'], \config::sep, $sausage);
        return "{$parent}{$sep}{$path}.{$ext}";
    }

    public static function execute_command(string $command): array
    {
        $output = [];
        $result_code = 0;
        \config::$logger->info("executing command: {$command}");
        try {
            exec($command, $output, $result_code);
        } catch (\Throwable $exception) {
            return ["error: {$exception->GetMessage()}"];
        }
        if ($result_code !== 0) {
            return ["error: command failed with code {$result_code}"];
        }
        return $output;
    }

    public static function code_to_html(string $code): string
    {
        return "<pre><code>{$code}</code></pre>";
    }
}
