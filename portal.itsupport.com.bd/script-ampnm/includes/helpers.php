<?php
function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function sanitizeHost(string $host): string
{
    $trimmed = trim($host);
    // Allow IPv4, IPv6 (without brackets), or hostname characters
    if (filter_var($trimmed, FILTER_VALIDATE_IP) !== false) {
        return $trimmed;
    }

    if (preg_match('/^[A-Za-z0-9.-]+$/', $trimmed)) {
        return $trimmed;
    }

    throw new InvalidArgumentException('Invalid host provided.');
}

function pingHost(string $host, int $count = 3, int $timeoutSeconds = 2): array
{
    $safeHost = sanitizeHost($host);

    $isWindows = PHP_OS_FAMILY === 'Windows';
    $countFlag = $isWindows ? '-n' : '-c';
    $timeoutFlag = $isWindows ? '-w' : '-W';
    $timeout = $isWindows ? $timeoutSeconds * 1000 : $timeoutSeconds;

    $command = sprintf(
        'ping %s %d %s %d %s',
        $countFlag,
        $count,
        $timeoutFlag,
        $timeout,
        escapeshellarg($safeHost)
    );

    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);

    $success = $exitCode === 0;

    return [
        'success' => $success,
        'command' => $command,
        'output' => implode("\n", $output),
    ];
}
