<?php

namespace mc;

use Exception;

/**
 * GET-based router with attribute discovery support.
 *
 * URL format:
 * http[s]://<domain>/?<route-name>[/params]
 */
class router {

    private const ATTRIBUTE_NAME = \mc\route::class;

    private static $routes = [];
    private static $param = "q";
    private static $default = "/";
    private static $selectedRoute = "/";

    /**
        * Initializes built-in and discovered routes.
        *
        * @param array<string, callable> $routes Additional route callbacks.
        * @return void
     */
    public static function init(array $routes = []): void {
        self::$routes[self::$default] = function (): string {
            return "";
        };
        self::scan_classes();
        self::scan_functions();
        foreach ($routes as $route_name => $route_method) {
            self::register($route_name, $route_method);
        }
    }

    /**
     * Scans declared classes for static methods with route attribute.
     *
     * @return void
     */
    private static function scan_classes(): void {
        $classes = \get_declared_classes();
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
            foreach ($methods as $method) {
                self::register_method($method);
            }
        }
    }

    /**
     * Scans user functions for route attribute.
     *
     * @return void
     */
    private static function scan_functions(): void {
        $functions = \get_defined_functions();
        foreach ($functions['user'] as $function) {
            $reflection = new \ReflectionFunction($function);
            self::register_method($reflection);
        }
    }

    /**
     * Registers reflected callable if route attribute is present.
     *
     * @param \ReflectionFunctionAbstract $reflection Function or method reflection.
     * @return void
     */
    private static function register_method($reflection): void {
        $attribute = self::get_method_attribute($reflection, self::ATTRIBUTE_NAME);
        if ($attribute != null) {
            $route = $attribute->getArguments()[0];
            self::register($route, $reflection->getClosure());
        }
    }

    /**
     * Finds a specific attribute on reflected callable.
     *
     * @param \ReflectionFunctionAbstract $method Function or method reflection.
     * @param string $attributeName Fully-qualified attribute class name.
     * @return \ReflectionAttribute|null Matching attribute or null.
     */
    private static function get_method_attribute($method, $attributeName) {
        /** @var \ReflectionAttribute $attributes */
        $attributes = $method->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getName() == $attributeName) {
                return $attribute;
            }
        }
        return null;
    }

    /**
     * Loads routes from a JSON file.
     *
     * @param string $jsonfile JSON file path.
     * @return void
     */
    public static function load(string $jsonfile = "routes.json"): void {
        $routes = json_decode(file_get_contents($jsonfile));
        self::init((array) $routes);
    }

    /**
     * Registers a route callback.
     *
     * @param string $route_name Route key.
     * @param callable $route_method Route callback.
     * @return void
     */
    public static function register(string $route_name, callable $route_method): void {
        if (is_callable($route_method) === false) {
            throw new Exception("`{$route_method}` is not callable");
        }
        self::$routes[$route_name] = $route_method;
    }

    /**
     * Sets GET parameter name used for route lookup.
     *
     * @param string $param GET parameter name.
     * @return void
     */
    public static function set_param(string $param): void {
        self::$param = $param;
    }

    /**
     * Resolves current request and executes matching route callback.
     *
     * @return string Route handler output.
     */
    public static function run(): string {
        $path = filter_input(INPUT_GET, self::$param, FILTER_DEFAULT, ["default" => self::$default]);
        if (empty($path)) {
            $path = self::$default;
        }
        $chunks = explode("/", $path);

        // two-word label
        if (count($chunks) > 1 && isset(self::$routes["{$chunks[0]}/{$chunks[1]}"])) {
            self::$selectedRoute = "{$chunks[0]}/{$chunks[1]}";
            array_shift($chunks);
            array_shift($chunks);

            return self::$routes[self::$selectedRoute]($chunks);
        }

        // one-word label
        if (isset(self::$routes[$chunks[0]])) {
            self::$selectedRoute = $chunks[0];
            array_shift($chunks);

            return self::$routes[self::$selectedRoute]($chunks);
        }
        self::$selectedRoute = self::$default;
        return self::$routes[self::$selectedRoute]([]);
    }

    /**
     * Returns registered routes optionally filtered by prefix.
     *
     * @param string $needle Route prefix filter.
     * @return array<int, string> List of route keys.
     */
    public static function get_routes(string $needle = ""): array {
        $routes = [];
        foreach (self::$routes as $route => $method) {
            if (strpos($route, $needle) === 0) {
                $routes[] = $route;
            }
        }
        return $routes;
    }

    /**
     * Returns selected route for the current request.
     *
     * @return string Selected route key.
     */
    public static function get_selected_route(): string {
        return self::$selectedRoute;
    }
}
