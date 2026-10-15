/**
 * server.js — Node Bank of VULN (Express + MongoDB) — INTENTIONALLY INSECURE
 * ============================================================================
 * A faithful port of the PHP/MySQL "Bank of VULN" lab. The SQL-injection
 * exercises become NoSQL-injection exercises (operator injection + $where);
 * every other bug is reproduced 1:1. TRAINING ON LOCALHOST ONLY.
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

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);
const env = loadEnv();
const JWT_SECRET = env.JWT_SECRET || 'change_me_bank_super_secret_123';
const PORT = process.env.PORT || 3000;

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

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const delay = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------- shared HTML chrome ----------
const CSS = `
  body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#0b1526;color:#e8eef7;margin:0}
  .nav{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;padding:16px;background:#0f1a2f;border-bottom:1px solid #22355a}
  .nav a{color:#f5c04c;text-decoration:none;font-size:.9em}.nav a:hover{text-decoration:underline}
  .wrap{max-width:760px;margin:2vh auto;padding:0 16px}
  .brand{text-align:center;font-size:1.6em;font-weight:700;color:#f5c04c;margin:18px 0}
  .card{background:#12203a;border:1px solid #22355a;border-radius:12px;padding:24px;margin-bottom:16px}
  h2{margin:0 0 6px}.sub{color:#9fb3d1;font-size:.85em;margin-bottom:14px}
  .hint{background:#1c2c4d;border-left:3px solid #f5c04c;padding:10px 12px;font-size:.82em;border-radius:4px;margin:12px 0}
  .hint code,.debug code{background:#0b1526;padding:1px 5px;border-radius:3px;color:#7ee08f}
  .msg{background:#143a16;border:1px solid #3f9e4f;color:#b8e6bd;padding:10px;border-radius:6px;margin:12px 0;font-size:.9em}
  .err{background:#3a1620;border:1px solid #ff6b6b;color:#ffb3b3;padding:10px;border-radius:6px;margin:12px 0;font-size:.9em}
  label{display:block;margin:12px 0 4px;font-size:.85em;color:#9fb3d1}
  input{width:100%;box-sizing:border-box;padding:10px;border-radius:6px;border:1px solid #2c4270;background:#0b1526;color:#fff}
  button{width:100%;padding:11px;margin-top:16px;background:#1f6feb;color:#fff;border:none;border-radius:6px;font-size:1em;cursor:pointer}
  table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #22355a;padding:8px;font-size:.84em;text-align:left}
  th{color:#f5c04c}td code{color:#ff8a8a}
  .balance-card{background:linear-gradient(135deg,#1f6feb,#12315e);border-radius:12px;padding:22px;margin:16px 0}
  .balance{font-size:2.2em;font-weight:700}
  .debug{background:#0a101d;border:1px solid #2c4270;padding:10px;margin-top:16px;font-size:.78em;border-radius:6px;color:#9fb3d1}
  pre{background:#0a101d;border:1px solid #2c4270;color:#7ee08f;padding:12px;border-radius:6px;overflow:auto;max-height:420px;font-size:.8em}
`;
const NAV = `<div class="nav">
  <a href="/">Dashboard</a><a href="/transfer">Transfer</a><a href="/bonus">Claim Bonus</a>
  <a href="/profile">Profile</a><a href="/view-account">Browse Accounts</a>
  <a href="/admin?admin=1">Admin</a><a href="/file">File viewer</a><a href="/logout">Logout</a>
</div>`;
const page = (title, body, nav = true) => `<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Bank of VULN — ${esc(title)}</title><style>${CSS}</style></head>
<body>${nav ? NAV : ''}<div class="wrap"><div class="brand">🏦 Node Bank of VULN</div>${body}</div></body></html>`;

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

  res.send(page('Login', `<div class="card">
    <h2>Customer Login</h2>
    <div class="hint">💡 <strong>NoSQL auth bypass</strong> — send JSON:<br>
      <code>{"email":"admin@bank.local","password":{"$ne":"x"}}</code><br>
      or form fields: <code>email=admin@bank.local</code> &amp; <code>password[$ne]=x</code><br>
      or match the FIRST user (admin): <code>email[$gt]=</code> &amp; <code>password[$gt]=</code></div>
    ${message ? `<div class="err">${esc(message)}</div>` : ''}
    <form method="POST">
      <label>Email</label><input name="email" placeholder="alice@bank.local">
      <label>Password</label><input name="password" type="password">
      <button name="login" value="1">Sign in</button>
    </form>
    <p class="sub"><a href="/register" style="color:#f5c04c">Open an account →</a></p>
    <div class="debug"><strong>DEBUG</strong><br>
      X-Forwarded-For: <code>${esc(req.headers['x-forwarded-for'] || '-')}</code><br>
      Rate-limit key: <code>${esc(clientIp)}</code> &nbsp; attempts: ${rec.n}/3<br>
      Last filter: <code>${esc(lastQuery)}</code></div>
  </div>`, false));
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
  const rows = results.map((u) => `<tr><td>${u.id}</td><td>${esc(u.username)}</td>
    <td>${esc(u.email)}</td><td><code>${esc(u.password)}</code></td>
    <td>$${money(u.balance)}</td><td>${esc(u.role)}</td></tr>`).join('');
  res.send(page('Search', `<div class="card">
    <h2>🔎 Account search</h2>
    <div class="hint">💡 <strong>NoSQL injection:</strong> the <code>filter</code> query param
      is JSON passed straight to <code>db.users.find()</code>.<br>
      Try <code>?filter={"$where":"return true"}</code> to dump every account + plaintext password.</div>
    <form method="GET"><label>filter (JSON)</label>
      <input name="filter" value='${esc(raw ?? '{"$where":"return true"}')}'>
      <button>Search</button></form>
    ${err ? `<div class="err">${esc(err)}</div>` : ''}
    ${results.length ? `<table><tr><th>id</th><th>username</th><th>email</th><th>password</th><th>balance</th><th>role</th></tr>${rows}</table>` : ''}
    <div class="debug"><strong>DEBUG</strong><br>filter used: <code>${esc(used)}</code> &nbsp; matched: ${results.length}</div>
  </div>`));
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
  res.send(page('Register', `<div class="card">
    <h2>Open an Account</h2>
    <div class="hint">💡 <strong>Stored XSS:</strong> register with username
      <code>&lt;script&gt;alert(document.cookie)&lt;/script&gt;</code> — it is stored raw and
      echoed raw on the dashboard and in the admin table.</div>
    ${message ? `<div class="msg">${message}</div>` : ''}
    <form method="POST">
      <label>Username</label><input name="username">
      <label>Full name</label><input name="full_name">
      <label>Email</label><input name="email">
      <label>Password</label><input name="password" type="password">
      <button name="register" value="1">Create account</button>
    </form></div>`, false));
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
  const rows = txns.map((t) => {
    const inbound = t.to_account === me.account_number;
    return `<tr><td>${esc(t.from_account)}</td><td>${esc(t.to_account)}</td>
      <td style="color:${inbound ? '#7ee08f' : '#ff8a8a'}">${inbound ? '+' : '-'}$${money(t.amount)}</td>
      <td>${esc(t.note)}</td></tr>`;
  }).join('');
  res.send(page('Dashboard', `<div class="card">
    <!-- VULN: raw username echo -> stored XSS fires here -->
    <h2>Welcome back, ${me.username} 👋</h2>
    <div class="sub">Account # ${esc(me.account_number)} · ${esc(me.email)}</div>
    <div class="balance-card"><div>Available balance</div><div class="balance">$${money(me.balance)}</div></div>
    <h3 style="color:#f5c04c;font-size:1em">Recent transactions</h3>
    ${txns.length ? `<table><tr><th>From</th><th>To</th><th>Amount</th><th>Note</th></tr>${rows}</table>`
      : '<p class="sub">No transactions yet. Try a transfer!</p>'}
  </div>`));
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
  res.send(page('Transfer', `<div class="card">
    <h2>💸 Transfer funds</h2>
    <div class="sub">Your account: <b>${esc(me.account_number)}</b> · Balance: $${money(me.balance)}</div>
    <div class="hint">💡 <strong>IDOR:</strong> the <em>from</em> field is hidden — edit it in DevTools to
      another account (admin's <code>1000000001</code>) to move <em>their</em> money.<br>
      💡 <strong>Negative amount:</strong> send <code>-500</code> to <em>gain</em> money.</div>
    ${message ? `<div class="${isErr ? 'err' : 'msg'}">${message}</div>` : ''}
    <form method="POST">
      <label>From account (hidden — tamper in DevTools)</label>
      <input type="hidden" name="from_account" value="${esc(me.account_number)}">
      <label>To account number</label><input name="to_account" value="1000000002">
      <label>Amount</label><input name="amount" type="number" value="10" step="0.01">
      <label>Note</label><input name="note">
      <button name="transfer" value="1">Transfer</button>
    </form></div>`));
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
  res.send(page('Bonus', `<div class="card">
    <h2>🎁 Daily $10 bonus</h2>
    <div class="balance-card"><div>Balance</div><div class="balance">$${money(me.balance)}</div></div>
    <div class="hint">💡 One click is fine — that is NOT the race. Fire many at once:
    <pre># log in, saving the session cookie
curl -s -c jar.txt -H 'Content-Type: application/json' \\
  -d '{"email":"bob@bank.local","password":{"$ne":"x"}}' http://localhost:${PORT}/login -o /dev/null
# 10 concurrent claims — balance will NOT rise by $100
for i in $(seq 1 10); do curl -s -b jar.txt -X POST http://localhost:${PORT}/bonus -o /dev/null & done; wait</pre></div>
    ${message ? `<div class="msg">${esc(message)}</div>` : ''}
    <form method="POST"><button name="claim" value="1">Claim $10</button></form></div>`));
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
  if (!user) return res.send(page('Profile', `<div class="card"><div class="err">No user with id=${esc(id)}</div></div>`));
  res.send(page('Profile', `<div class="card">
    <h2>🛠️ Edit profile</h2>
    <div class="sub">Editing user id = <b>${esc(id)}</b> (taken from <code>?id=</code>)</div>
    <div class="hint">💡 <strong>IDOR:</strong> visit <code>/profile?id=1</code> and reset the
      <em>admin's</em> password with no old-password check, then log in as admin. 😈</div>
    ${message ? `<div class="${isErr ? 'err' : 'msg'}">${esc(message)}</div>` : ''}
    <form method="POST">
      <label>Username (echoed raw → stored XSS)</label>
      <input name="username" value="${user.username}">
      <label>Email</label><input name="email" value="${esc(user.email)}">
      <label>New password (no current-password check!)</label>
      <input name="new_password" type="password">
      <button name="save" value="1">Save changes</button>
    </form>
    <div class="debug"><strong>DEBUG</strong><br>
      Session user id: ${esc(req.session.user_id)}<br>
      Editing user id: ${esc(id)} (any difference = IDOR)<br>
      Current password: <code>${esc(user.password)}</code></div></div>`));
});

// ============================================================================
// VIEW-ACCOUNT — IDOR (?id=) -> read anyone's plaintext password
// ============================================================================
app.get('/view-account', requireLogin, async (req, res) => {
  const id = Number(req.query.id ?? 2); // VULN: fully client-controlled, no authz
  const user = await users.findOne({ id });
  const body = user ? `<table>${Object.entries(user).filter(([k]) => k !== '_id').map(([k, v]) =>
    `<tr><th>${esc(k)}</th><td>${k === 'password' ? `<code>${esc(v)}</code> (plaintext!)` : esc(v)}</td></tr>`).join('')}</table>`
    : `<div class="err">No user found with id = ${esc(id)}</div>`;
  res.send(page('Customer Lookup', `<div class="card">
    <h2>👀 Customer lookup</h2>
    <div class="sub">Viewing user id = <b>${esc(id)}</b></div>
    <div class="hint">💡 <strong>IDOR:</strong> walk the ids — <a href="?id=1">?id=1 (admin)</a> ·
      <a href="?id=2">?id=2</a> · <a href="?id=3">?id=3</a> · <a href="?id=4">?id=4</a>.
      Every profile and plaintext password is readable by any logged-in user.
      (To dump ALL at once via NoSQL injection, see <a href="/search">/search</a>.)</div>
    ${body}</div>`));
});

// ============================================================================
// ADMIN — vertical privilege escalation via client-controlled `role` cookie
// ============================================================================
app.get('/admin', async (req, res) => {
  // VULN: trust the cookie the browser sends, not the server session.
  const role = req.cookies.role || 'user';
  const show = req.query.admin !== undefined;
  let body = '';
  if (show && role === 'admin') {
    const all = await users.find().toArray();
    const rows = all.map((r) => `<tr><td>${r.id}</td><td>${r.username}</td>
      <td>${esc(r.email)}</td><td><code>${esc(r.password)}</code></td>
      <td>$${money(r.balance)}</td><td>${esc(r.account_number)}</td><td>${esc(r.role)}</td></tr>`).join('');
    body = `<div class="msg">✅ Admin access granted (usernames echoed RAW → stored XSS fires here).</div>
      <table><tr><th>id</th><th>username</th><th>email</th><th>password</th><th>balance</th><th>acct</th><th>role</th></tr>${rows}</table>`;
  } else if (show) {
    body = `<div class="err">⛔ Access denied — you are not an admin.</div>
      <p class="sub">Open DevTools → Application → Cookies and set <code>role=admin</code>, then reload.
      The gate trusts a value your browser controls.</p>`;
  } else {
    body = `<p class="sub">The panel is at <code>/admin?admin=1</code>.</p>`;
  }
  res.send(page('Admin', `<div class="card">
    <h2>🛡️ Staff administration</h2>
    <div class="sub">Your role (from cookie): <b>${esc(role)}</b></div>
    ${body}
    <div class="debug"><strong>DEBUG</strong><br>
      Session role: <code>${esc(req.session.role || 'not logged in')}</code><br>
      Cookie role: <code>${esc(req.cookies.role || '(none)')}</code> ← the one the gate checks (bug!)</div></div>`));
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

  res.send(page('Internal Documents', `<div class="card">
    <h2>📄 Internal file viewer</h2>
    <form method="GET"><label>file</label><input name="file" value="${esc(file)}"><button>View</button></form>
    <p class="sub">Quick links:
      <a href="?file=.env">.env</a> ·
      <a href="?file=config.js.bak">config.js.bak</a> ·
      <a href="?file=server.js">server.js source</a> ·
      <a href="?file=../../../../../../etc/passwd">/etc/passwd</a> ·
      <a href="?page=config.js.bak">LFI require() config.js.bak</a></p>
    ${contents !== null ? `<h3 style="color:#f5c04c">File contents (raw)</h3><pre>${esc(contents)}</pre>`
      : err ? `<div class="err">${esc(err)}</div>` : ''}
    ${included !== null ? `<div class="err"><h3>LFI output (?page=)</h3><pre>${esc(included)}</pre></div>` : ''}
    <div class="debug"><strong>DEBUG</strong><br>?file=<code>${esc(file)}</code><br>Resolved: <code>${esc(resolved)}</code></div>
  </div>`));
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
