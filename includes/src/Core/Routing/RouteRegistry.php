<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Modules\ModuleLoader;
use InvalidArgumentException;

final class RouteRegistry
{
    /**
     * @var array<string,array<string,RouteGroup>>
     */
    private array $modules = [];

    /**
     * @var array<string,array{prefix:string,middleware:array<int,Middleware|string>}>
     */
    private array $groupDefinitions;

    /**
     * @param array<string,array{prefix?:string,middleware?:array<int,Middleware|string>}>|null $groupDefinitions
     */
    public function __construct(?array $groupDefinitions = null)
    {
        $this->groupDefinitions = $this->normalizeGroupDefinitions($groupDefinitions ?? $this->defaultGroupDefinitions());
    }

    /**
     * @param array<string,mixed> $routes
     */
    public function registerModuleRoutes(string $moduleId, array $routes): void
    {
        $moduleId = trim($moduleId);
        if ($moduleId === '') {
            throw new InvalidArgumentException('Route registry requires a module id.');
        }

        $this->modules[$moduleId] = $this->normalizeModuleRoutes($moduleId, $routes);
    }

    public function discover(string $modulesRoot): void
    {
        $loader = new ModuleLoader();
        foreach ($loader->discover($modulesRoot) as $moduleId => $metadata) {
            $routesFile = (string) ($metadata['routes'] ?? '');
            if ($routesFile === '' || !is_file($routesFile)) {
                continue;
            }

            $routes = require $routesFile;
            if (!is_array($routes)) {
                continue;
            }

            $this->registerModuleRoutes((string) $moduleId, $routes);
        }
    }

    /**
     * @return array<string,array{prefix:string,middleware:array<int,Middleware|string>}>
     */
    public function groupDefinitions(): array
    {
        return $this->groupDefinitions;
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    public function all(): array
    {
        $catalog = [];
        foreach ($this->modules as $moduleId => $groups) {
            foreach ($groups as $groupName => $group) {
                $catalog[$moduleId][$groupName] = $group->all();
            }
        }

        return $catalog;
    }

    /**
     * @param array<string,mixed> $routes
     * @return array<string,RouteGroup>
     */
    private function normalizeModuleRoutes(string $moduleId, array $routes): array
    {
        $groupNames = array_keys($this->groupDefinitions);
        $groups = [];
        $recognizedGroups = array_intersect_key($routes, array_flip($groupNames));

        if ($recognizedGroups !== []) {
            foreach ($this->groupDefinitions as $groupName => $definition) {
                $group = new RouteGroup($groupName, $definition['prefix'], $definition['middleware']);
                $groupRoutes = $recognizedGroups[$groupName] ?? [];
                if (is_array($groupRoutes)) {
                    $this->appendRoutes($group, $moduleId, $groupName, $groupRoutes);
                }

                $groups[$groupName] = $group;
            }

            return $groups;
        }

        $publicDefinition = $this->groupDefinitions['public'] ?? ['prefix' => '', 'middleware' => []];
        $group = new RouteGroup('public', $publicDefinition['prefix'], $publicDefinition['middleware']);
        $this->appendRoutes($group, $moduleId, 'public', $routes);
        $groups['public'] = $group;

        return $groups;
    }

    /**
     * @param array<string,mixed> $routes
     */
    private function appendRoutes(RouteGroup $group, string $moduleId, string $groupName, array $routes): void
    {
        foreach ($routes as $path => $definition) {
            if (is_string($definition)) {
                $definition = ['target' => $definition];
            }

            if (!is_array($definition)) {
                continue;
            }

            $definition['module'] = $moduleId;
            $definition['group'] = $groupName;
            $definition['path'] = (string) $path;
            $group->add((string) $path, $definition);
        }
    }

    /**
     * @return array<string,array{prefix:string,middleware:array<int,Middleware|string>}>
     */
    private function defaultGroupDefinitions(): array
    {
        return [
            'public' => ['prefix' => '', 'middleware' => []],
            'admin' => ['prefix' => 'admin', 'middleware' => []],
            'api' => ['prefix' => 'api', 'middleware' => []],
        ];
    }

    /**
     * @param array<string,array{prefix?:string,middleware?:array<int,Middleware|string>}> $definitions
     * @return array<string,array{prefix:string,middleware:array<int,Middleware|string>}>
     */
    private function normalizeGroupDefinitions(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $name => $definition) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $prefix = trim((string) ($definition['prefix'] ?? ''), "/\\ \t\n\r\0\x0B");
            if ($name !== 'public' && $prefix === '') {
                $prefix = $name;
            }

            $middleware = $definition['middleware'] ?? [];
            if (!is_array($middleware)) {
                $middleware = [];
            }

            $normalized[$name] = [
                'prefix' => $prefix,
                'middleware' => $this->normalizeMiddlewareList($middleware),
            ];
        }

        if (!isset($normalized['public'])) {
            $normalized = ['public' => ['prefix' => '', 'middleware' => []]] + $normalized;
        }

        return $normalized;
    }

    /**
     * @param array<int,mixed> $middleware
     * @return array<int,Middleware|string>
     */
    private function normalizeMiddlewareList(array $middleware): array
    {
        $normalized = [];

        foreach ($middleware as $candidate) {
            if ($candidate instanceof Middleware) {
                $normalized[] = $candidate;
                continue;
            }

            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate !== '') {
                $normalized[] = $candidate;
            }
        }

        return array_values($normalized);
    }
}
