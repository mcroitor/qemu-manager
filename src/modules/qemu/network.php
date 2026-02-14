<?php

namespace qemu;

use \mc\route;
use \mc\template;
use \mc\util;
use \qemu\hardware\network;

class NetworkManager {
    public const MODULE_PATH = __DIR__;
    public const TEMPLATE_PATH = self::MODULE_PATH . \config::sep . "templates";

    // Available network types in QEMU
    public const NETWORK_TYPES = [
        'user' => 'User mode (NAT)',
        'tap' => 'TAP interface',
        'bridge' => 'Bridge interface',
        'none' => 'No network'
    ];

    private static $menu = [
        "list" => "Network Interfaces",
        "create" => "Add Interface",
        "portforward" => "Port Forwarding",
    ];

    private const ALLOWED_COMMANDS = [
        'list',
        'create',
        'edit',
        'delete',
        'portforward',
        'delete_forward',
    ];

    private static function generate_menu(array $menu): string
    {
        $html = "";
        foreach ($menu as $key => $value) {
            $html .= template::load(util::sausage("machine.menu-item", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "menu-link" => \config::www . "/?q=network/manage/{$key}",
                    "menu-name" => $value,
                ])
                ->value();
        }
        return $html;
    }

    #[route("network/manage")]
    public static function manage(array $args): string
    {
        if (!\auth::requireRole(\user::ROLE_OPERATOR)) {
            return "<div style='color: red; background: #ffe6e6; padding: 15px; border: 1px solid #ff0000; margin-bottom: 10px;'>" .
                   "<h3>Access denied</h3>" .
                   "<p>You must be authenticated as operator or admin to access Network Settings.</p>" .
                   "<p><a href='/?q=auth/login' class='button button-primary'>Login</a></p>" .
                   "</div>";
        }

        $command = "list";
        if (count($args) > 0) {
            $command = $args[0];
            array_shift($args);
        }

        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            $command = 'list';
        }

        $result = "";
        if (method_exists(self::class, $command)) {
            $result = self::$command($args);
        }
            
        return template::load(\config::templates_dir . \config::sep . "ui" . \config::sep . "module-manager.tpl.php", template::comment_modifiers)
            ->fill([
                "module-state" => self::getNetworkState(),
                "module-content" => $result,
                "menu-list" => self::generate_menu(self::$menu),
            ])
            ->value();
    }

    /**
     * Display list of network interfaces
     */
    private static function list(array $args): string
    {
        try {
            $interfaces = \config::$db->select("network_interface", ["*"]);
            
            if (empty($interfaces)) {
                return template::load(util::sausage("network.list-empty", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                    ->value();
            }

            $rows = "";

            foreach ($interfaces as $interface) {
                $rows .= template::load(util::sausage("network.list-row", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                    ->fill([
                        "machine-name" => htmlspecialchars($interface['machine_name']),
                        "mac-address" => htmlspecialchars($interface['mac']),
                        "ip-address" => htmlspecialchars($interface['ip'] ?? 'DHCP'),
                        "netmask" => htmlspecialchars($interface['netmask'] ?? 'Auto'),
                        "gateway" => htmlspecialchars($interface['gateway'] ?? 'Auto'),
                        "dns" => htmlspecialchars($interface['dns'] ?? 'Auto'),
                        "machine-name-url" => urlencode($interface['machine_name']),
                    ])
                    ->value();
            }

            return template::load(util::sausage("network.list", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "network-rows" => $rows,
                ])
                ->value();
            
        } catch (\Exception $e) {
            \config::$logger->error("Error listing network interfaces: " . $e->getMessage());
            return template::load(util::sausage("network.list-error", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                ->fill([
                    "error-message" => htmlspecialchars($e->getMessage()),
                ])
                ->value();
        }
    }

    /**
     * Создание нового сетевого интерфейса
     */
    private static function create(array $args): string
    {
        if (empty($_POST)) {
            // Показать форму создания
            $machines = \config::$db->select("virtual_machine", ["name"]);
            $machine_options = "";
            foreach ($machines as $machine) {
                $safe_name = htmlspecialchars($machine['name']);
                $machine_options .= "<option value='{$safe_name}'>{$safe_name}</option>";
            }
            
            $network_types = "";
            foreach (self::NETWORK_TYPES as $type => $description) {
                $safe_type = htmlspecialchars($type);
                $safe_description = htmlspecialchars($description);
                $network_types .= "<option value='{$safe_type}'>{$safe_description}</option>";
            }

            $network_adapters = "";
            foreach (network::cases() as $adapter) {
                $selected = ($adapter === network::virtio_net_pci) ? "selected" : "";
                $safe_adapter = htmlspecialchars($adapter->value);
                $network_adapters .= "<option value='{$safe_adapter}' {$selected}>{$safe_adapter}</option>";
            }

            return self::renderNetworkForm([
                "machine-options" => $machine_options,
                "network-types" => $network_types,
                "network-adapters" => $network_adapters,
                "form-title" => "Add Network Interface",
                "form-action" => "/?q=network/manage/create",
                "submit-text" => "Create Interface",
                "mac-value" => self::generateRandomMAC(),
                "ip-value" => "",
                "netmask-value" => "255.255.255.0",
                "gateway-value" => "",
                "dns-value" => "",
            ]);
        }

        // Обработка POST данных
        return self::processNetworkForm();
    }

    /**
     * Редактирование сетевого интерфейса
     */
    private static function edit(array $args): string
    {
        if (empty($args[0])) {
            return "<div style='color: red;'>Error: Machine name not specified</div>";
        }

        $machine_name = filter_var($args[0], FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (empty($_POST)) {
            // Показать форму редактирования
            try {
                $interface = \config::$db->select("network_interface", ["*"], ["machine_name" => $machine_name]);
                
                if (empty($interface)) {
                    return "<div style='color: red;'>Network interface for machine '{$machine_name}' not found</div>";
                }

                $interface = $interface[0];
                
                $machines = \config::$db->select("virtual_machine", ["name"]);
                $machine_options = "";
                foreach ($machines as $machine) {
                    $selected = ($machine['name'] === $machine_name) ? "selected" : "";
                    $safe_name = htmlspecialchars($machine['name']);
                    $machine_options .= "<option value='{$safe_name}' {$selected}>{$safe_name}</option>";
                }
                
                $network_types = "";
                foreach (self::NETWORK_TYPES as $type => $description) {
                    $safe_type = htmlspecialchars($type);
                    $safe_description = htmlspecialchars($description);
                    $network_types .= "<option value='{$safe_type}'>{$safe_description}</option>";
                }

                $network_adapters = "";
                foreach (network::cases() as $adapter) {
                    $safe_adapter = htmlspecialchars($adapter->value);
                    $network_adapters .= "<option value='{$safe_adapter}'>{$safe_adapter}</option>";
                }

                return self::renderNetworkForm([
                    "machine-options" => $machine_options,
                    "network-types" => $network_types,
                    "network-adapters" => $network_adapters,
                    "form-title" => "Edit Network Interface",
                    "form-action" => "/?q=network/manage/edit/{$machine_name}",
                    "submit-text" => "Update Interface",
                    "mac-value" => $interface['mac'],
                    "ip-value" => $interface['ip'] ?? "",
                    "netmask-value" => $interface['netmask'] ?? "255.255.255.0",
                    "gateway-value" => $interface['gateway'] ?? "",
                    "dns-value" => $interface['dns'] ?? "",
                ]);
                
            } catch (\Exception $e) {
                return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Обработка POST данных для редактирования
        return self::processNetworkForm($machine_name);
    }

    /**
     * Удаление сетевого интерфейса
     */
    private static function delete(array $args): string
    {
        if (empty($args[0])) {
            return "<div style='color: red;'>Error: Machine name not specified</div>";
        }

        $machine_name = filter_var($args[0], FILTER_SANITIZE_SPECIAL_CHARS);
        
        try {
            $deleted = \config::$db->delete("network_interface", ["machine_name" => $machine_name]);
            
            \config::$logger->info("Deleted network interface for machine: {$machine_name}");
            return "<div style='color: green;'>Network interface for '{$machine_name}' deleted successfully. <a href='/?q=network/manage/list'>Back to list</a></div>";
            
        } catch (\Exception $e) {
            \config::$logger->error("Error deleting network interface: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Управление port forwarding
     */
    private static function portforward(array $args): string
    {
        if (empty($_POST)) {
            // Показать список и форму для port forwarding
            try {
                $forwards = \config::$db->select("port_forwarding", ["*"]);
                
                $html = "<h3>Port Forwarding Rules</h3>";
                
                if (!empty($forwards)) {
                    $html .= "<table class='u-full-width data'>";
                    $html .= "<thead><tr>";
                    $html .= "<th>VM Name</th><th>Protocol</th><th>Host Port</th><th>Guest Port</th><th>Guest IP</th><th>Actions</th>";
                    $html .= "</tr></thead><tbody>";

                    foreach ($forwards as $forward) {
                        $html .= template::load(util::sausage("network.portforward-row", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                            ->fill([
                                "machine-name" => htmlspecialchars($forward['machine_name']),
                                "protocol" => htmlspecialchars($forward['protocol']),
                                "host-port" => htmlspecialchars((string)$forward['host_port']),
                                "guest-port" => htmlspecialchars((string)$forward['guest_port']),
                                "guest-ip" => htmlspecialchars($forward['guest_ip'] ?? 'Default'),
                                "machine-name-url" => urlencode($forward['machine_name']),
                            ])
                            ->value();
                    }
                    $html .= "</tbody></table><br>";
                }

                // Форма для добавления нового правила
                $machines = \config::$db->select("virtual_machine", ["name"]);
                $machine_options = "";
                foreach ($machines as $machine) {
                    $safe_name = htmlspecialchars($machine['name']);
                    $machine_options .= "<option value='{$safe_name}'>{$safe_name}</option>";
                }

                $portforward_form = template::load(util::sausage("network.portforward-form", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                    ->fill([
                        "machine-options" => $machine_options,
                    ])
                    ->value();

                return template::load(util::sausage("network.portforward-manager", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
                    ->fill([
                        "portforward-table" => $html,
                        "portforward-form" => $portforward_form,
                    ])
                    ->value();
                
            } catch (\Exception $e) {
                return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Обработка добавления port forwarding
        return self::processPortForwardForm();
    }

    /**
     * Обработка формы сетевых настроек
     */
    private static function processNetworkForm(string $edit_machine = ""): string
    {
        try {
            $machine_name = filter_input(INPUT_POST, "machine_name", FILTER_SANITIZE_SPECIAL_CHARS);
            $mac_address = filter_input(INPUT_POST, "mac_address", FILTER_SANITIZE_SPECIAL_CHARS);
            $ip_address = filter_input(INPUT_POST, "ip_address", FILTER_SANITIZE_SPECIAL_CHARS);
            $netmask = filter_input(INPUT_POST, "netmask", FILTER_SANITIZE_SPECIAL_CHARS);
            $gateway = filter_input(INPUT_POST, "gateway", FILTER_SANITIZE_SPECIAL_CHARS);
            $dns = filter_input(INPUT_POST, "dns", FILTER_SANITIZE_SPECIAL_CHARS);

            // Валидация
            $errors = [];
            
            if (empty($machine_name)) {
                $errors[] = "Please select a virtual machine";
            }
            
            if (empty($mac_address) || !self::isValidMAC($mac_address)) {
                $errors[] = "Invalid MAC address format";
            }
            
            if (!empty($ip_address) && !filter_var($ip_address, FILTER_VALIDATE_IP)) {
                $errors[] = "Invalid IP address format";
            }
            
            if (!empty($gateway) && !filter_var($gateway, FILTER_VALIDATE_IP)) {
                $errors[] = "Invalid gateway IP address format";
            }

            // Проверка уникальности MAC адреса
            if (!empty($mac_address)) {
                $existing_conditions = ["mac" => $mac_address];
                if (!empty($edit_machine)) {
                    $existing_conditions[] = "machine_name != '{$edit_machine}'";
                }
                
                if (\config::$db->exists("network_interface", $existing_conditions)) {
                    $errors[] = "MAC address already exists";
                }
            }

            if (!empty($errors)) {
                $error_html = "<div style='color: red; background: #ffe6e6; padding: 10px; border: 1px solid #ff0000; margin-bottom: 10px;'>";
                $error_html .= "<h4>Please correct the following errors:</h4><ul>";
                foreach ($errors as $error) {
                    $error_html .= "<li>" . htmlspecialchars($error) . "</li>";
                }
                $error_html .= "</ul></div>";
                return $error_html;
            }

            // Сохранение в БД
            $interface_data = [
                'machine_name' => $machine_name,
                'mac' => $mac_address,
                'ip' => !empty($ip_address) ? $ip_address : null,
                'netmask' => !empty($netmask) ? $netmask : null,
                'gateway' => !empty($gateway) ? $gateway : null,
                'dns' => !empty($dns) ? $dns : null,
            ];

            if (!empty($edit_machine)) {
                // Обновление существующего интерфейса
                \config::$db->update("network_interface", $interface_data, ["machine_name" => $edit_machine]);
                \config::$logger->info("Updated network interface for machine: {$machine_name}");
                $action = "updated";
            } else {
                // Создание нового интерфейса
                \config::$db->insert("network_interface", $interface_data);
                \config::$logger->info("Created network interface for machine: {$machine_name}");
                $action = "created";
            }

            return "<div style='color: green; background: #e6ffe6; padding: 15px; border: 1px solid #00ff00; margin-bottom: 10px;'>" .
                   "<h3>✓ Success!</h3>" .
                   "<p>Network interface for '{$machine_name}' {$action} successfully.</p>" .
                   "<p><a href='/?q=network/manage/list' class='button'>View all interfaces</a></p>" .
                   "</div>";

        } catch (\Exception $e) {
            \config::$logger->error("Error processing network form: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Обработка формы port forwarding
     */
    private static function processPortForwardForm(): string
    {
        try {
            $machine_name = filter_input(INPUT_POST, "machine_name", FILTER_SANITIZE_SPECIAL_CHARS);
            $protocol = filter_input(INPUT_POST, "protocol", FILTER_SANITIZE_SPECIAL_CHARS);
            $host_port = filter_input(INPUT_POST, "host_port", FILTER_SANITIZE_NUMBER_INT);
            $guest_port = filter_input(INPUT_POST, "guest_port", FILTER_SANITIZE_NUMBER_INT);
            $guest_ip = filter_input(INPUT_POST, "guest_ip", FILTER_SANITIZE_SPECIAL_CHARS);

            // Валидация
            $errors = [];
            
            if (empty($machine_name)) {
                $errors[] = "Please select a virtual machine";
            }
            
            if (!in_array($protocol, ['tcp', 'udp'])) {
                $errors[] = "Invalid protocol";
            }
            
            if (empty($host_port) || $host_port < 1 || $host_port > 65535) {
                $errors[] = "Host port must be between 1 and 65535";
            }
            
            if (empty($guest_port) || $guest_port < 1 || $guest_port > 65535) {
                $errors[] = "Guest port must be between 1 and 65535";
            }

            if (!empty($guest_ip) && !filter_var($guest_ip, FILTER_VALIDATE_IP)) {
                $errors[] = "Invalid guest IP address format";
            }

            // Проверка уникальности правила
            if (\config::$db->exists("port_forwarding", ["machine_name" => $machine_name, "protocol" => $protocol, "host_port" => $host_port])) {
                $errors[] = "Port forwarding rule already exists";
            }

            if (!empty($errors)) {
                $error_html = "<div style='color: red;'><ul>";
                foreach ($errors as $error) {
                    $error_html .= "<li>" . htmlspecialchars($error) . "</li>";
                }
                $error_html .= "</ul></div>";
                return $error_html;
            }

            // Сохранение в БД
            $forward_data = [
                'machine_name' => $machine_name,
                'protocol' => $protocol,
                'host_port' => $host_port,
                'guest_port' => $guest_port,
                'guest_ip' => !empty($guest_ip) ? $guest_ip : null,
            ];

            \config::$db->insert("port_forwarding", $forward_data);
            \config::$logger->info("Created port forwarding rule: {$machine_name}:{$protocol}:{$host_port}->{$guest_port}");

            return "<div style='color: green;'>Port forwarding rule created successfully!</div>";

        } catch (\Exception $e) {
            \config::$logger->error("Error processing port forward form: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Удаление правила port forwarding
     */
    private static function delete_forward(array $args): string
    {
        if (empty($args[0])) {
            return "<div style='color: red;'>Error: Machine name not specified</div>";
        }

        $machine_name = filter_var($args[0], FILTER_SANITIZE_SPECIAL_CHARS);

        try {
            \config::$db->delete("port_forwarding", ["machine_name" => $machine_name]);
            \config::$logger->info("Deleted port forwarding rule for machine: {$machine_name}");
            return "<div style='color: green;'>Port forwarding rule for '{$machine_name}' deleted successfully. <a href='/?q=network/manage/portforward'>Back to port forwarding</a></div>";
        } catch (\Exception $e) {
            \config::$logger->error("Error deleting port forwarding rule: " . $e->getMessage());
            return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Рендеринг формы сетевых настроек
     */
    private static function renderNetworkForm(array $data): string
    {
        return template::load(util::sausage("network.form", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
            ->fill([
                "form-title" => htmlspecialchars($data['form-title']),
                "form-action" => htmlspecialchars($data['form-action']),
                "machine-options" => $data['machine-options'],
                "mac-value" => htmlspecialchars($data['mac-value']),
                "ip-value" => htmlspecialchars($data['ip-value']),
                "netmask-value" => htmlspecialchars($data['netmask-value']),
                "gateway-value" => htmlspecialchars($data['gateway-value']),
                "dns-value" => htmlspecialchars($data['dns-value']),
                "submit-text" => htmlspecialchars($data['submit-text']),
            ])
            ->value();
    }

    /**
     * Получение состояния сети
     */
    private static function getNetworkState(): string
    {
        try {
            $interface_rows = \config::$db->select("network_interface", ["count(*) as count"]);
            $forward_rows = \config::$db->select("port_forwarding", ["count(*) as count"]);

            $interface_count = empty($interface_rows) ? 0 : (int)$interface_rows[0]['count'];
            $forward_count = empty($forward_rows) ? 0 : (int)$forward_rows[0]['count'];
            
            return "Network interfaces: {$interface_count}, Port forwarding rules: {$forward_count}";
        } catch (\Exception $e) {
            return "Error getting network state";
        }
    }

    /**
     * Генерация случайного MAC адреса
     */
    private static function generateRandomMAC(): string
    {
        $mac = "52:54:00"; // QEMU OUI prefix
        for ($i = 0; $i < 3; $i++) {
            $mac .= ":" . sprintf("%02x", rand(0, 255));
        }
        return $mac;
    }

    /**
     * Валидация MAC адреса
     */
    private static function isValidMAC(string $mac): bool
    {
        return preg_match('/^[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}$/', $mac);
    }

    /**
     * Получение сетевых параметров для QEMU команды
     */
    public static function getNetworkArgsForVM(string $machine_name): string
    {
        try {
            $interface = \config::$db->select("network_interface", ["*"], ["machine_name" => $machine_name]);
            
            if (empty($interface)) {
                // Стандартные сетевые настройки
                return "-netdev user,id=net0 -device virtio-net-pci,netdev=net0";
            }

            $interface = $interface[0];
            $args = "-netdev user,id=net0";
            
            // Добавить port forwarding если есть
            $forwards = \config::$db->select("port_forwarding", ["*"], ["machine_name" => $machine_name]);
            foreach ($forwards as $forward) {
                $args .= ",hostfwd={$forward['protocol']}::{$forward['host_port']}-:{$forward['guest_port']}";
            }
            
            $args .= " -device virtio-net-pci,netdev=net0,mac={$interface['mac']}";
            
            return $args;
            
        } catch (\Exception $e) {
            \config::$logger->error("Error getting network args: " . $e->getMessage());
            return "-netdev user,id=net0 -device virtio-net-pci,netdev=net0";
        }
    }
}