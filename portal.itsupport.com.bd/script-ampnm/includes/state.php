<?php
require_once __DIR__ . '/bootstrap.php';

function ampnm_config_path(): string
{
    return dirname(__DIR__) . '/config.php';
}

function ampnm_config_save(array $config): void
{
    $configPath = ampnm_config_path();
    $export = var_export($config, true);
    $contents = "<?php\nreturn {$export};\n";
    if (file_put_contents($configPath, $contents) === false) {
        throw new RuntimeException('Failed to write configuration. Check file permissions.');
    }
}

function ampnm_base_url(): string
{
    global $baseUrl;
    return rtrim($baseUrl ?? '/script-ampnm', '/');
}

function ampnm_setup_complete(): bool
{
    global $config;
    return (bool)($config['setup_complete'] ?? false);
}

function ampnm_license_status(): string
{
    global $config;
    return (string)($config['license']['status'] ?? 'missing');
}

function ampnm_ensure_installed(): void
{
    if (!ampnm_setup_complete()) {
        header('Location: ' . ampnm_base_url() . '/install.php');
        exit;
    }
}

function ampnm_ensure_license_active(): void
{
    if (ampnm_license_status() !== 'active') {
        header('Location: ' . ampnm_base_url() . '/license_setup.php');
        exit;
    }
}

function ampnm_validate_license_key(string $key): bool
{
    $trimmed = trim($key);
    return (bool)preg_match('/^AMP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $trimmed);
}

function ampnm_normalize_host(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return strtolower(preg_replace('/:\d+$/', '', $host));
}
