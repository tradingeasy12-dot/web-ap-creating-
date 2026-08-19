<?php
// One-time installer. Delete or rename this file after installation is complete.

if (file_exists(__DIR__ . '/config.php')) {
    die('This site is already installed (config.php exists). Delete install.php now. If you really need to reinstall, delete config.php first — this will not touch your existing data otherwise.');
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost     = trim($_POST['db_host'] ?? 'localhost');
    $dbName     = trim($_POST['db_name'] ?? '');
    $dbUser     = trim($_POST['db_user'] ?? '');
    $dbPassword = $_POST['db_password'] ?? '';
    $siteUrl    = trim($_POST['site_url'] ?? '');

    $adminName     = trim($_POST['admin_name'] ?? '');
    $adminEmail    = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if (!$dbName || !$dbUser || !$adminEmail || !$adminPassword || !$adminName) {
        $error = 'Please fill in every field.';
    } else {
        try {
            // 1) Test the database connection using exactly what was typed in the form.
            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 2) Run schema.sql to create every table.
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            // Split on semicolons at line end — good enough for this schema file (no stored procedures).
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            // 3) Create the first admin user with a securely hashed password.
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
            );
            $stmt->execute([$adminName, $adminEmail, $hash, 'super_admin']);

            // 4) Write config.php with the real values.
            $appSecret = bin2hex(random_bytes(32));
            $configContents = "<?php\n"
                . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                . "define('DB_PASSWORD', " . var_export($dbPassword, true) . ");\n"
                . "define('APP_SECRET_KEY', " . var_export($appSecret, true) . ");\n"
                . "define('SITE_URL', " . var_export($siteUrl, true) . ");\n";

            if (!file_put_contents(__DIR__ . '/config.php', $configContents)) {
                throw new Exception('Could not write config.php — check that this folder is writable, then try again.');
            }

            // 5) Make sure the upload folders are writable so video/thumbnail uploads work.
            //    We try 755 first (safer), then fall back to 775 if the server needs it.
            $foldersToFix = [
                __DIR__ . '/uploads',
                __DIR__ . '/uploads/videos',
                __DIR__ . '/uploads/thumbnails',
            ];
            $permissionWarnings = [];
            foreach ($foldersToFix as $folder) {
                if (!is_dir($folder)) {
                    @mkdir($folder, 0755, true);
                }
                if (!@chmod($folder, 0755)) {
                    @chmod($folder, 0775);
                }
                if (!is_writable($folder)) {
                    $permissionWarnings[] = str_replace(__DIR__ . '/', '', $folder);
                }
            }

            $success = true;
        } catch (Throwable $e) {
            $error = 'Setup failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0D0F14;color:#E8EAF0;
    display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .card{background:#151822;border:1px solid #262B38;border-radius:14px;padding:32px;max-width:460px;width:100%;}
  h1{font-size:18px;margin:0 0 6px;} p.sub{color:#8B93A7;font-size:13px;margin:0 0 22px;}
  label{display:block;font-size:12px;color:#8B93A7;margin:14px 0 5px;}
  input{width:100%;background:#1B1F2B;border:1px solid #262B38;color:#E8EAF0;border-radius:8px;padding:9px 12px;
    font-size:13px;box-sizing:border-box;}
  button{width:100%;margin-top:22px;background:#4F7CFF;color:#fff;border:none;border-radius:8px;padding:11px;
    font-size:14px;font-weight:600;cursor:pointer;}
  .divider{margin:22px 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#5C6478;}
  .alert{padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
  .alert-error{background:rgba(240,96,122,.12);border:1px solid rgba(240,96,122,.3);color:#F0607A;}
  .alert-success{background:rgba(61,214,140,.12);border:1px solid rgba(61,214,140,.3);color:#3DD68C;}
  a.btnlink{display:block;text-align:center;margin-top:16px;background:#4F7CFF;color:#fff;text-decoration:none;
    border-radius:8px;padding:11px;font-weight:600;font-size:14px;}
</style>
</head>
<body>
<div class="card">
  <h1>Site installation</h1>
  <p class="sub">Enter your database details and create the first admin account.</p>

  <?php if ($success): ?>
    <div class="alert alert-success">Installed successfully. Every table has been created and your admin account is ready.</div>
    <?php if (empty($permissionWarnings)): ?>
      <div class="alert alert-success">Upload folders (<code>uploads/videos</code>, <code>uploads/thumbnails</code>) are writable — video uploads should work.</div>
    <?php else: ?>
      <div class="alert alert-error">
        Could not make these folders writable automatically: <?= htmlspecialchars(implode(', ', $permissionWarnings)) ?>.
        Please set permission 755 (or 775) on them manually via File Manager or FTP, or run:
        <br><code>chmod 755 <?= htmlspecialchars(implode(' ', $permissionWarnings)) ?></code>
      </div>
    <?php endif; ?>
    <a class="btnlink" href="/demo.php">Go to admin sign in →</a>
    <p class="sub" style="margin-top:16px;">Important: delete or rename <code>install.php</code> now so no one else can run it again.</p>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="divider">Database</div>
      <label>Database host</label>
      <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
      <label>Database name</label>
      <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
      <label>Database user</label>
      <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
      <label>Database password</label>
      <input type="password" name="db_password" required>
      <label>Site URL</label>
      <input type="text" name="site_url" placeholder="https://yourdomain.com" value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>">

      <div class="divider">Admin account</div>
      <label>Your name</label>
      <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
      <label>Admin email (used to sign in)</label>
      <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
      <label>Admin password</label>
      <input type="password" name="admin_password" required>

      <button type="submit">Install</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
