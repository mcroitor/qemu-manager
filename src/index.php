<?php

if (!file_exists(__DIR__ . "/config.php")) {
    echo "<h2>site is not installed!</h2>";
    exit();
}
include_once __DIR__ . "/config.php";

$routes = [];

// register routes
\mc\router::init($routes);

$page = new page();

$menu = [];

if (\auth::needsBootstrapAdmin()) {
    $menu["?q=auth/bootstrap-admin"] = "Bootstrap Admin";
}

if (\auth::isAuthenticated()) {
    $username = htmlspecialchars((string)\auth::currentUsername());

    $menu["?q=image/manage"] = "Images";
    $menu["?q=machine/manage"] = "Virtual Machines";
    $menu["?q=network/manage"] = "Network Settings";

    $menu["?q=auth/logout"] = "Logout ({$username})";
} else {
    $menu["?q=auth/login"] = "Login";
    $menu["?q=auth/register"] = "Register";
}

$page->menu($menu);

$page->content(\mc\router::run());

$settings = \config::$db->select("settings", ["*"], ["name like 'site-%'"]);

$fill = [];
foreach ($settings as $setting) {
    $fill[$setting["name"]] = $setting["value"];
}

$page->data($fill);

echo $page->html();
