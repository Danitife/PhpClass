<?php
/**
 * login.php — VULNERABLE BANK LOGIN
 * ============================================================================
 * Bugs baked into this page (each maps to a demo in the parent folder):
 *   1. SQL Injection ............ login.php / injection.php (auth bypass)
 *   2. Plaintext passwords ...... (no hashing anywhere)
 *   3. Broken rate limiting ..... rate-limit.php (keys on X-Forwarded-For)
 *   4. Verbose SQL errors ....... (error-based SQLi oracle)
 *   5. Role shipped in a cookie . broken-authorization.php (client-trusted)
 */
include 'database.php';
session_start();

// ---- VULN #3: rate limiter keys on X-Forwarded-For (client spoofable) ----
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_file = __DIR__ . '/.rate_state.json';
$state = file_exists($rate_file) ? (json_decode(file_get_contents($rate_file), true) ?: []) : [];
$now  = time();
$rec  = $state[$client_ip] ?? ['n' => 0, 't' => $now];
if ($now - $rec['t'] > 60) $rec = ['n' => 0, 't' => $now];
$blocked = $rec['n'] >= 3;

$message = '';
$last_query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($blocked) {
        $message = "⛔ Too many failed attempts. Try again in "
                 . (60 - ($now - $rec['t'])) . "s (key: $client_ip).";
    } else {
        // VULN #1: raw input concatenation straight into the query.
        $query = "SELECT * FROM users WHERE email='$email' AND password='$password'";
        $last_query = $query;
        $result = mysqli_query($database, $query);

        if (!$result) {
            $message = 'SQL ERROR: ' . mysqli_error($database);      // VULN #4
        } elseif (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            setcookie('role',    $user['role'], 0, '/');             // VULN #5
            setcookie('user_id', $user['id'],   0, '/');
            $rec = ['n' => 0, 't' => $now];
            header('Location: index.php');
            exit;
        } else {
            $rec['n']++;
            $message = "Invalid credentials. Attempts used: {$rec['n']}/3 (key: $client_ip).";
        }
    }
    $state[$client_ip] = $rec;
    file_put_contents($rate_file, json_encode($state));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Login</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .wrap { max-width:420px; margin:4vh auto; padding:0 16px; }
  .brand { text-align:center; font-size:1.6em; font-weight:700; color:#f5c04c; margin-bottom:18px; letter-spacing:.5px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.4); }
  h2 { margin:0 0 6px; font-size:1.2em; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code, .debug code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  .msg { background:#3a1620; border:1px solid #ff6b6b; color:#ffb3b3; padding:10px; border-radius:6px; margin:12px 0; font-size:.9em; }
  label { display:block; margin:12px 0 4px; font-size:.85em; color:#9fb3d1; }
  input { width:100%; box-sizing:border-box; padding:10px; border-radius:6px; border:1px solid #2c4270; background:#0b1526; color:#fff; }
  button { width:100%; padding:11px; margin-top:16px; background:#1f6feb; color:#fff; border:none; border-radius:6px; font-size:1em; cursor:pointer; }
  button:hover { background:#2b7df7; }
  .muted { font-size:.85em; color:#8aa2c4; text-align:center; }
  .muted a { color:#f5c04c; }
  .debug { background:#0a101d; border:1px solid #2c4270; padding:10px; margin-top:16px; font-size:.78em; border-radius:6px; color:#9fb3d1; }
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">🏦 Bank of VULN</div>
  <div class="card">
    <h2>Customer Login</h2>
    <div class="hint">
      💡 <strong>Payloads to try</strong> (SQLi auth bypass):<br>
      Email: <code>' OR '1'='1' #</code> &nbsp; Password: <code>anything</code><br>
      Email: <code>admin' #</code> &nbsp; Password: <code>anything</code>
    </div>
    <?php if ($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>
    <form method="POST">
      <label>Email</label>
      <input name="email" placeholder="alice@bank.local">
      <label>Password</label>
      <input name="password" type="password">
      <button name="login">Sign in</button>
    </form>
    <p class="muted"><a href="register.php">Open an account →</a></p>
    <div class="debug">
      <strong>DEBUG</strong><br>
      REMOTE_ADDR: <code><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '-') ?></code><br>
      Rate-limit key: <code><?= htmlspecialchars($client_ip) ?></code>
      &nbsp; attempts: <?= (int)$rec['n'] ?>/3<br>
      Last query: <code><?= htmlspecialchars($last_query) ?></code>
    </div>
  </div>
</div>
</body>
</html>
