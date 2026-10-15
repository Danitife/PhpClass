<?php
/**
 * admin.php — VULNERABLE admin panel (vertical privilege escalation)
 * ============================================================================
 * Bug (maps to broken-authorization.php): the "is this an admin?" check reads
 * the `role` COOKIE — a value the client fully controls. There is also a
 * `?admin=1` flag that merely reveals the panel UI. Flip the cookie and you
 * are staff.
 *
 * Bonus: the table below echoes usernames RAW -> any stored-XSS payload
 * planted earlier executes when an admin loads this page.
 */
include 'database.php';
session_start();

// VULN: trust the client-controlled cookie instead of the server session.
$role = $_COOKIE['role'] ?? 'user';
$show = isset($_GET['admin']);            // UI toggle only, not a real gate

$rows = null;
if ($role === 'admin') {
    $rows = mysqli_query($database,
        "SELECT id, username, full_name, email, password, balance, account_number, role FROM users");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Admin</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; } .nav a:hover { text-decoration:underline; }
  .wrap { max-width:820px; margin:2vh auto; padding:0 16px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; }
  h2 { margin:0 0 4px; } .sub { color:#9fb3d1; font-size:.85em; margin-bottom:14px; }
  .hint { background:#3a1620; border-left:3px solid #ff6b6b; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  table { width:100%; border-collapse:collapse; }
  th,td { border-bottom:1px solid #22355a; padding:8px; font-size:.82em; text-align:left; }
  th { color:#f5c04c; } td code { color:#ff8a8a; }
  .denied { color:#ff8a8a; }
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
    <h2>🛡️ Staff administration</h2>
    <div class="sub">Your role (from cookie): <b><?= htmlspecialchars($role) ?></b></div>

    <?php if ($show): ?>
      <?php if ($role === 'admin'): ?>
        <div class="hint" style="background:#143a16;border-color:#3f9e4f;color:#b8e6bd;">
          ✅ Admin access granted. Full customer table below (usernames echoed RAW → stored XSS fires here).
        </div>
        <table>
          <tr><th>id</th><th>username</th><th>full_name</th><th>email</th><th>password</th><th>balance</th><th>acct</th><th>role</th></tr>
          <?php while ($r = mysqli_fetch_assoc($rows)): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><?= $r['username'] ?></td>   <!-- VULN: raw echo -->
            <td><?= htmlspecialchars($r['full_name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><code><?= htmlspecialchars($r['password']) ?></code></td>
            <td>$<?= number_format((float)$r['balance'], 2) ?></td>
            <td><?= htmlspecialchars($r['account_number']) ?></td>
            <td><?= htmlspecialchars($r['role']) ?></td>
          </tr>
          <?php endwhile; ?>
        </table>
      <?php else: ?>
        <p class="denied">⛔ Access denied — you are not an admin.</p>
        <p style="font-size:.85em;color:#8aa2c4;">
          Hint: open DevTools → Application → Cookies and set <code>role=admin</code>,
          then reload this page. (That is exactly the bug: the server trusts a
          value your browser controls.)
        </p>
      <?php endif; ?>
    <?php else: ?>
      <p style="color:#8aa2c4;">
        The panel exists at <code>admin.php?admin=1</code>.
      </p>
    <?php endif; ?>

    <div class="debug">
      <strong>DEBUG</strong><br>
      Session role: <code><?= htmlspecialchars($_SESSION['role'] ?? 'not logged in') ?></code><br>
      Cookie role : <code><?= htmlspecialchars($_COOKIE['role'] ?? '(none)') ?></code>
      &nbsp;← this is the one the gate checks (bug!)
    </div>
  </div>
</div>
</body>
</html>
