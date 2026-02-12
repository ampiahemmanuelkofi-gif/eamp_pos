<?php

class Router
{
    private $routes = [];

    public function add($method, $path, $callback)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }

    public function dispatch($uri)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Strip query string and base path
        $basePath = '/eamp_pos/';
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = explode('?', $uri)[0];

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->match($route['path'], $uri)) {
                return call_user_func($route['callback']);
            }
        }

        // 404
        http_response_code(404);
        echo "404 Not Found";
    }

    private function match($pattern, $uri)
    {
        // Simple exact match for now, serve as MVP
        return $pattern === $uri;
        // In future: Regex matching for parameters e.g. /user/{id}
    }
}
?>