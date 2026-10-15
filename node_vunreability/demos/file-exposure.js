/**
 * ============================================================================
 * FILE EXPOSURE — Path Traversal, LFI → RCE, and Source Disclosure (Node.js)
 * ============================================================================
 * Node port of file-exposure.php.  Run it:   node demos/file-exposure.js
 * (It only touches the filesystem — no database, no network.)
 *
 * WHAT IS FILE EXPOSURE?
 * Any bug where the server hands the attacker a file they were never meant to
 * see — source code, config, credentials, logs, /etc/passwd, other users'
 * uploads. Three flavours show up constantly:
 *     (a) Source disclosure  — the server returns server-side source as text
 *     (b) Path traversal     — attacker climbs out of the intended folder
 *     (c) Local File Inclusion (LFI) — the server EXECUTES an attacker's file
 *
 * ----------------------------------------------------------------------------
 * STEP-BY-STEP: why the vulnerable code below is broken
 * ----------------------------------------------------------------------------
 * 1. The filename comes straight from the request (?file=) and is glued onto a
 *    base directory with path.join(). No allowlist, no containment check.
 * 2. The string "../" has special meaning to the filesystem. path.join happily
 *    resolves it, so "../../../../etc/passwd" climbs to the root and back down
 *    into any file the Node process can read.
 * 3. Reading a .js file as TEXT dumps its source — including secrets (the
 *    "source disclosure" case). In the browser the file would normally run;
 *    reading it as bytes bypasses that.
 * 4. The ?page= handler calls require() on a user-controlled path. require()
 *    EXECUTES the module's top-level code. If an attacker can plant a .js file
 *    anywhere (upload, log poisoning, /tmp), pointing ?page= at it = RCE.
 *      - In PHP this is include(); in Node it's require()/import()/eval().
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// ============================================================================
// VULNERABLE
// ============================================================================

/** VULN #1 — path traversal: user filename joined to a base, no containment. */
export function readFileVuln(baseDir, userFile) {
  const resolved = path.join(baseDir, userFile); // BUG: "../" is honoured
  return fs.readFileSync(resolved, 'utf8');       // reads ANY readable file
}

/** VULN #2 — LFI → RCE: require() a user-controlled path (executes it). */
export async function includePageVuln(baseDir, userPage) {
  const target = path.isAbsolute(userPage) ? userPage : path.join(baseDir, userPage);
  // Dynamic import runs the module's top-level code = arbitrary execution.
  const mod = await import(pathToFileURL(target).href); // BUG: never do this
  return mod;
}

// ============================================================================
// SECURE
// ============================================================================

/**
 * FIX #1 (best) — allowlist: map a token to a known file; reject anything else.
 */
const ALLOWED = { welcome: 'help.txt' };
export function readFileAllowlist(baseDir, token) {
  const name = ALLOWED[token];
  if (!name) throw new Error('403 Forbidden: unknown file token');
  return fs.readFileSync(path.join(baseDir, name), 'utf8');
}

/**
 * FIX #2 — if you must accept a name, anchor it: resolve, then verify the
 * real path is still INSIDE the intended directory (defeats ../, symlinks).
 */
export function readFileAnchored(baseDir, userFile) {
  const root = fs.realpathSync(baseDir);
  const resolved = path.resolve(root, userFile);
  // realpath the parent so symlinks can't escape; then prefix-check.
  if (resolved !== root && !resolved.startsWith(root + path.sep)) {
    throw new Error('403 Forbidden: path escapes base directory');
  }
  return fs.readFileSync(resolved, 'utf8');
}

// FIX #3 — never require()/import() user input. To pick among pages, switch on
//          an allowlisted key that maps to a *statically imported* module.
// FIX #4 — keep secrets OUT of the web root; block .env/.git/*.bak at the proxy;
//          run with least privilege so even a traversal reads little.

// ============================================================================
// LIVE DEMO
// ============================================================================
function demo() {
  const base = __dirname;
  const line = '─'.repeat(70);
  console.log(`\n${line}\nFILE EXPOSURE — live demo\n${line}`);

  console.log('\n[1] Intended use (read a help file):');
  try { console.log('   vuln reads help.txt →', JSON.stringify(readFileVuln(base, '../help.txt').slice(0, 40)) + '...'); }
  catch (e) { console.log('   (help.txt not found:', e.code + ')'); }

  console.log('\n[2] ATTACK — path traversal to a system file (/etc/hosts):');
  try {
    const hosts = readFileVuln(base, '../../../../../../../../etc/hosts');
    console.log('   ❌ VULNERABLE: leaked', hosts.split('\n').length, 'lines of /etc/hosts');
    console.log('      first line →', JSON.stringify(hosts.split('\n').find((l) => l.trim() && !l.startsWith('#')) || hosts.split('\n')[0]));
  } catch (e) { console.log('   (could not read /etc/hosts here:', e.code + ')'); }

  console.log('\n[3] ATTACK — source disclosure (read the lab\'s own .env):');
  try {
    const env = readFileVuln(base, '../.env');
    const secret = env.split('\n').find((l) => l.startsWith('JWT_SECRET'));
    console.log('   ❌ VULNERABLE: leaked secrets from .env →', JSON.stringify(secret));
  } catch (e) { console.log('   (.env not found:', e.code + ')'); }

  console.log('\n[4] SAME ATTACK against the SECURE allowlist + anchored readers:');
  for (const [fn, label] of [[() => readFileAllowlist(base, '../../../etc/hosts'), 'allowlist'],
                             [() => readFileAnchored(base, '../../../../../../etc/hosts'), 'anchored']]) {
    try { fn(); console.log(`   ⚠️  ${label} unexpectedly allowed it`); }
    catch (e) { console.log(`   ✅ ${label} blocked: ${e.message}`); }
  }
  console.log('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) demo();
