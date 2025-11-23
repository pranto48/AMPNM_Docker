<?php
require_once __DIR__ . '/bootstrap.php';

function ampnm_asset(string $path): string
{
    global $baseUrl;
    return rtrim($baseUrl, '/') . '/assets/' . ltrim($path, '/');
}

function ampnm_nav_links(): array
{
    return [
        'dashboard'   => ['label' => 'Dashboard', 'href' => '/dashboard.php', 'icon' => '📊'],
        'map'         => ['label' => 'Map', 'href' => '/map.php', 'icon' => '🗺️'],
        'devices'     => ['label' => 'Devices', 'href' => '/devices.php', 'icon' => '🖧'],
        'monitoring'  => ['label' => 'Monitoring', 'href' => '/monitoring.php', 'icon' => '📈'],
        'connectivity'=> ['label' => 'Connectivity', 'href' => '/connectivity.php', 'icon' => '🌐'],
        'logs'        => ['label' => 'Logs', 'href' => '/logs.php', 'icon' => '📜'],
        'users'       => ['label' => 'Users', 'href' => '/users.php', 'icon' => '👥'],
        'license'     => ['label' => 'License', 'href' => '/license.php', 'icon' => '🔑'],
    ];
}

function renderPageStart(string $title, string $active = ''): void
{
    global $config, $baseUrl;
    $appName = htmlspecialchars($config['app_name'] ?? 'AMPNM PHP');
    $pageTitle = htmlspecialchars($title);
    $activeKey = $active;
    $navLinks = ampnm_nav_links();
    $homeHref = htmlspecialchars(rtrim($baseUrl, '/'));
    $styleHref = htmlspecialchars(ampnm_asset('style.css'));
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    echo "    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "    <title>{$pageTitle} | {$appName}</title>\n";
    echo "    <link rel=\"stylesheet\" href=\"{$styleHref}\">\n";
    echo "</head>\n<body>\n";
    echo "<div class=\"app-shell\">\n";
    echo "    <aside class=\"sidebar\">\n        <div class=\"brand\">{$appName}</div>\n        <nav>\n";
    foreach ($navLinks as $key => $item) {
        $isActive = $key === $activeKey ? ' active' : '';
        $href = htmlspecialchars(rtrim($baseUrl, '/') . $item['href']);
        $label = htmlspecialchars($item['label']);
        $icon = htmlspecialchars($item['icon']);
        echo "            <a class=\"nav-link{$isActive}\" href=\"{$href}\"><span class=\"icon\">{$icon}</span>{$label}</a>\n";
    }
    echo "        </nav>\n        <a class=\"home-link\" href=\"{$homeHref}\">← Back to landing</a>\n    </aside>\n";
    echo "    <main class=\"content\">\n        <header class=\"page-header\">\n            <div>\n                <p class=\"eyebrow\">{$appName}</p>\n                <h1>{$pageTitle}</h1>\n            </div>\n            <div class=\"pill\">Base URL: " . htmlspecialchars($baseUrl) . "</div>\n        </header>\n";
}

function renderPageEnd(): void
{
    echo "    </main>\n</div>\n</body>\n</html>";
}
