<?php

class config {
    public const sep = DIRECTORY_SEPARATOR;
    public const root_dir = __DIR__;
    public const www = "http://localhost:8080";

    /**
     * defines core modules directory
     */
    private const core_dir = config::root_dir . config::sep . "core";
    /**
     * additional modules directory
     */
    public const modules_dir = config::root_dir . config::sep . "modules";
    /**
     * JS directory
     */
    public const scripts_dir = config::root_dir . config::sep . "scripts";
    /**
     * CSS directory
     */
    public const styles_dir = config::root_dir . config::sep . "styles";
    /**
     * HTML templates directory
     */
    public const templates_dir = config::root_dir . config::sep . "templates";
    public const data_dir = config::root_dir . config::sep . ".." . config::sep . "data";
    public const images_dir = config::root_dir . config::sep . ".." . config::sep . "images";

    private const dsn = "sqlite:" . config::data_dir . config::sep . "database.sqlite";

    private const core = [
        "user",
        "auth",
        "mc/crud",
        "mc/database",
        "mc/logger",
        "mc/query",
        "mc/route",
        "mc/router",
        "mc/template",
        "mc/util",
        "mc/validator",
        "page",
    ];

    private const modules = [
        "qemu",
    ];

    public static $db = null;
    public static $logger = null;

    public static function init(): void {
        foreach(config::core as $file) {
            include_once config::core_dir . config::sep . "{$file}.php";
        }
        config::$db = new \mc\sql\database(config::dsn);
        config::$logger = \mc\logger::stderr();
    }

    public static function modules(): void {
        foreach(config::modules as $module) {
            include_once config::modules_dir . config::sep . "{$module}/index.php";
        }
    }
}

config::init();
config::modules();