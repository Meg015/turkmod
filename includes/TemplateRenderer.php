<?php

declare(strict_types=1);

final class TemplateRenderer
{
    /** @var array<string, bool> */
    private array $rawKeys = [];

    private ?Closure $templateResolver = null;

    private bool $strictMissingVariables = false;

    private string $compiledCacheDir = '';

    public function __construct(private bool $debug = false, private ?Closure $logger = null)
    {
    }

    public function setLogger(Closure $logger): void
    {
        $this->logger = $logger;
    }

    public function setTemplateResolver(Closure $resolver): void
    {
        $this->templateResolver = $resolver;
    }

    public function setStrictMissingVariables(bool $strict): void
    {
        $this->strictMissingVariables = $strict;
    }

    /**
     * Set the directory for compiled template cache.
     */
    public function setCompiledCacheDir(string $dir): void
    {
        $this->compiledCacheDir = rtrim($dir, '/\\');
    }

    /**
     * Render a template file using compiled cache if available.
     *
     * @param string $templatePath Absolute path to the template file.
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    public function renderFile(string $templatePath, array $variables = [], array $rawKeys = []): string
    {
        $this->rawKeys = array_fill_keys($rawKeys, true);

        if ($this->compiledCacheDir !== '' && !$this->debug) {
            $cacheKey = md5($templatePath);
            $cacheFile = $this->compiledCacheDir . '/' . $cacheKey . '.php';

            if (is_file($cacheFile) && filemtime($cacheFile) >= filemtime($templatePath)) {
                return $this->executeCompiledTemplate($cacheFile, $variables, $rawKeys);
            }

            $template = (string) file_get_contents($templatePath);
            $compiled = $this->compileTemplate($template);

            if (!is_dir($this->compiledCacheDir)) {
                @mkdir($this->compiledCacheDir, 0775, true);
            }

            $header = "<?php\n// Compiled template cache - DO NOT EDIT\n// Generated: " . date('Y-m-d H:i:s') . "\n";
            @file_put_contents($cacheFile, $header . $compiled, LOCK_EX);

            return $this->executeCompiledTemplate($cacheFile, $variables, $rawKeys);
        }

        return $this->renderString((string) file_get_contents($templatePath), $variables, $rawKeys);
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    public function renderString(string $template, array $variables = [], array $rawKeys = []): string
    {
        $this->rawKeys = array_fill_keys($rawKeys, true);

        // Use compiled cache if available
        if ($this->compiledCacheDir !== '' && !$this->debug) {
            return $this->renderStringCompiled($template, $variables, $rawKeys);
        }

        $template = $this->renderIncludes($template, []);
        $template = $this->renderLoops($template, $variables, $rawKeys);
        $template = $this->renderConditions($template, $variables, $rawKeys);

        return $this->renderVariables($template, $variables);
    }

    /**
     * Compile TPL template to PHP code and cache it.
     * On subsequent requests, the compiled PHP is directly required.
     *
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    private function renderStringCompiled(string $template, array $variables = [], array $rawKeys = []): string
    {
        $cacheKey = md5($template);
        $cacheFile = $this->compiledCacheDir . '/' . $cacheKey . '.php';

        // Check if compiled cache exists and is valid
        if (is_file($cacheFile)) {
            return $this->executeCompiledTemplate($cacheFile, $variables, $rawKeys);
        }

        // Compile the template
        $compiled = $this->compileTemplate($template);

        // Write to cache
        if (!is_dir($this->compiledCacheDir)) {
            @mkdir($this->compiledCacheDir, 0775, true);
        }

        $header = "<?php\n// Compiled template cache - DO NOT EDIT\n// Generated: " . date('Y-m-d H:i:s') . "\n";
        @file_put_contents($cacheFile, $header . $compiled, LOCK_EX);

        return $this->executeCompiledTemplate($cacheFile, $variables, $rawKeys);
    }

    /**
     * Execute a compiled template file with output buffering.
     *
     * @param string $cacheFile
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    private function executeCompiledTemplate(string $cacheFile, array $variables, array $rawKeys): string
    {
        $V = $variables;
        $R = array_fill_keys($rawKeys, true);
        $renderer = $this;

        ob_start();
        try {
            require $cacheFile;
        } catch (Throwable $e) {
            ob_end_clean();
            if ($this->debug) {
                throw $e;
            }
            if ($this->logger !== null) {
                ($this->logger)('error', 'template', 'Compiled template error: ' . $e->getMessage(), []);
            } elseif (function_exists('appLog')) {
                appLog($GLOBALS['pdo'] ?? null, 'error', 'template', 'Compiled template error: ' . $e->getMessage(), []);
            }
            return '';
        }
        return (string) ob_get_clean();
    }

    /**
     * Compile TPL syntax to PHP code.
     */
    private function compileTemplate(string $template): string
    {
        // First resolve includes (inline them)
        $template = $this->resolveIncludesForCompilation($template, []);

        // Compile loops: {loop key}...{/loop}
        $template = $this->compileLoops($template);

        // Compile conditions: {if key}...{elseif key}...{else}...{/if}
        $template = $this->compileConditions($template);

        // Compile variables: {key}, {key|filter}, {raw:key}
        $template = $this->compileVariables($template);

        return $template;
    }

    /**
     * Resolve {include} tags by inlining the referenced template content.
     */
    private function resolveIncludesForCompilation(string $template, array $stack): string
    {
        $maxDepth = 20;
        $depth = 0;

        while (preg_match('/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/i', $template) === 1 && $depth < $maxDepth) {
            $template = (string) preg_replace_callback('/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/i', function (array $matches) use ($stack): string {
                $filename = $matches[1];
                if (in_array($filename, $stack, true)) {
                    return '';
                }
                if ($this->templateResolver) {
                    $content = ($this->templateResolver)($filename);
                    if ($content !== null) {
                        return $this->resolveIncludesForCompilation($content, array_merge($stack, [$filename]));
                    }
                }
                return '';
            }, $template);
            $depth++;
        }

        return $template;
    }

    /**
     * Compile {loop key}...{/loop} tags to PHP foreach.
     */
    private function compileLoops(string $template): string
    {
        $offset = 0;
        while (($start = strpos($template, '{loop ', $offset)) !== false) {
            $endKeyPos = strpos($template, '}', $start);
            if ($endKeyPos === false) {
                $offset = $start + 6;
                continue;
            }

            $tag = substr($template, $start, $endKeyPos - $start + 1);
            if (preg_match('/^\{loop\s+([a-zA-Z0-9_.-]+)\}$/', $tag, $matches) !== 1) {
                $offset = $start + 6;
                continue;
            }

            $loopKey = $matches[1];
            $blockStart = $endKeyPos + 1;

            $depth = 1;
            $searchOffset = $blockStart;
            $endPos = -1;

            while (true) {
                $nextOpen = strpos($template, '{loop ', $searchOffset);
                $nextClose = strpos($template, '{/loop}', $searchOffset);
                if ($nextClose === false) break;
                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $searchOffset = $nextOpen + 6;
                } else {
                    $depth--;
                    if ($depth === 0) { $endPos = $nextClose; break; }
                    $searchOffset = $nextClose + 7;
                }
            }

            if ($endPos === -1) {
                $offset = $start + 6;
                continue;
            }

            $block = substr($template, $blockStart, $endPos - $blockStart);
            $fullMatchLen = ($endPos + 7) - $start;

            $singular = $this->singularKey($loopKey);
            $pathExpr = $this->compilePathExpression($loopKey);

            $phpCode = '<?php '
                . '$_items = ' . $pathExpr . '; '
                . 'if (is_iterable($_items)): '
                . '$_itemCount = is_countable($_items) ? count($_items) : 0; '
                . '$_loopIdx = 0; '
                . 'foreach ($_items as $_loopItem): '
                . '$_itemArr = is_array($_loopItem) ? $_loopItem : [\'value\' => $_loopItem]; '
                . '$V[\'' . addslashes($singular) . '\'] = $_itemArr; '
                . '$V[\'item\'] = $_itemArr; '
                . '$V[\'loop\'] = [\'index\' => $_loopIdx, \'index0\' => $_loopIdx, \'number\' => $_loopIdx + 1, '
                . '\'first\' => $_loopIdx === 0, \'last\' => $_loopIdx === $_itemCount - 1, '
                . '\'odd\' => $_loopIdx % 2 === 0, \'even\' => $_loopIdx % 2 === 1, '
                . '\'revindex\' => $_itemCount - $_loopIdx - 1, \'revindex0\' => $_itemCount - $_loopIdx, '
                . '\'length\' => $_itemCount]; '
                . '?>';

            $compiledBlock = $this->compileLoops($block);
            $compiledBlock = $this->compileConditions($compiledBlock);
            $compiledBlock = $this->compileVariables($compiledBlock);

            $replacement = $phpCode . $compiledBlock
                . '<?php $_loopIdx++; endforeach; endif; ?>';

            $template = substr_replace($template, $replacement, $start, $fullMatchLen);
            $offset = $start + strlen($replacement);
        }

        return $template;
    }

    /**
     * Compile {if key}...{/if} tags to PHP if/elseif/else.
     */
    private function compileConditions(string $template): string
    {
        $offset = 0;
        while (($start = strpos($template, '{if ', $offset)) !== false) {
            $endKeyPos = strpos($template, '}', $start);
            if ($endKeyPos === false) {
                $offset = $start + 4;
                continue;
            }

            $tag = substr($template, $start, $endKeyPos - $start + 1);
            if (preg_match('/^\{if\s+(!?)([a-zA-Z0-9_.-]+)\}$/', $tag, $matches) !== 1) {
                $offset = $start + 4;
                continue;
            }

            $negated = $matches[1] === '!';
            $key = $matches[2];
            $blockStart = $endKeyPos + 1;

            $depth = 1;
            $searchOffset = $blockStart;
            $endPos = -1;
            $markers = [];
            $hasElse = false;

            while (true) {
                $nextOpen = strpos($template, '{if ', $searchOffset);
                $nextClose = strpos($template, '{/if}', $searchOffset);
                $nextElse = strpos($template, '{else}', $searchOffset);
                $nextElseif = strpos($template, '{elseif ', $searchOffset);

                $candidates = [];
                if ($nextOpen !== false) $candidates['open'] = $nextOpen;
                if ($nextClose !== false) $candidates['close'] = $nextClose;
                if ($nextElseif !== false && $depth === 1) $candidates['elseif'] = $nextElseif;
                if ($nextElse !== false && $depth === 1 && !$hasElse) $candidates['else'] = $nextElse;
                if ($candidates === []) break;

                asort($candidates);
                $type = (string) array_key_first($candidates);
                $pos = (int) $candidates[$type];

                if ($type === 'open') { $depth++; $searchOffset = $pos + 4; }
                elseif ($type === 'close') { $depth--; if ($depth === 0) { $endPos = $pos; break; } $searchOffset = $pos + 5; }
                elseif ($type === 'elseif') {
                    $closeBrace = strpos($template, '}', $pos);
                    if ($closeBrace === false) { $searchOffset = $pos + 8; continue; }
                    $elseifTag = substr($template, $pos, $closeBrace - $pos + 1);
                    if (preg_match('/^\{elseif\s+(!?)([a-zA-Z0-9_.-]+)\}$/', $elseifTag, $em) === 1) {
                        $markers[] = ['pos' => $pos, 'type' => 'elseif', 'key' => $em[2], 'negated' => $em[1] === '!', 'tag_end' => $closeBrace + 1];
                        $searchOffset = $closeBrace + 1;
                    } else { $searchOffset = $pos + 8; }
                } elseif ($type === 'else') {
                    $markers[] = ['pos' => $pos, 'type' => 'else', 'tag_end' => $pos + 6];
                    $hasElse = true;
                    $searchOffset = $pos + 6;
                }
            }

            if ($endPos === -1) {
                $offset = $start + 4;
                continue;
            }

            $fullMatchLen = ($endPos + 5) - $start;

            // Build PHP if/elseif/else structure
            $branches = [];
            $branchStart = $blockStart;
            $currentKey = $key;
            $currentNegated = $negated;

            foreach ($markers as $marker) {
                $branches[] = ['key' => $currentKey, 'negated' => $currentNegated, 'content' => substr($template, $branchStart, $marker['pos'] - $branchStart)];
                if ($marker['type'] === 'elseif') {
                    $currentKey = $marker['key'];
                    $currentNegated = $marker['negated'];
                } else {
                    $currentKey = null;
                    $currentNegated = false;
                }
                $branchStart = $marker['tag_end'];
            }
            $branches[] = ['key' => $currentKey, 'negated' => $currentNegated, 'content' => substr($template, $branchStart, $endPos - $branchStart)];

            $phpCode = '';
            $isFirst = true;
            foreach ($branches as $branch) {
                $compiledContent = $this->compileConditions($branch['content']);
                $compiledContent = $this->compileVariables($compiledContent);

                if ($branch['key'] === null) {
                    $phpCode .= '<?php else: ?>' . $compiledContent;
                } elseif ($isFirst) {
                    $condExpr = $this->compileTruthyExpression($branch['key'], $branch['negated']);
                    $phpCode .= '<?php if (' . $condExpr . '): ?>' . $compiledContent;
                } else {
                    $condExpr = $this->compileTruthyExpression($branch['key'], $branch['negated']);
                    $phpCode .= '<?php elseif (' . $condExpr . '): ?>' . $compiledContent;
                }
                $isFirst = false;
            }
            $phpCode .= '<?php endif; ?>';

            $template = substr_replace($template, $phpCode, $start, $fullMatchLen);
            $offset = $start + strlen($phpCode);
        }

        return $template;
    }

    /**
     * Compile variable tags to PHP echo statements.
     */
    private function compileVariables(string $template): string
    {
        return (string) preg_replace_callback('/\{([a-zA-Z0-9_.:-]+)(?:\|([^\}]+))?\}/', function (array $matches): string {
            $key = (string) $matches[1];
            $filtersStr = (string) ($matches[2] ?? '');
            $rawRequested = false;

            if (str_starts_with($key, 'raw:')) {
                $rawRequested = true;
                $key = substr($key, 4);
            }

            $pathExpr = $this->compilePathExpression($key);
            $isRaw = isset($this->rawKeys[$key]) || str_contains($filtersStr, 'raw') || $rawRequested;

            if ($filtersStr !== '') {
                $filterCode = $this->compileFilterChain($pathExpr, $filtersStr);
                if ($isRaw) {
                    return '<?php echo ' . $filterCode . '; ?>';
                }
                return '<?php echo htmlspecialchars((string)(' . $filterCode . '), ENT_QUOTES, \'UTF-8\'); ?>';
            }

            if ($isRaw) {
                return '<?php echo (string)(' . $pathExpr . ' ?? \'\'); ?>';
            }

            return '<?php echo htmlspecialchars((string)(' . $pathExpr . ' ?? \'\'), ENT_QUOTES, \'UTF-8\'); ?>';
        }, $template);
    }

    /**
     * Convert a dot-notation path to a PHP array access expression.
     */
    private function compilePathExpression(string $path): string
    {
        $segments = explode('.', $path);
        $expr = '$V';
        foreach ($segments as $segment) {
            $expr .= '[\'' . addslashes($segment) . '\']';
        }
        return '(isset(' . $expr . ') ? ' . $expr . ' : null)';
    }

    /**
     * Compile a truthy check expression for conditions.
     */
    private function compileTruthyExpression(string $key, bool $negated): string
    {
        $pathExpr = $this->compilePathExpression($key);
        $truthyCheck = '(function($_v) { '
            . 'if (is_bool($_v)) return $_v; '
            . 'if (is_numeric($_v)) return (float)$_v !== 0.0; '
            . 'if (is_array($_v)) return $_v !== []; '
            . 'if ($_v === null || $_v === \'\') return false; '
            . '$s = strtolower(trim((string)$_v)); '
            . 'return !in_array($s, [\'0\', \'false\', \'no\', \'off\', \'null\'], true); '
            . '})(' . $pathExpr . ')';

        if ($negated) {
            return '!(' . $truthyCheck . ')';
        }
        return $truthyCheck;
    }

    /**
     * Compile a filter chain into PHP expression.
     */
    private function compileFilterChain(string $valueExpr, string $filtersStr): string
    {
        $filters = explode('|', $filtersStr);
        $expr = $valueExpr;

        foreach ($filters as $filterExp) {
            $filterExp = trim($filterExp);
            if ($filterExp === '' || $filterExp === 'raw') continue;

            $name = $filterExp;
            $arg = '';
            if (preg_match('/^([a-z0-9_]+)(?:\((.*)\))?$/i', $filterExp, $m) === 1) {
                $name = strtolower($m[1]);
                if (isset($m[2])) $arg = trim($m[2], '\'" ');
            }

            $expr = match ($name) {
                'upper' => 'mb_strtoupper((string)(' . $expr . '))',
                'lower' => 'mb_strtolower((string)(' . $expr . '))',
                'truncate' => '(mb_strlen((string)(' . $expr . ')) > ' . (int)$arg . ' ? mb_substr((string)(' . $expr . '), 0, ' . (int)$arg . ') . \'...\' : (string)(' . $expr . '))',
                'escape' => 'htmlspecialchars((string)(' . $expr . '), ENT_QUOTES, \'UTF-8\')',
                'default' => '((string)(' . $expr . ') !== \'\' ? (string)(' . $expr . ') : \'' . addslashes($arg) . '\')',
                'strip_tags' => 'strip_tags((string)(' . $expr . '))',
                'nl2br' => 'nl2br((string)(' . $expr . '))',
                'ucfirst' => '(mb_strtoupper(mb_substr((string)(' . $expr . '), 0, 1)) . mb_substr((string)(' . $expr . '), 1))',
                'slug' => 'strtolower(trim(preg_replace(\'/[^a-zA-Z0-9-]+/\', \'-\', (string)(' . $expr . ')), \'-\'))',
                default => $expr,
            };
        }

        return $expr;
    }

    /**
     * Invalidate compiled template cache.
     */
    public function clearCompiledCache(): void
    {
        if ($this->compiledCacheDir === '' || !is_dir($this->compiledCacheDir)) {
            return;
        }

        foreach (glob($this->compiledCacheDir . '/*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * @param array<int, string> $includeStack filenames currently being resolved (cycle detection)
     */
    private function renderIncludes(string $template, array $includeStack): string
    {
        $maxDepth = 20;
        $depth = 0;

        while (preg_match('/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/i', $template) === 1 && $depth < $maxDepth) {
            $template = (string) preg_replace_callback('/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/i', function (array $matches) use ($includeStack): string {
                $filename = $matches[1];
                if (in_array($filename, $includeStack, true)) {
                    $cycle = implode(' -> ', array_merge($includeStack, [$filename]));
                    if ($this->debug) {
                        throw new RuntimeException('Template include cycle detected: ' . $cycle);
                    }
                    if ($this->logger !== null) {
                        ($this->logger)('error', 'template', 'TPL include cycle: ' . $cycle, ['cycle' => $cycle]);
                    } elseif (function_exists('appLog')) {
                        appLog($GLOBALS['pdo'] ?? null, 'error', 'template', 'TPL include cycle: ' . $cycle, ['cycle' => $cycle]);
                    }
                    return '';
                }
                if ($this->templateResolver) {
                    $content = ($this->templateResolver)($filename);
                    if ($content !== null) {
                        return $this->renderIncludes($content, array_merge($includeStack, [$filename]));
                    }
                }
                return $this->debug ? '{missing-include:' . $filename . '}' : '';
            }, $template);
            $depth++;
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    private function renderLoops(string $template, array $variables, array $rawKeys): string
    {
        $offset = 0;
        while (($start = strpos($template, '{loop ', $offset)) !== false) {
            $endKeyPos = strpos($template, '}', $start);
            if ($endKeyPos === false) {
                $offset = $start + 6;
                continue;
            }

            $tag = substr($template, $start, $endKeyPos - $start + 1);
            if (preg_match('/^\{loop\s+([a-zA-Z0-9_.-]+)\}$/', $tag, $matches) !== 1) {
                $offset = $start + 6;
                continue;
            }

            $loopKey = $matches[1];
            $blockStart = $endKeyPos + 1;
            
            $depth = 1;
            $searchOffset = $blockStart;
            $endPos = -1;

            while (true) {
                $nextOpen = strpos($template, '{loop ', $searchOffset);
                $nextClose = strpos($template, '{/loop}', $searchOffset);

                if ($nextClose === false) {
                    break;
                }

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $searchOffset = $nextOpen + 6;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $endPos = $nextClose;
                        break;
                    }
                    $searchOffset = $nextClose + 7;
                }
            }

            if ($endPos === -1) {
                if ($this->debug) {
                    throw new RuntimeException("Unclosed {loop {$loopKey}} tag in template.");
                }
                $offset = $start + 6;
                continue;
            }

            $block = substr($template, $blockStart, $endPos - $blockStart);
            $fullMatchLen = ($endPos + 7) - $start;
            $items = $this->valueByPath($variables, $loopKey);
            $html = '';

            if (is_iterable($items)) {
                $singular = $this->singularKey($loopKey);
                $itemCount = is_countable($items) ? count($items) : 0;
                $index = 0;
                foreach ($items as $item) {
                    $itemArray = is_array($item) ? $item : ['value' => $item];
                    $loopVars = $variables;
                    $loopVars[$singular] = $itemArray;
                    $loopVars['item'] = $itemArray;
                    $loopVars['loop'] = [
                        'index' => $index,
                        'index0' => $index,
                        'number' => $index + 1,
                        'first' => $index === 0,
                        'last' => $index === $itemCount - 1,
                        'odd' => $index % 2 === 0,
                        'even' => $index % 2 === 1,
                        'revindex' => $itemCount - $index - 1,
                        'revindex0' => $itemCount - $index,
                        'length' => $itemCount,
                    ];

                    $html .= $this->renderString($block, $loopVars, $rawKeys);
                    $index++;
                }
            }

            $template = substr_replace($template, $html, $start, $fullMatchLen);
            $offset = $start + strlen($html);
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<int, string> $rawKeys
     */
    private function renderConditions(string $template, array $variables, array $rawKeys): string
    {
        $offset = 0;
        while (($start = strpos($template, '{if ', $offset)) !== false) {
            $endKeyPos = strpos($template, '}', $start);
            if ($endKeyPos === false) {
                $offset = $start + 4;
                continue;
            }

            $tag = substr($template, $start, $endKeyPos - $start + 1);
            if (preg_match('/^\{if\s+(!?)([a-zA-Z0-9_.-]+)\}$/', $tag, $matches) !== 1) {
                $offset = $start + 4;
                continue;
            }

            $negated = $matches[1] === '!';
            $key = $matches[2];
            $blockStart = $endKeyPos + 1;

            $depth = 1;
            $searchOffset = $blockStart;
            $endPos = -1;
            $markers = []; // [['pos' => int, 'type' => 'elseif'|'else', 'key' => string, 'negated' => bool, 'tag_end' => int]]
            $hasElse = false;

            while (true) {
                $nextOpen = strpos($template, '{if ', $searchOffset);
                $nextClose = strpos($template, '{/if}', $searchOffset);
                $nextElse = strpos($template, '{else}', $searchOffset);
                $nextElseif = strpos($template, '{elseif ', $searchOffset);

                $candidates = [];
                if ($nextOpen !== false) {
                    $candidates['open'] = $nextOpen;
                }
                if ($nextClose !== false) {
                    $candidates['close'] = $nextClose;
                }
                if ($nextElseif !== false && $depth === 1) {
                    $candidates['elseif'] = $nextElseif;
                }
                if ($nextElse !== false && $depth === 1 && !$hasElse) {
                    $candidates['else'] = $nextElse;
                }

                if ($candidates === []) {
                    break;
                }

                asort($candidates);
                $type = (string) array_key_first($candidates);
                $pos = (int) $candidates[$type];

                if ($type === 'open') {
                    $depth++;
                    $searchOffset = $pos + 4;
                } elseif ($type === 'close') {
                    $depth--;
                    if ($depth === 0) {
                        $endPos = $pos;
                        break;
                    }
                    $searchOffset = $pos + 5;
                } elseif ($type === 'elseif') {
                    $closeBrace = strpos($template, '}', $pos);
                    if ($closeBrace === false) {
                        $searchOffset = $pos + 8;
                        continue;
                    }
                    $elseifTag = substr($template, $pos, $closeBrace - $pos + 1);
                    if (preg_match('/^\{elseif\s+(!?)([a-zA-Z0-9_.-]+)\}$/', $elseifTag, $em) === 1) {
                        $markers[] = [
                            'pos' => $pos,
                            'type' => 'elseif',
                            'key' => $em[2],
                            'negated' => $em[1] === '!',
                            'tag_end' => $closeBrace + 1,
                        ];
                        $searchOffset = $closeBrace + 1;
                    } else {
                        $searchOffset = $pos + 8;
                    }
                } elseif ($type === 'else') {
                    $markers[] = [
                        'pos' => $pos,
                        'type' => 'else',
                        'tag_end' => $pos + 6,
                    ];
                    $hasElse = true;
                    $searchOffset = $pos + 6;
                }
            }

            if ($endPos === -1) {
                if ($this->debug) {
                    throw new RuntimeException("Unclosed {if} tag for key '{$key}' in template.");
                }
                $offset = $start + 4;
                continue;
            }

            $branches = [];
            $branchStart = $blockStart;

            foreach ($markers as $marker) {
                $branches[] = [
                    'key' => $key,
                    'negated' => $negated,
                    'content_start' => $branchStart,
                    'content_end' => $marker['pos'],
                ];

                if ($marker['type'] === 'elseif') {
                    $key = $marker['key'];
                    $negated = $marker['negated'];
                } else {
                    $key = null;
                    $negated = false;
                }
                $branchStart = $marker['tag_end'];
            }

            $branches[] = [
                'key' => $key,
                'negated' => $negated,
                'content_start' => $branchStart,
                'content_end' => $endPos,
            ];

            $selected = '';
            foreach ($branches as $branch) {
                if ($branch['key'] === null) {
                    $selected = substr($template, $branch['content_start'], $branch['content_end'] - $branch['content_start']);
                    break;
                }

                $truthy = $this->isTruthy($this->valueByPath($variables, $branch['key']));
                if ($branch['negated']) {
                    $truthy = !$truthy;
                }

                if ($truthy) {
                    $selected = substr($template, $branch['content_start'], $branch['content_end'] - $branch['content_start']);
                    break;
                }
            }

            $html = $this->renderString($selected, $variables, $rawKeys);
            $fullMatchLen = ($endPos + 5) - $start;

            $template = substr_replace($template, $html, $start, $fullMatchLen);
            $offset = $start + strlen($html);
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderVariables(string $template, array $variables): string
    {
        $renderer = $this;

        return (string) preg_replace_callback('/\{([a-zA-Z0-9_.:-]+)(?:\|([^\}]+))?\}/', static function (array $matches) use ($variables, $renderer): string {
            $key = (string) $matches[1];
            $filtersStr = (string) ($matches[2] ?? '');
            $rawRequested = false;

            if (str_starts_with($key, 'raw:')) {
                $rawRequested = true;
                $key = substr($key, 4);
            }

            $value = $renderer->valueByPath($variables, $key);
            if ($value === null) {
                if ($renderer->strictMissingVariables) {
                    throw new RuntimeException('Missing template variable: ' . $key);
                }

                if ($renderer->debug) {
                    if ($renderer->logger !== null) {
                        ($renderer->logger)('warning', 'template', 'TPL Missing Variable: ' . $key, ['template_key' => $key]);
                    } elseif (function_exists('appLog')) {
                        appLog($GLOBALS['pdo'] ?? null, 'warning', 'template', 'TPL Missing Variable: ' . $key, ['template_key' => $key]);
                    }
                    return '{missing:' . $key . '}';
                }
                return '';
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $value = '';
            }

            $stringValue = (string) $value;
            if ($filtersStr !== '') {
                $stringValue = $renderer->applyFilters($stringValue, $filtersStr);
            }

            if (isset($renderer->rawKeys[$key]) || str_contains($filtersStr, 'raw')) {
                return $stringValue;
            }

            if ($rawRequested && $renderer->debug) {
                return '{raw-denied:' . $key . '}';
            }

            return htmlspecialchars($stringValue, ENT_QUOTES, 'UTF-8');
        }, $template);
    }

    private function applyFilters(string $value, string $filtersStr): string
    {
        $filters = explode('|', $filtersStr);
        foreach ($filters as $filterExp) {
            $filterExp = trim($filterExp);
            if ($filterExp === '') continue;

            $name = $filterExp;
            $arg = '';
            if (preg_match('/^([a-z0-9_]+)(?:\((.*)\))?$/i', $filterExp, $m) === 1) {
                $name = $m[1];
                if (isset($m[2])) {
                    $arg = trim($m[2], '\'" ');
                }
            }

            switch (strtolower($name)) {
                case 'upper':
                    $value = mb_strtoupper($value);
                    break;
                case 'lower':
                    $value = mb_strtolower($value);
                    break;
                case 'date':
                    if (is_numeric($value)) {
                        $value = date($arg !== '' ? $arg : 'Y-m-d H:i', (int)$value);
                    } else {
                        $time = strtotime($value);
                        if ($time !== false) {
                            $value = date($arg !== '' ? $arg : 'Y-m-d H:i', $time);
                        }
                    }
                    break;
                case 'truncate':
                    $len = (int)($arg !== '' ? $arg : 100);
                    if (mb_strlen($value) > $len) {
                        $value = mb_substr($value, 0, $len) . '...';
                    }
                    break;
                case 'slug':
                    $value = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $value), '-'));
                    break;
                case 'escape':
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
                case 'default':
                    if ($value === '' || $value === null) {
                        $value = $arg !== '' ? $arg : '';
                    }
                    break;
                case 'json':
                    $decoded = json_decode($value, true);
                    $value = $decoded !== null ? ($decoded[$arg] ?? $value) : $value;
                    break;
                case 'strip_tags':
                    $value = strip_tags($value);
                    break;
                case 'ucfirst':
                    $value = mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
                    break;
                case 'nl2br':
                    $value = nl2br($value);
                    break;
                case 'raw':
                    // raw filter is handled in renderVariables; no-op here
                    break;
            }
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function valueByPath(array $variables, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $variables;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};
                continue;
            }

            return null;
        }

        return $value;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        $string = strtolower(trim((string) $value));

        return !in_array($string, ['', '0', 'false', 'no', 'off', 'null'], true);
    }

    private function singularKey(string $loopKey): string
    {
        $last = basename(str_replace('.', '/', $loopKey));
        if (str_ends_with($last, 'ies')) {
            return substr($last, 0, -3) . 'y';
        }

        if (str_ends_with($last, 's') && strlen($last) > 1) {
            return substr($last, 0, -1);
        }

        return $last;
    }
}
