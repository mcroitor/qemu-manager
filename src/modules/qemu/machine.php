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

    // Error messages constants
    private const ERR_NAME_NOT_SPECIFIED = 'Machine name not specified';
    private const ERR_NAME_INVALID = 'Invalid machine name format';
    private const ERR_VM_NOT_FOUND = 'Virtual machine does not exist';
    private const ERR_IMAGE_NOT_FOUND = 'Disk image file does not exist';
    private const ERR_IMAGE_INVALID = 'Invalid disk image path';
    private const ERR_CDROM_NOT_FOUND = 'CDROM image file does not exist';
    private const ERR_CDROM_INVALID = 'Invalid CDROM image path';
    
    // Success messages constants
    private const MSG_VM_STARTED = 'Virtual machine started successfully';
    private const MSG_VM_STOPPED = 'Virtual machine stop signal sent';

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

    /**
     * Start virtual machine
     * @param array $args
     * @return string
     */
    private static function start(array $args): string
    {
        try {
            // Enhanced validation for machine name
            if (empty($args[0])) {
                return "<div style='color: red;'>Error: " . self::ERR_NAME_NOT_SPECIFIED . "</div>";
            }
            
            $validator = new \mc\Validator(['machine_name' => $args[0]]);
            $validator
                ->required('machine_name', self::ERR_NAME_NOT_SPECIFIED)
                ->machineName('machine_name', self::ERR_NAME_INVALID)
                ->exists('machine_name', 'virtual_machine', 'name', self::ERR_VM_NOT_FOUND);

            if ($validator->hasErrors()) {
                return "<div style='color: red;'>Error: " . htmlspecialchars($validator->getFirstError()) . "</div>";
            }
            
            $machine_name = $validator->get('machine_name');
            
            $machine = \config::$db->select("virtual_machine", ["*"], ["name" => $machine_name]);
            $vm = $machine[0];

            // Build QEMU command
            $command = self::QEMU_SYSTEM . $vm['platform'];
            $command .= " -name " . escapeshellarg($vm['name']);
            $command .= " -m " . $vm['memory'];
            $command .= " -smp " . $vm['cpu'];
            
            if (!empty($vm['hda'])) {
                $image_path = \config::images_dir . \config::sep . $vm['hda'];
                // Validate image path for security
                if (!file_exists($image_path)) {
                    return "<div style='color: red;'>Error: " . self::ERR_IMAGE_NOT_FOUND . "</div>";
                }
                $real_image_path = realpath($image_path);
                $real_images_dir = realpath(\config::images_dir);
                if ($real_image_path === false || $real_images_dir === false || strpos($real_image_path, $real_images_dir) !== 0) {
                    return "<div style='color: red;'>Error: " . self::ERR_IMAGE_INVALID . "</div>";
                }
                $command .= " -hda " . escapeshellarg($real_image_path);
            }
            
            if (!empty($vm['cdrom'])) {
                $cdrom_path = \config::images_dir . \config::sep . $vm['cdrom'];
                // Validate cdrom path for security
                if (!file_exists($cdrom_path)) {
                    return "<div style='color: red;'>Error: " . self::ERR_CDROM_NOT_FOUND . "</div>";
                }
                $real_cdrom_path = realpath($cdrom_path);
                $real_images_dir = realpath(\config::images_dir);
                if ($real_cdrom_path === false || $real_images_dir === false || strpos($real_cdrom_path, $real_images_dir) !== 0) {
                    return "<div style='color: red;'>Error: " . self::ERR_CDROM_INVALID . "</div>";
                }
                $command .= " -cdrom " . escapeshellarg($real_cdrom_path);
            }
            
            // Set up networking
            $network_args = NetworkManager::getNetworkArgsForVM($machine_name);
            $command .= " " . $network_args;
            
            $command .= " -boot " . $vm['boot'];
            $command .= " -daemonize"; // Start in background mode
            
            \config::$logger->info("Starting VM '{$machine_name}' with command: {$command}");
            
            $output = util::execute_command($command);
            
            if (empty($output) || !isset($output[0]) || strpos($output[0], 'error') === false) {
                return "<div style='color: green;'>" . self::MSG_VM_STARTED . ": '{$machine_name}'</div>";
            } else {
                return "<div style='color: red;'>Error starting VM: " . htmlspecialchars(implode("\n", $output)) . "</div>";
            }
            
        } catch (\Exception $e) {
            \config::$logger->error("Error starting VM: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Stop virtual machine
     */
    private static function stop(array $args): string
    {
        try {
            if (empty($args[0])) {
                return "<div style='color: red;'>Error: " . self::ERR_NAME_NOT_SPECIFIED . "</div>";
            }
            
            // Enhanced validation for machine name
            $validator = new \mc\Validator(['machine_name' => $args[0]]);
            $validator
                ->required('machine_name', self::ERR_NAME_NOT_SPECIFIED)
                ->machineName('machine_name', self::ERR_NAME_INVALID)
                ->exists('machine_name', 'virtual_machine', 'name', self::ERR_VM_NOT_FOUND);

            if ($validator->hasErrors()) {
                return "<div style='color: red;'>Error: " . htmlspecialchars($validator->getFirstError()) . "</div>";
            }
            
            $machine_name = $validator->get('machine_name');
            
            // TODO: Improve process management using PID files
            // Current implementation uses pkill which may affect multiple processes
            // Better approach: Store PID in file when starting VM, use kill with PID
            
            // Send SIGTERM to the QEMU process
            // Note: Using pkill with pattern matching - ensure machine names are unique
            $pattern = "qemu-system.*-name {$machine_name}";
            $command = "pkill -f " . escapeshellarg($pattern);
            \config::$logger->info("Stopping VM '{$machine_name}' with command: {$command}");
            
            $output = util::execute_command($command);
            
            return "<div style='color: green;'>" . self::MSG_VM_STOPPED . ": '{$machine_name}'</div>";
            
        } catch (\Exception $e) {
            \config::$logger->error("Error stopping VM: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Delete virtual machine and all related data (network settings, port forwarding)
     */
    private static function delete(array $args): string
    {
        try {
            if (empty($args[0])) {
                return "<div style='color: red;'>Error: " . self::ERR_NAME_NOT_SPECIFIED . "</div>";
            }
            
            // Enhanced validation for machine name
            $validator = new \mc\Validator(['machine_name' => $args[0]]);
            $validator
                ->required('machine_name', self::ERR_NAME_NOT_SPECIFIED)
                ->machineName('machine_name', self::ERR_NAME_INVALID)
                ->exists('machine_name', 'virtual_machine', 'name', self::ERR_VM_NOT_FOUND);

            if ($validator->hasErrors()) {
                return "<div style='color: red;'>Error: " . htmlspecialchars($validator->getFirstError()) . "</div>";
            }
            
            $machine_name = $validator->get('machine_name');

            // Remove from database (with cascading delete of related data)
            $deleted_rows = \config::$db->delete("virtual_machine", ["name" => $machine_name]);
            
            if ($deleted_rows > 0) {
                // Remove related network interfaces
                \config::$db->delete("network_interface", ["machine_name" => $machine_name]);

                // Remove related port forwarding
                \config::$db->delete("port_forwarding", ["machine_name" => $machine_name]);
                
                \config::$logger->info("Deleted virtual machine and all related data: {$machine_name}");
                
                return "<div style='color: green; background: #e6ffe6; padding: 15px; border: 1px solid #00ff00; margin-bottom: 10px;'>" .
                       "<h3>✓ Success!</h3>" .
                       "<p>Virtual machine '<strong>{$machine_name}</strong>' and all related network settings deleted successfully.</p>" .
                       "<p><a href='/?q=machine/manage/list' class='button'>View remaining machines</a></p>" .
                       "</div>";
            } else {
                return "<div style='color: red;'>Error: Failed to delete virtual machine from database</div>";
            }
            
        } catch (\Exception $e) {
            \config::$logger->error("Error deleting VM: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
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
        try {
            $machines = \config::$db->select("virtual_machine", ["*"]);
            
            if (empty($machines)) {
                return "<h3>No virtual machines found</h3><p><a href='/?q=machine/manage/create'>Create your first virtual machine</a></p>";
            }

            $html = "<h3>Virtual Machines</h3>";
            $html .= "<table class='u-full-width data'>";
            $html .= "<thead><tr>";
            $html .= "<th>Name</th><th>Platform</th><th>CPU</th><th>Memory (MB)</th><th>Primary Disk</th><th>Network</th><th>Actions</th>";
            $html .= "</tr></thead><tbody>";

            foreach ($machines as $machine) {
                // Get network info
                $network_info = \config::$db->select("network_interface", ["mac", "ip"], ["machine_name" => $machine['name']]);
                $network_display = "No network";
                if (!empty($network_info)) {
                    $net = $network_info[0];
                    $network_display = $net['mac'] . "<br><small>" . ($net['ip'] ?? 'DHCP') . "</small>";
                }
                
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($machine['name']) . "</td>";
                $html .= "<td>" . htmlspecialchars($machine['platform']) . "</td>";
                $html .= "<td>" . htmlspecialchars($machine['cpu']) . "</td>";
                $html .= "<td>" . htmlspecialchars($machine['memory']) . "</td>";
                $html .= "<td>" . htmlspecialchars($machine['hda'] ?? 'None') . "</td>";
                $html .= "<td>" . $network_display . "</td>";
                $html .= "<td>";
                $html .= "<a href='/?q=machine/manage/start/" . urlencode($machine['name']) . "' class='button w120px'>Start</a> ";
                $html .= "<a href='/?q=machine/manage/stop/" . urlencode($machine['name']) . "' class='button w120px'>Stop</a> ";
                $html .= "<a href='/?q=network/manage/edit/" . urlencode($machine['name']) . "' class='button w120px'>Network</a> ";
                $html .= "<a href='/?q=machine/manage/delete/" . urlencode($machine['name']) . "' class='button w120px' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
                $html .= "</td>";
                $html .= "</tr>";
            }

            $html .= "</tbody></table>";
            return $html;
            
        } catch (\Exception $e) {
            \config::$logger->error("Error listing virtual machines: " . $e->getMessage());
            return "<div style='color: red;'><h3>Error!</h3><p>Failed to load virtual machines: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        }
    }

    public static function create(array $args): string{
        if(empty($_POST)){
            // Показать форму создания ВМ
            $images = image::list_images();
            \config::$logger->info("Images: " . json_encode($images));
            $list_images = "";
            foreach($images as $image){
                $list_images .= "<option value='{$image}'>{$image}</option>";
            }
            return template::load(util::sausage("machine.create", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "disk-image-list" => $list_images,
                    "machine-name-value" => "",
                    "machine-cpu-value" => "1",
                    "machine-ram-value" => "512",
                ])
                ->value();
        }
        
        // Обработать POST данные для создания ВМ
        try {
            // Enhanced validation using centralized Validator
            $validator = new \mc\Validator($_POST);
            
            $validator
                ->required('machine-name', 'Machine name is required')
                ->machineName('machine-name', 'Invalid machine name. Use only letters, numbers, hyphens and underscores')
                ->unique('machine-name', 'virtual_machine', 'name', 'Virtual machine with this name already exists', [])
                ->required('machine-cpu', 'CPU cores are required')
                ->integer('machine-cpu', 'CPU cores must be a number')
                ->range('machine-cpu', 1, 32, 'CPU cores must be between 1 and 32')
                ->required('machine-ram', 'RAM is required')
                ->integer('machine-ram', 'RAM must be a number')
                ->range('machine-ram', 128, 32768, 'RAM must be between 128MB and 32GB')
                ->required('machine-image', 'Please select a disk image')
                ->custom('machine-image', function($value) {
                    $available_images = image::list_images();
                    return in_array($value, $available_images);
                }, 'Selected disk image does not exist');

            if ($validator->hasErrors()) {
                $error_html = "<div style='color: red; background: #ffe6e6; padding: 10px; border: 1px solid #ff0000; margin-bottom: 10px;'>";
                $error_html .= "<h4>Please correct the following errors:</h4><ul>";
                foreach ($validator->getErrors() as $error) {
                    $error_html .= "<li>" . htmlspecialchars($error) . "</li>";
                }
                $error_html .= "</ul></div>";
                
                // Show form again with errors and previously entered values
                $images = image::list_images();
                $list_images = "";
                $machine_image = $validator->get('machine-image', '');
                foreach($images as $image){
                    $selected = ($image === $machine_image) ? "selected" : "";
                    $list_images .= "<option value='{$image}' {$selected}>{$image}</option>";
                }
                
                return $error_html . template::load(util::sausage("machine.create", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                    ->fill([
                        "disk-image-list" => $list_images,
                        "machine-name-value" => htmlspecialchars($validator->get('machine-name', '')),
                        "machine-cpu-value" => htmlspecialchars($validator->get('machine-cpu', '1')),
                        "machine-ram-value" => htmlspecialchars($validator->get('machine-ram', '512')),
                    ])
                    ->value();
            }

            // Extract validated data
            $machine_name = $validator->get('machine-name');
            $machine_cpu = (int)$validator->get('machine-cpu');
            $machine_ram = (int)$validator->get('machine-ram');
            $machine_image = $validator->get('machine-image');

            // Create database record
            $vm_data = [
                'name' => $machine_name,
                'platform' => self::$platform->name, // x86_64
                'hda' => $machine_image,
                'memory' => $machine_ram,
                'cpu' => $machine_cpu,
                'boot' => 'c' // boot from hard disk
            ];

            $vm_id = \config::$db->insert("virtual_machine", $vm_data);
            
            if ($vm_id) {
                // Automatically create network interface for new VM
                try {
                    $network_data = [
                        'machine_name' => $machine_name,
                        'mac' => self::generateRandomMAC(),
                        'ip' => null, // DHCP by default
                        'netmask' => null,
                        'gateway' => null,
                        'dns' => null,
                    ];
                    \config::$db->insert("network_interface", $network_data);
                    \config::$logger->info("Created default network interface for VM: {$machine_name}");
                } catch (\Exception $e) {
                    \config::$logger->warn("Failed to create network interface for VM {$machine_name}: " . $e->getMessage());
                }
                
                \config::$logger->info("Created virtual machine: {$machine_name}");
                return "<div style='color: green; background: #e6ffe6; padding: 15px; border: 1px solid #00ff00; margin-bottom: 10px;'>" .
                       "<h3>✓ Success!</h3>" .
                       "<p>Virtual machine '<strong>{$machine_name}</strong>' has been created successfully.</p>" .
                       "<p><strong>Configuration:</strong></p>" .
                       "<ul>" .
                       "<li>CPU cores: {$machine_cpu}</li>" .
                       "<li>Memory: {$machine_ram} MB</li>" .
                       "<li>Primary disk: {$machine_image}</li>" .
                       "<li>Platform: " . self::$platform->name . "</li>" .
                       "<li>Network: Default interface created</li>" .
                       "</ul>" .
                       "<p>" .
                       "<a href='/?q=machine/manage/list' class='button w120px'>View all machines</a> " .
                       "<a href='/?q=machine/manage/start/{$machine_name}' class='button button-primary w120px'>Start this machine</a> " .
                       "<a href='/?q=network/manage/edit/{$machine_name}' class='button w120px'>Configure Network</a>" .
                       "</p>" .
                       "</div>";
            } else {
                throw new \Exception("Failed to save virtual machine to database");
            }

        } catch (\Exception $e) {
            \config::$logger->error("Error creating virtual machine: " . $e->getMessage());
            return "<div style='color: red; background: #ffe6e6; padding: 15px; border: 1px solid #ff0000; margin-bottom: 10px;'>" .
                   "<h3>✗ Error!</h3>" .
                   "<p>Failed to create virtual machine: " . htmlspecialchars($e->getMessage()) . "</p>" .
                   "<p><a href='/?q=machine/manage/create' class='button'>Try again</a></p>" .
                   "</div>";
        }
    }

    /**
     * Генерация случайного MAC адреса для QEMU
     */
    private static function generateRandomMAC(): string
    {
        $mac = "52:54:00"; // QEMU OUI prefix
        for ($i = 0; $i < 3; $i++) {
            $mac .= ":" . sprintf("%02x", rand(0, 255));
        }
        return $mac;
    }
}