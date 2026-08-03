<?php
// Business Logic Flaws Demo — "Buy a product" page
//
// A logic flaw is a bug where the code does EXACTLY what the developer wrote
// but the developer didn't anticipate edge cases that violate the business
// rules. Unlike SQL injection or XSS, the code looks "correct" — there's no
// dangerous function, no unescaped output. The bug is in the THINKING, not
// the syntax. That makes logic flaws nearly invisible to automated scanners.
//
// We don't need the database — this demo uses $_SESSION as a fake wallet.

session_start();

// First visit: seed a wallet of $100 so the user can see the numbers move.
if (!isset($_SESSION['wallet'])) {
    $_SESSION['wallet'] = 100.00;
}
if (!isset($_SESSION['used_coupons'])) {
    $_SESSION['used_coupons'] = [];   // not per-USER — per-CART. That's the bug.
}

// Reset button for the demo.
if (isset($_GET['reset'])) {
    $_SESSION['wallet'] = 100.00;
    $_SESSION['used_coupons'] = [];
    header("Location: logic-flaw.php"); exit;
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------------------------------------
    // VULNERABLE LOGIC #1 — Trusting the hidden price field
    //
    // The product page sends the price in a hidden form input. The server
    // uses it directly to compute the total. An attacker can edit that
    // hidden field in DevTools (or with curl) before submitting.
    // The price MUST come from the database, never the request.
    // ------------------------------------------------------------------
    $price    = (float)$_POST['price'];      // BUG: client-supplied
    $quantity = (int)  $_POST['quantity'];   // BUG: not bounds-checked
    $coupon   =        $_POST['coupon'] ?? '';

    // ------------------------------------------------------------------
    // VULNERABLE LOGIC #2 — No lower bound on quantity
    //
    // Math is math: price * (-3) = a negative total, which when subtracted
    // from the wallet ADDS money. The dev assumed "the form has min=1",
    // forgetting that the form is just a suggestion to the browser.
    // ------------------------------------------------------------------
    $total = $price * $quantity;

    // ------------------------------------------------------------------
    // VULNERABLE LOGIC #3 — Coupon reuse
    //
    // The "used_coupons" list is reset every checkout-session. It does not
    // track WHICH user used WHICH coupon globally. So the same coupon can
    // be applied again after a reset, or by a different browser/session.
    // The real check belongs in the database: "has THIS user redeemed this
    // code?" — and it must be atomic to survive race conditions.
    // ------------------------------------------------------------------ 100 99.99 * 1 50.01
    if ($coupon === 'SAVE50' && !in_array($coupon, $_SESSION['used_coupons'])) {
        $total = $total * 0.5;
        $_SESSION['used_coupons'][] = $coupon;
        $message = "Coupon SAVE50 applied (50% off).";
    }

    $_SESSION['wallet'] -= $total;
    $message = ($message ? $message . " " : "")
             . "Charged \$" . number_format($total, 2)
             . " for {$quantity} × \$" . number_format($price, 2)
             . ". New balance: \$" . number_format($_SESSION['wallet'], 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Buy Product</title>
    <style>
        body  { font-family: sans-serif; max-width: 720px; margin: 2em auto; }
        .card { border:1px solid #ccc; padding:16px; border-radius:6px; }
        .debug { background:#f5f5dc; border:1px solid #999; padding:10px; margin-top:20px; font-size:0.9em; }
        .msg  { background:#e0f0ff; border:1px solid #06c; padding:10px; margin:12px 0; }
        .wallet { font-size:1.4em; color:#063; }
        label { display:block; margin:8px 0 4px; }
        input { padding:4px 6px; }
    </style>
</head>
<body>

<h2>Online Store</h2>
<p class="wallet">Wallet balance: $<?= number_format($_SESSION['wallet'], 2) ?></p>
<p><a href="?reset=1">[reset wallet to $100]</a></p>

<?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
    <h3>Premium Subscription</h3>
    <p>The pro plan with all features. Normally $99.99.</p>

    <form method="POST">
        <!-- BUG: the price lives in the form. Open DevTools and change it. -->
        <input type="hidden" name="price" value="99.99">

        <label>Quantity:
            <!-- BUG: min="1" is enforced ONLY by the browser. Curl ignores it. -->
            <input type="number" name="quantity" value="1" min="1">
        </label>

        <label>Coupon code (try SAVE50):
            <input type="text" name="coupon" value="">
        </label>

        <br>
        <button type="submit">Buy now</button>
    </form>
</div>

<div class="debug">
    <strong>DEBUG</strong><br>
    Wallet: $<?= number_format($_SESSION['wallet'], 2) ?><br>
    Coupons used (this session): <?= htmlspecialchars(implode(', ', $_SESSION['used_coupons']) ?: '(none)') ?>
</div>

</body>
</html>

<?php
/* =============================================================================
   BUSINESS LOGIC FLAWS  —  the bugs that look like "correct" code
   =============================================================================

   A logic flaw is a bug in the rules, not the syntax. The code parses fine,
   passes lint, even passes unit tests of the happy path. But it lets a user
   do something the business never intended:

       - buy a $100 item for $1
       - get refunded for a purchase they never made
       - apply the same single-use coupon forever
       - transfer money from another account
       - skip ahead in a multi-step workflow

   Three classic flaws live in this page. Try each one.

   -----------------------------------------------------------------------------
   EXPLOIT 1 — Hidden price tampering
   -----------------------------------------------------------------------------
   Open DevTools (F12) → Elements. Find:
       <input type="hidden" name="price" value="99.99">
   Change the value attribute to 0.01 (or 0, or -50). Click Buy.
   The server computes total = 0.01 * 1 and charges you one cent.

   Or with curl (no browser needed):
       curl -X POST -d "price=0.01&quantity=1" http://localhost/vunerability/logic-flaw.php

   Why it works: the server trusts a value the client controls. The price
   must be looked up server-side from the product id, not accepted from the
   form.

   -----------------------------------------------------------------------------
   EXPLOIT 2 — Negative quantity (refund abuse)
   -----------------------------------------------------------------------------
   The HTML form says min="1" but that only constrains the BROWSER. Submit
   directly with a negative number:

       curl -X POST -d "price=99.99&quantity=-10" \
            http://localhost/vunerability/logic-flaw.php

   total = 99.99 * (-10) = -999.90
   wallet -= -999.90   →   wallet GOES UP by $999.90.

   Real-world version: a 2018 Shopify report paid out for exactly this — a
   shop's checkout let -1 quantities issue store credit. Amazon, Steam, and
   several airlines have had public variants of this bug.

   Why it works: the developer assumed quantity ≥ 1. The MATH is correct;
   the BUSINESS RULE "you can't buy a negative number of things" was never
   written down in code.

   -----------------------------------------------------------------------------
   EXPLOIT 3 — Coupon reuse
   -----------------------------------------------------------------------------
   Enter the code SAVE50 — it gives 50% off and remembers it was used.
   Now click [reset wallet] (or open a private window). The "used" list
   lives in $_SESSION, not in the database. Apply SAVE50 again. And again.
   And again.

   Why it works: single-use means "this user has not redeemed this code,
   ever, across all of time, across all sessions and devices". The check
   must be in shared persistent storage (the database), and it must be
   ATOMIC — otherwise two concurrent requests can both see "unused" and
   both succeed (a classic race condition).

   -----------------------------------------------------------------------------
   OTHER LOGIC FLAWS TO KNOW
   -----------------------------------------------------------------------------
   - Workflow skipping: jump straight to /checkout/confirm without going
     through /checkout/payment.
   - Currency confusion: the API accepts amount in cents in one endpoint
     and dollars in another. Send $50.00 to the cents endpoint → charged 50¢.
   - TOCTOU (time-of-check vs time-of-use): "do you have enough balance?"
     yes → "deduct balance" — but two requests race between those steps.
   - Mass assignment: POST role=admin to an /update-profile endpoint that
     blindly copies request fields into the user record.
   - Replay: a "claim signup bonus" endpoint with no idempotency key.

   -----------------------------------------------------------------------------
   WHY THIS CODE IS BROKEN
   -----------------------------------------------------------------------------
   1. Price comes from the client. Anything the browser sends is attacker-
      controlled. Hidden fields, cookies, JS-set values, JWT claims — all
      of them.
   2. Quantity has no lower bound. Validate at the server: must be a
      positive integer within a sensible maximum.
   3. Coupon state lives in the session, not in the database. State that
      enforces "single use" must be shared and atomic.

   -----------------------------------------------------------------------------
   SECURE VERSION (sketch)
   -----------------------------------------------------------------------------

       // 1. Take a PRODUCT ID from the form, look up the price server-side.
       $product_id = (int)$_POST['product_id'];
       $stmt = mysqli_prepare($db, "SELECT price FROM products WHERE id=?");
       mysqli_stmt_bind_param($stmt, 'i', $product_id);
       mysqli_stmt_execute($stmt);
       $price = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['price'];

       // 2. Validate quantity strictly.
       $qty = filter_var($_POST['quantity'], FILTER_VALIDATE_INT,
                         ['options' => ['min_range' => 1, 'max_range' => 100]]);
       if ($qty === false) { http_response_code(400); exit('Bad quantity'); }

       // 3. Atomic coupon redemption in the DB.
       //    INSERT IGNORE INTO coupon_redemptions (user_id, code) VALUES (?, ?);
       //    if (mysqli_stmt_affected_rows($stmt) === 0) reject as already-used.

       // 4. Wrap the whole purchase in a transaction so balance checks and
       //    deductions can't race.
============================================================================= */
?>
