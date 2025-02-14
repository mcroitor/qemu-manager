<?php

namespace modules\container;

class container{
    private const engine = "docker";

    private static function is_ready(): bool {
        // TODO: check if engine is ready
        return true;
    }

    private static function version(): string {
        // TODO: check engine version
        return "UNKNOWN";
    }

    private static function engine(): string {
        return self::engine;
    }

    private static function commands(): array {
        // TODO: return supported commands
        return [];
    }

    private static function can_execute(string $command): bool {
        return !(array_search($command, self::commands()) === false);
    }

    private static function execute(string $command, array $options = [], string $image = ""): bool {
        // TODO: build command and execute
        $args = "";
        foreach($options as $key => $value) {
            $args .= (strlen($key) == 1) ? "-{$key} " : "--{$key} ";
            if(!empty($value)) {
                $args .= "{$value} ";
            }
        }
        $command = container::engine() . " {$command} {$args} {$image}";
        // TODO: execute now
        return true;
    }
};