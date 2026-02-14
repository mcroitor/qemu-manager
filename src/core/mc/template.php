<?php

namespace mc;

/**
 * Simple string-based template filler.
 */
class template
{
    public const prefix = "prefix";
    public const suffix = "suffix";

    public const comment_modifiers = [
        self::prefix => "<!-- ",
        self::suffix => " -->",
    ];
    public const bracket_modifiers = [
        self::prefix => "{{",
        self::suffix => "}}",
    ];

    /**
     * template
     * @var string
     */
    protected string $template;
    protected array $modifiers = [
        self::prefix => "",
        self::suffix => ""
    ];

    /**
     * Creates template instance from raw template string.
     *
     * @param string $template Template content.
     * @param array<string, string> $modifiers Placeholder delimiters.
     */
    public function __construct(string $template, array $modifiers = [])
    {
        $this->template = $template;
        if(isset($modifiers["prefix"])){
            $this->modifiers["prefix"] = $modifiers["prefix"];
        }
        if(isset($modifiers["suffix"])){
            $this->modifiers["suffix"] = $modifiers["suffix"];
        }
    }

    /**
     * Creates template object from a file.
     *
     * @param string $file Template file path.
     * @param array<string, string> $modifiers Placeholder delimiters.
     * @return \mc\template Template instance.
     */
    public static function load(string $file, array $modifiers = []): template
    {
        return new template(file_get_contents($file), $modifiers);
    }

    /**
     * Sets placeholder prefix.
     *
     * @param string $prefix Prefix marker.
     * @return void
     */
    public function set_prefix(string $prefix)
    {
        $this->modifiers["prefix"] = $prefix;
    }

    /**
     * Sets placeholder suffix.
     *
     * @param string $suffix Suffix marker.
     * @return void
     */
    public function set_suffix(string $suffix)
    {
        $this->modifiers["suffix"] = $suffix;
    }

    /**
     * Replaces placeholders with values and returns new template instance.
     *
     * @param array<string, string> $data Placeholder-value map.
     * @return \mc\template New template instance.
     */
    public function fill(array $data): template
    {
        $html = $this->template;
        foreach ($data as $pattern => $value) {
            $pattern = $this->modifiers["prefix"] . $pattern . $this->modifiers["suffix"];
            $html = str_replace($pattern, $value, $html);
        }
        return new template($html);
    }

    /**
     * Replaces one pattern with one value.
     *
     * @param string $pattern Search pattern.
     * @param string $value Replacement value.
     * @return \mc\template New template instance.
     */
    public function fill_value(string $pattern, string $value): template
    {
        return  new template(str_replace($pattern, $value, $this->template));
    }

    /**
     * Returns current template content.
     *
     * @return string Template content.
     */
    public function value(): string
    {
        return $this->template;
    }
}
