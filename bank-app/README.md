# 🏦 Bank of VULN — deliberately vulnerable banking lab

A training bank that reproduces **every** vulnerability demonstrated in the
parent `vunerability/` folder, unified into one believable app.

> ⚠️ **Lab only.** Run it on `localhost` (XAMPP), never on a public server.
> Every file here is intentionally broken for security education.

## Quick start (XAMPP)

1. Start Apache + MySQL from the XAMPP control panel.
2. **Provision the MySQL database + user once** (see *Deploy to MySQL* below),
   or just hit any page and the app self-bootstraps as `bank_app`.
3. Open the dashboard:

   ```
   http://localhost/vunerability/bank-app/index.php
   ```

4. Read the full attack playbook: **`/vunerability/vunerability-explore.md`**.

## Deploy to MySQL (the database the code actually uses)

The app connects with a **dedicated MySQL user** (`bank_app`), not `root`.
Credentials are read from `.env` at runtime. Provision the database/user once:

```bash
# XAMPP (run from this folder):
/Applications/XAMPP/xamppfiles/bin/mysql -u root < setup-mysql.sql

# any other MySQL server:
mysql -h <host> -u <admin-user> -p < setup-mysql.sql
```

This creates:

| Item | Value |
|------|-------|
| Database | `vulnerable_bank` |
| User | `bank_app` (grants limited to `vulnerable_bank.*`, plus `CREATE` for self-bootstrap) |
| Password | `BankVuln@2026!` |
| Hosts | `localhost` and `127.0.0.1` |

**Change the credentials?** Edit **both** `setup-mysql.sql` and `.env`
(and `config.php.bak` is the intentionally-leaked mirror of them), then
re-run the setup script.

**Deploying to a remote MySQL / different host?** Edit `.env`:

```ini
DB_HOST=db.example.com
DB_PORT=3306
DB_USER=bank_app
DB_PASS=YourNewPassword
DB_NAME=vulnerable_bank
```

First request still self-bootstraps the tables + seed data if they're missing.

> 💡 The leaked `.env` / `config.php.bak` / `backup.sql` are **not** fake —
> they contain the real `bank_app` credentials. That's the whole point of the
> dev-file-leak exercise (§13 in the playbook).

> ⚠️ **Write permission (required for a few demos).** Apache runs as the
> `daemon` user. The login rate-limiter (`.rate_state.json`), the sniffer
> (`stolen.txt`), etc. write files into this folder, so make it writable once:
>
> ```bash
> chmod 777 /Applications/XAMPP/xamppfiles/htdocs/vunerability/bank-app
> ```
>
> Without this, the rate-limit and cookie-stealing demos will silently no-op.

## Seed accounts

| id | username  | email            | password   | role  | balance    | account #  |
|----|-----------|------------------|------------|-------|------------|------------|
| 1  | admin     | admin@bank.local | Admin@123  | admin | $999,999.99| 1000000001 |
| 2  | alice     | alice@bank.local | alice123   | user  | $1,000.00  | 1000000002 |
| 3  | bob       | bob@bank.local   | bob123     | user  | $500.00    | 1000000003 |
| 4  | mallory   | mallory@bank.local | mallory123 | user | $25.00   | 1000000004 |

## Pages & the vulnerability each carries

| Page             | Vulnerability(ies)                                    | Source demo file             |
|------------------|-------------------------------------------------------|------------------------------|
| `login.php`      | SQLi auth bypass, error-based SQLi, plaintext pw, broken rate limit (XFF), client-trusted role cookie | `login.php`, `injection.php`, `rate-limit.php`, `ip.php`, `broken-authorization.php` |
| `register.php`   | SQLi INSERT, plaintext pw, stored XSS                 | `signup.php`                 |
| `index.php`      | Stored XSS (raw username echo), session cookie not HttpOnly | `signup.php`            |
| `transfer.php`   | IDOR (hidden from-account), negative amount, overdraft, TOCTOU race, SQLi | `broken-authorization.php`, `logic-flaw.php`, `race-condition.php` |
| `bonus.php`      | TOCTOU read-modify-write race (lost updates)          | `race-condition.php`         |
| `profile.php`    | IDOR (edit any user), password reset without auth, SQLi, stored XSS | `broken-authorization.php`, `signup.php` |
| `view-account.php` | IDOR (read any profile incl. password)              | `broken-authorization.php`   |
| `admin.php`      | Vertical privilege escalation via cookie tampering, stored XSS sink | `broken-authorization.php` |
| `file.php`       | Path traversal, LFI → source disclosure / RCE         | `file-exposure.php`          |
| `api.php`        | JWT `alg:none`, leaked secret, no expiry              | `check.php`, `dev-file-leak.php` |
| `sniffer.php`    | (helper) cookie exfiltration target for the XSS lab   | —                            |

## Deliberately leaked files

| File              | What it gives away                                 |
|-------------------|----------------------------------------------------|
| `.env`            | DB creds, `JWT_SECRET`, Stripe/SendGrid/Twilio keys, admin password |
| `config.php.bak`  | same secrets in a "backup config"                   |
| `backup.sql`      | full DB dump with plaintext passwords               |
| `schema.sql`      | schema reference (documentation, not a leak)        |

Reset anything anytime: delete the `users`/`transactions` rows or drop the
`vulnerable_bank` database — it re-seeds on next request.
