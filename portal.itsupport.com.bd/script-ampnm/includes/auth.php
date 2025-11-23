<?php
require_once __DIR__ . '/state.php';
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ampnm_current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        $pdo = ampnm_pdo();
        $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function ampnm_require_auth(): void
{
    ampnm_ensure_installed();
    ampnm_ensure_license_active();

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . ampnm_base_url() . '/login.php');
        exit;
    }
}

function ampnm_handle_login(string $email, string $password): array
{
    ampnm_ensure_installed();
    ampnm_ensure_license_active();

    $pdo = ampnm_pdo();
    $stmt = $pdo->prepare('SELECT id, email, password_hash, name FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => trim($email)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['error' => 'Invalid credentials'];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    return ['success' => true];
}

function ampnm_logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
