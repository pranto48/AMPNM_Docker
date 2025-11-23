<?php
$projectRoot = dirname(__DIR__);
$configFile = $projectRoot . '/config.php';
$sampleConfigFile = $projectRoot . '/config.sample.php';

if (file_exists($configFile)) {
    $config = require $configFile;
} elseif (file_exists($sampleConfigFile)) {
    $config = require $sampleConfigFile;
} else {
    $config = [];
}

if (!is_array($config)) {
    throw new RuntimeException('Configuration must return an array.');
}

require_once __DIR__ . '/helpers.php';

$baseUrl = rtrim($config['app_url'] ?? '/script-ampnm', '/');
