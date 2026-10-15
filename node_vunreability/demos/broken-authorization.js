/**
 * ============================================================================
 * BROKEN AUTHORIZATION — IDOR + Privilege Escalation (Node.js)
 * ============================================================================
 * Node port of broken-authorization.php.  Run:  node demos/broken-authorization.js
 *
 * Authentication = "Who are you?"   Authorization = "May you do THIS to THAT?"
 * Broken Authorization = the server skips the second question, or trusts the
 * client to answer it. OWASP A01:2021 — the #1 web-app risk.
 *
 * ----------------------------------------------------------------------------
 * STEP-BY-STEP: why the vulnerable code below is broken
 * ----------------------------------------------------------------------------
 * 1. IDOR (horizontal): getProfile() reads the requested id straight from the
 *    request and returns THAT user — never checking the row belongs to the
 *    caller. Incrementing ?id= walks every account (and its password).
 * 2. Privilege escalation (vertical): the "admin" gate trusts req.cookies.role
 *    — a value the browser fully controls. Set role=admin and you are staff.
 * 3. The identity itself (who am I) is read from a client cookie, not a
 *    server-side session, so even "who" is forgeable.
 */
import { fileURLToPath } from 'url';

// Pretend datastore. password shown only to make the leak obvious in the demo.
const USERS = [
  { id: 1, name: 'admin',   email: 'admin@bank.local', password: 'Admin@123',  role: 'admin' },
  { id: 2, name: 'alice',   email: 'alice@bank.local', password: 'alice123',   role: 'user' },
  { id: 3, name: 'bob',     email: 'bob@bank.local',   password: 'bob123',     role: 'user' },
];
const db = { findById: (id) => USERS.find((u) => u.id === Number(id)) };

// ============================================================================
// VULNERABLE
// ============================================================================

/** VULN #1 — IDOR: returns any id the caller names, no ownership check. */
export function getProfileVuln(req) {
  const id = req.query.id ?? req.cookies.user_id; // client-controlled
  return db.findById(id);                          // BUG: no "does caller own id?"
}

/** VULN #2 — vertical escalation: admin gate trusts the client's role cookie. */
export function adminListVuln(req) {
  if (req.cookies.role !== 'admin') throw new Error('403: not admin'); // BUG: cookie
  return USERS; // full table incl. passwords
}

// ============================================================================
// SECURE
// ============================================================================

/**
 * FIX — identity and role come from a trusted server-side session; every
 * object access is checked for ownership (or an explicit admin override).
 */
export function getProfileSecure(session, req) {
  const requested = Number(req.query.id ?? session.userId);
  const isOwner = requested === session.userId;
  const isAdmin = session.role === 'admin'; // role from the SESSION, not a cookie
  if (!isOwner && !isAdmin) throw new Error('403: you may not view this profile');
  const u = db.findById(requested);
  if (!u) throw new Error('404');
  const { password, ...safe } = u; // never ship the password column
  return isAdmin ? u : safe;
}

export function adminListSecure(session) {
  if (session.role !== 'admin') throw new Error('403: not admin'); // SESSION role
  return USERS.map(({ password, ...safe }) => safe);
}

// ============================================================================
// LIVE DEMO
// ============================================================================
function demo() {
  const line = '─'.repeat(70);
  console.log(`\n${line}\nBROKEN AUTHORIZATION — live demo (logged in as user id=2, alice)\n${line}`);

  console.log('\n[1] IDOR — alice walks the ids to read the admin row:');
  for (const id of [1, 2, 3]) {
    const u = getProfileVuln({ query: { id }, cookies: { user_id: '2', role: 'user' } });
    console.log(`   vuln  ?id=${id} → ${u.name.padEnd(6)} password=${u.password}  ❌ leaked`);
  }

  console.log('\n[2] Same request through the SECURE handler:');
  const session = { userId: 2, role: 'user' };
  for (const id of [1, 2]) {
    try {
      const u = getProfileSecure(session, { query: { id } });
      console.log(`   secure ?id=${id} → ${u.name} (own profile, no password) ✅`);
    } catch (e) { console.log(`   secure ?id=${id} → ${e.message} ✅ blocked`); }
  }

  console.log('\n[3] Vertical escalation — alice forges the role cookie:');
  try {
    const rows = adminListVuln({ cookies: { role: 'admin' } }); // forged
    console.log(`   vuln: role=admin cookie → full table (${rows.length} rows incl. passwords) ❌`);
  } catch (e) { console.log('   vuln:', e.message); }
  try { adminListSecure(session); }
  catch (e) { console.log('   secure: forged cookie ignored, session role=user →', e.message, '✅'); }
  console.log('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) demo();
