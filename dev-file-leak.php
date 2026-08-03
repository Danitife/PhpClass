<?php
// Developer File Leak / Exposed Configuration Demo
//
// WHAT IS A DEV FILE LEAK?
// A dev file leak is when sensitive files left in the web root by developers
// are accessible to anyone via HTTP requests. These are NOT exploited via
// vulnerabilities (no path traversal needed) — they're accessible by default
// because they're sitting in the public directory. An attacker simply guesses
// the filename and downloads it.
//
// Common leaked files:
//   .env                    → environment variables with DB credentials, API keys
//   .git/                   → full source code history (git repository)
//   .git/config             → git configuration with credentials
//   package.json            → Node.js dependencies, versions, vulnerabilities
//   package-lock.json       → locked dependency versions
//   composer.json / .lock   → PHP dependencies
//   docker-compose.yml      → Docker setup with passwords, DB ports
//   .htaccess               → Apache configuration, rewrites, auth
//   config.php.bak          → backup files from editors
//   .DS_Store               → macOS metadata revealing file structure
//   *.map                   → source maps revealing original source code
//   README, INSTALL         → deployment instructions, hints
//   .env.local, .env.*.php  → local environment overrides
//   wp-config.php~          → WordPress config backups
//   debug.log               → error logs with stack traces, paths, credentials
//
// WHY IS THIS SO COMMON?
// Developers use these files locally, commit them by accident (after removing
// them from .gitignore), forget to .gitignore them, or use a deployment tool
// that copies the entire project directory to the web root without filtering.
// A single misconfigured rsync or git pull into /var/www/ and your secrets
// are public.
//
// WHY IS THIS CRITICAL?
// A .env file alone can expose:
//   - Database hostname, username, password, name
//   - Third-party API keys (Stripe, AWS, SendGrid)
//   - Session encryption keys
//   - JWT secrets
//   - OAuth credentials
// A .git directory exposes:
//   - Every line of code ever written (git log)
//   - Every secret ever checked in (including ones later removed!)
//   - Full commit history with author names and timestamps
//   - Deleted branches and dead code paths

// Demo: List what files would be at risk if exposed
$leaked_files = [
    '.env' => [
        'exposed' => true,
        'content_snippet' => 'DB_HOST=db.internal.corp
DB_USER=app_user
DB_PASSWORD=MySecretPassword123!
DB_NAME=production_db
API_KEY_STRIPE=replace_with_stripe_key
API_KEY_SENDGRID=SG.ABC123XYZ...',
        'impact' => 'Full database access, third-party API abuse'
    ],
    '.git/config' => [
        'exposed' => true,
        'content_snippet' => '[core]
    repositoryformatversion = 0
    filemode = true
[remote "origin"]
    url = https://user:password@github.com/company/private-repo.git
[credential]
    helper = osxkeychain',
        'impact' => 'GitHub credentials, full repo access'
    ],
    'package.json' => [
        'exposed' => true,
        'content_snippet' => '{
  "name": "internal-api",
  "version": "1.0.0",
  "dependencies": {
    "express": "4.17.1",
    "mysql": "2.18.1"  ← known vulnerability CVE-2021-12345
  },
  "scripts": {
    "dev": "node server.js --debug"
  }
}',
        'impact' => 'Known vulnerable dependencies, infrastructure hints'
    ],
    'docker-compose.yml' => [
        'exposed' => true,
        'content_snippet' => 'version: "3.8"
services:
  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: root123!
      MYSQL_DATABASE: production
    ports:
      - "3306:3306"
  redis:
    image: redis:6
    ports:
      - "6379:6379"',
        'impact' => 'Database port exposure, password leak, infrastructure layout'
    ],
    'wp-config.php.bak or wp-config.php~' => [
        'exposed' => true,
        'content_snippet' => "define('DB_NAME', 'wordpress_prod');
define('DB_USER', 'wp_admin');
define('DB_PASSWORD', 'P@ssw0rd!');
define('DB_HOST', 'db.internal.example.com');
define('AUTH_KEY',  'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');",
        'impact' => 'WordPress database credentials, auth keys'
    ],
    'debug.log or error.log' => [
        'exposed' => true,
        'content_snippet' => '[2026-06-09 12:34:56] ERROR: SQL error in /var/www/html/api/users.php:42
  Query: SELECT * FROM users WHERE id=1
  Error: Access denied for user \'app\'@\'localhost\' using password (YES)
[2026-06-09 12:35:01] FATAL: PDOException: SQLSTATE[HY000]: General error: 1030 Got error 28 from storage engine
  File: /app/src/Database.php:156
  Stack: ...',
        'impact' => 'Path disclosure, database credentials, stack traces, error details'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dev File Leak Demo</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 900px;
            margin: 2em auto;
        }

        .file-card {
            border: 1px solid #ccc;
            margin: 16px 0;
            padding: 12px;
            border-radius: 4px;
        }

        .status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 3px;
        }

        .exposed {
            background: #fdd;
            color: #c33;
        }

        .safe {
            background: #dfd;
            color: #393;
        }

        .snippet {
            background: #111;
            color: #0f0;
            padding: 8px;
            font-family: monospace;
            font-size: 0.85em;
            max-height: 150px;
            overflow: auto;
            margin: 8px 0;
        }

        .impact {
            background: #fff9e6;
            border-left: 4px solid #ff9800;
            padding: 8px;
            margin: 8px 0;
        }

        .debug {
            background: #f5f5dc;
            border: 1px solid #999;
            padding: 10px;
            margin-top: 20px;
            font-size: 0.9em;
        }

        h3 {
            margin-top: 0;
        }
    </style>
</head>

<body>

    <h2>Dev File Leak Vulnerability Demo</h2>
    <p>These files are commonly left in production web roots, exposing sensitive data to anyone who guesses the filename.</p>

    <?php foreach ($leaked_files as $filename => $details): ?>
        <div class="file-card">
            <h3>
                <code><?= htmlspecialchars($filename) ?></code>
                <span class="status <?= $details['exposed'] ? 'exposed' : 'safe' ?>">
                    <?= $details['exposed'] ? '⚠️ EXPOSED' : '✓ SAFE' ?>
                </span>
            </h3>

            <p><strong>What it contains:</strong></p>
            <div class="snippet"><?= htmlspecialchars($details['content_snippet']) ?></div>

            <div class="impact">
                <strong>Impact if exposed:</strong> <?= htmlspecialchars($details['impact']) ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="debug">
        <strong>DEBUG / EXPLORATION</strong><br><br>
        <strong>Try accessing these files in your browser:</strong><br>
        (Most will 404, but attackers try them anyway)<br><br>
        <code style="display:block; padding:8px; background:#eee; margin:8px 0;">
            http://localhost/vunerability/.env<br>
            http://localhost/vunerability/.env.local<br>
            http://localhost/vunerability/.env.production<br>
            http://localhost/vunerability/.git/config<br>
            http://localhost/vunerability/.git/HEAD<br>
            http://localhost/vunerability/config.php.bak<br>
            http://localhost/vunerability/config.php~<br>
            http://localhost/vunerability/package.json<br>
            http://localhost/vunerability/composer.json<br>
            http://localhost/vunerability/docker-compose.yml<br>
            http://localhost/vunerability/debug.log<br>
            http://localhost/vunerability/.DS_Store<br>
            http://localhost/vunerability/wp-config.php.bak<br>
        </code>
        <br>
        <strong>Real attackers use automated scanners (like nikto, dirsearch, Burp)
            that try thousands of these filenames in parallel.</strong>
    </div>

</body>

</html>

<?php
/* =============================================================================
   DEVELOPER FILE LEAK  —  secrets left in the public directory
   =============================================================================

   A dev file leak is a class of misconfigurations where developers
   accidentally expose sensitive files in the web root. Unlike file exposure
   (which requires a vulnerability like path traversal), dev file leaks are
   accessible BY DEFAULT — just by requesting a common filename like /.env.

   This is one of the most common real-world security incidents because:
       1. Easy to do by accident (commit .env, forget .gitignore, copy project
          to prod with rsync -r)
       2. Trivial to exploit (guess filename, HTTP GET)
       3. Catastrophic impact (full DB access, API keys, secrets)
       4. Automated scanners find them instantly

   =============================================================================
   EXPLOIT 1 — .env file exposure
   =============================================================================
   The .env file stores environment variables: DB credentials, API keys, session
   secrets. It's meant ONLY for development/deployment scripts, never the web.

   Try in your browser:
       http://localhost/app/.env

   Or with curl:
       curl http://localhost/app/.env

   If the server returns 200 (not 404), you've got:
       - Database username, password, hostname, port, database name
       - Stripe, SendGrid, Twilio, AWS API keys
       - JWT secrets, session encryption keys
       - Slack webhooks, GitHub tokens
       - Any other credentials the app uses

   The attacker now has full access to your infrastructure and third-party
   services. Compromise is complete.

   Real examples:
       - GitHub user exposed a .env with AWS credentials. Attacker spawned
         EC2 instances for crypto mining. $1000+ bill in hours.
       - Heroku app had .env checked in. Git history exposed credentials
         that were used elsewhere. Full account takeover.

   =============================================================================
   EXPLOIT 2 — .git directory exposure (GIT FOLDER)
   =============================================================================
   A .git/ directory is the Git repository itself. It contains:
       - Every commit ever made (full source code history)
       - Every file ever committed (including files later deleted!)
       - Every secret ever committed (even after .gitignore)
       - Author names, timestamps, email addresses
       - Branch history and merge commits

   If .git/ is in the web root, attackers can:

   Option A — Download the entire repo:
       git clone http://target.com/.git

   Option B — Download just specific files:
       curl http://target.com/.git/config
       curl http://target.com/.git/HEAD
       curl http://target.com/.git/logs/HEAD

   Then reconstruct the entire source code offline using:
       git fsck --lost-found
       git log --all --oneline

   Option C — Use a tool like GitTools or git-dumper to auto-download:
       python3 git_dumper.py http://target.com/.git dump/

   Real-world impact:
       - Source code theft (competitors, black market resale)
       - Hardcoded credentials in git history (aws keys, db passwords)
       - Private endpoints revealed (admin panels, API endpoints)
       - Vulnerability disclosure (reviewable without source access)
       - Configuration details (internal IPs, architecture)

   Real examples:
       - Company left .git in Kubernetes config. Full source + K8s secrets
         exposed. 50+ employees' AWS access compromised.
       - Startup's .git contained deleted commit with hardcoded API keys.
         Keys never rotated. Attacker accessed production DB for 6 months
         before detection.

   =============================================================================
   EXPLOIT 3 — Dependency files (package.json, composer.json, requirements.txt)
   =============================================================================
   These list dependencies and their exact versions:

       package.json (Node.js)
           "mongoose": "5.11.0"  ← known RCE vulnerability
       composer.json (PHP)
           "symfony/http-kernel": "5.0.0"  ← known auth bypass
       requirements.txt (Python)
           Django==2.2.3  ← known vulnerability

   Attackers see:
       - Exact library versions (searchable against CVE databases)
       - Architecture hints (what frameworks you use)
       - Potential attack surface (which libraries to focus on)

   Then they:
       1. Check if any dependency version has a known CVE
       2. Exploit that CVE if your app is exposed to the internet
       3. Gain RCE, data access, or auth bypass

   Real examples:
       - package.json revealed Lodash 4.17.13 (DoS vulnerability CVE-2019-10744)
         Attacker could crash the entire service
       - composer.json revealed Symfony 5.0.0 (auth bypass). Attacker bypassed
         login and accessed every user's profile

   =============================================================================
   EXPLOIT 4 — docker-compose.yml / Dockerfile
   =============================================================================
   These contain:
       - Database root password (plaintext)
       - Redis passwords
       - Internal service ports (usually hidden from internet)
       - Environment setup hints
       - Disk mount paths
       - Network configuration

   Example:
       MYSQL_ROOT_PASSWORD=SuperSecret123
       ports: 3306:3306  (MySQL accessible from outside)

   Attacker now knows:
       - Exact DB location and credentials
       - Infrastructure layout
       - Whether services are exposed to the internet

   Then:
       mysql -h target.com -u root -p SuperSecret123

   Full database access, data theft, ransomware.

   =============================================================================
   EXPLOIT 5 — Source maps (.js.map, .css.map)
   =============================================================================
   In production, JavaScript is usually minified (obfuscated). Source maps
   (.map files) are meant ONLY for development — they unminify the code so
   browsers can show you readable stack traces.

   If served in production:
       http://target.com/app.min.js.map

   Attacker downloads it and sees:
       - Original, readable source code
       - Variable names, function names, logic flow
       - Hidden API endpoints and parameters
       - Client-side validation logic (can be bypassed)
       - Comments revealing bugs, secrets, architecture

   This defeats the whole point of minification.

   =============================================================================
   EXPLOIT 6 — Backup and editor files
   =============================================================================
   Editors create temporary files:
       config.php.bak        (backup from text editor)
       config.php~           (Emacs backup)
       .config.php.swp       (Vim swap file)
       config.php.orig       (patch conflict)
       .#config.php          (Emacs lock file)

   Attackers try all of these. A single misconfigured deployment script
   that copies *.* to production will include them.

   Also common:
       README.md (setup instructions, hints)
       INSTALL.txt (deployment steps)
       config.example.php (template revealing structure)
       .env.example (template showing required variables)

   Real example:
       WP-config.php.bak left in /var/www/html/
       Contained hardcoded admin username "admin"
       Combined with known plugin RCE → full site takeover

   =============================================================================
   EXPLOIT 7 — Debug logs and error logs
   =============================================================================
   debug.log, error.log, or stdout written to disk:

       [ERROR] PDOException: SQLSTATE[HY000]: Access denied for user
       'app'@'10.0.2.15' identified by password (YES)
       File: /var/www/html/src/Database.php:156

   Attacker extracts:
       - Database username: 'app'
       - Database password: we know it's set (YES), can brute-force or
         check if it's a common default
       - Internal IP: 10.0.2.15 (infrastructure mapping)
       - Source code paths: /var/www/html/src/ (attack surface mapping)

   Stack traces are goldmines for reconnaissance.

   =============================================================================
   REAL-WORLD INCIDENT TIMELINE
   =============================================================================
   1. Developer commits .env to private GitHub repo (accident, forgot .gitignore)
   2. 3 weeks later, repo becomes public (transferred to new org, permission
      misconfiguration, or accidentally made public during demo)
   3. Attacker runs:
          curl https://github.com/company/project/raw/main/.env
          OR git clone https://github.com/company/project.git
   4. .env contains AWS_SECRET_ACCESS_KEY=AKIAIO5FODNN7EXAMPLE
   5. Attacker spawns 100 EC2 instances for crypto mining
   6. $50,000 bill in 48 hours, discovered by AWS alerts
   7. Full post-mortem: all committers' AWS access revoked, keys rotated,
      password resets sent to every AWS user
   8. Headlines: "Company exposed AWS credentials on GitHub, faces $50k bill"

   This scenario has happened to companies like Uber, Travis CI, Tesla, and
   hundreds of smaller startups.

   =============================================================================
   WHY THIS CODE IS BROKEN
   =============================================================================
   1. Sensitive files are in the web root. They should NEVER be there.
      .env, .git/, docker-compose.yml, etc. belong in /srv or parent
      directories, OUTSIDE /var/www/.

   2. No server-side filtering. Apache/Nginx should be configured to
      block these files at the server level, before PHP even sees them.

   3. .gitignore entries are DEVELOPMENT hints, not security. If you forgot
      to add /.env to .gitignore, and you committed it, it's in the repo
      FOREVER (even after deleting and recommitting).

   4. Deployment scripts often copy entire directories with rsync -r or
      git clone without excluding sensitive files. A single line of
      automation can expose everything.

   =============================================================================
   SECURE VERSION (sketch)
   =============================================================================

       // 1. NEVER put sensitive files in the web root.
       //    /var/www/html/          (web root, public)
       //    /var/www/shared/.env     (parent, NOT accessible via HTTP)

       // 2. Load .env from OUTSIDE the web root in your app:
       $dotenv = Dotenv\Dotenv::createImmutable('/var/www/shared');
       $dotenv->load();

       // 3. Server-side protection (Apache):
       <Files ~ "\.(env|git|bak|swp|log)">
           Deny from all
       </Files>
       <Directory ~ "^.*(\.git|node_modules|vendor)">
           Deny from all
       </Directory>

       // 4. Nginx:
       location ~ /\. {
           deny all;
           access_log off;
           log_not_found off;
       }
       location ~ ~$ {
           deny all;
       }
       location ~ /(vendor|node_modules) {
           deny all;
       }

       // 5. In .gitignore (and CHECK what you've already committed):
       .env
       .env.local
       .env.*.php
       .git
       node_modules/
       vendor/
       *.log
       *.bak
       *.swp
       .DS_Store
       docker-compose.yml  (or at least sanitize it before commit)

       // 6. Deployment checklist:
       //    - Do NOT copy entire project directory to web root
       //    - Use selective sync: rsync --exclude=.env --exclude=.git
       //    - After deployment, verify sensitive files are NOT in /var/www/
       //    - Run: find /var/www -name ".env" -o -name ".git" -o -name "*.bak"

       // 7. Git hygiene:
       //    - Run: git log -p -S "password" to find secrets in history
       //    - Use git-secrets tool to prevent secrets from being committed
       //    - If you already committed credentials:
       //      a. Rotate all keys immediately
       //      b. Use BFG Repo-Cleaner to remove from history
       //      c. Educate team on .gitignore and secrets management

       // 8. Automated scanning:
       //    - Use SAST tools (Snyk, Semgrep) to catch hardcoded secrets
       //    - Use pre-commit hooks (detect-secrets, gitleaks)
       //    - Scan Git history: gitleaks detect -v -r .

============================================================================= */
?>
