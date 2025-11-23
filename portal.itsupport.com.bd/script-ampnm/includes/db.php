<?php
require_once __DIR__ . '/bootstrap.php';

function ampnm_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;
    $db = $config['db'] ?? [];
    $host = $db['host'] ?? '127.0.0.1';
    $port = (int)($db['port'] ?? 3306);
    $name = $db['name'] ?? 'ampnm';
    $user = $db['user'] ?? '';
    $pass = $db['password'] ?? '';
    $charset = $db['charset'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
    }
}

function ampnm_bootstrap_schema(PDO $pdo): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            name VARCHAR(191) NOT NULL,\n            email VARCHAR(191) NOT NULL UNIQUE,\n            password_hash VARCHAR(255) NOT NULL,\n            role VARCHAR(50) NOT NULL DEFAULT 'admin',\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS licenses (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            license_key VARCHAR(191) NOT NULL,\n            status VARCHAR(50) NOT NULL,\n            expires_at DATE NULL,\n            bound_host VARCHAR(191) NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS notifications (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            title VARCHAR(191) NOT NULL,\n            body TEXT NULL,\n            severity VARCHAR(50) DEFAULT 'info',\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS devices (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            name VARCHAR(191) NOT NULL,\n            host VARCHAR(191) NOT NULL,\n            monitor_method VARCHAR(50) DEFAULT 'ping',\n            monitor_port INT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}
