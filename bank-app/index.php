<?php
/**
 * index.php — Dashboard
 * ============================================================================
 * VULN: the logged-in username is echoed RAW (no htmlspecialchars), so any
 * stored-XSS payload planted via register.php/profile.php executes here.
 * The session cookie is not HttpOnly (XAMPP default), so the XSS can steal
 * the PHPSESSID -> session hijacking.
 */
include 'database.php';
session_start();
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { header('Location: login.php'); exit; }

$me = mysqli_fetch_assoc(mysqli_query($database, "SELECT * FROM users WHERE id=$uid"));
if (!$me) { die('User not found — did someone drop the table?'); }

$txns = mysqli_query($database,
    "SELECT * FROM transactions
     WHERE from_account='{$me['account_number']}' OR to_account='{$me['account_number']}'
     ORDER BY id DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Dashboard</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; }
  .nav a:hover { text-decoration:underline; }
  .wrap { max-width:760px; margin:2vh auto; padding:0 16px; }
  .hello { font-size:1.5em; margin:18px 0 4px; }
  .acct { color:#9fb3d1; font-size:.9em; margin-bottom:20px; }
  .balance-card { background:linear-gradient(135deg,#1f6feb,#12315e); border-radius:12px; padding:22px; margin-bottom:20px; }
  .balance-label { font-size:.85em; opacity:.85; }
  .balance { font-size:2.4em; font-weight:700; margin-top:4px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; margin-bottom:22px; }
  .tile { background:#12203a; border:1px solid #22355a; border-radius:10px; padding:16px; text-align:center; }
  .tile a { color:#f5c04c; text-decoration:none; font-weight:600; } .tile a:hover { text-decoration:underline; }
  .tile p { font-size:.8em; color:#9fb3d1; margin:6px 0 0; }
  table { width:100%; border-collapse:collapse; background:#12203a; border-radius:10px; overflow:hidden; }
  th,td { border-bottom:1px solid #22355a; padding:10px; font-size:.85em; text-align:left; }
  th { background:#0f1a2f; color:#f5c04c; }
  .neg { color:#ff8a8a; } .pos { color:#7ee08f; }
</style>
</head>
<body>
<div class="nav">
  <a href="index.php">Dashboard</a>
  <a href="transfer.php">Transfer</a>
  <a href="bonus.php">Claim Bonus</a>
  <a href="profile.php">Profile</a>
  <a href="view-account.php">Browse Accounts</a>
  <a href="admin.php">Admin</a>
  <a href="logout.php">Logout</a>
</div>
<div class="wrap">
  <!-- VULN: raw username echo -> stored XSS fires here -->
  <div class="hello">Welcome back, <?= $me['username'] ?> 👋</div>
  <div class="acct">Account # <?= $me['account_number'] ?> &nbsp;·&nbsp; <?= htmlspecialchars($me['email']) ?></div>

  <div class="balance-card">
    <div class="balance-label">Available balance</div>
    <div class="balance">$<?= number_format((float)$me['balance'], 2) ?></div>
  </div>

  <div class="grid">
    <div class="tile"><a href="transfer.php">💸 Transfer money</a><p>Move funds to any account number</p></div>
    <div class="tile"><a href="bonus.php">🎁 Claim $10 bonus</a><p>Daily reward (single-use... maybe)</p></div>
    <div class="tile"><a href="profile.php">🛠️ Edit profile</a><p>Change username / email / password</p></div>
    <div class="tile"><a href="view-account.php">👀 Browse accounts</a><p>Look up any customer</p></div>
    <div class="tile"><a href="admin.php">🛡️ Admin area</a><p>Staff only</p></div>
    <div class="tile"><a href="file.php">📄 File viewer</a><p>Internal documents</p></div>
  </div>

  <h3 style="color:#f5c04c;font-size:1em;">Recent transactions</h3>
  <?php if ($txns && mysqli_num_rows($txns) > 0): ?>
  <table>
    <tr><th>From</th><th>To</th><th>Amount</th><th>Note</th><th>Time</th></tr>
    <?php while ($t = mysqli_fetch_assoc($txns)): ?>
      <tr>
        <td><?= htmlspecialchars($t['from_account']) ?></td>
        <td><?= htmlspecialchars($t['to_account']) ?></td>
        <td class="<?= ($t['to_account'] === $me['account_number']) ? 'pos' : 'neg' ?>">
          <?= $t['to_account'] === $me['account_number'] ? '+' : '-' ?>$<?= number_format((float)$t['amount'], 2) ?>
        </td>
        <td><?= htmlspecialchars($t['note']) ?></td>
        <td><?= htmlspecialchars($t['created_at']) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
  <?php else: ?>
    <p style="color:#8aa2c4;font-size:.9em;">No transactions yet. Try a transfer!</p>
  <?php endif; ?>
</div>
</body>
</html>
