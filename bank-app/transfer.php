<?php
/**
 * transfer.php — VULNERABLE money transfer
 * ============================================================================
 * Bugs (maps to logic-flaw.php + broken-authorization.php + race-condition.php):
 *   1. IDOR: the `from_account` comes from a HIDDEN form field. The server
 *      NEVER checks that it belongs to the logged-in user, so you can move
 *      money out of ANYONE's account.
 *   2. No amount validation: `amount` may be negative -> your balance
 *      INCREASES (refund-abuse / free money).
 *   3. No balance check: you can go overdrawn.
 *   4. Race condition: read-modify-write with NO lock / atomic UPDATE ->
 *      concurrent transfers double-spend (lost update).
 *   5. SQL Injection: account numbers concatenated raw into queries.
 */
include 'database.php';
session_start();
$uid = $_SESSION['user_id'] ?? null;
// Release the session file lock so concurrent transfers with the same
// PHPSESSID can overlap — otherwise PHP serializes them and the TOCTOU
// double-spend race below would be hidden.
session_write_close();
if (!$uid) { header('Location: login.php'); exit; }

$message = '';
$debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer'])) {
    $from   = $_POST['from_account'] ?? '';      // VULN #1: hidden field (IDOR)
    $to     = $_POST['to_account'] ?? '';        // VULN #5: raw -> SQLi
    $amount = (float)($_POST['amount'] ?? 0);    // VULN #2: no > 0 check
    $note   = $_POST['note'] ?? '';

    $debug = "SELECT balance FROM users WHERE account_number='$to'";
    $dest = mysqli_fetch_assoc(mysqli_query($database, $debug));

    if (!$dest) {
        $message = "Destination account not found: " . htmlspecialchars($to);
    } else {
        // VULN #4: read-modify-write, no SELECT ... FOR UPDATE / atomic op
        $srow = mysqli_fetch_assoc(mysqli_query($database,
            "SELECT balance FROM users WHERE account_number='$from'"));
        $from_balance = (float)$srow['balance'];
        $new_from = $from_balance - $amount;      // negative amount ADDS money here
        $new_to   = (float)$dest['balance'] + $amount;

        mysqli_query($database, "UPDATE users SET balance=$new_from WHERE account_number='$from'");
        mysqli_query($database, "UPDATE users SET balance=$new_to   WHERE account_number='$to'");
        mysqli_query($database, "INSERT INTO transactions (from_account, to_account, amount, note)
                                 VALUES ('$from','$to',$amount,'$note')");

        $message = "Transferred \${$amount} from <b>$from</b> to <b>$to</b>. "
                 . "<span style='color:#ffb3b3'>No ownership check was performed!</span>";
    }
}
$me = mysqli_fetch_assoc(mysqli_query($database, "SELECT * FROM users WHERE id=$uid"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Transfer</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; } .nav a:hover { text-decoration:underline; }
  .wrap { max-width:560px; margin:2vh auto; padding:0 16px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; }
  h2 { margin:0 0 4px; } .sub { color:#9fb3d1; font-size:.85em; margin-bottom:14px; }
  .bal { font-size:1.2em; color:#f5c04c; margin-bottom:16px; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code, .debug code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
  .msg { background:#143a16; border:1px solid #3f9e4f; color:#b8e6bd; padding:10px; border-radius:6px; margin:12px 0; font-size:.9em; }
  label { display:block; margin:12px 0 4px; font-size:.85em; color:#9fb3d1; }
  input { width:100%; box-sizing:border-box; padding:10px; border-radius:6px; border:1px solid #2c4270; background:#0b1526; color:#fff; }
  button { width:100%; padding:11px; margin-top:16px; background:#1f6feb; color:#fff; border:none; border-radius:6px; font-size:1em; cursor:pointer; }
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
    <h2>💸 Transfer funds</h2>
    <div class="sub">Your account: <b><?= $me['account_number'] ?></b></div>
    <div class="bal">Balance: $<?= number_format((float)$me['balance'], 2) ?></div>

    <div class="hint">
      💡 <strong>IDOR:</strong> the <em>from</em> field is a hidden input — open
      DevTools and change it to another account number (e.g. admin's
      <code>1000000001</code>) to transfer <em>their</em> money.<br>
      💡 <strong>Negative amount:</strong> send <code>-500</code> to <em>gain</em> money.
    </div>

    <?php if ($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>

    <form method="POST">
      <label>From account (hidden — tamper in DevTools)</label>
      <input type="hidden" name="from_account" value="<?= $me['account_number'] ?>">
      <label>To account number</label>
      <input name="to_account" value="1000000002">
      <label>Amount</label>
      <input name="amount" type="number" value="10" step="0.01">
      <label>Note</label>
      <input name="note" value="">
      <button name="transfer">Transfer</button>
    </form>

    <div class="debug">
      <strong>DEBUG</strong><br>
      Lookup query: <code><?= htmlspecialchars($debug) ?></code>
    </div>
  </div>
</div>
</body>
</html>
