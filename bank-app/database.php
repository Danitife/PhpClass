<?php
/**
 * database.php — Bank of VULN connection
 * ============================================================================
 * INTENTIONALLY INSECURE — training lab only. Bugs in THIS file:
 *
 *   1. DB credentials are hard-coded in source (and mirrored in `.env`,
 *      `config.php.bak`, and `backup.sql`). Any source-disclosure or
 *      file-read bug in the app leaks them.
 *   2. mysqli_report is OFF so SQL errors are RETURNED (not exceptions) and
 *      every page happily echoes `mysqli_error()` to the browser — that is
 *      the error-based SQLi oracle.
 *   3. The schema is self-bootstrapping: if the DB/tables don't exist they
 *      are created and seeded on first connect, so the lab "just works".
 */
mysqli_report(MYSQLI_REPORT_OFF);

// ---- tiny .env loader (kept intentionally simple) ----
function bank_load_env($path) {
    $env = [];
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $k = trim(substr($line, 0, $eq));
            $v = trim(substr($line, $eq + 1));
            if ($k !== '') $env[$k] = $v;
        }
    }
    return $env;
}

$env = bank_load_env(__DIR__ . '/.env');

$DB_HOST = $env['DB_HOST'] ?? '127.0.0.1';
$DB_PORT = (int)($env['DB_PORT'] ?? 3306);
$DB_USER = $env['DB_USER'] ?? 'bank_app';
$DB_PASS = $env['DB_PASS'] ?? 'BankVuln@2026!';
$DB_NAME = $env['DB_NAME'] ?? 'vulnerable_bank';

$database = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
if (!$database) {
    die('DB connection failed: ' . mysqli_connect_error());
}

// select the database, creating it first if it doesn't exist yet
if (!mysqli_select_db($database, $DB_NAME)) {
    if (!mysqli_query($database, "CREATE DATABASE IF NOT EXISTS `$DB_NAME`")) {
        die('Cannot create database `' . $DB_NAME . '`. Provision it first with: '
            . 'mysql -u root -p < setup-mysql.sql   (' . mysqli_error($database) . ')');
    }
    mysqli_select_db($database, $DB_NAME);
}

mysqli_query($database, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,          -- stored PLAINTEXT (bug)
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// keep a pre-existing (old-schema) lab DB usable
mysqli_query($database, "ALTER TABLE users MODIFY username VARCHAR(255) NOT NULL, MODIFY full_name VARCHAR(255) NOT NULL");

mysqli_query($database, "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_account VARCHAR(20) NOT NULL,
    to_account VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    note VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// seed a handful of accounts only if the table is empty
$res = mysqli_query($database, "SELECT COUNT(*) AS c FROM users");
$row = mysqli_fetch_assoc($res);
if ((int)$row['c'] === 0) {
    $seed = [
        ['admin',   'System Administrator', 'admin@bank.local',   'Admin@123',  'admin', 999999.99, '1000000001'],
        ['alice',   'Alice Wonder',         'alice@bank.local',   'alice123',   'user',  1000.00,   '1000000002'],
        ['bob',     'Bob Builder',          'bob@bank.local',     'bob123',     'user',  500.00,    '1000000003'],
        ['mallory', 'Mallory Malice',       'mallory@bank.local', 'mallory123', 'user',  25.00,     '1000000004'],
    ];
    foreach ($seed as $u) {
        mysqli_query($database,
            "INSERT INTO users (username, full_name, email, password, role, balance, account_number)
             VALUES ('{$u[0]}', '{$u[1]}', '{$u[2]}', '{$u[3]}', '{$u[4]}', {$u[5]}, '{$u[6]}')");
    }
}
