<?php
/**
 * bonus.php — "Claim your daily $10 bonus"
 * ============================================================================
 * VULN (maps to race-condition.php): read-modify-write with NO lock.
 *   Thread A reads balance(10), Thread B reads balance(10),
 *   both write back 20 -> one deposit is LOST.
 * Fire N concurrent requests and you do NOT get N×$10 — you get a race.
 * (If this page were a transfer, the same bug = double-spend / lost updates.)
 */
include 'database.php';
session_start();
$uid = $_SESSION['user_id'] ?? null;
// Release the PHP session file lock immediately: PHP's default session
// handler serializes requests that share a PHPSESSID, which would HIDE the
// TOCTOU race this page exists to demonstrate. (Real apps hit this exact
// subtlety: the lock is held for the whole request by default.)
session_write_close();
if (!$uid) { header('Location: login.php'); exit; }

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim'])) {
    // VULN: Step 1 READ, Step 2 COMPUTE, Step 3 WRITE — no atomic UPDATE,
    // no SELECT ... FOR UPDATE, no transaction. A concurrent request can
    // interleave between any two steps.
    $row = mysqli_fetch_assoc(mysqli_query($database, "SELECT balance FROM users WHERE id=$uid"));

    // Simulated processing/network latency. In production this delay is real
    // (app logic, round-trips), and it is EXACTLY what opens the TOCTOU
    // window between the read and the write. We add it so the race is
    // reliably reproducible in this lab instead of a 1-in-1000 event.
    usleep(200000);  // 200 ms — concurrent requests now both read the OLD balance

    $new = (float)$row['balance'] + 10.00;
    mysqli_query($database, "UPDATE users SET balance=$new WHERE id=$uid");
    $message = "Claimed \$10 bonus. New balance: \$" . number_format($new, 2);
}

$me = mysqli_fetch_assoc(mysqli_query($database, "SELECT * FROM users WHERE id=$uid"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Bonus</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; } .nav a:hover { text-decoration:underline; }
  .wrap { max-width:560px; margin:2vh auto; padding:0 16px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; }
  h2 { margin:0 0 4px; } .sub { color:#9fb3d1; font-size:.85em; margin-bottom:14px; }
  .bal { font-size:2em; color:#f5c04c; margin:14px 0; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  .msg { background:#143a16; border:1px solid #3f9e4f; color:#b8e6bd; padding:10px; border-radius:6px; margin:12px 0; font-size:.9em; }
  button { width:100%; padding:11px; margin-top:8px; background:#1f6feb; color:#fff; border:none; border-radius:6px; font-size:1em; cursor:pointer; }
  pre { background:#0a101d; border:1px solid #2c4270; padding:10px; border-radius:6px; overflow:auto; font-size:.78em; color:#9fb3d1; }
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
    <h2>🎁 Daily $10 bonus</h2>
    <div class="sub">Click the button to claim your reward.</div>
    <div class="bal">Balance: $<?= number_format((float)$me['balance'], 2) ?></div>

    <div class="hint">
      💡 A single click works fine — that is NOT the race. You need
      <strong>many simultaneous requests</strong>. Try this from your terminal
      (log in with a cookie jar first):
    </div>

    <pre># login once, save the session cookie
curl -s -c jar.txt -d "email=bob@bank.local&password=bob123&login=1" \
  http://localhost/vunerability/bank-app/login.php -L -o /dev/null

# fire 10 concurrent bonus claims — watch the balance NOT go up by $100
for i in $(seq 1 10); do
  curl -s -b jar.txt -d "claim=1" \
    http://localhost/vunerability/bank-app/bonus.php -o /dev/null &
done; wait
# now check index.php — lost updates mean balance &lt; expected</pre>

    <?php if ($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>

    <form method="POST">
      <button name="claim">Claim $10</button>
    </form>
  </div>
</div>
</body>
</html>
