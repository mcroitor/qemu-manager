<?php

use \mc\template;

/**
 * Page renderer for the main application layout.
 *
 * Collects menu, content, and placeholder data, then renders
 * the root template with filled values.
 */
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

    /**
     * Sets menu items and active menu key.
     *
     * @param array<string, string> $menu Menu map (URL => title).
     * @param string $active Active menu URL.
     * @return void
     */
    public function menu(array $menu, string $active = ""): void {
        $this->menu = $menu;
        $this->active_menu = $active;
    }

    /**
     * Updates active menu key.
     *
     * @param string $active_menu Active menu URL.
     * @return void
     */
    public function activate_menu(string $active_menu): void {
        $this->active_menu = $active_menu;
    }

    /**
     * Sets page content HTML.
     *
     * @param string $content Content HTML.
     * @return void
     */
    public function content(string $content): void {
        $this->content = $content;
    }

    /**
     * Merges custom placeholder data for template rendering.
     *
     * @param array<string, string> $data Placeholder values.
     * @return void
     */
    public function data(array $data): void {
        foreach($data as $key => $value) {
            $this->fill_data[$key] = $value;
        }
    }

    /**
     * Renders final page HTML.
     *
     * @return string Rendered HTML page.
     */
    public function html(): string {
        $this->fill_data["menu"] = $this->build_menu();
        $this->fill_data["content"] = $this->content;

        return $this->template->fill($this->fill_data)->value();
    }

    /**
     * Builds HTML menu buttons from configured menu items.
     *
     * @return string Rendered menu HTML.
     */
    private function build_menu(): string {
        $menu = "";

        foreach($this->menu as $link => $title) {
            $menu .= "<a href='{$link}' class='button'>{$title}</a>";
        }
        return $menu;
    }
}