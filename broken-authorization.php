<?php
// Broken Authorization (IDOR) Vulnerable Profile Page
// IDOR (Insecure Direct Object Reference) is a web security vulnerability where an application provides direct access to objects based on user-supplied input without verifying if the user is authorized to access those objects.
include "database.php";

// -----------------------------------------------------------------------------
// Pretend the user "logged in" as user id = 2.
// In a real app this would come from $_SESSION after a successful login.
// We use a cookie here so you can SEE and TAMPER with it from the browser.
// -----------------------------------------------------------------------------
if (!isset($_COOKIE['user_id'])) {
    setcookie('user_id', '2', time() + 3600, '/');
    $_COOKIE['user_id'] = '2';
}
if (!isset($_COOKIE['role'])) {
    setcookie('role', 'user', time() + 3600, '/');
    $_COOKIE['role'] = 'user';
}

$logged_in_user_id = $_COOKIE['user_id'];
$logged_in_role    = $_COOKIE['role'];

// -----------------------------------------------------------------------------
// VULNERABLE LOGIC #1 — Insecure Direct Object Reference (IDOR)
// The page reads ?id= straight from the URL and shows THAT user's profile
// without ever checking whether the logged-in user is allowed to see it.
// -----------------------------------------------------------------------------
$id = $_GET['id'] ?? $logged_in_user_id;

$query  = "SELECT id, fullname, email, phone, password FROM users WHERE id='$id'";
$result = mysqli_query($database, $query);

// -----------------------------------------------------------------------------
// VULNERABLE LOGIC #2 — Vertical privilege escalation via parameter tampering
// "Admin panel" is gated by a URL flag and a cookie the client controls.
// Anyone can flip ?admin=1 and edit the role cookie to become an admin.
// -----------------------------------------------------------------------------
$show_admin_panel = isset($_GET['admin']) && $_GET['admin'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <style>
        body  { font-family: sans-serif; max-width: 720px; margin: 2em auto; }
        .debug { background:#f5f5dc; border:1px solid #999; padding:10px; margin-top:20px; font-size:0.9em; }
        .admin { background:#ffe0e0; border:1px solid #c00; padding:10px; margin-top:20px; }
        table { border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 4px 8px; }
    </style>
</head>
<body>

<h2>User Profile</h2>

<?php
if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "<table>";
    foreach ($user as $col => $val) {
        echo "<tr><th>" . htmlspecialchars($col) . "</th><td>" . htmlspecialchars($val) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No user found with id = " . htmlspecialchars($id) . "</p>";
}
?>

<p>
    <a href="?id=1">View id=1</a> |
    <a href="?id=2">View id=2</a> |
    <a href="?id=3">View id=3</a> |
    <a href="?admin=1">Open admin panel</a>
</p>

<?php
// -------- Vertical escalation demo: "admin only" area with no real check ----
if ($show_admin_panel) {
    echo "<div class='admin'><h3>Admin Panel — All Users</h3>";

    // Notice: we only check the COOKIE (which the client controls).
    // A real check must use a server-side session, not a cookie value.
    if ($logged_in_role === 'admin') {
        $all = mysqli_query($database, "SELECT id, fullname, email, password FROM users");
        echo "<table><tr><th>id</th><th>fullname</th><th>email</th><th>password</th></tr>";
        while ($row = mysqli_fetch_assoc($all)) {
            echo "<tr><td>{$row['id']}</td><td>"
                . htmlspecialchars($row['fullname']) . "</td><td>"
                . htmlspecialchars($row['email']) . "</td><td>"
                . htmlspecialchars($row['password']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>You are not an admin. (role cookie = "
            . htmlspecialchars($logged_in_role) . ")</p>";
        echo "<p><em>Hint: edit the <code>role</code> cookie in DevTools and reload.</em></p>";
    }
    echo "</div>";
}
?>

<div class="debug">
    <strong>DEBUG</strong><br>
    Logged-in user id (from cookie): <?= htmlspecialchars($logged_in_user_id) ?><br>
    Logged-in role     (from cookie): <?= htmlspecialchars($logged_in_role) ?><br>
    Viewing profile id (from URL)  : <?= htmlspecialchars($id) ?><br>
    Query: <code><?= htmlspecialchars($query) ?></code>
</div>

</body>
</html>

<?php
/* =============================================================================
   BROKEN AUTHORIZATION  —  OWASP A01:2021 (the #1 web-app risk)
   =============================================================================

   Authentication answers: "Who are you?"
   Authorization  answers: "Are you allowed to do THIS to THAT object?"

   Broken Authorization = the server skips (or trusts the client for) the
   second question. The user is logged in, but the app forgets to check
   whether THIS user can access THAT resource.

   -----------------------------------------------------------------------------
   EXPLOIT 1 — IDOR (Insecure Direct Object Reference) — horizontal escalation
   -----------------------------------------------------------------------------
   You are "logged in" as user id = 2 (see the cookie). Try:

       broken-authorization.php?id=1
       broken-authorization.php?id=2
       broken-authorization.php?id=3
       broken-authorization.php?id=4
       ...

   You can read every other user's profile — including their password — just by
   incrementing a number in the URL. The server never asks
   "does user 2 own profile 4?".

   Real-world equivalents:
       /orders/1043          -> view someone else's order
       /api/users/9/messages -> read another account's DMs
       /download?file=42     -> grab a private file by guessing the id

   -----------------------------------------------------------------------------
   EXPLOIT 2 — Vertical privilege escalation via cookie tampering
   -----------------------------------------------------------------------------
   1. Open broken-authorization.php?admin=1   -> "You are not an admin."
   2. Open DevTools -> Application -> Cookies -> change `role` from `user` to `admin`.
   3. Reload the page. The full user table (with passwords) is now exposed.

   The bug: the server trusts a value the client fully controls. Anything sent
   by the browser (cookies, hidden form fields, localStorage, JWT payloads
   without signature verification) can be edited by the user.

   -----------------------------------------------------------------------------
   EXPLOIT 3 — Forced browsing (related sibling bug)
   -----------------------------------------------------------------------------
   Even without a link, an attacker can guess URLs:

       /admin.php
       /backup.zip
       /api/v1/users
       /.git/config

   If the server returns the resource without checking the caller's role,
   that's broken authorization too.

   -----------------------------------------------------------------------------
   WHY THIS CODE IS BROKEN
   -----------------------------------------------------------------------------
   1. $id comes straight from $_GET with no ownership check.
      Required check (pseudo):
          if ($id != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
              http_response_code(403); exit('Forbidden');
          }

   2. The role is read from a COOKIE. Cookies are client-side data — never
      trust them for authorization. The role must live in the server-side
      session and be set only by trusted code (e.g. after login from the DB).

   3. There is no central access-control layer. Every page re-implements
      (and re-forgets) its own checks. Real apps should use a middleware /
      policy layer that runs BEFORE the handler.

   -----------------------------------------------------------------------------
   SECURE VERSION (sketch)
   -----------------------------------------------------------------------------
       session_start();
       if (!isset($_SESSION['user_id'])) {
           header('Location: login.php'); exit;
       }

       $id = (int)($_GET['id'] ?? $_SESSION['user_id']);

       // Authorization: you can only see yourself, unless you're an admin.
       if ($id !== (int)$_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
           http_response_code(403);
           exit('Forbidden');
       }

       // And use a prepared statement so we don't reintroduce SQL injection.
       $stmt = mysqli_prepare($database,
           "SELECT id, fullname, email, phone FROM users WHERE id = ?");
       mysqli_stmt_bind_param($stmt, 'i', $id);
       mysqli_stmt_execute($stmt);
       $result = mysqli_stmt_get_result($stmt);

   Note we also stopped selecting the password column — least privilege:
   never return data the page doesn't actually need.
============================================================================= */
?>
