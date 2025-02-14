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

    private static function execute_command(string $command): array
    {
        $output = [];
        \config::$logger->info("executing command: {$command}");
        try {
            exec($command, $output);
        } catch (\Throwable $exception) {
            return ["error: {$exception->GetMessage()}"];
        }
        return $output;
    }

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
        $command = "ls -l " . \config::images_dir . " | grep -i '\\.img$'";

        $result = self::execute_command($command);
        if (empty($result)) {
            $result[] = "No images found";
        }
        $html = "<ul>";
        foreach ($result as $line) {
            $chunks = explode(" ", $line);
            $image_name = end($chunks);
            $html .= "<li><a href='?q=image/info/{$image_name}'>{$image_name}</a></li>";
        }
        $html .= "</ul>";

        $tpl = template::load(self::TEMPLATE_PATH . \config::sep . "image_manager.tpl.php", template::comment_modifiers);
        $tpl = $tpl->fill([
            "image-state" => self::state(),
            "image-content" => $html,
        ]);
        
        \config::$logger->info(json_encode($result));
        return $tpl->value();
    }

    #[route("image/create")]
    public static function create(array $args): string
    {
        if(empty($_POST)) {
            return template::load(self::TEMPLATE_PATH . \config::sep . "image_create.tpl.php", template::comment_modifiers)->value();
        }
        $image_name = filter_input(INPUT_POST, "image-name", FILTER_SANITIZE_SPECIAL_CHARS);
        $image_size = filter_input(INPUT_POST, "image-size", FILTER_SANITIZE_NUMBER_INT);
        $image_format = filter_input(INPUT_POST, "image-format", FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($image_name) || empty($image_size) || empty($image_format)) {
            return "error: missing required fields";
        }

        $command = self::QEMU_IMG . " create -f {$image_format} " . \config::images_dir . \config::sep . "{$image_name}.img {$image_size}M";
        $output = self::execute_command($command);
        $out = implode("<br />", $output);
        return $out;
    }

    #[route("image/info")]
    public static function info(array $args): string
    {
        if(empty($args)) {
            return "error: missing image name";
        }
        $image_name = filter_var($args[0], FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($image_name)) {
            return "error: invalid image name";
        }

        $command = self::QEMU_IMG . " info " . \config::images_dir . \config::sep . "{$image_name}";
        $output = self::execute_command($command);
        if(empty($output)) {
            return "error: no output";
        }
        return "<pre><code>" . implode("\n", $output) . "</code></pre>";
    }

    public static function state(): string
    {
        $command = self::QEMU_IMG . " --version";

        $result = self::execute_command($command);
        if (empty($result)) {
            return "error: no output";
        }
        return $result[0] ?? "unknown error";
    }
}