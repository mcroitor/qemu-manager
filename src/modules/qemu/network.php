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
        $command = "list";
        if (count($args) > 0) {
            $command = $args[0];
            array_shift($args);
        }

        $result = "";
        if (method_exists(self::class, $command)) {
            $result = self::$command($args);
        }
            
        return template::load(util::sausage("machine.manager", "tpl.php", self::TEMPLATE_PATH), template::comment_modifiers)
            ->fill([
                "machine-state" => self::getNetworkState(),
                "machine-content" => $result,
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
                return "<h3>No network interfaces configured</h3>" .
                       "<p><a href='/?q=network/manage/create' class='button button-primary'>Create first interface</a></p>";
            }

            $html = "<h3>Network Interfaces</h3>";
            $html .= "<table class='u-full-width data'>";
            $html .= "<thead><tr>";
            $html .= "<th>VM Name</th><th>MAC Address</th><th>IP Address</th><th>Netmask</th><th>Gateway</th><th>DNS</th><th>Actions</th>";
            $html .= "</tr></thead><tbody>";

            foreach ($interfaces as $interface) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($interface['machine_name']) . "</td>";
                $html .= "<td>" . htmlspecialchars($interface['mac']) . "</td>";
                $html .= "<td>" . htmlspecialchars($interface['ip'] ?? 'DHCP') . "</td>";
                $html .= "<td>" . htmlspecialchars($interface['netmask'] ?? 'Auto') . "</td>";
                $html .= "<td>" . htmlspecialchars($interface['gateway'] ?? 'Auto') . "</td>";
                $html .= "<td>" . htmlspecialchars($interface['dns'] ?? 'Auto') . "</td>";
                $html .= "<td>";
                $html .= "<a href='/?q=network/manage/edit/" . urlencode($interface['machine_name']) . "' class='button w120px'>Edit</a> ";
                $html .= "<a href='/?q=network/manage/delete/" . urlencode($interface['machine_name']) . "' class='button w120px' onclick='return confirm(\"Delete interface?\")'>Delete</a>";
                $html .= "</td>";
                $html .= "</tr>";
            }

            $html .= "</tbody></table>";
            $html .= "<p><a href='/?q=network/manage/create' class='button button-primary'>Add New Interface</a></p>";
            
            return $html;
            
        } catch (\Exception $e) {
            \config::$logger->error("Error listing network interfaces: " . $e->getMessage());
            return "<div style='color: red;'>Error loading network interfaces: " . htmlspecialchars($e->getMessage()) . "</div>";
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
                $machine_options .= "<option value='{$machine['name']}'>{$machine['name']}</option>";
            }
            
            $network_types = "";
            foreach (self::NETWORK_TYPES as $type => $description) {
                $network_types .= "<option value='{$type}'>{$description}</option>";
            }

            $network_adapters = "";
            foreach (network::cases() as $adapter) {
                $selected = ($adapter === network::virtio_net_pci) ? "selected" : "";
                $network_adapters .= "<option value='{$adapter->value}' {$selected}>{$adapter->value}</option>";
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
                    $machine_options .= "<option value='{$machine['name']}' {$selected}>{$machine['name']}</option>";
                }
                
                $network_types = "";
                foreach (self::NETWORK_TYPES as $type => $description) {
                    $network_types .= "<option value='{$type}'>{$description}</option>";
                }

                $network_adapters = "";
                foreach (network::cases() as $adapter) {
                    $network_adapters .= "<option value='{$adapter->value}'>{$adapter->value}</option>";
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
                        $html .= "<tr>";
                        $html .= "<td>" . htmlspecialchars($forward['machine_name']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($forward['protocol']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($forward['host_port']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($forward['guest_port']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($forward['guest_ip'] ?? 'Default') . "</td>";
                        $html .= "<td>";
                        $html .= "<a href='/?q=network/manage/delete_forward/" . urlencode($forward['machine_name']) . "' class='button' onclick='return confirm(\"Delete rule?\")'>Delete</a>";
                        $html .= "</td>";
                        $html .= "</tr>";
                    }
                    $html .= "</tbody></table><br>";
                }

                // Форма для добавления нового правила
                $machines = \config::$db->select("virtual_machine", ["name"]);
                $machine_options = "";
                foreach ($machines as $machine) {
                    $machine_options .= "<option value='{$machine['name']}'>{$machine['name']}</option>";
                }

                $html .= "<h4>Add Port Forwarding Rule</h4>";
                $html .= "<form method='post'>";
                $html .= "<label>Virtual Machine:</label>";
                $html .= "<select name='machine_name' required>{$machine_options}</select>";
                $html .= "<label>Protocol:</label>";
                $html .= "<select name='protocol' required>";
                $html .= "<option value='tcp'>TCP</option>";
                $html .= "<option value='udp'>UDP</option>";
                $html .= "</select>";
                $html .= "<label>Host Port:</label>";
                $html .= "<input type='number' name='host_port' min='1' max='65535' required>";
                $html .= "<label>Guest Port:</label>";
                $html .= "<input type='number' name='guest_port' min='1' max='65535' required>";
                $html .= "<label>Guest IP (optional):</label>";
                $html .= "<input type='text' name='guest_ip' placeholder='Leave empty for default'>";
                $html .= "<input type='submit' value='Add Rule' class='button button-primary'>";
                $html .= "</form>";

                return $html;
                
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
                    $error_html .= "<li>{$error}</li>";
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
                    $error_html .= "<li>{$error}</li>";
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
     * Рендеринг формы сетевых настроек
     */
    private static function renderNetworkForm(array $data): string
    {
        $html = "<h3>{$data['form-title']}</h3>";
        $html .= "<form action='{$data['form-action']}' method='post'>";
        
        $html .= "<label for='machine_name'>Virtual Machine:</label>";
        $html .= "<select id='machine_name' name='machine_name' required>{$data['machine-options']}</select>";
        
        $html .= "<label for='mac_address'>MAC Address:</label>";
        $html .= "<input type='text' id='mac_address' name='mac_address' value='{$data['mac-value']}' ";
        $html .= "pattern='[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}' ";
        $html .= "title='Format: AA:BB:CC:DD:EE:FF' required>";
        
        $html .= "<label for='ip_address'>IP Address (leave empty for DHCP):</label>";
        $html .= "<input type='text' id='ip_address' name='ip_address' value='{$data['ip-value']}' placeholder='192.168.1.100'>";
        
        $html .= "<label for='netmask'>Netmask:</label>";
        $html .= "<input type='text' id='netmask' name='netmask' value='{$data['netmask-value']}' placeholder='255.255.255.0'>";
        
        $html .= "<label for='gateway'>Gateway:</label>";
        $html .= "<input type='text' id='gateway' name='gateway' value='{$data['gateway-value']}' placeholder='192.168.1.1'>";
        
        $html .= "<label for='dns'>DNS Server:</label>";
        $html .= "<input type='text' id='dns' name='dns' value='{$data['dns-value']}' placeholder='8.8.8.8'>";
        
        $html .= "<input type='submit' value='{$data['submit-text']}' class='button button-primary'>";
        $html .= "<a href='/?q=network/manage/list' class='button'>Cancel</a>";
        $html .= "</form>";
        
        return $html;
    }

    /**
     * Получение состояния сети
     */
    private static function getNetworkState(): string
    {
        try {
            $interface_count = count(\config::$db->select("network_interface", ["count(*) as count"]));
            $forward_count = count(\config::$db->select("port_forwarding", ["count(*) as count"]));
            
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