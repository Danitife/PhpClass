/**
 * server.js — Node Bank of VULN (Express + MongoDB) — INTENTIONALLY INSECURE
 * ============================================================================
 * A faithful port of the PHP/MySQL "Bank of VULN" lab. The SQL-injection
 * exercises become NoSQL-injection exercises (operator injection + $where);
 * every other bug is reproduced 1:1. TRAINING ON LOCALHOST ONLY.
 *
 * All HTML lives in views.js — this file is just route logic + the bugs.
 *
 * Bug map (see README.md for payloads):
 *   login        NoSQL operator injection (auth bypass), plaintext pw,
 *                rate-limit bypass via X-Forwarded-For, role in a cookie
 *   /search      NoSQL injection ($where / operators) -> dump every account
 *   register     plaintext pw, no validation, stored XSS (username)
 *   index        stored XSS (raw username echo), non-HttpOnly session cookie
 *   transfer     IDOR (from_account), negative amount, no balance check,
 *                TOCTOU race (non-atomic read-modify-write), operator injection
 *   bonus        TOCTOU race (lost updates)
 *   profile      IDOR (?id=), password reset w/o auth, stored XSS
 *   view-account IDOR (?id=) -> read anyone's plaintext password
 *   admin        privilege escalation via client-controlled `role` cookie
 *   file         path traversal (?file=) + LFI->RCE (?page= require())
 *   api          JWT alg:none, leaked+echoed secret, no exp
 *   .env / .bak  dev-file leak (served from web root)
 *   sniffer      XSS cookie exfiltration sink
 */
import express from 'express';
import session from 'express-session';
import cookieParser from 'cookie-parser';
import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

import { users, transactions, MONGO_URL, DB_NAME } from './db.js';
import { loadEnv } from './env.js';
import {
  esc,
  money,
  page,
  renderLogin,
  renderSearch,
  renderRegister,
  renderDashboard,
  renderTransfer,
  renderBonus,
  renderProfile,
  renderProfileMissing,
  renderViewAccount,
  renderAdmin,
  renderFile,
} from './views.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);
const env = loadEnv();
const JWT_SECRET = env.JWT_SECRET || 'change_me_bank_super_secret_123';
const PORT = process.env.PORT || 3000;

const delay = (ms) => new Promise((r) => setTimeout(r, ms));

const app = express();

// VULN: extended:true uses `qs`, which turns  email[$ne]=  into  {email:{$ne:''}}
// (operator injection) for BOTH the query string and urlencoded bodies.
// express.json() lets the same operators arrive as a raw JSON object.
app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(cookieParser());
app.use(session({
  name: 'BANK_SESSION',
  secret: 'bank-of-vuln-session',
  resave: false,
  saveUninitialized: false,
  // VULN: httpOnly:false -> JavaScript (stored XSS) can read the session cookie.
  cookie: { httpOnly: false },
}));

// VULN (dev-file leak): serve the whole app directory statically, INCLUDING
// dotfiles, so GET /.env and GET /config.js.bak hand over every secret.
app.use(express.static(__dirname, { dotfiles: 'allow', index: false }));

const requireLogin = (req, res, next) => {
  if (!req.session.user_id) return res.redirect('/login');
  next();
};

// ============================================================================
// LOGIN — NoSQL operator injection (auth bypass) + rate-limit bypass
// ============================================================================
const RATE_FILE = path.join(__dirname, '.rate_state.json');

app.all('/login', async (req, res) => {
  // VULN: rate limiter keyed on X-Forwarded-For — a header the client controls.
  const clientIp = req.headers['x-forwarded-for'] || req.socket.remoteAddress || 'unknown';
  let state = {};
  try { state = JSON.parse(fs.readFileSync(RATE_FILE, 'utf8')); } catch {}
  const now = Math.floor(Date.now() / 1000);
  let rec = state[clientIp] || { n: 0, t: now };
  if (now - rec.t > 60) rec = { n: 0, t: now };
  const blocked = rec.n >= 3;

  let message = '', lastQuery = '';

  if (req.method === 'POST') {
    const { email = '', password = '' } = req.body;
    if (blocked) {
      message = `⛔ Too many failed attempts. Try again in ${60 - (now - rec.t)}s (key: ${clientIp}).`;
    } else {
      // VULN: the raw body values go straight into the Mongo filter. If they
      // are objects ({$ne:null}, {$gt:""}) the query turns into an operator
      // match that ignores the real password. Passwords are PLAINTEXT too.
      const query = { email, password };
      lastQuery = JSON.stringify(query);
      const user = await users.findOne(query);
      if (user) {
        req.session.user_id = user.id;
        req.session.username = user.username;
        req.session.role = user.role;
        // VULN: role shipped in a client-controlled cookie (see /admin).
        res.cookie('role', user.role, { httpOnly: false });
        res.cookie('user_id', String(user.id), { httpOnly: false });
        return res.redirect('/');
      }
      rec.n++;
      message = `Invalid credentials. Attempts used: ${rec.n}/3 (key: ${clientIp}).`;
    }
    state[clientIp] = rec;
    try { fs.writeFileSync(RATE_FILE, JSON.stringify(state)); } catch {}
  }

  res.send(renderLogin({
    message,
    xff: req.headers['x-forwarded-for'],
    clientIp,
    attempts: rec.n,
    lastQuery,
  }));
});

// ============================================================================
// SEARCH — NoSQL injection ($where / operators) -> dump every account
//   (the analog of the SQLi "UNION dump the users table")
// ============================================================================
app.get('/search', requireLogin, async (req, res) => {
  let results = [], err = '', used = '{}';
  const raw = req.query.filter;
  if (raw !== undefined) {
    try {
      // VULN: a client-supplied JSON filter passed straight to find().
      //   ?filter={"$where":"return true"}            -> dumps everything
      //   ?filter={"role":"admin"}                    -> only admins
      //   ?filter={"balance":{"$gt":100}}             -> operator injection
      const filter = typeof raw === 'object' ? raw : JSON.parse(raw);
      used = JSON.stringify(filter);
      results = await users.find(filter).toArray();
    } catch (e) { err = e.message; }
  }
  res.send(renderSearch({ rawFilter: raw, err, used, results }));
});

// ============================================================================
// REGISTER — plaintext pw, no validation, stored XSS via username
// ============================================================================
app.all('/register', async (req, res) => {
  let message = '';
  if (req.method === 'POST') {
    const { username = '', full_name = '', email = '', password = '' } = req.body;
    const last = (await users.find().sort({ id: -1 }).limit(1).toArray())[0];
    // VULN: username/full_name stored raw (stored XSS), password PLAINTEXT.
    await users.insertOne({
      id: (last?.id || 0) + 1,
      username: String(username), full_name: String(full_name), email: String(email),
      password: String(password), role: 'user', balance: 0,
      account_number: '10' + Math.floor(10000000 + Math.random() * 89999999),
    });
    message = '✅ Account created! <a href="/login" style="color:#7ee08f">Log in now</a>';
  }
  res.send(renderRegister({ message }));
});

// ============================================================================
// INDEX / dashboard — stored XSS (raw username), non-HttpOnly cookie
// ============================================================================
app.get('/', requireLogin, async (req, res) => {
  const me = await users.findOne({ id: req.session.user_id });
  if (!me) return res.send(page('Error', '<div class="card">User not found.</div>', false));
  const txns = await transactions.find({
    $or: [{ from_account: me.account_number }, { to_account: me.account_number }],
  }).sort({ _id: -1 }).limit(10).toArray();
  res.send(renderDashboard({ me, txns }));
});

// ============================================================================
// TRANSFER — IDOR, negative amount, no balance check, TOCTOU race, injection
// ============================================================================
app.all('/transfer', requireLogin, async (req, res) => {
  let message = '', isErr = false;
  if (req.method === 'POST') {
    // VULN #1 IDOR: `from` comes from a hidden field, never checked against
    //   the session. VULN: account numbers used raw -> operator injection.
    const from = req.body.from_account;
    const to = req.body.to_account;
    const amount = parseFloat(req.body.amount ?? 0); // VULN #2: no > 0 check
    const note = String(req.body.note ?? '');

    const dest = await users.findOne({ account_number: to });
    if (!dest) {
      message = `Destination account not found: ${esc(JSON.stringify(to))}`; isErr = true;
    } else {
      // VULN #4 TOCTOU: read -> (await) -> compute -> write, non-atomic. Two
      // concurrent requests both read the OLD balance; the second write wins.
      const src = await users.findOne({ account_number: from });
      await delay(150); // widen the race window (real latency does this for free)
      const newFrom = Number(src.balance) - amount; // negative amount ADDS money
      const newTo = Number(dest.balance) + amount;
      await users.updateOne({ account_number: from }, { $set: { balance: newFrom } });
      await users.updateOne({ account_number: to }, { $set: { balance: newTo } });
      await transactions.insertOne({ from_account: from, to_account: to, amount, note });
      message = `Transferred $${amount} from <b>${esc(from)}</b> to <b>${esc(to)}</b>. No ownership check was performed!`;
    }
  }
  const me = await users.findOne({ id: req.session.user_id });
  res.send(renderTransfer({ me, message, isErr }));
});

// ============================================================================
// BONUS — TOCTOU race (lost updates)
// ============================================================================
app.all('/bonus', requireLogin, async (req, res) => {
  let message = '';
  if (req.method === 'POST') {
    // VULN: read -> await -> write, with no atomic $inc. Fire N concurrent
    // claims and you get far less than N×$10 (lost updates).
    const row = await users.findOne({ id: req.session.user_id });
    await delay(200); // concurrent requests now both read the OLD balance
    const next = Number(row.balance) + 10;
    await users.updateOne({ id: req.session.user_id }, { $set: { balance: next } });
    message = `Claimed $10 bonus. New balance: $${money(next)}`;
  }
  const me = await users.findOne({ id: req.session.user_id });
  res.send(renderBonus({ me, message, port: PORT }));
});

// ============================================================================
// PROFILE — IDOR (?id=), password reset w/o auth, stored XSS
// ============================================================================
app.all('/profile', requireLogin, async (req, res) => {
  // VULN: the id to edit comes from ?id= with NO ownership check.
  const id = Number(req.query.id ?? req.session.user_id);
  let message = '', isErr = false;
  if (req.method === 'POST') {
    const { username = '', email = '', new_password = '' } = req.body;
    // VULN: no authz, no current-password prompt.
    await users.updateOne({ id }, { $set: { username: String(username), email: String(email), password: String(new_password) } });
    message = `Profile updated for user id = ${id}`;
  }
  const user = await users.findOne({ id });
  if (!user) return res.send(renderProfileMissing({ id }));
  res.send(renderProfile({ id, user, message, isErr, sessionUserId: req.session.user_id }));
});

// ============================================================================
// VIEW-ACCOUNT — IDOR (?id=) -> read anyone's plaintext password
// ============================================================================
app.get('/view-account', requireLogin, async (req, res) => {
  const id = Number(req.query.id ?? 2); // VULN: fully client-controlled, no authz
  const user = await users.findOne({ id });
  res.send(renderViewAccount({ id, user }));
});

// ============================================================================
// ADMIN — vertical privilege escalation via client-controlled `role` cookie
// ============================================================================
app.get('/admin', async (req, res) => {
  // VULN: trust the cookie the browser sends, not the server session.
  const role = req.cookies.role || 'user';
  const show = req.query.admin !== undefined;
  const allUsers = show && role === 'admin' ? await users.find().toArray() : [];
  res.send(renderAdmin({
    role,
    show,
    allUsers,
    sessionRole: req.session.role,
    cookieRole: req.cookies.role,
  }));
});

// ============================================================================
// FILE — path traversal (?file=) + LFI -> RCE (?page= require())
// ============================================================================
app.get('/file', (req, res) => {
  const file = req.query.file ?? 'help.txt';
  const resolved = path.join(__dirname, String(file)); // VULN: no allowlist / normalisation
  let contents = null, err = null;
  try { contents = fs.readFileSync(resolved, 'utf8'); } catch (e) { err = `Cannot read: ${resolved} (${e.code})`; }

  let included = null;
  if (req.query.page !== undefined) {
    // VULN: user-controlled require() — a .js file is EXECUTED (RCE analog).
    // A relative path resolves under the app dir; an absolute path (e.g. a
    // planted /tmp/shell.js) runs its top-level code on require().
    const p = String(req.query.page);
    const target = path.isAbsolute(p) ? p : path.join(__dirname, p);
    try {
      delete require.cache[require.resolve(target)];
      const mod = require(target);
      included = typeof mod === 'function' ? String(mod(req.query)) : JSON.stringify(mod, null, 2);
    } catch (e) { included = `include error: ${e.message}`; }
  }

  res.send(renderFile({ file, resolved, contents, err, included }));
});

// ============================================================================
// API — JWT alg:none, leaked+echoed secret, no exp validation
// ============================================================================
const b64u = (buf) => Buffer.from(buf).toString('base64url');
function jwtDecodeUnsafe(token, secret) {
  const parts = String(token).split('.');
  if (parts.length !== 3) return null;
  let header, payload;
  try {
    header = JSON.parse(Buffer.from(parts[0], 'base64url').toString());
    payload = JSON.parse(Buffer.from(parts[1], 'base64url').toString());
  } catch { return null; }
  // VULN #1: alg:none accepted with NO signature check.
  if (String(header.alg).toUpperCase() === 'NONE') return payload;
  const sig = b64u(crypto.createHmac('sha256', secret).update(`${parts[0]}.${parts[1]}`).digest());
  // VULN #3: signature verified but exp/nbf never checked -> tokens never expire.
  if (crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(parts[2]))) return payload;
  return null;
}

app.get('/api', (req, res) => {
  const auth = req.headers.authorization || '';
  const m = auth.match(/Bearer\s+(\S+)/);
  const token = m ? m[1] : (req.query.token || '');
  const payload = jwtDecodeUnsafe(token, JWT_SECRET);
  if (!payload) return res.json({ error: 'invalid or missing token' });
  res.json({
    ok: true,
    user: payload.sub || 'unknown',
    role: payload.role || 'user',
    note: 'If alg:none works, forge a token with role=admin.',
    secret: JWT_SECRET, // VULN #4: it even hands you the secret
  });
});

// ============================================================================
// SNIFFER — XSS cookie exfiltration sink; LOGOUT
// ============================================================================
app.get('/sniffer', (req, res) => {
  const c = req.query.c || '';
  if (c) { try { fs.appendFileSync(path.join(__dirname, 'stolen.txt'), `${new Date().toISOString()}  ${c}\n`); } catch {} }
  res.set('Content-Type', 'image/gif');
  res.send(Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64'));
});

app.get('/logout', (req, res) => { req.session.destroy(() => {}); res.clearCookie('role'); res.redirect('/login'); });

app.listen(PORT, () => {
  console.log(`\n🏦 Node Bank of VULN running:  http://localhost:${PORT}/`);
  console.log(`   DB: ${DB_NAME} @ ${MONGO_URL}`);
  console.log('   Seed logins: admin@bank.local/Admin@123 · alice@bank.local/alice123 · bob@bank.local/bob123\n');
});
