<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$host = $payload['host'] ?? ($_POST['host'] ?? '');

try {
    $result = pingHost($host);
    jsonResponse([
        'host' => $host,
        'success' => $result['success'],
        'command' => $result['command'],
        'output' => $result['output'],
    ]);
} catch (InvalidArgumentException $e) {
    jsonResponse(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Unable to run ping: ' . $e->getMessage()], 500);
}
