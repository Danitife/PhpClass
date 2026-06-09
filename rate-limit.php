<?php
// Rate-Limiting Bypass Demo
//
// WHAT IS RATE LIMITING?
// Rate limiting is a defensive control that restricts how often someone can
// attempt an action. It's essential for protecting against:
//   - Brute force (trying many passwords)
//   - Credential stuffing (testing stolen password lists)
//   - Scraping (downloading the entire database)
//   - DoS amplification (exhausting server resources)
//   - Abuse (spam signups, rapid-fire requests)
//
// The premise: pick a KEY that identifies the requester (IP, username, API key),
// count their requests per time window, and block when the count exceeds a limit.
//
// WHAT MAKES A GOOD KEY?
// The key must identify the REAL ACTOR and be non-spoofable:
//   Good: $_SERVER['REMOTE_ADDR']           (the TCP connection source)
//   Bad:  $_SERVER['HTTP_X_FORWARDED_FOR']  (any HTTP client can spoof it)
//   Bad:  user cookie                       (attacker can delete/clear it)
//   Bad:  useragent or referer header       (trivial to change)
//
// THE BUG ON THIS PAGE:
// This demo implements rate limiting correctly in concept — "3 strikes per 60s"
// — but gets the KEY selection wrong. It reads X-Forwarded-For, which the
// attacker fully controls. So an attacker just rotates the value and gets a
// fresh 3-attempt bucket on every request. The rate limit "works" but it
// limits nothing.
//
// We don't need MySQL — we'll store attempt counters in a flat JSON file.

$store_path = sys_get_temp_dir() . '/vuln_rate_limit.json';

function load_state($path) {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}
function save_state($path, $state) {
    file_put_contents($path, json_encode($state));
}
if (isset($_GET['reset'])) {
    @unlink($store_path);
    header('Location: rate-limit.php'); exit;
}

$state = load_state($store_path);

// -----------------------------------------------------------------------------
// VULNERABLE LOGIC — trusting X-Forwarded-For for the rate-limit key
//
// X-Forwarded-For (XFF) is a HEADER. Apache forwards it to PHP as
// $_SERVER['HTTP_X_FORWARDED_FOR']. The HTTP spec allows ANY client to set
// it. Only trust it if you KNOW the request came through a proxy you
// control (and even then, only trust the last hop).
//
// Here the dev wrote "use the real IP if behind a proxy", but forgot to
// check whether there even IS a proxy. So the attacker sets the header
// themselves and gets a brand new rate-limit bucket on every request.
// -----------------------------------------------------------------------------
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR']
          ?? $_SERVER['REMOTE_ADDR']
          ?? 'unknown';

$WINDOW = 60;   // seconds
$LIMIT  = 3;    // max failed attempts per window

$now = time();
$record = $state[$client_ip] ?? ['count' => 0, 'first' => $now];
if ($now - $record['first'] > $WINDOW) {
    $record = ['count' => 0, 'first' => $now];   // window expired, reset
}

$message = null;
$blocked = $record['count'] >= $LIMIT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($blocked) {
        $message = "BLOCKED: too many failed attempts from {$client_ip}. "
                 . "Try again in " . ($WINDOW - ($now - $record['first'])) . "s.";
    } else {
        // Hard-coded "real" credentials for the demo (do not do this either).
        $valid = ($_POST['user'] ?? '') === 'admin'
              && ($_POST['pass'] ?? '') === 'hunter2';

        if ($valid) {
            $message = "LOGIN OK — welcome, admin.";
            $record  = ['count' => 0, 'first' => $now];  // reset on success
        } else {
            $record['count']++;
            $remaining = max(0, $LIMIT - $record['count']);
            $message = "Wrong credentials. Attempts left for "
                     . "{$client_ip}: {$remaining}.";
            if ($record['count'] >= $LIMIT) {
                $message .= " You are now blocked for {$WINDOW}s.";
            }
        }
    }
    $state[$client_ip] = $record;
    save_state($store_path, $state);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login (rate-limited)</title>
    <style>
        body  { font-family: sans-serif; max-width: 720px; margin: 2em auto; }
        .debug { background:#f5f5dc; border:1px solid #999; padding:10px; margin-top:20px; font-size:0.9em; }
        .msg   { padding:10px; margin:12px 0; border-radius:4px; }
        .ok    { background:#dfd; border:1px solid #393; }
        .bad   { background:#fdd; border:1px solid #c33; }
        input  { padding:4px 6px; margin:4px 0; }
        pre    { background:#111; color:#0f0; padding:10px; overflow:auto; }
    </style>
</head>
<body>

<h2>Login (rate-limited to <?= $LIMIT ?> failed attempts per <?= $WINDOW ?>s)</h2>

<?php if ($message): ?>
    <div class="msg <?= strpos($message,'OK')!==false ? 'ok' : 'bad' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<form method="POST">
    <label>Username: <input name="user" value="admin"></label><br>
    <label>Password: <input name="pass" type="password" value=""></label><br>
    <button type="submit">Login</button>
</form>

<p><a href="?reset=1">[clear all rate-limit state]</a></p>

<div class="debug">
    <strong>DEBUG</strong><br>
    REMOTE_ADDR          : <code><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '-') ?></code><br>
    X-Forwarded-For (raw): <code><?= htmlspecialchars($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '(not sent)') ?></code><br>
    Rate-limit KEY used  : <code><?= htmlspecialchars($client_ip) ?></code><br>
    Attempts this window : <code><?= (int)$record['count'] ?> / <?= $LIMIT ?></code><br>
    Window resets in     : <code><?= max(0, $WINDOW - ($now - $record['first'])) ?>s</code><br>
    Store file           : <code><?= htmlspecialchars($store_path) ?></code>
</div>

</body>
</html>

<?php
/* =============================================================================
   RATE LIMIT BYPASS  —  the limiter that limits nothing
   =============================================================================

   Rate limiting is meant to stop brute force, credential stuffing, scraping,
   and resource exhaustion. The premise: pick a KEY that identifies the
   actor (IP, user id, API key), count requests per key per time window,
   block when the count exceeds the limit.

   The bypass: choose the wrong KEY — one the attacker controls. Then they
   just rotate the key and get unlimited fresh buckets. The limit is still
   "enforced", but trivially defeated.

   -----------------------------------------------------------------------------
   EXPLOIT 1 — Rotate X-Forwarded-For for unlimited brute force
   -----------------------------------------------------------------------------
   First, get yourself blocked the honest way (3 wrong passwords in a row):

       curl -X POST -d "user=admin&pass=wrong1" http://localhost:8080/vunerability/rate-limit.php
       curl -X POST -d "user=admin&pass=wrong2" http://localhost/vunerability/rate-limit.php
       curl -X POST -d "user=admin&pass=wrong3" http://localhost/vunerability/rate-limit.php
       curl -X POST -d "user=admin&pass=wrong4" http://localhost/vunerability/rate-limit.php
       # -> "BLOCKED: too many failed attempts from 127.0.0.1"

   Now bypass it by faking your IP via the header:

       curl -H "X-Forwarded-For: 1.2.3.4" -X POST \
            -d "user=admin&pass=wrong" http://localhost/vunerability/rate-limit.php
       curl -H "X-Forwarded-For: 1.2.3.5" -X POST \
            -d "user=admin&pass=wrong" http://localhost/vunerability/rate-limit.php
       curl -H "X-Forwarded-For: 1.2.3.6" ...

   Each new value gets a fresh 3-attempt bucket. With a loop:

       for i in $(seq 1 1000); do
         curl -s -H "X-Forwarded-For: 10.0.0.$i" -X POST \
              -d "user=admin&pass=guess$i" http://localhost/vunerability/rate-limit.php \
           | grep -o 'LOGIN OK' && break
       done

   That's 1000 password guesses with the rate limit "on".

   -----------------------------------------------------------------------------
   EXPLOIT 2 — Other key rotations attackers try
   -----------------------------------------------------------------------------
   Same idea, different header. Whichever header the server trusts becomes
   the bypass vector:

       X-Real-IP: 9.9.9.9
       X-Client-IP: 9.9.9.9
       True-Client-IP: 9.9.9.9            (Cloudflare / Akamai)
       CF-Connecting-IP: 9.9.9.9          (Cloudflare)
       Forwarded: for=9.9.9.9             (RFC 7239)
       Via: 1.1 fakeproxy

   Servers that limit by USERNAME instead of IP have a different bypass:

       admin / Admin / ADMIN / admin@ / admin­     (different keys!)

   Servers that limit per session id: just drop the cookie and you get a new
   one.

   -----------------------------------------------------------------------------
   EXPLOIT 3 — Limit on /login but not /api/login
   -----------------------------------------------------------------------------
   Web team rate-limits the HTML form. Mobile team built a JSON endpoint
   that calls the same backend, without the limiter middleware. Attackers
   brute-force the API endpoint instead. Always inventory every entry
   point to a sensitive function.

   -----------------------------------------------------------------------------
   EXPLOIT 4 — Race against the counter
   -----------------------------------------------------------------------------
   A limiter implemented as "read counter, if < N then increment" is racy.
   Send 50 requests in parallel; many of them read counter=0 before any
   has incremented, and all 50 succeed. Use atomic increments (Redis
   INCR, DB UPDATE ... SET n = n + 1) and check the RESULT, not a prior read.

   -----------------------------------------------------------------------------
   WHY THIS CODE IS BROKEN
   -----------------------------------------------------------------------------
   1. The key is read from a client-controlled header without checking
      that the request actually came through a trusted proxy.
   2. There is no account-level lock — only an IP-level lock. Even a
      "correct" IP limiter can be bypassed by an attacker with a botnet.
   3. The limiter is per-window-reset rather than progressive — once the
      60s window expires there is no penalty for repeated abuse.

   -----------------------------------------------------------------------------
   SECURE VERSION (sketch)
   -----------------------------------------------------------------------------

       // 1. Derive the client IP from REMOTE_ADDR ONLY, unless you KNOW you
       //    are behind a trusted proxy. If you are, parse XFF carefully —
       //    take the LEFTMOST entry that isn't one of your own proxies'
       //    IPs, and trust nothing past that.
       $client_ip = $_SERVER['REMOTE_ADDR'];

       // 2. Limit on MULTIPLE keys at once: per-IP, per-username, and
       //    globally. An attacker has to defeat all of them.
       //       failed_attempts[ip]       += 1
       //       failed_attempts[username] += 1
       //       failed_attempts[global]   += 1
       //    Block if ANY exceeds its limit.

       // 3. Use atomic counters (Redis INCR + EXPIRE, or a DB UPDATE that
       //    returns the new value). Never read-then-increment.

       // 4. Lock the ACCOUNT after N failures (with an unlock flow), not
       //    just the IP. Even a perfect IP limiter loses to a botnet.

       // 5. Apply the limiter to EVERY entry point: /login, /api/login,
       //    /reset-password, /2fa-verify, /signup. List them.

       // 6. Add exponential backoff and CAPTCHA. After 5 failures, force a
       //    CAPTCHA; after 20, require email confirmation to re-enable
       //    the account.
============================================================================= */
?>
