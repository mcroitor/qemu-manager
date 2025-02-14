<?php

namespace mc;

/**
 * simple template filler
 *
 * @author Croitor Mihail <mcroitor@gmail.com>
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
     *
     * @param string $template
     * @param array $modifiers
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
     * Create new template object from file
     * @param string $file
     * @param array $modifiers
     * @return \mc\template
     */
    public static function load(string $file, array $modifiers = []): template
    {
        return new template(file_get_contents($file), $modifiers);
    }

    /**
     * set filler prefix
     * @param string $prefix
     */
    public function set_prefix(string $prefix)
    {
        $this->modifiers["prefix"] = $prefix;
    }

    /**
     * set filler suffix
     * @param string $suffix
     */
    public function set_suffix(string $suffix)
    {
        $this->modifiers["suffix"] = $suffix;
    }

    /**
     * $data is a simple list of pairs <i>$pattern</i> => <i>$value</i>
     * Method replace <i>$pattern</i> with <i>$value</i>
     * and return new template object
     * Example:
     * <pre>$template->fill($data1)->fill(data2)->value();</pre>
     * @param array $data
     * @return \mc\template
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
     * Replace single $pattern with $value
     * @param string $pattern
     * @param string $value
     * @return \mc\template
     */
    public function fill_value(string $pattern, string $value): template
    {
        return  new template(str_replace($pattern, $value, $this->template));
    }

    /**
     * returns template value
     * @return string
     */
    public function value(): string
    {
        return $this->template;
    }
}
