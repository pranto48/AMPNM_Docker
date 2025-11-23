<?php
require_once __DIR__ . '/../config.php';

// Function to check a TCP port on a host
function checkPortStatus($host, $port, $timeout = 1) {
    $startTime = microtime(true);
    // The '@' suppresses warnings on connection failure, which we handle ourselves.
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $endTime = microtime(true);

    if ($socket) {
        fclose($socket);
        return [
            'success' => true,
            'time' => round(($endTime - $startTime) * 1000, 2), // time in ms
            'output' => "Successfully connected to $host on port $port."
        ];
    } else {
        return [
            'success' => false,
            'time' => 0,
            'output' => "Connection failed: $errstr (Error no: $errno)"
        ];
    }
}

// Function to execute ping command more reliably across platforms
function executePing($host, $count = 4, $timeoutSeconds = 1) {
    $host = trim($host);
    $count = max(1, (int) $count);
    $timeoutSeconds = max(1, (int) $timeoutSeconds);

    if (
        $host === '' ||
        (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $host))
    ) {
        return ['output' => 'Invalid host provided.', 'return_code' => -1, 'success' => false, 'error' => 'Invalid host provided.'];
    }

    if (!function_exists('exec')) {
        return ['output' => 'The PHP exec() function is disabled on this server.', 'return_code' => -1, 'success' => false, 'error' => 'exec disabled'];
    }

    $isWindows = stripos(PHP_OS, 'WIN') === 0;
    $binaryLookup = $isWindows ? 'where ping' : 'command -v ping';
    $resolvedBinary = trim((string) @shell_exec($binaryLookup));

    if ($resolvedBinary === '') {
        return [
            'output' => 'Ping executable not found on this server. Please install ping and allow the web user to execute it.',
            'return_code' => 127,
            'success' => false,
            'error' => 'ping binary missing'
        ];
    }

    $pingBinary = $resolvedBinary;

    // Prefer IPv6 ping when a colon is present in the host on Unix systems
    $ipv6Requested = strpos($host, ':') !== false;
    $ipv6Flag = (!$isWindows && $ipv6Requested) ? ' -6' : '';

    $escaped_host = escapeshellarg($host);
    if ($isWindows) {
        $command = sprintf('%s -n %d -w %d %s', $pingBinary, $count, $timeoutSeconds * 1000, $escaped_host);
    } else {
        $command = sprintf('%s%s -c %d -W %d %s', $pingBinary, $ipv6Flag, $count, $timeoutSeconds, $escaped_host);
    }

    $output_array = [];
    $return_code = -1;
    @exec($command . ' 2>&1', $output_array, $return_code);

    $output = implode("\n", $output_array);
    $failedResponses = ['100% packet loss', 'Destination host unreachable', 'Request timed out'];
    $hasFailureMarker = false;
    foreach ($failedResponses as $marker) {
        if (stripos($output, $marker) !== false) {
            $hasFailureMarker = true;
            break;
        }
    }

    $success = ($return_code === 0 && !$hasFailureMarker);

    if (!$success && empty($output)) {
        return [
            'output' => 'Ping failed to return output. Ensure the web user can execute ping and the host is reachable.',
            'return_code' => $return_code,
            'success' => false,
            'command' => $command,
            'error' => 'ping returned no output'
        ];
    }

    return [
        'output' => $output,
        'return_code' => $return_code,
        'success' => $success,
        'command' => $command,
        'error' => $success ? null : ($output_array ? null : 'Ping command did not return output. Ensure ping is installed and permitted.')
    ];
}

// Function to parse ping output from different OS
function parsePingOutput($output) {
    $packetLoss = 100;
    $avgTime = 0;
    $minTime = 0;
    $maxTime = 0;
    $ttl = null;
    
    // Regex for Windows
    if (preg_match('/Lost = \d+ \((\d+)% loss\)/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $maxTime = (float)$matches[2];
        $avgTime = (float)$matches[3];
    }
    if (preg_match('/TTL=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    // Regex for Linux/Mac
    if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $avgTime = (float)$matches[2];
        $maxTime = (float)$matches[3];
    }
    if (preg_match('/ttl=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    return [
        'packet_loss' => $packetLoss,
        'avg_time' => $avgTime,
        'min_time' => $minTime,
        'max_time' => $maxTime,
        'ttl' => $ttl
    ];
}

// Function to save a ping result to the database
function savePingResult($pdo, $host, $pingResult) {
    $parsed = parsePingOutput($pingResult['output'] ?? '');

    // Normalize values to satisfy NOT NULL schema constraints and avoid type warnings
    $packetLoss = isset($parsed['packet_loss']) ? (int)$parsed['packet_loss'] : 100;
    $avgTime = isset($parsed['avg_time']) ? (float)$parsed['avg_time'] : 0;
    $minTime = isset($parsed['min_time']) ? (float)$parsed['min_time'] : 0;
    $maxTime = isset($parsed['max_time']) ? (float)$parsed['max_time'] : 0;
    $successFlag = !empty($pingResult['success']) ? 1 : 0;

    $sql = "INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $host,
        $packetLoss,
        $avgTime,
        $minTime,
        $maxTime,
        $successFlag,
        $pingResult['output'] ?? ''
    ]);
}

// Function to ping a single device and return structured data
function pingDevice($ip) {
    $pingResult = executePing($ip, 1); // Ping once for speed
    $parsedResult = parsePingOutput($pingResult['output']);
    $alive = $pingResult['success'];
    $errorMessage = $pingResult['error'] ?? (!$alive ? 'Host unreachable or timed out' : null);

    return [
        'ip' => $ip,
        'alive' => $alive,
        'time' => $alive ? $parsedResult['avg_time'] : null,
        'timestamp' => date('c'), // ISO 8601 format
        'error' => $errorMessage
    ];
}

// Function to scan the network for devices using nmap
function scanNetwork($subnet) {
    // NOTE: This function requires 'nmap' to be installed on the server.
    // The web server user (e.g., www-data) may need permissions to run it.
    if (empty($subnet) || !preg_match('/^[a-zA-Z0-9\.\/]+$/', $subnet)) {
        // Default to a common local subnet if none is provided or if input is invalid
        $subnet = '192.168.1.0/24';
    }

    // Escape the subnet to prevent command injection
    $escaped_subnet = escapeshellarg($subnet);
    
    // Use nmap for a discovery scan (-sn: ping scan, -oG -: greppable output)
    $command = "nmap -sn $escaped_subnet -oG -";
    $output = @shell_exec($command);

    if (empty($output)) {
        return []; // nmap might not be installed or failed to run
    }

    $results = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (strpos($line, 'Host:') === 0 && strpos($line, 'Status: Up') !== false) {
            $parts = preg_split('/\s+/', $line);
            $ip = $parts[1];
            $hostname = (isset($parts[2]) && $parts[2] !== '') ? trim($parts[2], '()') : null;
            
            $results[] = [
                'ip' => $ip,
                'hostname' => $hostname,
                'mac' => null, // nmap -sn doesn't always provide MAC, a privileged scan is needed
                'vendor' => null,
                'alive' => true
            ];
        }
    }
    return $results;
}

// Function to check if host is reachable via HTTP
function checkHttpConnectivity($host) {
    if (empty($host) || filter_var($host, FILTER_VALIDATE_IP) === false) {
        return ['success' => false, 'http_code' => 0, 'error' => 'Invalid IP address'];
    }
    $url = "http://$host";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Reduced timeout for faster checks
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 400),
        'http_code' => $httpCode,
        'error' => $error
    ];
}

/**
 * Generates a Font Awesome icon as an SVG data URL.
 * This version assumes Font Awesome CSS is already loaded in the browser.
 *
 * @param string $iconCode The Font Awesome Unicode character (e.g., '\uf233' for server).
 * @param int $size The desired size of the icon in pixels.
 * @param string $color The color of the icon (e.g., '#ffffff').
 * @return string The SVG data URL.
 */
function generateFaSvgDataUrl(string $iconCode, int $size, string $color): string {
    // Ensure the icon code is properly escaped for XML
    $escapedIconCode = htmlspecialchars($iconCode);

    // Font Awesome 6 Free Solid font family
    $fontFamily = 'Font Awesome 6 Free';
    $fontWeight = '900'; // Solid icons

    // Create SVG content, assuming the font is already loaded by the browser's CSS
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
    <text x="50%" y="50%" style="font-family: '{$fontFamily}'; font-weight: {$fontWeight}; font-size: {$size}px; fill: {$color}; text-anchor: middle; dominant-baseline: central;">{$escapedIconCode}</text>
</svg>
SVG;

    // Encode SVG for data URL
    $encodedSvg = rawurlencode($svg);
    return "data:image/svg+xml;charset=utf-8,{$encodedSvg}";
}