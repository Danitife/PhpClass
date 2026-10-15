/**
 * ============================================================================
 * RATE-LIMIT BYPASS — the limiter that limits nothing (Node.js)
 * ============================================================================
 * Node port of rate-limit.php.  Run:  node demos/rate-limit.js
 *
 * Rate limiting stops brute force, credential stuffing, scraping and resource
 * exhaustion. The premise: pick a KEY that identifies the actor, count their
 * requests per time window, block when the count exceeds a limit.
 *
 * The whole security rests on the KEY being something the attacker CANNOT
 * freely change:
 *     Good: the TCP connection source (req.socket.remoteAddress)
 *     Bad : X-Forwarded-For / X-Real-IP / CF-Connecting-IP  (just headers)
 *     Bad : a cookie or session id  (attacker clears it)
 *
 * ----------------------------------------------------------------------------
 * STEP-BY-STEP: why the vulnerable code below is broken
 * ----------------------------------------------------------------------------
 * 1. The limiter keys on X-Forwarded-For — a header ANY client can set to any
 *    value, whether or not a proxy is involved.
 * 2. So the attacker rotates the header: every new value is a brand-new bucket
 *    with a fresh allowance. The "3 per minute" limit never bites.
 * 3. It limits only per-IP, never per-account, so even a correct IP limiter
 *    would fall to a botnet hammering one username.
 * 4. (Common cousin) read-then-increment counters are themselves racy — see
 *    race-condition.js; use atomic increments.
 */
import { fileURLToPath } from 'url';

const LIMIT = 3;       // max failed attempts
const WINDOW = 60_000; // per 60s

// ============================================================================
// VULNERABLE
// ============================================================================

/** VULN — key is taken from the client-controlled X-Forwarded-For header. */
export function makeVulnLimiter() {
  const state = new Map();
  return function attempt(req) {
    // BUG: trust XFF even though we don't know a trusted proxy set it.
    const key = req.headers['x-forwarded-for'] || req.socket.remoteAddress;
    const now = Date.now();
    let rec = state.get(key);
    if (!rec || now - rec.first > WINDOW) rec = { count: 0, first: now };
    if (rec.count >= LIMIT) { state.set(key, rec); return { allowed: false, key }; }
    rec.count++;
    state.set(key, rec);
    return { allowed: true, key, used: rec.count };
  };
}

// ============================================================================
// SECURE
// ============================================================================

/**
 * FIX — key on the real socket address (trust XFF ONLY behind a proxy you
 * control, and then only the hop you added). Limit on MULTIPLE keys at once:
 * per-IP AND per-account, so the attacker must defeat both. Use atomic
 * counters in production (Redis INCR + EXPIRE); lock the ACCOUNT after N fails.
 */
export function makeSecureLimiter({ trustProxy = false } = {}) {
  const byIp = new Map();
  const byUser = new Map();
  const bump = (map, key, now) => {
    let rec = map.get(key);
    if (!rec || now - rec.first > WINDOW) rec = { count: 0, first: now };
    rec.count++;
    map.set(key, rec);
    return rec.count;
  };
  return function attempt(req, username) {
    const now = Date.now();
    // Only consult XFF if we KNOW we are behind our own proxy; otherwise ignore it.
    const ip = trustProxy
      ? String(req.headers['x-forwarded-for'] || '').split(',')[0].trim() || req.socket.remoteAddress
      : req.socket.remoteAddress;
    const ipCount = bump(byIp, ip, now);
    const userCount = bump(byUser, username, now); // per-account lock
    const allowed = ipCount <= LIMIT && userCount <= LIMIT;
    return { allowed, ip, ipCount, userCount };
  };
}

// ============================================================================
// LIVE DEMO
// ============================================================================
function demo() {
  const line = '─'.repeat(70);
  console.log(`\n${line}\nRATE-LIMIT BYPASS — live demo (limit ${LIMIT} failed attempts / 60s)\n${line}`);

  const vuln = makeVulnLimiter();
  const sameIp = { headers: {}, socket: { remoteAddress: '127.0.0.1' } };

  console.log('\n[1] Honest attacker, one IP — gets blocked after 3:');
  for (let i = 1; i <= 5; i++) {
    const r = vuln(sameIp);
    console.log(`   guess ${i}: ${r.allowed ? 'allowed' : '⛔ BLOCKED'}`);
  }

  console.log('\n[2] ATTACK — rotate X-Forwarded-For: unlimited guesses:');
  let allowed = 0;
  for (let i = 1; i <= 50; i++) {
    const req = { headers: { 'x-forwarded-for': `10.0.0.${i}` }, socket: { remoteAddress: '127.0.0.1' } };
    if (vuln(req).allowed) allowed++;
  }
  console.log(`   ❌ VULNERABLE: ${allowed}/50 forged-IP guesses allowed (limit bypassed)`);

  console.log('\n[3] SAME attack against the SECURE limiter (keys on real socket + account):');
  const secure = makeSecureLimiter({ trustProxy: false });
  let secAllowed = 0, blockedAt = null;
  for (let i = 1; i <= 50; i++) {
    const req = { headers: { 'x-forwarded-for': `10.0.0.${i}` }, socket: { remoteAddress: '127.0.0.1' } };
    const r = secure(req, 'admin'); // brute-forcing one account
    if (r.allowed) secAllowed++; else if (blockedAt === null) blockedAt = i;
  }
  console.log(`   ✅ SECURE: only ${secAllowed}/50 allowed; blocked from attempt #${blockedAt} (forged header ignored)`);
  console.log('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) demo();
