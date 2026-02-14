<?php

namespace qemu;

use \mc\route;
use \mc\template;
use \mc\util;

/**
 * QEMU disk image management module.
 *
 * Provides routes and helpers to list, create, inspect, and check
 * virtual disk images stored in the configured images directory.
 */
class image
{
    /**
     * Absolute path to this module directory.
     */
    public const MODULE_PATH = __DIR__;

    /**
     * Absolute path to this module templates directory.
     */
    public const TEMPLATE_PATH = self::MODULE_PATH . \config::sep . "templates";

    /**
     * qemu-img binary executable name.
     */
    private const QEMU_IMG = "qemu-img";
    
    // Error messages constants
    private const ERR_NAME_NOT_SPECIFIED = 'Image name not specified';
    private const ERR_NAME_INVALID = 'Invalid image name';
    private const ERR_PATH_UNSAFE = 'Unsafe file path';
    private const ERR_FILE_NOT_EXIST = 'Image file does not exist';
    
    private const SYSTEM_LIST = "list";

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

    private const ALLOWED_COMMANDS = [
        self::SYSTEM_LIST,
        self::CHECK,
        self::CREATE,
        self::INFO,
    ];

    private const REQUIRED_ROLE = \auth::ROLE_OPERATOR;

    /**
     * Returns access denied HTML or null when user is authorized.
     *
     * @return string|null Access denied HTML when unauthorized, otherwise null.
     */
    private static function ensureAccess(): ?string
    {
        if (\auth::requireRole(self::REQUIRED_ROLE)) {
            return null;
        }

        return \auth::renderAccessDenied('Images');
    }

    /**
     * Builds module HTML menu.
     *
     * @param array $menu Associative array of menu items [route => label].
     * @return string Menu HTML markup.
     */
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

    /**
     * Main module route handler.
     *
     * Resolves command, executes corresponding method, and wraps output
     * into the shared module manager template.
     *
     * @param array<int, string> $args Route arguments.
     * @return string Rendered manager page HTML.
     */
    #[route("image/manage")]
    public static function manage(array $args): string
    {
        $accessDenied = self::ensureAccess();
        if ($accessDenied !== null) {
            return $accessDenied;
        }

        $command = self::SYSTEM_LIST;
        if (!empty($args) && in_array($args[0], self::ALLOWED_COMMANDS, true)) {
            $command = $args[0];
        }

        // remove the first element from the array
        array_shift($args);
        // populate result
        $result = "";
        if (method_exists(self::class, $command)) {
            $result = self::$command($args);
        }

        return template::load(\config::templates_dir . \config::sep . "ui" . \config::sep . "module-manager.tpl.php", template::comment_modifiers)
            ->fill([
            "module-state" => "<pre><code>" . htmlspecialchars(self::state()) . "</code></pre>",
            "module-content" => $result,
                "menu-list" => self::generate_menu(self::$menu),
            ])
            ->value();
    }

    /**
     * Lists available image files.
     *
     * @param array<int, string> $args Route arguments (unused).
     * @return string Rendered image list HTML.
     */
    #[route("image/list")]
    public static function list(array $args): string
    {
        $accessDenied = self::ensureAccess();
        if ($accessDenied !== null) {
            return $accessDenied;
        }

        $files = self::list_images();
        $images = [];

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
            $safe_name = htmlspecialchars($image["name"]);
            $link_name = rawurlencode($image["name"]);
            $html .= template::load(self::TEMPLATE_PATH . \config::sep . "image/list-item.tpl.php", template::comment_modifiers)
                ->fill([
                    "image-name" => "<a href='/?q=image/manage/info/{$link_name}'>{$safe_name}</a>",
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

    /**
     * Renders image creation form or handles image creation POST request.
     *
     * Validates input, creates image via qemu-img, and returns a status block.
     *
     * @param array<int, string> $args Route arguments (unused).
     * @return string Rendered form or operation result HTML.
     */
    // #[route("image/create")]
    /**
     * Creates a new disk image via qemu-img.
     *
     * For GET-like flow (empty POST), returns the form.
     * For POST, validates input and attempts to create the image file.
     *
     * @param array $args Route arguments (unused).
     * @return string Form HTML or operation result HTML.
     */
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

        // Enhanced input validation
        $validator = \mc\Validator::fromPost();
        
        // Validate image name
        $validator->required('image-name', 'Image name is required')
                 ->minLength('image-name', 1, 'Image name cannot be empty')
                 ->maxLength('image-name', 100, 'Image name is too long (max 100 characters)')
                 ->filename('image-name', 'Invalid characters in image name')
                 ->pattern('image-name', '/^[a-zA-Z0-9_-]+$/', 'Image name can only contain letters, numbers, hyphens and underscores')
                 ->custom('image-name', function($value) {
                     // Check that file with this name does not exist
                     $filename = $value . '.img';
                     $images = self::list_images();
                     return !in_array($value, $images);
                 }, 'Image with this name already exists');

            // Validate image size
        $validator->required('image-size', 'Image size is required')
                 ->integer('image-size', 'Image size must be a number')
                 ->range('image-size', 1, 1048576, 'Image size must be between 1MB and 1TB'); // 1MB to 1TB

            // Validate image format
        $allowedFormats = ['qcow2', 'raw', 'vmdk', 'vdi', 'vhdx'];
        $validator->required('image-format', 'Image format is required')
                 ->in('image-format', $allowedFormats, 'Invalid image format. Allowed: ' . implode(', ', $allowedFormats));

            // Check validation results
        if ($validator->hasErrors()) {
            $error_items = "";
            foreach ($validator->getErrors() as $error) {
                $error_items .= "<li>" . htmlspecialchars($error) . "</li>";
            }

            // Re-render form with errors and preserved values
            $selected_format = $validator->get('image-format', 'qcow2');
            $form_html = template::load(self::TEMPLATE_PATH . \config::sep . "image/create.tpl.php", template::comment_modifiers)
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

            return template::load(self::TEMPLATE_PATH . \config::sep . "image/create-validation-error.tpl.php", template::comment_modifiers)
                ->fill([
                    "error-list" => $error_items,
                    "create-form" => $form_html,
                ])
                ->value();
        }

        // Read validated values
        $image_name = $validator->get('image-name');
        $image_size = $validator->get('image-size');
        $image_format = $validator->get('image-format');

        try {
            // Build full file path
            $image_path = \config::images_dir . \config::sep . "{$image_name}.img";
            
            // Ensure target directory exists
            if (!is_dir(\config::images_dir)) {
                if (!mkdir(\config::images_dir, 0700, true)) {
                    throw new \Exception("Failed to create images directory");
                }
            }

            // Check write permissions
            if (!is_writable(\config::images_dir)) {
                throw new \Exception("Images directory is not writable");
            }

            // Build safe shell command
            $command = self::QEMU_IMG . " create -f " . escapeshellarg($image_format) . 
                      " " . escapeshellarg($image_path) . 
                      " " . escapeshellarg($image_size . "M");
            
            \config::$logger->info("Creating image with command: {$command}");
            $output = util::execute_command($command);
            
            // Check if the image was created successfully
            if (file_exists($image_path)) {
                $fileSize = filesize($image_path);
                \config::$logger->info("Created image '{$image_name}.img' successfully, size: " . util::size_bytes_to_readable($fileSize));

                return template::load(self::TEMPLATE_PATH . \config::sep . "image/create-success.tpl.php", template::comment_modifiers)
                    ->fill([
                        "image-name" => htmlspecialchars($image_name) . ".img",
                        "image-format" => htmlspecialchars($image_format),
                        "image-size" => htmlspecialchars((string)$image_size),
                        "file-size" => util::size_bytes_to_readable($fileSize),
                        "image-location" => htmlspecialchars($image_path),
                        "image-info-link" => rawurlencode($image_name . '.img'),
                    ])
                    ->value();
            } else {
                throw new \Exception("Image file was not created");
            }

        } catch (\Exception $e) {
            \config::$logger->error("Error creating image '{$image_name}': " . $e->getMessage());

            return template::load(self::TEMPLATE_PATH . \config::sep . "image/create-failure.tpl.php", template::comment_modifiers)
                ->fill([
                    "error-message" => htmlspecialchars($e->getMessage()),
                ])
                ->value();
        }
    }

    /**
     * Shows detailed information for one image.
     *
     * @param array<int, string> $args Route arguments, expects image file name at index 0.
     * @return string Formatted image info or validation/error output.
     */
    #[route("image/info")]
    private static function info(array $args): string
    {
        $accessDenied = self::ensureAccess();
        if ($accessDenied !== null) {
            return $accessDenied;
        }

        if (empty($args)) {
            return util::code_to_html("Error: " . self::ERR_NAME_NOT_SPECIFIED);
        }

        // Validate image name
        $validator = new \mc\Validator(['image_name' => $args[0]]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE)
                 ->custom('image_name', function($value) {
                     // Ensure file exists
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

    /**
     * Returns qemu-img version information for module state display.
     *
     * @return string First output line from qemu-img --version or error text.
     */
    private static function state(): string
    {
        $command = self::QEMU_IMG . " --version";

        $result = util::execute_command($command);
        if (empty($result)) {
            return "error: no output";
        }
        return $result[0] ?? "unknown error";
    }

    /**
     * Runs image check and returns result as HTML.
     *
     * @param array $args Expects image name in $args[0].
     * @return string HTML with check result or error.
     */
    private static function check(array $args): string
    {
        if (empty($args)) {
            return util::code_to_html("Error: " . self::ERR_NAME_NOT_SPECIFIED);
        }

        // Validate image name
        $validator = new \mc\Validator(['image_name' => $args[0]]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE)
                 ->custom('image_name', function($value) {
                     // Check if the file exists
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

    /**
     * Executes `qemu-img check` for the specified image.
     *
     * @param string $image_name Image file name.
     * @return string Check output text or error.
     */
    public static function do_check(string $image_name): string
    {
        // Validate image name
        $validator = new \mc\Validator(['image_name' => $image_name]);
        $validator->required('image_name', self::ERR_NAME_NOT_SPECIFIED)
                 ->filename('image_name', self::ERR_NAME_INVALID)
                 ->safePath('image_name', self::ERR_PATH_UNSAFE);

        if ($validator->hasErrors()) {
            return "Error: " . $validator->getFirstError();
        }

        try {
            $image_path = \config::images_dir . \config::sep . $image_name;
            
            // Check if the file exists
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

    /**
     * Gets detailed image information via `qemu-img info`.
     *
     * @param string $image_name Image file name.
     * @return array Command output lines or error.
     */
    public static function get_info(string $image_name): array
    {
        // Validate image name
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
            
            // Check if the file exists
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

    /**
     * Scans image directory and returns list of `.img` files.
     *
     * @return array List of image file names.
     */
    public static function list_images(): array
    {
        $path = \config::images_dir;

        if (!is_dir($path)) {
            return [];
        }

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
