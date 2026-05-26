<?php

/**
 * ============================================================
 *  INSTRUCTOR NOTES — SQL INJECTION
 * ============================================================
 * WHAT IS IT?
 *   SQL Injection occurs when user-supplied input is directly
 *   concatenated into a SQL query without sanitisation.
 *   An attacker can manipulate the query logic to:
 *     - Bypass authentication
 *     - Dump the entire database
 *     - Delete or modify data
 *
 * DEMO PAYLOAD (Login bypass):
 *   Username: admin' -- 
 *   Password: anything
 *
 *   The resulting query becomes:
 *   SELECT * FROM users WHERE username='admin' -- ' AND password='anything'
 *   Everything after -- is commented out, so password is ignored.
 *
 * DEMO PAYLOAD (Data dump):
 *   Username: ' OR '1'='1
 *   Password: ' OR '1'='1
 *
 * WHY IS IT DANGEROUS?
 *   - Full database compromise
 *   - Authentication bypass
 *   - Data exfiltration
 *   - Data destruction (DROP TABLE)
 * ============================================================
 */

$host = 'localhost';
$db   = 'vulnerable_app';
$user = 'root';
$pass = '';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error   = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username']; // ❌ No sanitisation
    $password = $_POST['password']; // ❌ No sanitisation

    // ❌ VULNERABLE: User input directly concatenated into SQL
    $sql    = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = mysqli_query($conn, $sql);

    // ❌ Also exposes the raw query — never do this in production
    echo "<div class='debug'>DEBUG — Query executed: <code>" . htmlspecialchars($sql) . "</code></div>";

    if ($result && mysqli_num_rows($result) > 0) {
        $user_row = mysqli_fetch_assoc($result);
        $message  = "✅ Welcome, " . $user_row['username'] . "! You are logged in.";
    } else {
        $error = "❌ Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>SQL Injection — VULNERABLE</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a2e;
            color: #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: #16213e;
            padding: 2rem;
            border-radius: 12px;
            width: 380px;
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.3);
        }

        h2 {
            text-align: center;
            color: #e94560;
        }

        .badge {
            background: #e94560;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        label {
            display: block;
            margin-top: 1rem;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        input[type=text],
        input[type=password] {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: none;
            background: #0f3460;
            color: white;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            margin-top: 1.2rem;
            background: #e94560;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
        }

        .error {
            color: #ff6b6b;
            margin-top: 1rem;
        }

        .success {
            color: #6bffb8;
            margin-top: 1rem;
        }

        .debug {
            background: #2d1b00;
            border: 1px solid #ff9800;
            color: #ff9800;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            margin-top: 1rem;
            word-break: break-all;
        }

        .hint {
            background: #1e1e1e;
            border-left: 3px solid #e94560;
            padding: 8px 12px;
            font-size: 0.8rem;
            margin-top: 1rem;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>🔓 Login <span class="badge">VULNERABLE</span></h2>

        <div class="hint">
            💡 <strong>Try this payload:</strong><br>
            Username: <code>admin' -- </code><br>
            Password: <code>anything</code>
        </div>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" placeholder="e.g. admin' -- ">
            <label>Password</label>
            <input type="password" name="password" placeholder="Any password">
            <button type="submit">Login</button>
        </form>

        <?php if ($message): ?>
            <p class="success"><?= $message ?></p>
        <?php elseif ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
    </div>
</body>

</html>