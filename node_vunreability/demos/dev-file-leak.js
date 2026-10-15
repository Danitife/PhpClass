/**
 * ============================================================================
 * DEVELOPER FILE LEAK — secrets sitting in the web root (Node.js)
 * ============================================================================
 * Node port of dev-file-leak.php.  Run:  node demos/dev-file-leak.js
 *
 * A dev file leak needs NO exploit — no traversal, no injection. Sensitive
 * files are simply reachable over HTTP because they were left in the public
 * directory. The attacker guesses the name and downloads it.
 *
 * Commonly leaked, by just guessing the URL:
 *     .env  .env.local  .env.production   → DB creds, API keys, JWT secrets
 *     .git/  .git/config  .git/HEAD       → the ENTIRE source + every secret
 *                                            ever committed (even deleted ones)
 *     config.js.bak  wp-config.php~       → editor/backup copies of config
 *     *.sql  backup.sql  dump.sql         → full database dumps
 *     docker-compose.yml  .htaccess       → infra layout, ports, passwords
 *     *.map  .DS_Store                    → original source / file listing
 *
 * HOW IT HAPPENS IN NODE: `app.use(express.static(__dirname))` serves the
 * whole project folder. By default express.static does NOT serve dotfiles,
 * but `{ dotfiles: 'allow' }` (or serving a folder that contains .env and
 * *.bak) exposes them. A single over-broad static root and your secrets ship.
 *
 * ----------------------------------------------------------------------------
 * STEP-BY-STEP: why the vulnerable setup below is broken
 * ----------------------------------------------------------------------------
 * 1. The static root is the PROJECT directory, which also holds .env, backups,
 *    .git, and SQL dumps — not a dedicated /public folder of safe assets.
 * 2. `dotfiles: 'allow'` turns off the one default guard, so GET /.env works.
 * 3. Secrets live INSIDE the web root at all, so any static/read bug exposes
 *    them. Config belongs outside the served tree (or in the environment).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// A filename is "sensitive" if it is a dotfile or matches a risky pattern.
const SENSITIVE = [
  /^\./,                       // .env, .git, .htaccess, .DS_Store, ...
  /\.(bak|old|orig|save|swp)$/i,
  /~$/,                        // editor backups  config.js~
  /\.(sql|dump)$/i,
  /\.map$/i,
  /(^|\/)(docker-compose\.ya?ml|package-lock\.json)$/i,
];
export const isSensitive = (name) => SENSITIVE.some((re) => re.test(name));

// ============================================================================
// VULNERABLE — serves everything, including dotfiles/backups.
// ============================================================================
// import express from 'express';
// app.use(express.static(__dirname, { dotfiles: 'allow' }));   // BUG
export function vulnWouldServe(/* name */) {
  return true; // dotfiles:'allow' over the project root => anything is fetchable
}

// ============================================================================
// SECURE
// ============================================================================
/**
 * FIX #1 — serve ONLY a dedicated assets folder, never the project root:
 *     app.use('/static', express.static(path.join(__dirname, 'public')));
 * FIX #2 — keep the static default (dotfiles:'ignore') AND add a deny guard:
 */
export function secureWouldServe(name) {
  return !isSensitive(name); // dotfiles & backups & dumps are refused (404/403)
}
// Express middleware form of the guard:
export function denySensitive(req, res, next) {
  if (isSensitive(req.path.replace(/^\//, ''))) return res.status(404).end();
  next();
}
// FIX #3 — don't keep secrets in the repo/web root at all: read them from the
//          process environment (env vars / a secrets manager), and add .env,
//          *.bak, *.sql, .git to .gitignore and the deploy ignore list.
// FIX #4 — block these at the reverse proxy too (nginx `location ~ /\.` { deny all; }).

// ============================================================================
// LIVE DEMO — scan the real lab folder and show what leaks
// ============================================================================
function demo() {
  const line = '─'.repeat(70);
  console.log(`\n${line}\nDEV FILE LEAK — live demo (scanning the lab's web root)\n${line}`);

  const root = path.join(__dirname, '..'); // node_vunreability/
  const names = fs.readdirSync(root).filter((n) => n !== 'node_modules');
  const risky = names.filter(isSensitive);

  console.log('\n[1] Files an attacker could just request by name:');
  for (const n of risky) {
    console.log(`   vuln  GET /${n.padEnd(16)} → 200 served  ❌   |   secure → ${secureWouldServe(n) ? '200' : '404 denied ✅'}`);
  }
  if (!risky.length) console.log('   (no sensitive files found at root)');

  console.log('\n[2] Proof — download the leaked .env the way the attacker would:');
  try {
    const env = fs.readFileSync(path.join(root, '.env'), 'utf8');
    const secret = env.split('\n').find((l) => l.startsWith('JWT_SECRET'));
    console.log('   ❌ GET /.env leaked →', JSON.stringify(secret));
  } catch { console.log('   (.env not present)'); }

  console.log('\n[3] Secure guard applied to the same requests:');
  for (const n of ['.env', 'config.js.bak', 'server.js']) {
    console.log(`   GET /${n.padEnd(16)} → ${secureWouldServe(n) ? 'allowed (safe asset)' : 'DENIED ✅'}`);
  }
  console.log('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) demo();
