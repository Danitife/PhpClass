# Business Logic Flaw Practice Tasks (Beginner-Friendly)

Injection is about tricking an *interpreter*. **Logic flaws** are different:
every line of code is "correct," but a business *rule* was never written down —
so the app does exactly what the attacker asks, and that's profitable.

These five tasks mirror the five logic bugs you studied (reward abuse,
fake refund, IDOR, skipped MFA, race condition). Start your lab first:

```bash
cd node_vunreability
npm start                      # http://localhost:3000
```

> Same shape as `practice-tasks.md`: **Goal → Steps → Hint → You succeeded
> when → Now fix it.** The fix is the real lesson. Only ever do this on your
> own lab.

---

## Task 1 — Mint money with a bad amount ⭐ (easiest)
**You're practicing:** missing input validation (`amount > 0`).

**Goal:** Increase your own balance without spending anything.

**Steps:**
1. Log in as bob (operator-injection trick or real password `bob123`):
   ```bash
   curl -s -c jar.txt -H 'Content-Type: application/json' \
     -d '{"email":"bob@bank.local","password":{"$ne":"x"}}' \
     http://localhost:3000/login -o /dev/null
   ```
2. Send a **negative** transfer (bob = account `1000000003`):
   ```bash
   curl -s -b jar.txt -d 'from_account=1000000003&to_account=1000000002&amount=-1000&transfer=1' \
     http://localhost:3000/transfer | grep -o 'Transferred[^.]*'
   ```
3. Reload the dashboard and check bob's balance.

**Hint:** The server does `newBalance = balance - amount`. If `amount` is
negative, subtracting it *adds* money. Nobody checked `amount > 0`.

**You succeeded when:** bob's balance went **up** after the transfer.

**Now fix it:** compare with `transferSecure` in `demos/logic-flaw.js`. Add the
guard `if (amount <= 0) throw ...` (and a balance check) to the `/transfer`
route, then confirm the negative transfer is rejected.

---

## Task 2 — Pay your own price / fake a refund ⭐⭐ (code reading + fix)
**You're practicing:** trusting money values from the client.

**Goal:** Understand why reading a price or refund amount from `req.body` is a
disaster, and fix it.

**Look at this refund route:**
```js
function processRefundVuln(user, req) {
  const claimedPrice = parseFloat(req.body.originalPrice); // trusts the user!
  user.balance += claimedPrice;
  return { newBalance: user.balance };
}
```

**Questions:**
1. What request body would let a user "refund" themselves **$9,999** for a
   $5 item they may never have bought?
2. Rewrite it so the refund amount comes from the server's record of what the
   user actually paid (look up by `itemId` in their purchase history), not from
   `req.body`.

**Hint:** The client should never send monetary values for internal
adjustments. The server already knows the real price — use it.

**Check your answer:** the same idea is shown with `checkoutSecure` in
`demos/logic-flaw.js`, where the price comes from a server-side `CATALOG`, not
the request.

---

## Task 3 — Edit someone else's profile (IDOR) ⭐⭐
**You're practicing:** broken authorization — "logged in" ≠ "allowed."

**Goal:** As a normal user, reset the **admin's** password.

**Steps:**
1. Log in as any user.
2. Open the profile editor but point it at admin (id = 1):
   ```
   http://localhost:3000/profile?id=1
   ```
3. Type a new password and save. Then log in as admin with it.

**Hint:** The route reads `?id=` straight from the URL and never checks that id
belongs to *you*. Authentication was checked; **authorization** wasn't.

**You succeeded when:** you can log in as `admin@bank.local` with the password
you just set.

**Now fix it:** in the `/profile` route, ignore `?id=` for writes and use the
session instead: `const id = req.session.user_id` (allow another id only if the
session role is admin). Pattern: `getProfileSecure` in
`demos/broken-authorization.js`.

---

## Task 4 — Skip a required step (MFA state desync) ⭐⭐⭐ (reason + build)
**You're practicing:** trusting the client to complete steps in order.

**Goal:** See why a "final step" must re-check earlier steps, and fix it.

**Look at this final transfer route:**
```js
function processSensitiveTransactionVuln(req, userProfile) {
  // Step 1 verified the password; Step 2 was supposed to check an MFA code
  const amount = parseFloat(req.body.amount);
  userProfile.balance -= amount;        // BUG: never checks mfa_verified
  return { status: 'Success' };
}
```

**Questions:**
1. How could an attacker reach this route and move money **without** ever
   passing the MFA step? (Think: what stops them from calling the final URL
   directly?)
2. Rewrite the first two lines so the route refuses unless
   `req.session.mfa_verified === true`.

**Hint:** Treat every request as stateless. A sensitive endpoint must
independently confirm *all* prerequisite flags in the session store — it can't
assume "if you got here, you must have done the earlier steps."

**Build it (optional):** add two tiny Express routes — one that sets
`req.session.mfa_verified = true`, and a transfer route that checks it — and
prove the transfer fails when you skip the first route.

---

## Task 5 — Claim a one-time gift ten times (race condition) ⭐⭐⭐
**You're practicing:** TOCTOU — non-atomic check-then-set.

**Goal:** Redeem a "once only" reward multiple times by firing concurrent
requests.

**Steps:**
1. Log in and note your balance.
2. Fire 10 bonus claims at the *same instant*:
   ```bash
   for i in $(seq 1 10); do curl -s -b jar.txt -X POST http://localhost:3000/bonus -o /dev/null & done; wait
   ```
3. Check your balance (the `/bonus` route reads balance, waits, then writes).

**Hint:** The code does `read → (await) → write`. All 10 requests read the
*old* value during the await gap, so deposits get lost — or a "claim once"
flag gets bypassed because every request passed the check before any set it.

**You succeeded when:** the balance change proves the claims overlapped (for
`/bonus`, you gain far less than 10 × $10 — lost updates; for a "claim once"
flag, you'd claim multiple times).

**Now fix it:** use an atomic operation instead of read-modify-write:
```js
// MongoDB: one indivisible step, and only if not already claimed
const r = await users.updateOne(
  { id, hasClaimedGift: { $ne: true } },
  { $set: { hasClaimedGift: true }, $inc: { balance: 50 } }
);
if (r.modifiedCount === 0) throw new Error('already claimed');
```
Compare with `claimBonusSecureAtomic` in `demos/race-condition.js`.

---

## Answer key / where to check yourself
- Tasks 1, 3, 5 run against the lab's `/transfer`, `/profile`, `/bonus` routes —
  see the `VULN:` comments in `server.js`.
- Every secure pattern lives in `demos/logic-flaw.js`,
  `demos/broken-authorization.js`, and `demos/race-condition.js`.
- The golden rule for all five: **amounts, identity, and permissions come from
  the server (session + database), never from the client's request.**
- Want worked solutions? Ask and I'll add them to a `SOLUTIONS.md`.
