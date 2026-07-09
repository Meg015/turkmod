<?php

declare(strict_types=1);

final class ThemeManager
{
    private const EDITABLE_FILE_MAX_BYTES = 5242880;

    private string $themesDir;
    private string $activeThemeId = 'default';
    private TemplateRenderer $renderer;

    /** @var array<string, array<string, mixed>> */
    private array $manifestCache = [];

    /** @var array<int, string> */
    private array $editableExtensions = ['tpl', 'css', 'js', 'json', 'md', 'txt'];

    /** @var array<int, string> */
    private array $signatureExtensions = [
        'tpl', 'css', 'js', 'json',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'mp3', 'wav', 'ogg', 'mp4', 'webm',
    ];

    private array $themeSettings = [];

    /** @var array<int, string> */
    private array $blockedExtensions = ['php', 'phtml', 'phar', 'htaccess', 'exe', 'bat', 'cmd', 'com', 'ps1', 'sh'];

    public function __construct(
        private string $projectRoot,
        private string $baseUri = '',
        private bool $debug = false,
    ) {
        $this->projectRoot = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        $this->themesDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'themes';
        $this->renderer = new TemplateRenderer($debug);
        $this->renderer->setTemplateResolver(function (string $filename): ?string {
            $path = $this->resolvePath($this->activeThemeId, $this->normalizeRelativePath($filename), true);
            if ($path !== null && is_file($path)) {
                return (string) file_get_contents($path);
            }
            return null;
        });

        // Enable compiled template cache for performance
        $compiledCacheDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'compiled_templates';
        $this->renderer->setCompiledCacheDir($compiledCacheDir);
    }

    public function themesDirectory(): string
    {
        return $this->themesDir;
    }

    public function defaultThemeId(): string
    {
        if ($this->isRenderableTheme('turkmod')) {
            return 'turkmod';
        }

        if (!is_dir($this->themesDir)) {
            return 'default';
        }

        foreach (scandir($this->themesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $themePath = $this->themesDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($themePath)) {
                continue;
            }

            $themeId = $this->sanitizeThemeId($entry);
            if ($themeId === '' || !is_file($themePath . DIRECTORY_SEPARATOR . 'theme.json')) {
                continue;
            }

            if ($this->isRenderableTheme($themeId)) {
                return $themeId;
            }
        }

        return 'default';
    }

    public function setActiveTheme(string $themeId): void
    {
        $themeId = $this->sanitizeThemeId($themeId);
        if ($themeId === '') {
            throw new RuntimeException('Active theme not found: [invalid id]');
        }

        if (!$this->isRenderableTheme($themeId)) {
            $themeId = $this->defaultThemeId();
        }

        if (!$this->isRenderableTheme($themeId)) {
            throw new RuntimeException('Active theme not found: ' . ($themeId !== '' ? $themeId : '[invalid id]'));
        }

        $this->activeThemeId = $themeId;
    }

    public function activeThemeId(): string
    {
        return $this->activeThemeId;
    }

    public function themeSignature(?string $themeId = null): string
    {
        $themeId = $this->sanitizeThemeId($themeId ?: $this->activeThemeId);
        if ($themeId === '' || !$this->themeExists($themeId)) {
            return '';
        }

        $dir = $this->themeDirectory($themeId);
        if (!is_dir($dir)) {
            return '';
        }

        $fingerprints = ['theme:' . $themeId];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                if ($relative === '' || $this->isBackupPath($relative)) {
                    continue;
                }

                $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
                if (!in_array($ext, $this->signatureExtensions, true)) {
                    continue;
                }

                $hash = hash_file('sha256', $file->getPathname());
                $fingerprints[] = $relative . ':' . ($hash !== false ? $hash : 'unreadable');
            }
        } catch (Throwable) {
            return hash('sha256', 'theme:' . $themeId . '|scan-error');
        }

        sort($fingerprints, SORT_STRING);
        return hash('sha256', implode('|', $fingerprints));
    }

    private function isRenderableTheme(string $themeId): bool
    {
        $themeId = $this->sanitizeThemeId($themeId);
        if ($themeId === '' || !$this->themeExists($themeId)) {
            return false;
        }

        try {
            return $this->validateTheme($themeId)['ok'];
        } catch (Throwable) {
            return false;
        }
    }

    public function setThemeSettings(array $settings): void
    {
        $this->themeSettings = $settings;
    }

    public function sanitizeThemeId(string $themeId): string
    {
        $themeId = strtolower(trim($themeId));
        return preg_match('/^[a-z0-9_-]+$/', $themeId) === 1 ? $themeId : '';
    }

    public function themeExists(string $themeId): bool
    {
        $themeId = $this->sanitizeThemeId($themeId);
        return $themeId !== '' && is_dir($this->themeDirectory($themeId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discoverThemes(): array
    {
        if (!is_dir($this->themesDir)) {
            return [];
        }

        $themes = [];
        foreach (scandir($this->themesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $id = $this->sanitizeThemeId($entry);
            if ($id === '' || !is_dir($this->themeDirectory($id))) {
                continue;
            }

            if (!is_file($this->themeDirectory($id) . DIRECTORY_SEPARATOR . 'theme.json')) {
                continue;
            }

            $validation = $this->validateTheme($id);
            $manifest = $validation['manifest'];
            $themes[] = [
                'id' => $id,
                'name' => (string) ($manifest['name'] ?? $id),
                'version' => (string) ($manifest['version'] ?? ''),
                'author' => (string) ($manifest['author'] ?? ''),
                'description' => (string) ($manifest['description'] ?? ''),
                'preview_url' => $this->previewUrl($id, $manifest),
                'active' => $id === $this->activeThemeId,
                'ok' => $validation['ok'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'manifest' => $manifest,
            ];
        }

        usort($themes, static function (array $a, array $b): int {
            if (($a['id'] ?? '') === 'default') {
                return -1;
            }
            if (($b['id'] ?? '') === 'default') {
                return 1;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $themes;
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>, manifest: array<string, mixed>}
     */
    public function validateTheme(string $themeId, bool $deepValidation = false): array
    {
        $themeId = $this->sanitizeThemeId($themeId);
        $errors = [];
        $warnings = [];
        $manifest = [];

        if ($themeId === '') {
            return ['ok' => false, 'errors' => ['Invalid theme id.'], 'warnings' => [], 'manifest' => []];
        }

        $dir = $this->themeDirectory($themeId);
        if (!is_dir($dir)) {
            return ['ok' => false, 'errors' => ['Theme folder not found.'], 'warnings' => [], 'manifest' => []];
        }

        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => [$e->getMessage()], 'warnings' => [], 'manifest' => []];
        }

        foreach (['id', 'name', 'version', 'engine', 'templates'] as $required) {
            if (!array_key_exists($required, $manifest) || $manifest[$required] === '' || $manifest[$required] === []) {
                $errors[] = "theme.json missing required field: {$required}";
            }
        }

        if (($manifest['id'] ?? '') !== $themeId) {
            $errors[] = 'theme.json id must match folder name.';
        }

        if (($manifest['engine'] ?? '') !== 'turkmod-tpl') {
            $warnings[] = 'Theme engine is not turkmod-tpl.';
        }

        $layout = $this->templateRelativePath($manifest, 'layout');
        if ($layout === '' || !$this->fileExistsInsideTheme($themeId, $layout)) {
            $errors[] = 'layout.tpl is required.';
        }

        $templateMap = $this->flattenTemplateMap($manifest['templates'] ?? []);
        foreach ($templateMap as $key => $relativePath) {
            if ($relativePath === '') {
                $errors[] = "Template {$key} is empty.";
                continue;
            }

            if ($this->pathLooksExternal($relativePath)) {
                $errors[] = "Template {$key} must stay inside theme folder: {$relativePath}";
                continue;
            }

            if (!$this->fileExistsInsideTheme($themeId, $relativePath)) {
                $errors[] = "Template {$key} not found: {$relativePath}";
            }
        }

        foreach (['css', 'js'] as $assetType) {
            $assets = $manifest['assets'][$assetType] ?? [];
            if (!is_array($assets)) {
                $errors[] = "assets.{$assetType} must be an array.";
                continue;
            }

            foreach ($assets as $asset) {
                $asset = (string) $asset;
                if ($this->pathLooksExternal($asset)) {
                    $errors[] = "Asset must stay inside theme folder: {$asset}";
                    continue;
                }
                if ($asset === '' || !$this->fileExistsInsideTheme($themeId, $asset)) {
                    $errors[] = "Asset not found: {$asset}";
                }
            }
        }

        if ($deepValidation) {
            $this->validateTemplateFiles($themeId, $errors, $warnings);
            $this->validateCompatAssets($themeId, $warnings);
            $this->validateOrphanTemplates($themeId, $errors, $warnings);
            $this->validateVariablesSchema($themeId, $errors, $warnings);
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'manifest' => $manifest,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadManifest(string $themeId): array
    {
        $themeId = $this->sanitizeThemeId($themeId);
        if ($themeId === '') {
            throw new InvalidArgumentException('Invalid theme id.');
        }

        if (isset($this->manifestCache[$themeId])) {
            return $this->manifestCache[$themeId];
        }

        $manifestPath = $this->themeDirectory($themeId) . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('theme.json not found.');
        }

        $cachePath = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'theme_manifest_' . $themeId . '.php';

        if (is_file($cachePath) && filemtime($cachePath) >= filemtime($manifestPath)) {
            $decoded = require $cachePath;
        } else {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('theme.json is not valid JSON.');
            }

            // Write to cache
            if (!is_dir(dirname($cachePath))) {
                mkdir(dirname($cachePath), 0775, true);
            }
            file_put_contents($cachePath, "<?php\nreturn " . var_export($decoded, true) . ";\n", LOCK_EX);
        }

        $this->manifestCache[$themeId] = $decoded;
        return $decoded;
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    public function render(string $templateKey, array $variables = [], array $rawKeys = []): string
    {
        $themeId = $this->activeThemeId;
        $templatePath = $this->resolveTemplatePath($themeId, $templateKey);

        if ($templatePath === null) {
            throw new RuntimeException("Theme template not found: {$themeId}:{$templateKey}");
        }

        $manifest = $this->loadManifest($themeId);
        $this->renderer->setStrictMissingVariables(!empty($manifest['variables']['strict_missing']));
        $themeUrl = $this->themeUrl($themeId);
        $variables = array_merge([
            'base_url' => rtrim($this->baseUri, '/'),
            'theme_id' => $themeId,
            'theme_url' => $themeUrl,
            'theme' => ['settings' => $this->themeSettings],
        ], $variables);

        $this->assertRequiredVariables($templateKey, $variables, $themeId);

        if (method_exists($this->renderer, 'renderFile')) {
            return $this->renderer->renderFile(
                $templatePath,
                $variables,
                $this->allowedRawKeys($themeId, $rawKeys),
            );
        }

        return $this->renderer->renderString(
            (string) file_get_contents($templatePath),
            $variables,
            $this->allowedRawKeys($themeId, $rawKeys),
        );
    }

    public function usesPublicRenderer(?string $themeId = null): bool
    {
        $themeId = $this->sanitizeThemeId($themeId ?: $this->activeThemeId);
        if ($themeId === '') {
            return false;
        }

        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable) {
            return false;
        }

        return ($manifest['shell']['renderer'] ?? '') === 'public';
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    public function renderLayout(array $variables = [], array $rawKeys = []): string
    {
        return $this->render('layout', $variables, $rawKeys);
    }

    /**
     * @return array<int, string>
     */
    public function rawSlots(?string $themeId = null): array
    {
        $themeId = $this->sanitizeThemeId($themeId ?: $this->activeThemeId);
        if ($themeId === '') {
            return [];
        }

        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable $e) {
            return [];
        }

        $rawSlots = $manifest['variables']['raw_slots'] ?? [];
        if (!is_array($rawSlots)) {
            return [];
        }

        $slots = [];
        foreach ($rawSlots as $slot) {
            $slot = trim((string) $slot);
            if ($slot !== '' && preg_match('/^[a-zA-Z0-9_.-]+$/', $slot) === 1) {
                $slots[] = $slot;
            }
        }

        return array_values(array_unique($slots));
    }

    public function renderAssetTags(string $type, ?string $themeId = null): string
    {
        $themeId = $this->sanitizeThemeId($themeId ?: $this->activeThemeId);
        if ($themeId === '') {
            return '';
        }

        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable $e) {
            return '';
        }

        $assets = $manifest['assets'][$type] ?? [];
        if (!is_array($assets)) {
            return '';
        }

        $tags = [];
        foreach ($assets as $asset) {
            $asset = (string) $asset;
            if (!$this->fileExistsInsideTheme($themeId, $asset)) {
                $message = "Theme asset not found: {$themeId}:{$asset}";
                if ($this->debug) {
                    $tags[] = '<!-- ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . ' -->';
                }
                if (function_exists('appLog')) {
                    appLog($GLOBALS['pdo'] ?? null, 'warning', 'theme', $message, [
                        'theme_id' => $themeId,
                        'asset' => $asset,
                    ]);
                }
                continue;
            }

            $url = htmlspecialchars($this->assetUrl($themeId, $asset), ENT_QUOTES, 'UTF-8');
            if ($type === 'css') {
                $tags[] = '<link rel="stylesheet" href="' . $url . '" data-theme-asset="' . htmlspecialchars($themeId, ENT_QUOTES, 'UTF-8') . '">';
            } elseif ($type === 'js') {
                $tags[] = '<script src="' . $url . '" defer data-theme-asset="' . htmlspecialchars($themeId, ENT_QUOTES, 'UTF-8') . '"></script>';
            }
        }

        return implode("\n    ", $tags);
    }

    public function themeUrl(string $themeId): string
    {
        $themeId = $this->sanitizeThemeId($themeId);
        $folderName = $themeId !== '' ? $this->themeFolderName($themeId) : '';

        return rtrim($this->baseUri, '/') . '/themes/' . rawurlencode($folderName);
    }

    public function assetUrl(string $themeId, string $relativePath): string
    {
        $themeId = $this->sanitizeThemeId($themeId);
        $relativePath = $this->normalizeRelativePath($relativePath);
        $path = $this->resolvePath($themeId, $relativePath, true);
        $version = $path && is_file($path) ? (string) filemtime($path) : '1';

        return $this->themeUrl($themeId) . '/' . str_replace('%2F', '/', rawurlencode($relativePath)) . '?v=' . rawurlencode($version);
    }

    public function publicAssetUrl(string $relativePath, ?string $themeId = null): ?string
    {
        $themeId = $this->sanitizeThemeId($themeId ?: $this->activeThemeId);
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($themeId === '' || $relativePath === '') {
            return null;
        }

        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable $e) {
            return null;
        }

        $publicAssets = $manifest['public_assets'] ?? [];
        if (!is_array($publicAssets) || empty($publicAssets['isolated'])) {
            return null;
        }

        $root = $this->normalizeRelativePath((string) ($publicAssets['root'] ?? ''));
        if ($root === '') {
            return null;
        }

        $themeRelativePath = $root . '/' . $relativePath;
        if (!$this->fileExistsInsideTheme($themeId, $themeRelativePath)) {
            return null;
        }

        return $this->assetUrl($themeId, $themeRelativePath);
    }

    public function isAssetIsolated(?string $themeId = null): bool
    {
        $themeId = $this->sanitizeThemeId($themeId ?: $this->activeThemeId);
        if ($themeId === '') {
            return false;
        }

        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable $e) {
            return false;
        }

        return !empty($manifest['public_assets']['isolated']);
    }

    /**
     * @return array<int, array{path: string, size: int, modified: int, editable: bool, large: bool, size_warning: string}>
     */
    public function editableFiles(string $themeId): array
    {
        $themeId = $this->sanitizeThemeId($themeId);
        $dir = $this->themeDirectory($themeId);
        if ($themeId === '' || !is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            if (!$this->isEditableRelativePath($relative)) {
                continue;
            }

            $files[] = [
                'path' => $relative,
                'size' => (int) $file->getSize(),
                'modified' => (int) $file->getMTime(),
                'editable' => true,
                'large' => (int) $file->getSize() > 512 * 1024,
                'size_warning' => (int) $file->getSize() > 512 * 1024
                    ? 'Buyuk dosya: admin editorunde arama/duzenleme yavas olabilir.'
                    : '',
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['path'], $b['path']));

        return $files;
    }

    public function readEditableFile(string $themeId, string $relativePath): string
    {
        if (!$this->isEditableRelativePath($relativePath)) {
            throw new InvalidArgumentException('File type is not editable.');
        }

        $path = $this->resolvePath($themeId, $relativePath, true);
        if ($path === null || !is_file($path)) {
            throw new RuntimeException('File not found.');
        }

        if (filesize($path) > self::EDITABLE_FILE_MAX_BYTES) {
            throw new RuntimeException('File is too large to edit.');
        }

        return (string) file_get_contents($path);
    }

    public function writeEditableFile(string $themeId, string $relativePath, string $content): void
    {
        if (!$this->isEditableRelativePath($relativePath)) {
            throw new InvalidArgumentException('File type is not editable.');
        }

        if (strlen($content) > self::EDITABLE_FILE_MAX_BYTES) {
            throw new InvalidArgumentException('File content is too large.');
        }

        if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'json') {
            $decoded = json_decode($content, true, 32);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('JSON is invalid: ' . json_last_error_msg());
            }

            if ($this->normalizeRelativePath($relativePath) === 'theme.json') {
                if (!is_array($decoded)) {
                    throw new InvalidArgumentException('theme.json must contain a JSON object.');
                }
                foreach (['id', 'name', 'version', 'engine', 'templates'] as $required) {
                    if (!array_key_exists($required, $decoded) || $decoded[$required] === '' || $decoded[$required] === []) {
                        throw new InvalidArgumentException("theme.json missing or empty required field: {$required}");
                    }
                }
                if (($decoded['id'] ?? '') !== $themeId) {
                    throw new InvalidArgumentException("theme.json id must be '{$themeId}' to match folder name.");
                }
            }
        }

        $path = $this->resolvePath($themeId, $relativePath, false);
        if ($path === null) {
            throw new RuntimeException('Invalid file path.');
        }

        if (is_file($path)) {
            $this->backupEditableFile($themeId, $relativePath, $path);
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create target directory.');
        }

        file_put_contents($path, $content, LOCK_EX);
        $this->manifestCache = [];
    }

    public function duplicateTheme(string $sourceThemeId, string $newThemeId, string $newName): void
    {
        $sourceThemeId = $this->sanitizeThemeId($sourceThemeId);
        $newThemeId = $this->sanitizeThemeId($newThemeId);
        $newName = trim($newName);

        if ($sourceThemeId === 'default' && !$this->themeExists($sourceThemeId)) {
            $sourceThemeId = $this->defaultThemeId();
        }

        if ($sourceThemeId === '' || !$this->themeExists($sourceThemeId)) {
            throw new InvalidArgumentException('Source theme not found.');
        }

        if ($newThemeId === '') {
            throw new InvalidArgumentException('New theme id is invalid.');
        }

        if ($this->themeExists($newThemeId)) {
            throw new InvalidArgumentException('A theme with this id already exists.');
        }

        $source = $this->themeDirectory($sourceThemeId);
        $target = $this->themeDirectory($newThemeId);
        if (!mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException('Cannot create theme folder.');
        }

        $this->copyThemeFiles($source, $target);

        $manifestPath = $target . DIRECTORY_SEPARATOR . 'theme.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($manifest)) {
                $manifest['id'] = $newThemeId;
                $manifest['name'] = $newName !== '' ? $newName : $newThemeId;
                $manifest['version'] = (string) ($manifest['version'] ?? '1.0.0');
                file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
            }
        }

        $this->manifestCache = [];
    }

    /**
     * @return array{theme_id: string, warnings: array<int, string>}
     */
    public function installZip(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is not available.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP file cannot be opened.');
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $relative = $this->normalizeRelativePath($name);
            if ($relative === '' || $relative !== $name || !$this->isImportSafeRelativePath($relative)) {
                $zip->close();
                throw new RuntimeException('Unsafe file in ZIP: ' . $name);
            }

            $names[] = $relative;
        }

        $rootPrefix = $this->commonRootPrefix($names);
        $manifestName = $rootPrefix . 'theme.json';
        $manifestContent = $zip->getFromName($manifestName);
        if ($manifestContent === false && $rootPrefix !== '') {
            $manifestName = 'theme.json';
            $manifestContent = $zip->getFromName($manifestName);
            $rootPrefix = '';
        }

        if ($manifestContent === false) {
            $zip->close();
            throw new RuntimeException('theme.json not found in ZIP.');
        }

        $manifest = json_decode((string) $manifestContent, true);
        if (!is_array($manifest)) {
            $zip->close();
            throw new RuntimeException('theme.json in ZIP is invalid.');
        }

        $themeId = $this->sanitizeThemeId((string) ($manifest['id'] ?? ''));
        if ($themeId === '') {
            $zip->close();
            throw new RuntimeException('Theme id in manifest is invalid.');
        }

        if ($this->themeExists($themeId)) {
            $zip->close();
            throw new RuntimeException('Theme already exists: ' . $themeId);
        }

        $target = $this->themeDirectory($themeId);
        if (!mkdir($target, 0775, true) && !is_dir($target)) {
            $zip->close();
            throw new RuntimeException('Cannot create theme folder.');
        }

        $warnings = [];
        foreach ($names as $name) {
            if ($rootPrefix !== '' && !str_starts_with($name, $rootPrefix)) {
                continue;
            }

            $relative = $rootPrefix !== '' ? substr($name, strlen($rootPrefix)) : $name;
            if ($relative === '') {
                continue;
            }

            $targetPath = $this->resolvePath($themeId, $relative, false);
            if ($targetPath === null) {
                $warnings[] = 'Skipped unsafe file: ' . $name;
                continue;
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                $warnings[] = 'Cannot create directory for: ' . $relative;
                continue;
            }

            $stream = $zip->getStream($name);
            if (!$stream) {
                $warnings[] = 'Cannot read: ' . $name;
                continue;
            }

            $content = stream_get_contents($stream);
            fclose($stream);
            file_put_contents($targetPath, (string) $content, LOCK_EX);
        }

        $zip->close();
        $this->manifestCache = [];

        return ['theme_id' => $themeId, 'warnings' => $warnings];
    }

    private function themeDirectory(string $themeId): string
    {
        return $this->themesDir . DIRECTORY_SEPARATOR . $this->themeFolderName($themeId);
    }

    private function themeFolderName(string $themeId): string
    {
        $themeId = $this->sanitizeThemeId($themeId);
        if ($themeId === '' || !is_dir($this->themesDir)) {
            return $themeId;
        }

        foreach (scandir($this->themesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if ($this->sanitizeThemeId($entry) === $themeId) {
                return $entry;
            }
        }

        return $themeId;
    }

    private function resolveTemplatePath(string $themeId, string $templateKey): ?string
    {
        try {
            $manifest = $this->loadManifest($themeId);
        } catch (Throwable $e) {
            return null;
        }

        $relative = $this->templateRelativePath($manifest, $templateKey);
        if ($relative === '') {
            return null;
        }

        return $this->resolvePath($themeId, $relative, true);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function templateRelativePath(array $manifest, string $templateKey): string
    {
        $value = $manifest['templates'] ?? [];
        foreach (explode('.', $templateKey) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return '';
        }

        return is_string($value) ? $this->normalizeRelativePath($value) : '';
    }

    /**
     * @param mixed $templates
     * @return array<string, string>
     */
    private function flattenTemplateMap(mixed $templates, string $prefix = ''): array
    {
        if (!is_array($templates)) {
            return [];
        }

        $flat = [];
        foreach ($templates as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            if (is_array($value)) {
                $flat += $this->flattenTemplateMap($value, $fullKey);
            } else {
                $flat[$fullKey] = $this->normalizeRelativePath((string) $value);
            }
        }

        return $flat;
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function validateTemplateFiles(string $themeId, array &$errors, array &$warnings): void
    {
        $dir = $this->themeDirectory($themeId);
        if (!is_dir($dir)) {
            return;
        }

        $allowedRawSlots = array_fill_keys($this->rawSlots($themeId), true);
        $rawWarnings = [];
        $legacyWarnings = [];
        $includeGraph = [];
        $legacyPatterns = [
            '/(?<!\[)\[(?:\/)?(?:not-)?logged\]/i' => 'DLE login block',
            '/(?<!\[)\[(?:\/)?(?:not-)?available[^\]]*\]/i' => 'DLE available block',
            '/(?<!\[)\[(?:\/)?(?:not-)?aviable[^\]]*\]/i' => 'DLE aviable block',
            '/(?<!\[)\[(?:\/)?(?:fullresult|shortresult|group|not-group|catlist|not-catlist)[^\]]*\]/i' => 'DLE conditional block',
            '/\{THEME\}/i' => 'DLE theme path token',
            '/\{include\s+file\s*=/i' => 'DLE include token',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'tpl') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            if ($this->isBackupPath($relative)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if (!is_string($content)) {
                $warnings[] = 'Cannot read TPL file: ' . $relative;
                continue;
            }

            if (preg_match('/<\?(?:php|=)?/i', $content) === 1) {
                $errors[] = 'TPL must not contain PHP tags: ' . $relative;
            }

            $this->validateTemplateSyntax($relative, $content, $errors, $warnings);
            $includeGraph[$relative] = $this->validateTemplateIncludes($themeId, $relative, $content, $errors, $warnings);

            foreach ($legacyPatterns as $pattern => $label) {
                if (preg_match($pattern, $content) !== 1) {
                    continue;
                }

                $warningKey = $relative . ':' . $label;
                if (!isset($legacyWarnings[$warningKey])) {
                    $warnings[] = "TPL contains {$label}: {$relative}";
                    $legacyWarnings[$warningKey] = true;
                }
            }

            if (preg_match_all('/\{raw:([a-zA-Z0-9_.-]+)\}/', $content, $matches) < 1) {
                continue;
            }

            foreach ($matches[1] as $rawSlot) {
                $rawSlot = (string) $rawSlot;
                if (isset($allowedRawSlots[$rawSlot])) {
                    continue;
                }

                $warningKey = $relative . ':' . $rawSlot;
                if (!isset($rawWarnings[$warningKey])) {
                    $warnings[] = "TPL raw slot is not declared in theme.json variables.raw_slots: {$rawSlot} ({$relative})";
                    $rawWarnings[$warningKey] = true;
                }
            }
        }

        $this->validateTemplateIncludeCycles($includeGraph, $warnings);
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function validateTemplateSyntax(string $relative, string $content, array &$errors, array &$warnings): void
    {
        if (preg_match_all('/\{(\/?)(if|loop)(?:\s+[^}]*)?\}|\{else\}|\{elseif\s+[^}]*\}/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return;
        }

        $stack = [];
        foreach ($matches as $match) {
            $token = (string) $match[0][0];
            $offset = (int) $match[0][1];
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;

            if (strcasecmp($token, '{else}') === 0 || str_starts_with(strtolower($token), '{elseif')) {
                $top = $stack[count($stack) - 1] ?? null;
                if (!is_array($top) || $top['type'] !== 'if') {
                    $errors[] = "TPL " . ($token === '{else}' ? '{else}' : '{elseif}') . " without matching {if}: {$relative}:{$line}";
                    continue;
                }
                if ($top['closed']) {
                    $errors[] = "TPL " . ($token === '{else}' ? '{else}' : '{elseif}') . " after closing branch in {if}: {$relative}:{$line}";
                    continue;
                }
                if ($token === '{else}') {
                    if (!empty($top['else'])) {
                        $errors[] = "TPL duplicate {else} in {if}: {$relative}:{$line}";
                    }
                    $top['else'] = true;
                } else {
                    $top['elseif'] = true;
                }
                $top['closed'] = true;
                $stack[count($stack) - 1] = $top;
                continue;
            }

            $closing = (string) ($match[1][0] ?? '') === '/';
            $type = strtolower((string) ($match[2][0] ?? ''));
            if (!$closing) {
                $stack[] = ['type' => $type, 'line' => $line, 'else' => false, 'elseif' => false, 'closed' => false];
                continue;
            }

            $top = array_pop($stack);
            if (!is_array($top)) {
                $errors[] = "TPL closing tag without opener: {$token} ({$relative}:{$line})";
                continue;
            }
            if ($top['type'] !== $type) {
                $errors[] = "TPL mismatched closing tag {$token}: opened {{$top['type']}} at {$relative}:{$top['line']}, closed at {$line}";
            }
        }

        foreach (array_reverse($stack) as $open) {
            if (is_array($open)) {
                $errors[] = "TPL unclosed {{$open['type']}} tag: {$relative}:{$open['line']}";
            }
        }

        if (preg_match_all('/\{(?:if|loop)\s*\}/i', $content, $invalidMatches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($invalidMatches[0] as $invalid) {
                $line = substr_count(substr($content, 0, (int) $invalid[1]), "\n") + 1;
                $errors[] = "TPL empty control tag: {$relative}:{$line}";
            }
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     * @return array<int, string>
     */
    private function validateTemplateIncludes(string $themeId, string $relative, string $content, array &$errors, array &$warnings): array
    {
        $includes = [];
        if (preg_match_all('/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        foreach ($matches as $match) {
            $include = $this->normalizeRelativePath((string) ($match[1][0] ?? ''));
            $line = substr_count(substr($content, 0, (int) $match[0][1]), "\n") + 1;
            if ($include === '') {
                $errors[] = "TPL include path is invalid: {$relative}:{$line}";
                continue;
            }

            if (!$this->fileExistsInsideTheme($themeId, $include)) {
                $errors[] = "TPL include target not found: {$include} ({$relative}:{$line})";
                continue;
            }

            if (strtolower(pathinfo($include, PATHINFO_EXTENSION)) !== 'tpl') {
                $warnings[] = "TPL include target should be a .tpl file: {$include} ({$relative}:{$line})";
            }

            $includes[] = $include;
        }

        return array_values(array_unique($includes));
    }

    /**
     * @param array<string, array<int, string>> $includeGraph
     * @param array<int, string> $warnings
     */
    private function validateTemplateIncludeCycles(array $includeGraph, array &$warnings): void
    {
        $visited = [];
        $active = [];
        $warned = [];

        $visit = function (string $node, array $path) use (&$visit, &$includeGraph, &$visited, &$active, &$warned, &$warnings): void {
            $visited[$node] = true;
            $active[$node] = true;
            $path[] = $node;

            foreach ($includeGraph[$node] ?? [] as $target) {
                if (!isset($includeGraph[$target])) {
                    continue;
                }

                if (!isset($visited[$target])) {
                    $visit($target, $path);
                    continue;
                }

                if (!empty($active[$target])) {
                    $start = array_search($target, $path, true);
                    $cycle = $start === false ? array_merge($path, [$target]) : array_slice($path, (int) $start);
                    $cycle[] = $target;
                    $message = 'TPL include cycle detected: ' . implode(' -> ', $cycle);
                    if (!isset($warned[$message])) {
                        $warnings[] = $message;
                        $warned[$message] = true;
                    }
                }
            }

            unset($active[$node]);
        };

        foreach (array_keys($includeGraph) as $node) {
            if (!isset($visited[$node])) {
                $visit($node, []);
            }
        }
    }

    /**
     * @param array<int, string> $warnings
     */
    private function validateCompatAssets(string $themeId, array &$warnings): void
    {
        $manifest = $this->loadManifest($themeId);
        $publicAssets = $manifest['public_assets'] ?? [];
        if (!is_array($publicAssets) || empty($publicAssets['isolated'])) {
            return;
        }

        $root = $this->normalizeRelativePath((string) ($publicAssets['root'] ?? ''));
        if ($root === '') {
            return;
        }

        $pairs = [
            'assets/css/theme.css' => $root . '/assets/css/theme.css',
        ];

        foreach ($pairs as $projectRelative => $themeRelative) {
            $projectPath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $projectRelative);
            $themePath = $this->resolvePath($themeId, $themeRelative, true);
            if (!is_file($projectPath) || $themePath === null || !is_file($themePath)) {
                continue;
            }

            $projectHash = hash_file('sha256', $projectPath);
            $themeHash = hash_file('sha256', $themePath);
            if ($projectHash !== $themeHash) {
                $warnings[] = "Compat asset drift: {$themeRelative} differs from {$projectRelative}. Update the documented source or resync intentionally.";
            }
        }
    }

    /**
     * @param array<int, string> $requestedRawKeys
     * @return array<int, string>
     */
    private function allowedRawKeys(string $themeId, array $requestedRawKeys): array
    {
        $allowed = array_fill_keys($this->rawSlots($themeId), true);
        $rawKeys = [];

        foreach ($requestedRawKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && isset($allowed[$key])) {
                $rawKeys[] = $key;
            }
        }

        return array_values(array_unique($rawKeys));
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function assertRequiredVariables(string $templateKey, array $variables, string $themeId): void
    {
        if ($templateKey !== 'layout') {
            return;
        }

        $manifest = $this->loadManifest($themeId);
        $required = $manifest['variables']['required'] ?? [];
        if (!is_array($required)) {
            throw new RuntimeException("Theme variables.required must be an array: {$themeId}");
        }

        foreach ($required as $key) {
            $key = trim((string) $key);
            if ($key !== '' && !array_key_exists($key, $variables)) {
                throw new RuntimeException("Required theme variable missing: {$themeId}:{$key}");
            }
        }
    }

    private function resolvePath(string $themeId, string $relativePath, bool $mustExist): ?string
    {
        $themeId = $this->sanitizeThemeId($themeId);
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($themeId === '' || $relativePath === '') {
            return null;
        }

        $themeDir = realpath($this->themeDirectory($themeId));
        if ($themeDir === false) {
            return null;
        }

        $candidate = $themeDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if ($mustExist) {
            $real = realpath($candidate);
            if ($real === false || !$this->pathIsInside($real, $themeDir)) {
                return null;
            }

            return $real;
        }

        $parent = dirname($candidate);
        $parentReal = realpath($parent);
        if ($parentReal !== false && !$this->pathIsInside($parentReal, $themeDir)) {
            return null;
        }

        if ($parentReal === false) {
            $closest = $parent;
            while (!is_dir($closest) && dirname($closest) !== $closest) {
                $closest = dirname($closest);
            }
            $closestReal = realpath($closest);
            if ($closestReal === false || !$this->pathIsInside($closestReal, $themeDir)) {
                return null;
            }
        }

        return $candidate;
    }

    private function fileExistsInsideTheme(string $themeId, string $relativePath): bool
    {
        $path = $this->resolvePath($themeId, $relativePath, true);
        return $path !== null && is_file($path);
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($this->pathLooksExternal($path)) {
            return '';
        }
        $path = ltrim($path, '/');
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function pathLooksExternal(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path));
        return $path === ''
            || str_starts_with($path, '/')
            || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path) === 1
            || str_contains($path, '//');
    }

    private function isEditableRelativePath(string $relativePath): bool
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '' || $this->isBackupPath($relativePath)) {
            return false;
        }

        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        return in_array($ext, $this->editableExtensions, true);
    }

    private function isBackupPath(string $relativePath): bool
    {
        return preg_match('~(^|/)\.theme-backups(?:/|$)~', str_replace('\\', '/', $relativePath)) === 1;
    }

    private function backupEditableFile(string $themeId, string $relativePath, string $sourcePath): void
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            return;
        }

        $backupRelative = '.theme-backups/' . date('Ymd-His') . '/' . $relativePath;
        $backupPath = $this->resolvePath($themeId, $backupRelative, false);
        if ($backupPath === null) {
            return;
        }

        $backupDir = dirname($backupPath);
        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            return;
        }

        copy($sourcePath, $backupPath);
    }

    private function isImportSafeRelativePath(string $relativePath): bool
    {
        if ($this->isBackupPath($relativePath)) {
            return false;
        }

        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, $this->blockedExtensions, true)) {
            return false;
        }

        return in_array(
            $ext,
            array_merge($this->editableExtensions, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'map', 'mp3', 'wav', 'ogg']),
            true,
        );
    }

    private function pathIsInside(string $path, string $root): bool
    {
        $path = strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        $root = strtolower(rtrim(str_replace('\\', '/', $root), '/'));

        return $path === $root || str_starts_with($path, $root . '/');
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function previewUrl(string $themeId, array $manifest): string
    {
        $preview = (string) ($manifest['assets']['preview'] ?? '');
        if ($preview !== '' && $this->fileExistsInsideTheme($themeId, $preview)) {
            return $this->assetUrl($themeId, $preview);
        }

        return '';
    }

    private function copyThemeFiles(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
            if ($this->isBackupPath($relative)) {
                continue;
            }

            $targetPath = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                    throw new RuntimeException('Cannot create directory: ' . $relative);
                }
                continue;
            }

            if (!$this->isImportSafeRelativePath($relative)) {
                continue;
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create directory: ' . $relative);
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    /**
     * @param array<int, string> $names
     */
    private function commonRootPrefix(array $names): string
    {
        $firstSegments = [];
        foreach ($names as $name) {
            $segment = strtok($name, '/');
            if ($segment === false || $segment === $name) {
                return '';
            }
            $firstSegments[$segment] = true;
        }

        if (count($firstSegments) !== 1) {
            return '';
        }

        return (string) array_key_first($firstSegments) . '/';
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function validateOrphanTemplates(string $themeId, array &$errors, array &$warnings): void
    {
        $manifest = $this->loadManifest($themeId);
        $allReferenced = [];

        $templateMap = $this->flattenTemplateMap($manifest['templates'] ?? []);
        foreach ($templateMap as $relativePath) {
            if ($relativePath !== '') {
                $allReferenced[$relativePath] = true;
            }
        }

        $partials = $manifest['templates']['partials'] ?? [];
        if (is_array($partials)) {
            foreach ($partials as $partialPath) {
                if (is_string($partialPath) && $partialPath !== '') {
                    $allReferenced[$partialPath] = true;
                }
            }
        }

        $dir = $this->themeDirectory($themeId);
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        $allTplFiles = [];
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'tpl') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            if ($this->isBackupPath($relative)) {
                continue;
            }

            $allTplFiles[$relative] = false;

            $content = file_get_contents($file->getPathname());
            if (is_string($content) && preg_match_all('/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/i', $content, $matches) > 0) {
                foreach ($matches[1] as $includePath) {
                    $inc = $this->normalizeRelativePath((string) $includePath);
                    if ($inc !== '') {
                        $allReferenced[$inc] = true;
                    }
                }
            }
        }

        foreach ($allTplFiles as $relative => $referenced) {
            if (isset($allReferenced[$relative])) {
                continue;
            }
            $warnings[] = "Orphan TPL file not referenced in theme.json or any {include}: {$relative}";
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function validateVariablesSchema(string $themeId, array &$errors, array &$warnings): void
    {
        $manifest = $this->loadManifest($themeId);
        $schemaPathRaw = $manifest['variables']['schema'] ?? '';
        if (!is_string($schemaPathRaw) || $schemaPathRaw === '') {
            return;
        }

        $schemaPath = $this->resolvePath($themeId, $this->normalizeRelativePath($schemaPathRaw), true);
        if ($schemaPath === null || !is_file($schemaPath)) {
            $warnings[] = "Variables schema file not found: {$schemaPathRaw}";
            return;
        }

        $schemaContent = file_get_contents($schemaPath);
        if (!is_string($schemaContent)) {
            $warnings[] = "Cannot read variables schema: {$schemaPathRaw}";
            return;
        }

        $schema = json_decode($schemaContent, true);
        if (!is_array($schema)) {
            $warnings[] = "Variables schema is not valid JSON: {$schemaPathRaw}";
            return;
        }

        $required = $manifest['variables']['required'] ?? [];
        if (is_array($required)) {
            $schemaVars = $schema['properties']['variables']['properties'] ?? [];
            foreach ($required as $key) {
                $key = (string) $key;
                if ($key === '') {
                    continue;
                }
                if (!isset($schemaVars[$key])) {
                    $warnings[] = "Required variable '{$key}' has no type definition in variables.schema.json";
                }
            }
        }

        $rawSlots = $manifest['variables']['raw_slots'] ?? [];
        if (is_array($rawSlots)) {
            $schemaRawSlots = $schema['properties']['raw_slots_list']['items']['enum'] ?? [];
            foreach ($rawSlots as $slot) {
                $slot = (string) $slot;
                if ($slot !== '' && !in_array($slot, $schemaRawSlots, true)) {
                    $warnings[] = "Raw slot '{$slot}' is not listed in variables.schema.json raw_slots_list enum";
                }
            }
        }
    }
}
