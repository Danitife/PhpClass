# Injection Practice Tasks (Beginner-Friendly)

Five hands-on tasks to learn injection, ordered easy → harder. They all run
against your own lab, so start it first:

```bash
cd node_vunreability
npm start                      # http://localhost:3000
```

> **Rules of the game:** this is YOUR lab on localhost. Never try these on a
> site you don't own. Each task has a **Goal**, **Steps**, a **Hint**, how to
> know you **Succeeded**, and a **Now fix it** challenge. Do the fix — that's
> where the real learning is.

---

## Task 1 — Log in as admin without the password ⭐ (easiest)
**You're practicing:** NoSQL *operator injection*.

**Goal:** Get into the admin account on `/login` without knowing `Admin@123`.

**Steps:**
1. Open a terminal and run:
   ```bash
   curl -s -i -H 'Content-Type: application/json' \
     -d '{"email":"admin@bank.local","password":{"$ne":"x"}}' \
     http://localhost:3000/login | grep -i location
   ```
2. Look at the response.

**Hint:** `{"$ne":"x"}` means "password is NOT equal to x" — which is true for
admin, so the password check is skipped.

**You succeeded when:** you see `Location: /` (a redirect to the dashboard =
logged in).

**Now fix it:** open `server.js`, find the `/login` route, and change
`const query = { email, password }` so the password is forced to a string:
`password: String(password)`. Re-run the curl — it should now fail.

---

## Task 2 — Leak every account in one request ⭐⭐
**You're practicing:** NoSQL injection for *bulk data exposure*.

**Goal:** Make one request return ALL users (with their plaintext passwords).

**Steps:**
1. Log in first (Task 1 saves a cookie if you add `-c jar.txt`).
2. Open this URL in your browser:
   ```
   http://localhost:3000/search?filter={"$gt":""}
   ```
   Then also try:
   ```
   http://localhost:3000/search?filter={"$where":"return true"}
   ```

**Hint:** The server passes your `filter` straight into `db.users.find()`.
A filter that is *always true* matches every document.

**You succeeded when:** the page shows a table of all 4 accounts and their
passwords instead of just one.

**Now fix it:** a real search should only ever filter by a known field, like
`{ username: String(req.query.q) }` — never accept a raw filter object from the
user. Rewrite the `/search` route that way and confirm the dump stops.

---

## Task 3 — Spot the flaw and fix it ⭐⭐ (no attacking, just reading)
**You're practicing:** recognising injection in code review.

**Goal:** Read the snippet below and answer two questions.

```js
const { exec } = require('child_process');

function downloadReport(fileName) {
  exec(`curl https://mysite.com/${fileName}`);   // runs a shell command
}
```

**Questions:**
1. If an attacker passes `report.pdf; rm -rf /tmp/demo` as `fileName`, what
   does the computer actually run, and why is that dangerous?
2. Rewrite `downloadReport` safely using `execFile` so the filename can't add
   extra commands.

**Hint:** The `;` lets you chain a second command. `execFile('curl', [url])`
passes arguments as a list, so symbols like `;` are treated as plain text.

**Check your answer:** compare your rewrite with the `execFile` pattern in
`demos/file-exposure.js` and the command-injection example you studied.

---

## Task 4 — Read a secret file off the server (path traversal) ⭐⭐⭐
**You're practicing:** path-traversal injection (stealing files, not data).

**Goal:** Use the file viewer to read the server's secret `.env`.

**Steps:**
1. Visit the viewer normally: `http://localhost:3000/file?file=help.txt`.
2. Now climb out of the folder:
   ```
   http://localhost:3000/file?file=.env
   http://localhost:3000/file?file=../../../../../../etc/hosts
   ```

**Hint:** `../` means "go up one folder." The code glues your input onto a path
with no checks, so you can walk anywhere the server can read.

**You succeeded when:** you see `JWT_SECRET=...` from `.env`, or the contents of
`/etc/hosts`.

**Now fix it:** in the `/file` route, reject anything that isn't on an
allowlist, OR resolve the path and confirm it still starts with the app folder.
The secure pattern is in `demos/file-exposure.js` (`readFileAnchored`). Apply it
and confirm `?file=.env` now returns "Forbidden".

---

## Task 5 — Build your own tiny vulnerable login, then secure it ⭐⭐⭐ (capstone)
**You're practicing:** the whole cycle — build, break, fix — on your own.

**Goal:** Write a 20-line Node script that is injectable, prove it, then fix it.

**Steps:**
1. Make a new folder and script:
   ```bash
   mkdir inj-practice && cd inj-practice && npm init -y
   ```
2. Write `login.js` with an in-memory user list and a `login(email, password)`
   function that uses a MongoDB-style filter object `{ email, password }`.
   (You can simulate `findOne` with `users.find(u => matches(u, filter))`.)
3. Prove it's vulnerable: call `login("admin@x.com", { $ne: "x" })` and show it
   logs in.
4. Fix it: coerce inputs with `String(...)`, or validate that both are strings
   before querying. Show the same attack now fails.

**Hint:** The bug and the fix are the same idea as Task 1 — the difference is
you're writing both sides yourself this time.

**Stretch (optional):** add a `$regex` "blind" guess — loop `^a`, `^b`, `^c`…
against a secret field and watch which prefix returns a match. This is how
attackers extract a secret one character at a time.

---

## Answer key / where to check yourself
- Tasks 1, 2, 4 map to the lab's `/login`, `/search`, `/file` routes — the
  `VULN:` comments in `server.js` explain each bug.
- The secure patterns for every task live in the `demos/*.js` files and the
  **Defence checklist** in `README.md`.
- Want the worked solutions? Ask and I'll add a `SOLUTIONS.md`.
