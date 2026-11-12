/**
 * ============================================================================
 * FILE EXPOSURE (Path Traversal, LFI, Source Disclosure) — Node.js
 * ============================================================================
 * Run it:   node demos/file-exposure.js
 * (It only touches the filesystem — no database, no network.)
 *
 * THINK OF IT LIKE A HOTEL.
 * You hand the front desk your room number and they fetch what's in that room.
 * That's fine — until a guest writes "../manager's office" on the slip and the
 * clerk, following it literally, walks out of the guest floor and brings back
 * the manager's private files. "File exposure" is any bug where the server
 * fetches a file the user should never get to see.
 *
 * THE THREE VERSIONS YOU'LL SEE (plain English):
 *   (a) Source disclosure — the server shows you its OWN code as text, so any
 *       passwords written in that code are now yours.
 *   (b) Path traversal    — the user sneaks "../" into a filename to walk UP
 *       out of the intended folder and read files elsewhere on the computer.
 *   (c) LFI (Local File Inclusion) — worse: the server doesn't just READ the
 *       file the user picked, it RUNS it. If the attacker can get their own
 *       code onto the server first, this runs it = full takeover (called RCE,
 *       "remote code execution").
 *
 * ----------------------------------------------------------------------------
 * WHY THE CODE BELOW IS BROKEN (step by step)
 * ----------------------------------------------------------------------------
 * 1. The filename comes straight from the user (the ?file= in the URL) and is
 *    stuck onto a folder path. The code never checks it's a file we meant to
 *    share.
 * 2. "../" means "go up one folder" (same as `cd ..` in a terminal). Chain a
 *    few together — "../../../../etc/passwd" — and you climb all the way to the
 *    top of the disk, then down into any file the server is allowed to open.
 * 3. A .js file opened as TEXT just shows its letters — including any secret
 *    written inside it. Normally that file would RUN and hide its code; reading
 *    it as text instead leaks the code.
 * 4. The ?page= part doesn't read the file, it `require()`s it — and require()
 *    RUNS the file. Point it at a file the attacker planted (via an upload, a
 *    poisoned log, /tmp) and their code runs on your server.
 *      (PHP calls this include(); in Node it's require() / import() / eval().)
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// ============================================================================
// VULNERABLE
// ============================================================================

/** VULN #1 — path traversal: we glue the user's filename onto our folder and
 *  open whatever comes out. If they sneak in "../", we read files we shouldn't. */
export function readFileVuln(baseDir, userFile) {
  const resolved = path.join(baseDir, userFile); // BUG: "../" climbs out of baseDir
  return fs.readFileSync(resolved, 'utf8');       // opens ANY file the server can read
}

/** VULN #2 — LFI: instead of READING the user's file, we RUN it. If the
 *  attacker planted a file first, this executes their code (that's RCE). */
export async function includePageVuln(baseDir, userPage) {
  const target = path.isAbsolute(userPage) ? userPage : path.join(baseDir, userPage);
  // import() doesn't just load the file — it runs the code inside it.
  const mod = await import(pathToFileURL(target).href); // BUG: never run a user-named file
  return mod;
}

// ============================================================================
// SECURE
// ============================================================================

/**
 * FIX #1 (best) — a menu, not a free text box. Keep a short list of files you're
 * willing to share. The user picks a NAME from the list ("welcome"); they never
 * get to type a real path. Anything not on the menu is refused.
 */
const ALLOWED = { welcome: 'help.txt', reader: 'readme.md', license: 'LICENSE' };
export function readFileAllowlist(baseDir, token) {
  const name = ALLOWED[token];
  if (!name) throw new Error('403 Forbidden: unknown file token');
  return fs.readFileSync(path.join(baseDir, name), 'utf8');
}

/**
 * FIX #2 — if you really must accept a filename, check where it LANDS. Work out
 * the final, real path, then make sure it still sits inside your intended
 * folder. If "../" pushed it outside, refuse. (This also stops shortcut tricks.)
 */
export function readFileAnchored(baseDir, userFile) {
  const root = fs.realpathSync(baseDir);           // the folder we allow, fully resolved
  const resolved = path.resolve(root, userFile);   // where the request actually points
  // If the final path isn't inside our folder, it escaped — block it.
  if (resolved !== root && !resolved.startsWith(root + path.sep)) {
    throw new Error('403 Forbidden: path escapes base directory');
  }
  return fs.readFileSync(resolved, 'utf8');
}

// FIX #3 — never run a file the user named. To switch between pages, map an
//          allowed name to a module you already imported at the top of the file.
// FIX #4 — keep secrets OUT of the public folder, block .env/.git/*.bak at the
//          web server, and give the app the fewest permissions possible, so even
//          a successful traversal reaches almost nothing.

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
