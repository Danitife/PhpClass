<?php
/**
 * file.php — VULNERABLE file viewer (path traversal + LFI)
 * ============================================================================
 * Bugs (maps to file-exposure.php):
 *   1. Path traversal: `?file=` is concatenated onto __DIR__ with NO
 *      normalisation or allowlist -> read ANY file on disk (including the
 *      bank's own PHP source and the leaked .env / config.php.bak).
 *   2. Local File Inclusion: `?page=` is passed to include() -> the file is
 *      EXECUTED as PHP. Combine with a php:// filter to read source, or with
 *      a planted file to run arbitrary code (RCE).
 */
$file = $_GET['file'] ?? 'help.txt';
$path = __DIR__ . '/' . $file;          // BUG: no allowlist, no realpath check

$contents = null;
$err = null;
if (is_file($path) && is_readable($path)) {
    $contents = file_get_contents($path);
} else {
    $err = "Cannot read: $path";
}

$included = null;
if (isset($_GET['page'])) {
    // BUG: user-controlled include(). For a relative path we prepend the
    // app dir; a php:// stream wrapper is passed straight through to
    // include() so `php://filter/convert.base64-encode/resource=database.php`
    // leaks the raw source (LFI → source disclosure).
    $inc = $_GET['page'];
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $inc)) {
        $inc = __DIR__ . '/' . $inc;
    }
    ob_start();
    @include $inc;
    $included = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Internal Documents</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; } .nav a:hover { text-decoration:underline; }
  .wrap { max-width:860px; margin:2vh auto; padding:0 16px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; }
  h2 { margin:0 0 4px; } .sub { color:#9fb3d1; font-size:.85em; margin-bottom:14px; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  input[type=text] { width:70%; padding:9px; border-radius:6px; border:1px solid #2c4270; background:#0b1526; color:#fff; }
  button { padding:9px 18px; background:#1f6feb; color:#fff; border:none; border-radius:6px; cursor:pointer; }
  pre { background:#0a101d; border:1px solid #2c4270; color:#7ee08f; padding:12px; border-radius:6px; overflow:auto; max-height:420px; font-size:.8em; }
  .danger { background:#3a1620; border:1px solid #ff6b6b; padding:10px; margin-top:14px; border-radius:6px; }
  .debug { background:#0a101d; border:1px solid #2c4270; padding:10px; margin-top:16px; font-size:.78em; border-radius:6px; color:#9fb3d1; }
</style>
</head>
<body>
<div class="nav">
  <a href="index.php">Dashboard</a> <a href="transfer.php">Transfer</a>
  <a href="bonus.php">Claim Bonus</a> <a href="profile.php">Profile</a>
  <a href="view-account.php">Browse Accounts</a> <a href="admin.php">Admin</a>
</div>
<div class="wrap">
  <div class="card">
    <h2>📄 Internal file viewer</h2>
    <div class="sub">Intended use: read one of our help text files.</div>

    <form method="GET">
      <input type="text" name="file" value="<?= htmlspecialchars($file) ?>" size="60">
      <button type="submit">View</button>
    </form>

    <p style="font-size:.85em;color:#8aa2c4;">
      Quick links:
      <a href="?file=.env">.env</a> ·
      <a href="?file=config.php.bak">config.php.bak</a> ·
      <a href="?file=../database.php">read database.php source</a> ·
      <a href="?file=../../../../../../etc/passwd">/etc/passwd</a> ·
      <a href="?page=php://filter/convert.base64-encode/resource=database.php">LFI → base64 source</a>
    </p>

    <?php if ($contents !== null): ?>
      <h3 style="color:#f5c04c;">File contents (raw)</h3>
      <pre><?= htmlspecialchars($contents) ?></pre>
    <?php elseif ($err !== null): ?>
      <p style="color:#ff8a8a;"><?= htmlspecialchars($err) ?></p>
    <?php endif; ?>

    <?php if ($included !== null): ?>
      <div class="danger">
        <h3>LFI output (?page=...)</h3>
        <pre><?= htmlspecialchars($included) ?></pre>
      </div>
    <?php endif; ?>

    <div class="debug">
      <strong>DEBUG</strong><br>
      ?file = <code><?= htmlspecialchars($file) ?></code><br>
      Resolved path = <code><?= htmlspecialchars($path) ?></code><br>
      Realpath = <code><?= htmlspecialchars(realpath($path) ?: '(not found)') ?></code>
    </div>
  </div>
</div>
</body>
</html>
