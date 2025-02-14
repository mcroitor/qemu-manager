<?php

use \mc\template;

class page
{
    private $content = "";
    private $menu = [];
    private $active_menu = "";
    private $template = null;
    private $fill_data = [
        "menu" => "",
        "content" => "",
        "www" => \config::www,
    ];

    public function __construct()
    {
        $this->template = template::load(
            \config::templates_dir . \config::sep . "index.tpl.php",
            template::comment_modifiers
        );
    }

    public function menu(array $menu, string $active = ""): void {
        $this->menu = $menu;
        $this->active_menu = $active;
    }

    public function activate_menu(string $active_menu): void {
        $this->active_menu = $active_menu;
    }

    public function content(string $content): void {
        $this->content = $content;
    }
    public function data(array $data): void {
        foreach($data as $key => $value) {
            $this->fill_data[$key] = $value;
        }
    }

    public function html(): string {
        $this->fill_data["menu"] = $this->build_menu();
        $this->fill_data["content"] = $this->content;

        return $this->template->fill($this->fill_data)->value();
    }

    private function build_menu(): string {
        $menu = "";

        foreach($this->menu as $link => $title) {
            $menu .= "<a href='{$link}' class='button'>{$title}</a>";
        }
        return $menu;
    }
}