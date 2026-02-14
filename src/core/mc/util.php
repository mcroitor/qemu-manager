<?php

namespace mc;

use BackedEnum;

/**
 * Common utility helpers.
 */
class util
{
    /**
     * Converts bytes value to a human-readable unit string.
     *
     * @param int $sizeBytes Size in bytes.
     * @return string Readable size string.
     */
    public static function size_bytes_to_readable(int $sizeBytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $sizeBytes > 1024; $i++) {
            $sizeBytes /= 1024;
        }
        return round($sizeBytes, 2) . ' ' . $units[$i];
    }

    /**
     * Converts dotted/slashed key into file path under parent directory.
     *
     * @param string $sausage Logical resource key (e.g. module.template-name).
     * @param string $ext File extension without leading dot.
     * @param string $parent Parent directory path.
     * @return string Resolved full path.
     */
    public static function sausage(
        string $sausage,
        string $ext = "",
        string $parent = ""
    ): string {
        $sep = \config::sep;
        $path = str_replace(['/', '.', '\\'], \config::sep, $sausage);
        return "{$parent}{$sep}{$path}.{$ext}";
    }

    /**
     * Executes shell command and returns output lines.
     *
     * @param string $command Shell command.
     * @return array<int, string> Output lines or one error line.
     */
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

    /**
     * Wraps plain text into code block HTML.
     *
     * @param string $code Text to display.
     * @return string HTML code block.
     */
    public static function code_to_html(string $code): string
    {
        return "<pre><code>{$code}</code></pre>";
    }

    /**
     * Renders HTML select for enum cases.
     *
     * @param BackedEnum $enum Enum instance to infer enum class.
     * @return string Rendered select element.
     */
    public static function select(BackedEnum $enum): string
    {
        $class_name = $enum::class;
        $html = "<select name='{$class_name}'>";
        foreach ($enum::cases() as $key => $value) {
            $html .= "<option value='" . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</option>";
        }
        $html .= "</select>";
        return $html;
    }
}
