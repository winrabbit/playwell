<?php
/**
 * Playwell Framework (http://playwell.jun.cloud)
 *
 * @link      https://github.com/winrabbit/playwell
 * @copyright Copyright (c) 2016-2018 jun wang
 * @license   https://github.com/winrabbit/playwell/blob/master/LICENSE.md (MIT License)
 */

namespace Playwell;

use \Exception;
use \Closure;
use \InvalidArgumentException;
use \Pimple\Container;
use \Dotenv\Dotenv;

Class App
{

    /**
     * Current version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Container
     *
     * @var Container
     */
    private $container;

    /**
     * routes
     *
     * @var routes
     */
    private $routes = array();

    /**
     * Create new application
     *
     * @param ContainerInterface|array $container Either a ContainerInterface or an associative array of app settings
     * @throws InvalidArgumentException when no container is provided that implements ContainerInterface
     */
    public function __construct()
    {
        $this->container = new Container();
    }

    /**
     * Enable access to the DI container by consumers of $app
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    public function __get($property)
    {
        if (isset($this->container[$property])) {
            $obj = $this->container[$property];
            if (! $obj instanceof Closure) {
                return $obj;
            }
        }

        return null;
    }

    public function __call($method, $args)
    {
        if (isset($this->container[$method])) {
            $obj = $this->container[$method];
            if (!is_callable($obj)) {
                return $obj;
            }

            return call_user_func_array($obj, $args);
        }

        throw new \BadMethodCallException("Method $method is not a valid method");
    }

    public function route(array $routes) {
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        foreach($routes as $route) {
            if (empty($route[0]) || $route[0] == 'ANY') {
                $route[0] = $allowedMethods;
            }
            if (is_string($route[0]) && !in_array($route[0], $allowedMethods)) {
                continue;
            }

            $this->routes[] = [
                'method'    => $route[0],
                'pattern'   => $route[1],
                'target'    => $route[2],
            ];
        }
    }

    public function start($root='')
    {
        $root = $root ? : dirname(__DIR__);
        $this->container['root_path'] = $root;
        if (!isset($this->container['config_path'])) {
            $this->container['config_path'] = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config';
            $configFile = $this->container['config_path'] . DIRECTORY_SEPARATOR . 'main.php';
            if ( file_exists($configFile) ) {
                $this->container['config'] = include($configFile);
            }
        }
        if (!isset($this->container['controller_path'])) {
            $this->container['controller_path'] = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'controllers';
        }
        if (!isset($this->container['model_path'])) {
            $this->container['model_path'] = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'models';
        }

        if (file_exists($root . DIRECTORY_SEPARATOR . '.env')) {
            $env = new \Dotenv\Dotenv($this->container['root_path']);
            $env->load();
        }

        // Fetch method and URI from somewhere
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        $dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
            foreach($this->routes as $route) {
                $r->addRoute($route['method'], $route['pattern'], $route['target']);
            }
        });

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
        if ($routeInfo[0] == \FastRoute\Dispatcher::NOT_FOUND) {
            $controllerFile = $this->container['controller_path'] . $uri . '.php';
            if ( file_exists($controllerFile) ) {
                $app = $this;
                $this->container['method']  = $httpMethod;
                $render = function ($controller) use ($app) {
                    include $controller;
                };

                echo $render($controllerFile);

                return;
            }

            echo '404 Not Found';
            return;
        }

        switch ($routeInfo[0]) {
        case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            echo '405 Method Not Allowed';
            break;
        case \FastRoute\Dispatcher::FOUND:
            $handler    = $routeInfo[1];
            $this->container['method']  = $httpMethod;
            $this->container['vars']    = $routeInfo[2];

            $controllerFile = $this->container['controller_path'] . DIRECTORY_SEPARATOR . $handler;
            if ( file_exists($controllerFile) ) {
                $app = $this;
                $render = function ($controller) use ($app) {
                    include $controller;
                };

                echo $render($controllerFile);

            } else {
                echo '404 Not Found';
                return;
            }
        }

    }

}
