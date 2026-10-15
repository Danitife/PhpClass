<?php
/**
 * api.php — "authenticated" JSON API (JWT) — VULNERABLE
 * ============================================================================
 * Bugs (maps to check.php JWT decode + the leaked .env secret):
 *   1. Algorithm confusion: tokens with `alg: none` are accepted WITHOUT any
 *      signature verification -> forge any identity.
 *   2. The HMAC secret is the SAME value that sits in the publicly
 *      downloadable `.env` -> read the file, forge a signed admin token.
 *   3. No `exp`/`nbf` validation -> tokens never expire.
 *   4. It even echoes the secret back to you. 😬
 *
 *   Use:  curl -H "Authorization: Bearer <token>" http://.../api.php
 *         or  api.php?token=<token>
 */
header('Content-Type: application/json');

$secret = 'change_me_bank_super_secret_123';   // ALSO in .env — go read it

function b64u($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }

function jwt_decode_unsafe($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $header  = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    $alg = strtoupper($header['alg'] ?? '');
    if ($alg === 'NONE') return $payload;                       // VULN #1
    $sig = hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true);
    if (hash_equals(b64u($sig), $parts[2])) return $payload;    // VULN #3: no exp
    return null;
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// Apache/mod_php sometimes hides the header; check the fallbacks too.
if ($auth === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}
if ($auth === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strcasecmp($k, 'Authorization') === 0) { $auth = $v; break; }
    }
}
$token = '';
if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $token = $m[1];
} else {
    $token = $_GET['token'] ?? '';
}

$payload = jwt_decode_unsafe($token, $secret);
if (!$payload) {
    echo json_encode(['error' => 'invalid or missing token']);
    exit;
}

// Trusts whatever the (possibly forged) token says, including role=admin.
echo json_encode([
    'ok'   => true,
    'user' => $payload['sub'] ?? 'unknown',
    'role' => $payload['role'] ?? 'user',
    'note' => 'If alg=none works, forge a token with role=admin.',
    'secret' => $secret,          // VULN #4
]);
