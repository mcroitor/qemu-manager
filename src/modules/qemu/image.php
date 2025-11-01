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
    
    // Error messages constants
    private const ERR_NAME_NOT_SPECIFIED = 'Image name not specified';
    private const ERR_NAME_INVALID = 'Invalid image name';
    private const ERR_PATH_UNSAFE = 'Unsafe file path';
    private const ERR_FILE_NOT_EXIST = 'Image file does not exist';
    
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
            return template::load(self::TEMPLATE_PATH . \config::sep . "image/create.tpl.php", template::comment_modifiers)
                ->fill([
                    "image-name-value" => "",
                    "image-size-value" => "1024",
                    "qcow2-selected" => "selected",
                    "raw-selected" => "",
                    "vmdk-selected" => "",
                    "vdi-selected" => "",
                    "vhdx-selected" => "",
                ])
                ->value();
        }

        // Улучшенная валидация входных данных
        $validator = \mc\Validator::fromPost();
        
        // Валидация имени образа
        $validator->required('image-name', 'Image name is required')
                 ->minLength('image-name', 1, 'Image name cannot be empty')
                 ->maxLength('image-name', 100, 'Image name is too long (max 100 characters)')
                 ->filename('image-name', 'Invalid characters in image name')
                 ->pattern('image-name', '/^[a-zA-Z0-9_-]+$/', 'Image name can only contain letters, numbers, hyphens and underscores')
                 ->custom('image-name', function($value) {
                     // Проверяем, что файл с таким именем не существует
                     $images = self::list_images();
                     return !in_array($value . '.img', $images);
                 }, 'Image with this name already exists');

        // Валидация размера образа
        $validator->required('image-size', 'Image size is required')
                 ->integer('image-size', 'Image size must be a number')
                 ->range('image-size', 1, 1048576, 'Image size must be between 1MB and 1TB'); // 1MB to 1TB

        // Валидация формата образа
        $allowedFormats = ['qcow2', 'raw', 'vmdk', 'vdi', 'vhdx'];
        $validator->required('image-format', 'Image format is required')
                 ->in('image-format', $allowedFormats, 'Invalid image format. Allowed: ' . implode(', ', $allowedFormats));

        // Проверяем результаты валидации
        if ($validator->hasErrors()) {
            $error_html = "<div style='color: red; background: #ffe6e6; padding: 10px; border: 1px solid #ff0000; margin-bottom: 10px;'>";
            $error_html .= "<h4>Please correct the following errors:</h4><ul>";
            foreach ($validator->getErrors() as $error) {
                $error_html .= "<li>{$error}</li>";
            }
            $error_html .= "</ul></div>";

            // Показать форму с ошибками и сохраненными значениями
            $selected_format = $validator->get('image-format', 'qcow2');
            return $error_html . template::load(self::TEMPLATE_PATH . \config::sep . "image/create.tpl.php", template::comment_modifiers)
                ->fill([
                    "image-name-value" => htmlspecialchars($validator->get('image-name', '')),
                    "image-size-value" => htmlspecialchars($validator->get('image-size', '1024')),
                    "qcow2-selected" => $selected_format === 'qcow2' ? 'selected' : '',
                    "raw-selected" => $selected_format === 'raw' ? 'selected' : '',
                    "vmdk-selected" => $selected_format === 'vmdk' ? 'selected' : '',
                    "vdi-selected" => $selected_format === 'vdi' ? 'selected' : '',
                    "vhdx-selected" => $selected_format === 'vhdx' ? 'selected' : '',
                ])
                ->value();
        }

        // Получаем валидированные данные
        $image_name = $validator->get('image-name');
        $image_size = $validator->get('image-size');
        $image_format = $validator->get('image-format');

        try {
            // Создаем полный путь к файлу
            $image_path = \config::images_dir . \config::sep . "{$image_name}.img";
            
            // Проверяем, что директория существует
            if (!is_dir(\config::images_dir)) {
                if (!mkdir(\config::images_dir, 0755, true)) {
                    throw new \Exception("Failed to create images directory");
                }
            }

            // Проверяем права на запись
            if (!is_writable(\config::images_dir)) {
                throw new \Exception("Images directory is not writable");
            }

            // Строим безопасную команду
            $command = self::QEMU_IMG . " create -f " . escapeshellarg($image_format) . 
                      " " . escapeshellarg($image_path) . 
                      " " . escapeshellarg($image_size . "M");
            
            \config::$logger->info("Creating image with command: {$command}");
            $output = util::execute_command($command);
            
            // Проверяем успешность создания
            if (file_exists($image_path)) {
                $fileSize = filesize($image_path);
                \config::$logger->info("Created image '{$image_name}.img' successfully, size: " . util::size_bytes_to_readable($fileSize));
                
                return "<div style='color: green; background: #e6ffe6; padding: 15px; border: 1px solid #00ff00; margin-bottom: 10px;'>" .
                       "<h3>✓ Success!</h3>" .
                       "<p>Disk image '<strong>{$image_name}.img</strong>' created successfully!</p>" .
                       "<p><strong>Details:</strong></p>" .
                       "<ul>" .
                       "<li>Format: {$image_format}</li>" .
                       "<li>Size: {$image_size} MB</li>" .
                       "<li>File size: " . util::size_bytes_to_readable($fileSize) . "</li>" .
                       "<li>Location: " . htmlspecialchars($image_path) . "</li>" .
                       "</ul>" .
                       "<p>" .
                       "<a href='/?q=image/manage/list' class='button'>View all images</a> " .
                       "<a href='/?q=image/manage/info/{$image_name}.img' class='button'>Image info</a> " .
                       "<a href='/?q=machine/manage/create' class='button button-primary'>Create VM with this image</a>" .
                       "</p>" .
                       "</div>";
            } else {
                throw new \Exception("Image file was not created");
            }

        } catch (\Exception $e) {
            \config::$logger->error("Error creating image '{$image_name}': " . $e->getMessage());
            return "<div style='color: red; background: #ffe6e6; padding: 15px; border: 1px solid #ff0000; margin-bottom: 10px;'>" .
                   "<h3>✗ Error!</h3>" .
                   "<p>Failed to create disk image: " . htmlspecialchars($e->getMessage()) . "</p>" .
                   "<p><a href='/?q=image/manage/create' class='button'>Try again</a></p>" .
                   "</div>";
        }
    }

    #[route("image/info")]
    private static function info(array $args): string
    {
        if (empty($args)) {
            return util::code_to_html("Error: " . self::ERR_NAME_NOT_SPECIFIED);
        }

        // Валидация имени образа
        $validator = new \mc\Validator(['image_name' => $args[0]]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE)
                 ->custom('image_name', function($value) {
                     // Проверяем, что файл существует
                     $images = self::list_images();
                     return in_array($value, $images);
                 }, self::ERR_FILE_NOT_EXIST);

        if ($validator->hasErrors()) {
            return util::code_to_html("Error: " . $validator->getFirstError());
        }

        $image_name = $validator->get('image_name');

        try {
            $output = self::get_info($image_name);
            if (empty($output)) {
                return util::code_to_html("Error: No information available for this image");
            }

            self::$menu["check/{$image_name}"] = "Check Image";
            return util::code_to_html(implode("\n", $output));
            
        } catch (\Exception $e) {
            \config::$logger->error("Error getting image info for '{$image_name}': " . $e->getMessage());
            return util::code_to_html("Error: " . htmlspecialchars($e->getMessage()));
        }
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
            return util::code_to_html("Error: " . self::ERR_NAME_NOT_SPECIFIED);
        }

        // Валидация имени образа
        $validator = new \mc\Validator(['image_name' => $args[0]]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE)
                 ->custom('image_name', function($value) {
                     // Проверяем, что файл существует
                     $images = self::list_images();
                     return in_array($value, $images);
                 }, self::ERR_FILE_NOT_EXIST);

        if ($validator->hasErrors()) {
            return util::code_to_html("Error: " . $validator->getFirstError());
        }

        $image_name = $validator->get('image_name');

        try {
            $result = self::do_check($image_name);
            return util::code_to_html($result);
        } catch (\Exception $e) {
            \config::$logger->error("Error checking image '{$image_name}': " . $e->getMessage());
            return util::code_to_html("Error: " . htmlspecialchars($e->getMessage()));
        }
    }

    public static function do_check(string $image_name): string
    {
        // Валидация имени образа
        $validator = new \mc\Validator(['image_name' => $image_name]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE);

        if ($validator->hasErrors()) {
            return "Error: " . $validator->getFirstError();
        }

        try {
            $image_path = \config::images_dir . \config::sep . $image_name;
            
            // Проверяем, что файл существует
            if (!file_exists($image_path)) {
                return "Error: Image file does not exist: {$image_name}";
            }

            $command = self::QEMU_IMG . " check " . escapeshellarg($image_path);
            \config::$logger->info("Checking image with command: {$command}");
            
            $output = util::execute_command($command);
            if (empty($output)) {
                return "Error: No output from check command";
            }
            
            return implode("\n", $output);
            
        } catch (\Exception $e) {
            \config::$logger->error("Error in do_check for '{$image_name}': " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    public static function get_info(string $image_name): array
    {
        // Валидация имени образа
        $validator = new \mc\Validator(['image_name' => $image_name]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE);

        if ($validator->hasErrors()) {
            \config::$logger->error("Validation error in get_info: " . $validator->getFirstError());
            return ["Error: " . $validator->getFirstError()];
        }

        try {
            $image_path = \config::images_dir . \config::sep . $image_name;
            
            // Проверяем, что файл существует
            if (!file_exists($image_path)) {
                return ["Error: Image file does not exist: {$image_name}"];
            }

            $command = self::QEMU_IMG . " info " . escapeshellarg($image_path);
            \config::$logger->info("Getting image info with command: {$command}");
            
            $output = util::execute_command($command);
            return $output;
            
        } catch (\Exception $e) {
            \config::$logger->error("Error in get_info for '{$image_name}': " . $e->getMessage());
            return ["Error: " . $e->getMessage()];
        }
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
