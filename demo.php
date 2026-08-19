<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_admin()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password && attempt_login($email, $password)) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = 'Incorrect email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign In</title>
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;}
  .login-card{background:var(--panel);border:1px solid var(--border);border-radius:14px;
    padding:34px 30px;max-width:380px;width:100%;}
  .login-brand{display:flex;align-items:center;gap:10px;margin-bottom:22px;}
  .login-card h1{font-size:16px;margin:0 0 4px;}
  .login-card p.sub{font-size:12.5px;color:var(--text-faint);margin:0 0 22px;}
</style>
</head>
<body>
  <div class="login-card">
    <div class="login-brand">
      <div class="brand-mark">S</div>
      <div>
        <div class="brand-name">Studio Admin</div>
      </div>
    </div>
    <h1>Sign in</h1>
    <p class="sub">Enter your admin credentials to continue.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required autofocus>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sign in</button>
    </form>
  </div>
</body>
</html>
