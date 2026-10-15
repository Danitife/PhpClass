<?php
/**
 * profile.php — VULNERABLE profile editor
 * ============================================================================
 * Bugs (maps to broken-authorization.php IDOR + login.php SQLi + signup.php XSS):
 *   1. IDOR: `?id=` in the URL picks WHICH user's profile is edited. There is
 *      NO ownership check -> edit ANYONE's username/email/password.
 *   2. No "current password" prompt -> a password reset with zero auth.
 *   3. SQL Injection: fields concatenated raw into an UPDATE.
 *   4. Username echoed raw -> another stored-XSS sink.
 */
include 'database.php';
session_start();
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { header('Location: login.php'); exit; }

// VULN #1: the id is fully client-controlled
$id = $_GET['id'] ?? $uid;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $username = $_POST['username'] ?? '';
    $email    = $_POST['email'] ?? '';
    $newpass  = $_POST['new_password'] ?? '';

    // VULN #2 + #3: no authz, no current-password, raw SQL
    $query = "UPDATE users SET username='$username', email='$email', password='$newpass' WHERE id=$id";
    if (mysqli_query($database, $query)) {
        $message = "Profile updated for user id = $id";
    } else {
        $message = 'Error: ' . mysqli_error($database);
    }
}

$user = mysqli_fetch_assoc(mysqli_query($database, "SELECT * FROM users WHERE id=$id"));
if (!$user) { die("No user with id=$id"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank of VULN — Profile</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1526; color:#e8eef7; margin:0; }
  .nav { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; padding:16px; background:#0f1a2f; border-bottom:1px solid #22355a; }
  .nav a { color:#f5c04c; text-decoration:none; font-size:.9em; } .nav a:hover { text-decoration:underline; }
  .wrap { max-width:560px; margin:2vh auto; padding:0 16px; }
  .card { background:#12203a; border:1px solid #22355a; border-radius:12px; padding:24px; }
  h2 { margin:0 0 4px; } .sub { color:#9fb3d1; font-size:.85em; margin-bottom:14px; }
  .hint { background:#1c2c4d; border-left:3px solid #f5c04c; padding:10px 12px; font-size:.82em; border-radius:4px; margin:12px 0; }
  .hint code { background:#0b1526; padding:1px 5px; border-radius:3px; color:#7ee08f; }
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
    <h2>🛠️ Edit profile</h2>
    <div class="sub">Editing user id = <b><?= $id ?></b> (taken from <code>?id=</code>)</div>

    <div class="hint">
      💡 <strong>IDOR:</strong> change the URL to <code>profile.php?id=1</code>
      (admin) and you can reset the <em>admin's</em> password without knowing
      the old one. Then log in as admin. 😈
    </div>

    <?php if ($message): ?><div class="msg"><?= $message ?></div><?php endif; ?>

    <form method="POST">
      <label>Username (echoed raw → stored XSS)</label>
      <input name="username" value="<?= $user['username'] ?>">
      <label>Email</label>
      <input name="email" value="<?= htmlspecialchars($user['email']) ?>">
      <label>New password (no current-password check!)</label>
      <input name="new_password" type="password" value="">
      <button name="save">Save changes</button>
    </form>

    <div class="debug">
      <strong>DEBUG</strong><br>
      Session user id: <?= $uid ?><br>
      Editing user id : <?= $id ?> &nbsp; (any difference = IDOR)<br>
      Current row: username=<code><?= $user['username'] ?></code>
      &nbsp; password=<code><?= htmlspecialchars($user['password']) ?></code>
    </div>
  </div>
</div>
</body>
</html>
