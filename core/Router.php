<?php
/** Explicit route map. Never derives an include path from untrusted input. */
class Router
{
    private $routes = array();
    public function add($methods, $pattern, $handler) { $this->routes[] = array($methods, $pattern, $handler); }
    public function resolve($method, $path)
    {
        $allowed = array();
        foreach ($this->routes as $route) {
            if (!preg_match($route[1], $path, $matches)) { continue; }
            if (!in_array($method, $route[0], true)) { $allowed = array_merge($allowed, $route[0]); continue; }
            return array('handler' => $route[2], 'params' => array_slice($matches, 1));
        }
        return array('status' => $allowed ? 405 : 404, 'allow' => array_unique($allowed));
    }
    public function dispatch($method, $path)
    {
        $match = $this->resolve($method, $path);
        if (isset($match['handler'])) { call_user_func_array($match['handler'], $match['params']); return; }
        http_response_code($match['status']);
        if ($match['allow']) { header('Allow: ' . implode(', ', $match['allow'])); echo 'Method not allowed.'; return; }
        require BASE_PATH . '/404.php';
    }
}
