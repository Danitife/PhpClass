/**
 * views.js — all HTML rendering for Node Bank of VULN.
 * ============================================================================
 * Kept separate from server.js so the route logic (and its vulnerability
 * comments) stay readable. Each page has its own render function.
 *
 * NOTE ON XSS: some fields are deliberately echoed RAW (no esc()) to preserve
 * the stored-XSS bug — those spots are marked `VULN: raw echo`. Everything
 * else is escaped via esc().
 */

// ---------- helpers ----------
export const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export const money = (n) =>
  Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ---------- shared chrome ----------
const CSS = `
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #0b1526; color: #e8eef7; margin: 0; }
  .nav { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; padding: 16px; background: #0f1a2f; border-bottom: 1px solid #22355a; }
  .nav a { color: #f5c04c; text-decoration: none; font-size: .9em; }
  .nav a:hover { text-decoration: underline; }
  .wrap { max-width: 760px; margin: 2vh auto; padding: 0 16px; }
  .brand { text-align: center; font-size: 1.6em; font-weight: 700; color: #f5c04c; margin: 18px 0; }
  .card { background: #12203a; border: 1px solid #22355a; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
  h2 { margin: 0 0 6px; }
  .sub { color: #9fb3d1; font-size: .85em; margin-bottom: 14px; }
  .hint { background: #1c2c4d; border-left: 3px solid #f5c04c; padding: 10px 12px; font-size: .82em; border-radius: 4px; margin: 12px 0; }
  .hint code, .debug code { background: #0b1526; padding: 1px 5px; border-radius: 3px; color: #7ee08f; }
  .msg { background: #143a16; border: 1px solid #3f9e4f; color: #b8e6bd; padding: 10px; border-radius: 6px; margin: 12px 0; font-size: .9em; }
  .err { background: #3a1620; border: 1px solid #ff6b6b; color: #ffb3b3; padding: 10px; border-radius: 6px; margin: 12px 0; font-size: .9em; }
  label { display: block; margin: 12px 0 4px; font-size: .85em; color: #9fb3d1; }
  input { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 6px; border: 1px solid #2c4270; background: #0b1526; color: #fff; }
  button { width: 100%; padding: 11px; margin-top: 16px; background: #1f6feb; color: #fff; border: none; border-radius: 6px; font-size: 1em; cursor: pointer; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border-bottom: 1px solid #22355a; padding: 8px; font-size: .84em; text-align: left; }
  th { color: #f5c04c; }
  td code { color: #ff8a8a; }
  .balance-card { background: linear-gradient(135deg, #1f6feb, #12315e); border-radius: 12px; padding: 22px; margin: 16px 0; }
  .balance { font-size: 2.2em; font-weight: 700; }
  .debug { background: #0a101d; border: 1px solid #2c4270; padding: 10px; margin-top: 16px; font-size: .78em; border-radius: 6px; color: #9fb3d1; }
  pre { background: #0a101d; border: 1px solid #2c4270; color: #7ee08f; padding: 12px; border-radius: 6px; overflow: auto; max-height: 420px; font-size: .8em; }
`;

const NAV = `
  <div class="nav">
    <a href="/">Dashboard</a>
    <a href="/transfer">Transfer</a>
    <a href="/bonus">Claim Bonus</a>
    <a href="/profile">Profile</a>
    <a href="/view-account">Browse Accounts</a>
    <a href="/admin?admin=1">Admin</a>
    <a href="/file">File viewer</a>
    <a href="/logout">Logout</a>
  </div>`;

/** Wrap a page body in the shared HTML shell. */
export const page = (title, body, nav = true) => `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bank of VULN — ${esc(title)}</title>
  <style>${CSS}</style>
</head>
<body>
  ${nav ? NAV : ''}
  <div class="wrap">
    <div class="brand">🏦 Node Bank of VULN</div>
    ${body}
  </div>
</body>
</html>`;

// ============================================================================
// Page views
// ============================================================================

export function renderLogin({ message, xff, clientIp, attempts, lastQuery }) {
  const body = `
    <div class="card">
      <h2>Customer Login</h2>
      <div class="hint">
        💡 <strong>NoSQL auth bypass</strong> — send JSON:<br>
        <code>{"email":"admin@bank.local","password":{"$ne":"x"}}</code><br>
        or form fields: <code>email=admin@bank.local</code> &amp; <code>password[$ne]=x</code><br>
        or match the FIRST user (admin): <code>email[$gt]=</code> &amp; <code>password[$gt]=</code>
      </div>
      ${message ? `<div class="err">${esc(message)}</div>` : ''}
      <form method="POST">
        <label>Email</label>
        <input name="email" placeholder="alice@bank.local">
        <label>Password</label>
        <input name="password" type="password">
        <button name="login" value="1">Sign in</button>
      </form>
      <p class="sub"><a href="/register" style="color:#f5c04c">Open an account →</a></p>
      <div class="debug">
        <strong>DEBUG</strong><br>
        X-Forwarded-For: <code>${esc(xff || '-')}</code><br>
        Rate-limit key: <code>${esc(clientIp)}</code> &nbsp; attempts: ${attempts}/3<br>
        Last filter: <code>${esc(lastQuery)}</code>
      </div>
    </div>`;
  return page('Login', body, false);
}

export function renderSearch({ rawFilter, err, used, results }) {
  const rows = results
    .map(
      (u) => `
        <tr>
          <td>${u.id}</td>
          <td>${esc(u.username)}</td>
          <td>${esc(u.email)}</td>
          <td><code>${esc(u.password)}</code></td>
          <td>$${money(u.balance)}</td>
          <td>${esc(u.role)}</td>
        </tr>`
    )
    .join('');
  const table = results.length
    ? `<table>
         <tr><th>id</th><th>username</th><th>email</th><th>password</th><th>balance</th><th>role</th></tr>
         ${rows}
       </table>`
    : '';
  const body = `
    <div class="card">
      <h2>🔎 Account search</h2>
      <div class="hint">
        💡 <strong>NoSQL injection:</strong> the <code>filter</code> query param is JSON passed
        straight to <code>db.users.find()</code>.<br>
        Try <code>?filter={"$where":"return true"}</code> to dump every account + plaintext password.
      </div>
      <form method="GET">
        <label>filter (JSON)</label>
        <input name="filter" value='${esc(rawFilter ?? '{"$where":"return true"}')}'>
        <button>Search</button>
      </form>
      ${err ? `<div class="err">${esc(err)}</div>` : ''}
      ${table}
      <div class="debug">
        <strong>DEBUG</strong><br>
        filter used: <code>${esc(used)}</code> &nbsp; matched: ${results.length}
      </div>
    </div>`;
  return page('Search', body);
}

export function renderRegister({ message }) {
  const body = `
    <div class="card">
      <h2>Open an Account</h2>
      <div class="hint">
        💡 <strong>Stored XSS:</strong> register with username
        <code>&lt;script&gt;alert(document.cookie)&lt;/script&gt;</code> — it is stored raw and
        echoed raw on the dashboard and in the admin table.
      </div>
      ${message ? `<div class="msg">${message}</div>` : ''}
      <form method="POST">
        <label>Username</label>
        <input name="username">
        <label>Full name</label>
        <input name="full_name">
        <label>Email</label>
        <input name="email">
        <label>Password</label>
        <input name="password" type="password">
        <button name="register" value="1">Create account</button>
      </form>
    </div>`;
  return page('Register', body, false);
}

export function renderDashboard({ me, txns }) {
  const rows = txns
    .map((t) => {
      const inbound = t.to_account === me.account_number;
      const color = inbound ? '#7ee08f' : '#ff8a8a';
      const sign = inbound ? '+' : '-';
      return `
        <tr>
          <td>${esc(t.from_account)}</td>
          <td>${esc(t.to_account)}</td>
          <td style="color:${color}">${sign}$${money(t.amount)}</td>
          <td>${esc(t.note)}</td>
        </tr>`;
    })
    .join('');
  const txnTable = txns.length
    ? `<table>
         <tr><th>From</th><th>To</th><th>Amount</th><th>Note</th></tr>
         ${rows}
       </table>`
    : '<p class="sub">No transactions yet. Try a transfer!</p>';
  const body = `
    <div class="card">
      <!-- VULN: raw username echo -> stored XSS fires here -->
      <h2>Welcome back, ${me.username} 👋</h2>
      <div class="sub">Account # ${esc(me.account_number)} · ${esc(me.email)}</div>
      <div class="balance-card">
        <div>Available balance</div>
        <div class="balance">$${money(me.balance)}</div>
      </div>
      <h3 style="color:#f5c04c;font-size:1em">Recent transactions</h3>
      ${txnTable}
    </div>`;
  return page('Dashboard', body);
}

export function renderTransfer({ me, message, isErr }) {
  const body = `
    <div class="card">
      <h2>💸 Transfer funds</h2>
      <div class="sub">Your account: <b>${esc(me.account_number)}</b> · Balance: $${money(me.balance)}</div>
      <div class="hint">
        💡 <strong>IDOR:</strong> the <em>from</em> field is hidden — edit it in DevTools to another
        account (admin's <code>1000000001</code>) to move <em>their</em> money.<br>
        💡 <strong>Negative amount:</strong> send <code>-500</code> to <em>gain</em> money.
      </div>
      ${message ? `<div class="${isErr ? 'err' : 'msg'}">${message}</div>` : ''}
      <form method="POST">
        <label>From account (hidden — tamper in DevTools)</label>
        <input type="hidden" name="from_account" value="${esc(me.account_number)}">
        <label>To account number</label>
        <input name="to_account" value="1000000002">
        <label>Amount</label>
        <input name="amount" type="number" value="10" step="0.01">
        <label>Note</label>
        <input name="note">
        <button name="transfer" value="1">Transfer</button>
      </form>
    </div>`;
  return page('Transfer', body);
}

export function renderBonus({ me, message, port }) {
  const body = `
    <div class="card">
      <h2>🎁 Daily $10 bonus</h2>
      <div class="balance-card">
        <div>Balance</div>
        <div class="balance">$${money(me.balance)}</div>
      </div>
      <div class="hint">
        💡 One click is fine — that is NOT the race. Fire many at once:
        <pre># log in, saving the session cookie
curl -s -c jar.txt -H 'Content-Type: application/json' \\
  -d '{"email":"bob@bank.local","password":{"$ne":"x"}}' http://localhost:${port}/login -o /dev/null
# 10 concurrent claims — balance will NOT rise by $100
for i in $(seq 1 10); do curl -s -b jar.txt -X POST http://localhost:${port}/bonus -o /dev/null & done; wait</pre>
      </div>
      ${message ? `<div class="msg">${esc(message)}</div>` : ''}
      <form method="POST"><button name="claim" value="1">Claim $10</button></form>
    </div>`;
  return page('Bonus', body);
}

export function renderProfileMissing({ id }) {
  return page('Profile', `<div class="card"><div class="err">No user with id=${esc(id)}</div></div>`);
}

export function renderProfile({ id, user, message, isErr, sessionUserId }) {
  const body = `
    <div class="card">
      <h2>🛠️ Edit profile</h2>
      <div class="sub">Editing user id = <b>${esc(id)}</b> (taken from <code>?id=</code>)</div>
      <div class="hint">
        💡 <strong>IDOR:</strong> visit <code>/profile?id=1</code> and reset the <em>admin's</em>
        password with no old-password check, then log in as admin. 😈
      </div>
      ${message ? `<div class="${isErr ? 'err' : 'msg'}">${esc(message)}</div>` : ''}
      <form method="POST">
        <label>Username (echoed raw → stored XSS)</label>
        <input name="username" value="${user.username}"> <!-- VULN: raw echo -->
        <label>Email</label>
        <input name="email" value="${esc(user.email)}">
        <label>New password (no current-password check!)</label>
        <input name="new_password" type="password">
        <button name="save" value="1">Save changes</button>
      </form>
      <div class="debug">
        <strong>DEBUG</strong><br>
        Session user id: ${esc(sessionUserId)}<br>
        Editing user id: ${esc(id)} (any difference = IDOR)<br>
        Current password: <code>${esc(user.password)}</code>
      </div>
    </div>`;
  return page('Profile', body);
}

export function renderViewAccount({ id, user }) {
  const details = user
    ? `<table>
         ${Object.entries(user)
           .filter(([k]) => k !== '_id')
           .map(([k, v]) => {
             const cell = k === 'password' ? `<code>${esc(v)}</code> (plaintext!)` : esc(v);
             return `<tr><th>${esc(k)}</th><td>${cell}</td></tr>`;
           })
           .join('')}
       </table>`
    : `<div class="err">No user found with id = ${esc(id)}</div>`;
  const body = `
    <div class="card">
      <h2>👀 Customer lookup</h2>
      <div class="sub">Viewing user id = <b>${esc(id)}</b></div>
      <div class="hint">
        💡 <strong>IDOR:</strong> walk the ids —
        <a href="?id=1">?id=1 (admin)</a> ·
        <a href="?id=2">?id=2</a> ·
        <a href="?id=3">?id=3</a> ·
        <a href="?id=4">?id=4</a>.
        Every profile and plaintext password is readable by any logged-in user.
        (To dump ALL at once via NoSQL injection, see <a href="/search">/search</a>.)
      </div>
      ${details}
    </div>`;
  return page('Customer Lookup', body);
}

export function renderAdmin({ role, show, allUsers, sessionRole, cookieRole }) {
  let inner = '';
  if (show && role === 'admin') {
    const rows = allUsers
      .map(
        (r) => `
        <tr>
          <td>${r.id}</td>
          <td>${r.username}</td> <!-- VULN: raw echo -->
          <td>${esc(r.email)}</td>
          <td><code>${esc(r.password)}</code></td>
          <td>$${money(r.balance)}</td>
          <td>${esc(r.account_number)}</td>
          <td>${esc(r.role)}</td>
        </tr>`
      )
      .join('');
    inner = `
      <div class="msg">✅ Admin access granted (usernames echoed RAW → stored XSS fires here).</div>
      <table>
        <tr><th>id</th><th>username</th><th>email</th><th>password</th><th>balance</th><th>acct</th><th>role</th></tr>
        ${rows}
      </table>`;
  } else if (show) {
    inner = `
      <div class="err">⛔ Access denied — you are not an admin.</div>
      <p class="sub">
        Open DevTools → Application → Cookies and set <code>role=admin</code>, then reload.
        The gate trusts a value your browser controls.
      </p>`;
  } else {
    inner = `<p class="sub">The panel is at <code>/admin?admin=1</code>.</p>`;
  }
  const body = `
    <div class="card">
      <h2>🛡️ Staff administration</h2>
      <div class="sub">Your role (from cookie): <b>${esc(role)}</b></div>
      ${inner}
      <div class="debug">
        <strong>DEBUG</strong><br>
        Session role: <code>${esc(sessionRole || 'not logged in')}</code><br>
        Cookie role: <code>${esc(cookieRole || '(none)')}</code> ← the one the gate checks (bug!)
      </div>
    </div>`;
  return page('Admin', body);
}

export function renderFile({ file, resolved, contents, err, included }) {
  const fileBlock =
    contents !== null
      ? `<h3 style="color:#f5c04c">File contents (raw)</h3><pre>${esc(contents)}</pre>`
      : err !== null
      ? `<div class="err">${esc(err)}</div>`
      : '';
  const lfiBlock =
    included !== null ? `<div class="err"><h3>LFI output (?page=)</h3><pre>${esc(included)}</pre></div>` : '';
  const body = `
    <div class="card">
      <h2>📄 Internal file viewer</h2>
      <form method="GET">
        <label>file</label>
        <input name="file" value="${esc(file)}">
        <button>View</button>
      </form>
      <p class="sub">Quick links:
        <a href="?file=.env">.env</a> ·
        <a href="?file=config.js.bak">config.js.bak</a> ·
        <a href="?file=server.js">server.js source</a> ·
        <a href="?file=../../../../../../etc/passwd">/etc/passwd</a> ·
        <a href="?page=config.js.bak">LFI require() config.js.bak</a>
      </p>
      ${fileBlock}
      ${lfiBlock}
      <div class="debug">
        <strong>DEBUG</strong><br>
        ?file=<code>${esc(file)}</code><br>
        Resolved: <code>${esc(resolved)}</code>
      </div>
    </div>`;
  return page('Internal Documents', body);
}
