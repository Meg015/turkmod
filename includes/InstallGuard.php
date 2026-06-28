<?php

declare(strict_types=1);

function installGuardEnvPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
}

function installGuardIsInstallRequest(): bool
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

    return preg_match('~(?:^|/)install(?:/|$)~i', $path) === 1
        || preg_match('~(?:^|/)install(?:/|$)~i', $script) === 1;
}

function installGuardInstallUrl(): string
{
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = realpath(dirname(__DIR__));
    if ($documentRoot !== false && $appRoot !== false) {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');
        if (stripos($appRoot . '/', $documentRoot . '/') === 0) {
            $webRoot = trim(substr($appRoot, strlen($documentRoot)), '/');
            return ($webRoot === '' ? '' : '/' . $webRoot) . '/install/';
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $root = rtrim(str_replace('/route.php', '', str_replace('/index.php', '', $script)), '/');
    if ($root === '') {
        $root = rtrim(dirname($script), '/\\');
    }
    if ($root === '/' || $root === '.') {
        $root = '';
    }

    return $root . '/install/';
}

function installGuardRedirectIfNeeded(): void
{
    if (PHP_SAPI === 'cli' || is_file(installGuardEnvPath()) || installGuardIsInstallRequest()) {
        return;
    }

    header('Location: ' . installGuardInstallUrl(), true, 302);
    exit;
}
