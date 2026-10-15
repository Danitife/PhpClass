/**
 * ============================================================================
 * BUSINESS LOGIC FLAWS — negative amounts, client-trusted price, overdraft,
 * coupon reuse (Node.js)
 * ============================================================================
 * Node port of logic-flaw.php.  Run:  node demos/logic-flaw.js
 *
 * A logic flaw is a bug where every line of code is "correct" but a business
 * RULE was never written down in code. No injection, no overflow — the app
 * just does exactly what you told it, and what you told it is profitable.
 *
 * ----------------------------------------------------------------------------
 * STEP-BY-STEP: why the vulnerable code below is broken
 * ----------------------------------------------------------------------------
 * 1. Negative amount: transfer never checks amount > 0. The math
 *    `sender -= amount` with a negative amount ADDS money to the sender.
 * 2. Overdraft: no check that the sender actually has the funds, so balances
 *    go negative — money is created from nothing.
 * 3. Client-trusted price: checkout reads the item PRICE from the request
 *    body instead of the server's catalogue, so the buyer names their price.
 * 4. Coupon reuse: the "used" flag is checked but applied non-transactionally
 *    and the discount is trusted from the client too.
 */
import { fileURLToPath } from 'url';

const CATALOG = { book: 1200, laptop: 150000 }; // cents — the source of truth

// ============================================================================
// VULNERABLE
// ============================================================================

/** VULN #1 + #2 — no amount>0 check, no balance check. */
export function transferVuln(accounts, from, to, amount) {
  amount = parseFloat(amount);          // BUG: no "amount > 0"
  accounts[from] -= amount;             // negative amount => sender GAINS
  accounts[to] += amount;               // BUG: no "accounts[from] >= amount"
  return { from: accounts[from], to: accounts[to] };
}

/** VULN #3 + #4 — price and discount trusted from the request. */
export function checkoutVuln(req) {
  const qty = parseInt(req.body.qty, 10);
  const price = parseFloat(req.body.price);          // BUG: client sets the price
  const discount = parseFloat(req.body.discount || 0); // BUG: client sets discount
  return Math.max(0, qty * price - discount);
}

// ============================================================================
// SECURE
// ============================================================================

/** FIX — validate the rule: positive amount, sufficient funds, atomic apply. */
export function transferSecure(accounts, from, to, amount) {
  amount = Number(amount);
  if (!Number.isFinite(amount) || amount <= 0) throw new Error('amount must be > 0');
  if (from === to) throw new Error('cannot transfer to self');
  if (accounts[from] < amount) throw new Error('insufficient funds (no overdraft)');
  accounts[from] -= amount;
  accounts[to] += amount;
  return { from: accounts[from], to: accounts[to] };
}

/** FIX — price comes from the server catalogue; coupon validated server-side. */
export function checkoutSecure(req, coupons) {
  const qty = parseInt(req.body.qty, 10);
  if (!Number.isInteger(qty) || qty < 1) throw new Error('bad quantity');
  const unit = CATALOG[req.body.item];               // price from the SERVER
  if (unit === undefined) throw new Error('unknown item');
  let total = qty * unit;
  const code = req.body.coupon;
  if (code) {
    const c = coupons[code];
    if (!c || c.used) throw new Error('invalid or used coupon');
    c.used = true;                                    // single-use, server-owned
    total = Math.max(0, total - c.amountOff);         // discount from the SERVER
  }
  return total;
}

// ============================================================================
// LIVE DEMO
// ============================================================================
function demo() {
  const line = '─'.repeat(70);
  console.log(`\n${line}\nLOGIC FLAWS — live demo\n${line}`);

  console.log('\n[1] Negative-amount transfer MINTS money:');
  let acc = { bob: 500, mallory: 25 };
  console.log('   before:', JSON.stringify(acc));
  transferVuln(acc, 'bob', 'mallory', -1000); // bob -= -1000  => bob += 1000
  console.log('   vuln  transfer(-1000) → ', JSON.stringify(acc), '❌ bob gained $1000');

  acc = { bob: 500, mallory: 25 };
  try { transferSecure(acc, 'bob', 'mallory', -1000); }
  catch (e) { console.log('   secure transfer(-1000) → blocked:', e.message, '✅'); }
  try { transferSecure(acc, 'bob', 'mallory', 99999); }
  catch (e) { console.log('   secure transfer(99999) → blocked:', e.message, '✅'); }

  console.log('\n[2] Client-trusted price — buy a $1500 laptop for 1 cent:');
  const evilReq = { body: { item: 'laptop', qty: 1, price: 0.01, discount: 0 } };
  console.log('   vuln  checkout → charged', checkoutVuln(evilReq), 'cents ❌');
  console.log('   secure checkout → charged', checkoutSecure(evilReq, {}), 'cents (catalogue price) ✅');

  console.log('\n[3] Coupon reuse:');
  const coupons = { SAVE10: { amountOff: 1000, used: false } };
  const r = { body: { item: 'laptop', qty: 1, coupon: 'SAVE10' } };
  console.log('   secure first use →', checkoutSecure(r, coupons), 'cents');
  try { checkoutSecure(r, coupons); }
  catch (e) { console.log('   secure second use → blocked:', e.message, '✅'); }
  console.log('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) demo();
