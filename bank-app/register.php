<?php
/**
 * register.php — VULNERABLE account opening
 * ============================================================================
 * Bugs (maps to signup.php):
 *   1. SQL Injection: raw input concatenated into an INSERT.
 *   2. Passwords stored in PLAINTEXT.
 *   3. No input validation / no output escaping -> the username is a stored
 *      XSS vector (it is echoed raw on the dashboard and in admin panel).
 */
include 'database.php';
$message = '';

if (isset($_POST['register'])) {
    $username  = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email     = $_POST['email'] ?? '';
    $password  = $_POST['password'] ?? '';
    $acct = '10' . mt_rand(10000000, 99999999);   // 10-digit account number

    // VULN #1: raw concatenation into INSERT (also no uniqueness check)
    $query = "INSERT INTO users (username, full_name, email, password, account_number)
              VALUES ('$username', '$full_name', '$email', '$password', '$acct')";

    if (mysqli_query($database, $query)) {
        $message = '✅ Account created! <a href="login.php">Log in now</a>';
    } else {
        $message = 'Error: ' . mysqli_error($database);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Open an Account</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .wrap { max-width:420px; margin:4vh auto; padding:0 16px; }
  .brand { text-align:center; font-size:1.6em; font-weight:700; color:#f5c04c; margin-bottom:18px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.4); }
  h2 { margin:0 0 6px; font-size:1.2em; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  .msg { background:#143a16; border:1px solid #3f9e4f; color:#b8e6bd; padding:10px; border-radius:6px; margin:12px 0; font-size:.9em; }
  label { display:block; margin:12px 0 4px; font-size:.85em; color:#9fb3d1; }
  input { width:100%; box-sizing:border-box; padding:10px; border-radius:6px; border:1px solid #2c4270; background:#0b1526; color:#fff; }
  button { width:100%; padding:11px; margin-top:16px; background:#1f6feb; color:#fff; border:none; border-radius:6px; font-size:1em; cursor:pointer; }
  .muted { font-size:.85em; color:#8aa2c4; text-align:center; } .muted a { color:#f5c04c; }
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">🏦 Bank of VULN</div>
  <div class="card">
    <h2>Open an Account</h2>
    <div class="hint">
      💡 <strong>Stored XSS:</strong> register with username
      <code>&lt;script&gt;alert(document.cookie)&lt;/script&gt;</code> and watch
      it execute on your dashboard. It is stored raw and echoed raw everywhere.
      (Avoid <code>'</code> in the payload — a raw single quote would break the
      SQL INSERT first, which is its own bug.)
    </div>
    <?php if ($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>
    <form method="POST">
      <label>Username</label>
      <input name="username" placeholder="pick a username">
      <label>Full name</label>
      <input name="full_name" placeholder="Your real name">
      <label>Email</label>
      <input name="email" placeholder="you@example.com">
      <label>Password</label>
      <input name="password" type="password">
      <button name="register">Create account</button>
    </form>
    <p class="muted">Already a customer? <a href="login.php">Log in</a></p>
  </div>
</div>
</body>
</html>
