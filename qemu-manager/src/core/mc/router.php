<?php

namespace mc;

use Exception;

/**
 * this router class is based on $_GET
 * <URL> ::= http[s]://<domain>/?<route-name>[/params]
 */
class router {

    private const ATTRIBUTE_NAME = \mc\route::class;

    private static $routes = [];
    private static $param = "q";
    private static $default = "/";
    private static $selectedRoute = "/";

    /**
     * set routes
     * @param array $routes
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
     * scan classes. select all static methods and check attributes
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
     * scan functions. check attributes
     */
    private static function scan_functions(): void {
        $functions = \get_defined_functions();
        foreach ($functions['user'] as $function) {
            $reflection = new \ReflectionFunction($function);
            self::register_method($reflection);
        }
    }

    /**
     * if method or function has `route` attribute, register it
     * @param \ReflectionFunction $reflection
     */
    private static function register_method($reflection): void {
        $attribute = self::get_method_attribute($reflection, self::ATTRIBUTE_NAME);
        if ($attribute != null) {
            $route = $attribute->getArguments()[0];
            self::register($route, $reflection->getClosure());
        }
    }

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
     * load routes from JSON file
     */
    public static function load(string $jsonfile = "routes.json"): void {
        $routes = json_decode(file_get_contents($jsonfile));
        self::init((array) $routes);
    }

    /**
     * register a new route.
     * If $route_method is null, the $route_name will be
     */
    public static function register(string $route_name, callable $route_method): void {
        if (is_callable($route_method) === false) {
            throw new Exception("`{$route_method}` is not callable");
        }
        self::$routes[$route_name] = $route_method;
    }

    /**
     * rewrite default param name
     */
    public static function set_param(string $param): void {
        self::$param = $param;
    }

    /**
     * entry point for routing!
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
     * get list of routes. if $needle is not empty, return only routes 
     * that start with $needle
     * @param string $needle
     * @return array
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
     * get current route
     * @return string
     */
    public static function get_selected_route(): string {
        return self::$selectedRoute;
    }
}
