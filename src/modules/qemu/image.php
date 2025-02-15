<?php

namespace qemu;

use \mc\route;
use \mc\template;
use \mc\util;

class image
{
    public const MODULE_PATH = __DIR__;
    public const TEMPLATE_PATH = self::MODULE_PATH . \config::sep . "templates";

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
        //        self::AMEND,
        //        self::BITMAP,
        self::CHECK,
        //        self::COMMIT,
        //        self::COMPARE,
        //        self::CONVERT,
        self::CREATE,
        //        self::DD,
        self::INFO,
        //        self::MAP,
        //        self::MEASURE,
        //        self::SNAPSHOT,
        //        self::REBASE,
        //        self::RESIZE,
    ];

    private static $menu = [
        "list" => "List Images",
        "create" => "Create Image",
    ];

    private static function generate_menu(array $menu): string
    {
        $html = "";
        foreach ($menu as $key => $value) {
            $html .= template::load(util::sausage("image.menu-item", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "menu-link" => \config::www . "/?q=image/manage/{$key}",
                    "menu-name" => $value,
                ])
                ->value();
        }
        return $html;
    }

    #[route("image/manage")]
    public static function manage(array $args): string
    {
        $command = "list";
        if (!empty($args) && in_array($args[0], self::COMMAND)) {
            $command = $args[0];
        }

        // remove the first element from the array
        array_shift($args);
        // populate result
        $result = "";
        if (method_exists(self::class, $command)) {
            $result = self::$command($args);
        }

        return template::load(util::sausage("image.manager", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
            ->fill([
                "image-state" => "<pre><code>" . self::state() . "</code></pre>",
                "image-content" => $result,
                "menu-list" => self::generate_menu(self::$menu),
            ])
            ->value();
    }

    #[route("image/list")]
    public static function list(array $args): string
    {
        $files = self::list_images();

        foreach ($files as $file) {
            $images[] = [
                "name" => $file,
                "size" => filesize(\config::images_dir . \config::sep . $file),
                "type" => "qcow2",
            ];
        }

        if (empty($images)) {
            return "<h3>No images found</h3>";
        }
        $html = "";
        foreach ($images as $image) {
            $html .= template::load(self::TEMPLATE_PATH . \config::sep . "image/list-item.tpl.php", template::comment_modifiers)
                ->fill([
                    "image-name" => "<a href='/?q=image/manage/info/{$image["name"]}'>{$image["name"]}</a>",
                    "image-size" => util::size_bytes_to_readable($image["size"]),
                    "image-type" => $image["type"],
                ])
                ->value();
        }

        return template::load(self::TEMPLATE_PATH . \config::sep . "image/list.tpl.php", template::comment_modifiers)
            ->fill([
                "image-list-info" => $html,
            ])
            ->value();
    }

    // #[route("image/create")]
    public static function create(array $args): string
    {
        if (empty($_POST)) {
            return template::load(self::TEMPLATE_PATH . \config::sep . "image/create.tpl.php", template::comment_modifiers)->value();
        }
        $image_name = filter_input(INPUT_POST, "image-name", FILTER_SANITIZE_SPECIAL_CHARS);
        $image_size = filter_input(INPUT_POST, "image-size", FILTER_SANITIZE_NUMBER_INT);
        $image_format = filter_input(INPUT_POST, "image-format", FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($image_name) || empty($image_size) || empty($image_format)) {
            return "error: missing required fields";
        }

        $command = self::QEMU_IMG . " create -f {$image_format} " . \config::images_dir . \config::sep . "{$image_name}.img {$image_size}M";
        $output = util::execute_command($command);
        $out = implode("\n", $output);
        return $out;
    }

    #[route("image/info")]
    private static function info(array $args): string
    {
        if (empty($args)) {
            return util::code_to_html("error: missing image name");
        }
        $image_name = filter_var($args[0], FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($image_name)) {
            return util::code_to_html("error: invalid image name");
        }

        $output = self::get_info($image_name);
        if (empty($output)) {
            return util::code_to_html("error: no output");
        }

        self::$menu["check/{$image_name}"] = "Check Image";
        return util::code_to_html(implode("\n", $output));
    }

    private static function state(): string
    {
        $command = self::QEMU_IMG . " --version";

        $result = util::execute_command($command);
        if (empty($result)) {
            return "error: no output";
        }
        return $result[0] ?? "unknown error";
    }

    private static function check(array $args): string
    {
        if (empty($args)) {
            return util::code_to_html("error: missing image name");
        }
        $image_name = filter_var($args[0], FILTER_SANITIZE_SPECIAL_CHARS);
        return util::code_to_html(self::do_check($image_name));
    }

    public static function do_check(string $image_name): string
    {
        if (empty($image_name)) {
            return "error: invalid image name";
        }
        $command = self::QEMU_IMG . " check " . \config::images_dir . \config::sep . "{$image_name}";
        $output = util::execute_command($command);
        if (empty($output)) {
            return "error: no output";
        }
        return implode("\n", $output);
    }

    public static function get_info(string $image_name): array
    {
        $command = self::QEMU_IMG . " info " . \config::images_dir . \config::sep . "{$image_name}";
        $output = util::execute_command($command);
        return $output;
    }

    public static function list_images(): array
    {
        $path = \config::images_dir;

        $files = scandir($path);
        $images = [];

        foreach ($files as $file) {
            if (is_file($path . \config::sep . $file) && pathinfo($file, PATHINFO_EXTENSION) === "img") {
                $images[] = $file;
            }
        }
        return $images;
    }
}
