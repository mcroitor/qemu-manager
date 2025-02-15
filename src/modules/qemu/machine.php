<?php

namespace qemu;

include_once __DIR__ . \config::sep . "hardware/architecture.php";

use \mc\route;
use \mc\template;
use \mc\util;
use \qemu\hardware;
use qemu\hardware\architecture;

class machine {
    public const MODULE_PATH = __DIR__;
    public const TEMPLATE_PATH = self::MODULE_PATH . \config::sep . "templates";

    private const QEMU_SYSTEM = "qemu-system-";
    private static $platform = architecture::x86_64;

    private static $menu = [
        "list" => "List VM",
        "create" => "Create VM",
    ];

    private static function generate_menu(array $menu): string
    {
        $html = "";
        foreach ($menu as $key => $value) {
            $html .= template::load(util::sausage("machine.menu-item", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "menu-link" => \config::www . "/?q=machine/manage/{$key}",
                    "menu-name" => $value,
                ])
                ->value();
        }
        return $html;
    }

    #[route("machine/manage")]
    public static function manage(array $args): string
    {
        $command = "list";
        if (count($args) > 0) {
            $command = $args[0];
            array_shift($args);
        }

        // populate result
        $result = "";
        if (method_exists(self::class, $command)) {
            $result = self::$command($args);
        }
            
        return template::load(util::sausage("machine.manager", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
            ->fill([
                "machine-state" => self::state(),
                "machine-content" => $result,
                "menu-list" => self::generate_menu(self::$menu),
                ])
            ->value();
    }

    private static function state(): string
    {
        return "state";
    }

    private static function list(array $args): string
    {
        return "List VM";
    }

    public static function create(array $args): string{
        if(empty($_POST)){
            $images = image::list_images();
            \config::$logger->info("Images: " . json_encode($images));
            $list_images = "";
            foreach($images as $image){
                $list_images .= "<option value='{$image}'>{$image}</option>";
            }
            return template::load(util::sausage("machine.create", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "disk-image-list" => $list_images,
                ])
                ->value();
        }
        return "Create VM";
    }
}