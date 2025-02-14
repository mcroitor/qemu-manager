<?php

namespace qemu;

use \mc\route;
use \mc\template;

class image
{
    private const QEMU_IMG = "qemu-img";

    public const AMEND = "amend";
    public const BITMAP = "bitmap";
    public const CHECK = "check";
    public const COMMIT = "commit";
    public const COMPARE = "compare";
    public const CONVERT = "convert";
    public const CREATE = "create";
    public const DD = "dd";
    public const INFO = "info";
    public const MAP = "map";
    public const MEASURE = "measure";
    public const SNAPSHOT = "snapshot";
    public const REBASE = "rebase";
    public const RESIZE = "resize";

    private const COMMAND = [
        self::AMEND,
        self::BITMAP,
        self::CHECK,
        self::COMMIT,
        self::COMPARE,
        self::CONVERT,
        self::DD,
        self::INFO,
        self::MAP,
        self::MEASURE,
        self::SNAPSHOT,
        self::REBASE,
        self::RESIZE,
    ];
    public const MODULE_PATH = __DIR__;
    public const TEMPLATE_PATH = self::MODULE_PATH . \config::sep . "templates";

    #[route("image/manage")]
    public static function manage(array $args): string
    {
        return template::load(self::TEMPLATE_PATH . \config::sep . "image_manager.tpl.php", template::comment_modifiers)
            ->fill(["image-state" => self::state()])
            ->value();
    }

    #[route("image/list")]
    public static function list(array $args): string
    {
        $result = [];
        $command = "ls -l " . \config::images_dir . " | grep -i '\\.img$'";

        exec($command, $result);

        $tpl = template::load(self::TEMPLATE_PATH . \config::sep . "image_manager.tpl.php", template::comment_modifiers);
        $tpl = $tpl->fill([
            "image-state" => self::state(),
            "image-content" => implode("<br />", $result)
        ]);
        
        \config::$logger->info(json_encode($result));
        return $tpl->value();
    }

    #[route("image/create")]
    public static function create(array $args): string
    {
        return "";
    }

    #[route("image/info")]
    public static function info(array $args): string
    {
        return "";
    }

    public static function state(): string
    {
        $state = [];
        try {
            exec(self::QEMU_IMG . " --version", $state);
        } catch (\Exception $exception) {
            return "error: {$exception->GetMessage()}";
        }
        return isset($state[0]) ? $state[0] : "unknown error";
    }
}