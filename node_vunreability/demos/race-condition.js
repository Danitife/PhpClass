/**
 * ============================================================================
 * RACE CONDITION — Time-Of-Check to Time-Of-Use (TOCTOU) (Node.js)
 * ============================================================================
 * Node port of race-condition.php.  Run:  node demos/race-condition.js
 *
 * A race condition is a bug where the result depends on the TIMING of
 * concurrent operations. The classic shape is Read-Modify-Write with no lock:
 *
 *     request A: read balance (0)
 *     request B: read balance (0)      ← both see the same stale value
 *     request A: write 0 + 10 = 10
 *     request B: write 0 + 10 = 10     ← clobbers A; one deposit is LOST
 *
 * "But Node is single-threaded!" — right, and it still races. The event loop
 * runs ONE callback at a time, but `await` yields control. Between the read
 * and the write there is an `await` (a DB round-trip, an API call, any I/O),
 * and the loop happily runs other requests in that gap. They all read the old
 * value, then each writes back a value based on it. Updates are lost.
 *
 * ----------------------------------------------------------------------------
 * STEP-BY-STEP: why the vulnerable code below is broken
 * ----------------------------------------------------------------------------
 * 1. It READS the balance, then `await`s (yields the event loop), then WRITES.
 * 2. Concurrent requests interleave in that await gap and all read the SAME
 *    old balance before any write lands.
 * 3. The write is `balance = oldReadValue + 10` — based on a stale snapshot —
 *    instead of an atomic "increment by 10". The last writer wins; the rest
 *    are lost.
 */
import { fileURLToPath } from 'url';

const delay = (ms) => new Promise((r) => setTimeout(r, ms));

// ============================================================================
// VULNERABLE
// ============================================================================

/** VULN — read, await (yield), compute, write. Non-atomic read-modify-write. */
export async function claimBonusVuln(store, id) {
  const balance = store[id];      // READ (time-of-check)
  await delay(20);                // the gap: concurrent requests interleave here
  store[id] = balance + 10;       // WRITE based on the STALE read (time-of-use)
  return store[id];
}

// ============================================================================
// SECURE
// ============================================================================

/**
 * FIX #1 — a tiny async mutex serialises the critical section so each
 * read-modify-write completes before the next begins (like SELECT ... FOR
 * UPDATE inside a transaction).
 */
function createMutex() {
  let tail = Promise.resolve();
  return (fn) => {
    const run = tail.then(fn, fn);
    tail = run.catch(() => {});
    return run;
  };
}
const lock = createMutex();
export async function claimBonusSecureLocked(store, id) {
  return lock(async () => {
    const balance = store[id];
    await delay(20);
    store[id] = balance + 10;
    return store[id];
  });
}

/**
 * FIX #2 (better) — an ATOMIC increment, no read-modify-write at all. In Mongo:
 *     users.updateOne({ id }, { $inc: { balance: 10 } })
 * In SQL:
 *     UPDATE users SET balance = balance + 10 WHERE id = ?
 * Here we model it with a synchronous += (no await gap => indivisible in Node).
 */
export function claimBonusSecureAtomic(store, id) {
  store[id] += 10; // single synchronous op — nothing can interleave
  return store[id];
}

// ============================================================================
// LIVE DEMO
// ============================================================================
async function demo() {
  const line = '─'.repeat(70);
  const N = 10;
  console.log(`\n${line}\nRACE CONDITION — live demo (${N} concurrent $10 claims; honest total = $${N * 10})\n${line}`);

  let store = { bob: 0 };
  await Promise.all(Array.from({ length: N }, () => claimBonusVuln(store, 'bob')));
  console.log(`\n[1] VULNERABLE: fired ${N} concurrent claims → balance $${store.bob}`);
  console.log(`    ❌ lost ${(N * 10 - store.bob) / 10} of ${N} deposits to the race`);

  store = { bob: 0 };
  await Promise.all(Array.from({ length: N }, () => claimBonusSecureLocked(store, 'bob')));
  console.log(`\n[2] SECURE (mutex):  balance $${store.bob}  ${store.bob === N * 10 ? '✅ all deposits kept' : '⚠️'}`);

  store = { bob: 0 };
  await Promise.all(Array.from({ length: N }, () => Promise.resolve(claimBonusSecureAtomic(store, 'bob'))));
  console.log(`[3] SECURE (atomic): balance $${store.bob}  ${store.bob === N * 10 ? '✅ all deposits kept' : '⚠️'}`);
  console.log('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) demo();
