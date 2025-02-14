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

$page->menu([
    "?q=image/manage" => "Images",
    "?q=machine/manage" => "Virtual Machines",
]);

$page->content(\mc\router::run());

$settings = \config::$db->select("settings", ["*"], ["name like 'site-%'"]);

$fill = [];
foreach ($settings as $setting) {
    $fill[$setting["name"]] = $setting["value"];
}

$page->data($fill);

echo $page->html();
