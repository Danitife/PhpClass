<?php
/**
 * view-account.php — VULNERABLE customer lookup (IDOR)
 * ============================================================================
 * Bug (maps to broken-authorization.php): reads ANY profile by `?id=` with no
 * ownership or role check. Any logged-in customer can read everyone's data,
 * including the PLAINTEXT password column.
 */
include 'database.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$id = $_GET['id'] ?? 2;   // VULN: fully client-controlled
$query = "SELECT id, username, full_name, email, password, balance, account_number, role
          FROM users WHERE id=$id";
$user = mysqli_fetch_assoc(mysqli_query($database, $query));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Customer Lookup</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; } .nav a:hover { text-decoration:underline; }
  .wrap { max-width:640px; margin:2vh auto; padding:0 16px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; }
  h2 { margin:0 0 4px; } .sub { color:#9fb3d1; font-size:.85em; margin-bottom:14px; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  table { width:100%; border-collapse:collapse; }
  th,td { border-bottom:1px solid #22355a; padding:8px; font-size:.85em; text-align:left; }
  th { color:#f5c04c; } td code { color:#ff8a8a; }
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
    <h2>👀 Customer lookup</h2>
    <div class="sub">Viewing user id = <b><?= htmlspecialchars($id) ?></b></div>

    <div class="hint">
      💡 <strong>IDOR:</strong> walk the ids — <code>?id=1</code>,
      <code>?id=2</code>, <code>?id=3</code>… every profile (and password)
      is readable by any logged-in user.
    </div>

    <?php if ($user): ?>
    <table>
      <?php foreach ($user as $col => $val): ?>
        <tr>
          <th><?= htmlspecialchars($col) ?></th>
          <td><?php if ($col === 'password'): ?><code><?= htmlspecialchars($val) ?></code> (plaintext!)<?php else: ?><?= htmlspecialchars($val) ?><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
      <p style="color:#ff8a8a;">No user found with id = <?= htmlspecialchars($id) ?></p>
    <?php endif; ?>

    <p style="margin-top:16px;font-size:.9em;">
      <a href="?id=1">id=1 (admin)</a> ·
      <a href="?id=2">id=2</a> ·
      <a href="?id=3">id=3</a> ·
      <a href="?id=4">id=4</a>
    </p>

    <div class="debug"><strong>DEBUG</strong><br>Query: <code><?= htmlspecialchars($query) ?></code></div>
  </div>
</div>
</body>
</html>
