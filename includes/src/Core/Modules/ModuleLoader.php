<?php

declare(strict_types=1);

namespace App\Core\Modules;

use App\Core\Container\Container;
use App\Core\Events\Listener;
use RuntimeException;

final class ModuleLoader
{
    /**
     * @param array<int,string> $requiredKeys
     */
    public function __construct(
        private ?Container $container = null,
        private array $requiredKeys = ['id', 'name', 'version', 'enabled', 'routes'],
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function load(string $moduleDirectory): array
    {
        $moduleFile = $this->moduleFile($moduleDirectory);
        if (!is_file($moduleFile)) {
            error_log('Module metadata file missing: ' . $moduleFile);
            throw new RuntimeException('Module metadata file not found: ' . $moduleFile);
        }

        $metadata = require $moduleFile;
        if (!is_array($metadata)) {
            error_log('Module metadata did not return an array: ' . $moduleFile);
            throw new RuntimeException('Module metadata must return an array: ' . $moduleFile);
        }

        $metadata['module_path'] = rtrim($moduleDirectory, DIRECTORY_SEPARATOR);
        $metadata['module_file'] = $moduleFile;
        $metadata['routes'] = (string) $metadata['routes'];
        $metadata['requires'] = isset($metadata['requires']) && is_array($metadata['requires']) ? $metadata['requires'] : [];
        $metadata['requires_modules'] = isset($metadata['requires_modules']) && is_array($metadata['requires_modules']) ? $metadata['requires_modules'] : [];
        $metadata['permissions'] = isset($metadata['permissions']) && is_array($metadata['permissions']) ? $metadata['permissions'] : [];
        $metadata['config'] = isset($metadata['config']) && is_array($metadata['config']) ? $metadata['config'] : [];
        $metadata['events'] = isset($metadata['events']) && is_array($metadata['events']) ? $metadata['events'] : [];
        $metadata['migrations'] = isset($metadata['migrations']) ? (string) $metadata['migrations'] : '';
        $metadata['lang'] = isset($metadata['lang']) ? (string) $metadata['lang'] : '';
        $metadata['admin'] = isset($metadata['admin']) && is_array($metadata['admin']) ? $metadata['admin'] : [];

        $this->assertRequiredKeys($metadata, $moduleFile);

        return $metadata;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function discover(string $modulesRoot): array
    {
        if (!is_dir($modulesRoot)) {
            return [];
        }

        $modules = [];
        foreach (glob(rtrim($modulesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $moduleDirectory) {
            $metadata = $this->load($moduleDirectory);
            $modules[(string) $metadata['id']] = $metadata;
        }

        return $modules;
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array{requires:array<string,mixed>,requires_modules:array<string,mixed>}
     */
    public function resolveDependencies(array $metadata): array
    {
        return [
            'requires' => isset($metadata['requires']) && is_array($metadata['requires']) ? $metadata['requires'] : [],
            'requires_modules' => isset($metadata['requires_modules']) && is_array($metadata['requires_modules']) ? $metadata['requires_modules'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,list<callable|Listener|string>>
     */
    public function eventListeners(array $metadata): array
    {
        $events = $metadata['events'] ?? [];
        if (!is_array($events)) {
            return [];
        }

        $normalized = [];
        foreach ($events as $eventName => $listeners) {
            if (is_int($eventName) && is_array($listeners)) {
                $name = trim((string) ($listeners['event'] ?? ''));
                $target = $listeners['listener'] ?? ($listeners['listeners'] ?? null);
                if ($name !== '' && $target !== null) {
                    $this->appendEventListeners($normalized, $name, $target);
                }

                continue;
            }

            $name = trim((string) $eventName);
            if ($name === '') {
                continue;
            }

            $this->appendEventListeners($normalized, $name, $listeners);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function createLifecycle(array $metadata): ?ModuleLifecycle
    {
        $lifecycle = $metadata['lifecycle'] ?? null;
        if ($lifecycle === null || $lifecycle === [] || $lifecycle === '') {
            return null;
        }

        $class = is_array($lifecycle) ? (string) ($lifecycle['handler'] ?? '') : (string) $lifecycle;
        if ($class === '') {
            return null;
        }

        if (!class_exists($class)) {
            throw new RuntimeException('Module lifecycle handler class not found: ' . $class);
        }

        $instance = $this->container instanceof Container
            ? $this->container->get($class)
            : new $class();

        if (!$instance instanceof ModuleLifecycle) {
            throw new RuntimeException('Module lifecycle handler must implement ModuleLifecycle: ' . $class);
        }

        return $instance;
    }

    public function invokeLifecycle(ModuleLifecycle $lifecycle, string $hook): void
    {
        if (!method_exists($lifecycle, $hook)) {
            throw new RuntimeException('Unsupported lifecycle hook: ' . $hook);
        }

        $lifecycle->{$hook}();
    }

    private function moduleFile(string $moduleDirectory): string
    {
        return rtrim($moduleDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'module.php';
    }

    /**
     * @param array<string,list<callable|Listener|string>> $bucket
     */
    private function appendEventListeners(array &$bucket, string $eventName, mixed $listeners): void
    {
        if (
            is_string($listeners)
            || $listeners instanceof Listener
            || is_callable($listeners)
        ) {
            $listeners = [$listeners];
        }

        if (!is_array($listeners)) {
            return;
        }

        foreach ($listeners as $listener) {
            if (is_string($listener)) {
                $listener = trim($listener);
                if ($listener === '') {
                    continue;
                }

                $bucket[$eventName] ??= [];
                $bucket[$eventName][] = $listener;
                continue;
            }

            if ($listener instanceof Listener || is_callable($listener)) {
                $bucket[$eventName] ??= [];
                $bucket[$eventName][] = $listener;
            }
        }
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function assertRequiredKeys(array $metadata, string $moduleFile): void
    {
        foreach ($this->requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $metadata)) {
                error_log('Module metadata missing required key "' . $requiredKey . '": ' . $moduleFile);
                throw new RuntimeException('Module metadata missing required key "' . $requiredKey . '": ' . $moduleFile);
            }
        }

        if ($metadata['enabled'] !== true && $metadata['enabled'] !== false && !is_callable($metadata['enabled'])) {
            error_log('Module metadata enabled flag must be bool or callable: ' . $moduleFile);
            throw new RuntimeException('Module enabled flag must be bool or callable: ' . $moduleFile);
        }
    }
}
