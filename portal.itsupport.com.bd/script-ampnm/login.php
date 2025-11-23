<?php
require_once __DIR__ . '/includes/state.php';
require_once __DIR__ . '/includes/auth.php';

if (!ampnm_setup_complete()) {
    header('Location: ' . ampnm_base_url() . '/install.php');
    exit;
}
if (ampnm_license_status() !== 'active') {
    header('Location: ' . ampnm_base_url() . '/license_setup.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = ampnm_handle_login($email, $password);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        header('Location: ' . ampnm_base_url() . '/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AMPNM</title>
    <link rel="stylesheet" href="./assets/style.css">
</head>
<body class="setup-shell">
    <div class="setup-card">
        <p class="badge">Portal access</p>
        <h1>Welcome back</h1>
        <p class="muted">Sign in with the administrator you created during setup.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="grid two" style="gap:12px;">
            <div class="form-group" style="grid-column:1 / -1;">
                <label class="label">Email</label>
                <input class="input" name="email" type="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
                <label class="label">Password</label>
                <input class="input" name="password" type="password" required>
            </div>
            <button class="btn" type="submit">Login</button>
            <a class="home-link" href="<?php echo htmlspecialchars(ampnm_base_url()); ?>/install.php">Re-run setup</a>
        </form>
    </div>
</body>
</html>
