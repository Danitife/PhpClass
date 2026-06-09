<?php
// Race Condition Demo — Time-Of-Check vs Time-Of-Use (TOCTOU)
//
// WHAT IS A RACE CONDITION?
// A race condition is a bug where the final result depends on the TIMING and
// ORDER of concurrent events. Two or more requests execute at the same time
// and interleave in a way that breaks assumptions. The outcome is a "race"
// — whoever finishes first (or last) wins, and the result is unpredictable.
//
// WHY DO THEY HAPPEN?
// Modern servers handle multiple requests concurrently (in parallel). The OS
// schedules them on different CPU cores or threads. The dev *thinks* their
// code runs atomically — read value, modify, write value — but the OS can
// pause a thread between ANY two CPU instructions. A second thread starts,
// reads the (now stale) value, both threads modify the same old value, both
// write it back, and one update is lost.
//
// CLASSIC PATTERN — Read-Modify-Write:
//   Thread A: read balance (10)
//   Thread B: read balance (10)           ← both see same old value
//   Thread A: compute new balance (10+5 = 15)
//   Thread B: compute new balance (10+5 = 15)   ← same computation
//   Thread A: write balance (15)
//   Thread B: write balance (15)          ← overwrites A, lost update
//
//   Expected result: 20 (two deposits). Actual: 15 (one deposit lost).
//
// WHY ARE THEY HARD TO CATCH?
// The bug is INTERMITTENT and requires CONCURRENT requests. When you click a
// button once, the code runs serially — no race. But send TWO requests at the
// EXACT SAME TIME (milliseconds apart), and they'll interleave. So devs test
// locally with single clicks, it works fine, they ship it. Then it fails in
// production when multiple users hit the endpoint at once. It's nearly
// invisible in code review and impossible to catch with simple testing.
//
// IMPACT IN THE REAL WORLD:
//   - Banking: two deposits both see the old balance, one is lost
//   - E-commerce: coupon counted twice, inventory goes negative
//   - Messaging: duplicate email sent to the user
//   - Oauth: two concurrent token-exchange requests both succeed
//   - Ride-sharing: two drivers claim the same ride
//
// This demo uses file-based storage (intentionally fragile) so you can
// REPRODUCE and SEE the race. In production you'd use a database with
// transactions, atomic operations, or a real cache like Redis.

$store_path = sys_get_temp_dir() . '/vuln_race_condition.json';

function load_users($path)
{
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}
function save_users($path, $users)
{
    file_put_contents($path, json_encode($users));
}

if (isset($_GET['reset'])) {
    @unlink($store_path);
    header('Location: race-condition.php');
    exit;
}

// Simulate a logged-in user (in real code this would be $_SESSION['user_id']).
$user_id = 'student_' . substr(md5($_SERVER['REMOTE_ADDR']), 0, 8);

$users = load_users($store_path);
if (!isset($users[$user_id])) {
    $users[$user_id] = ['balance' => 0, 'last_claim' => null];
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ------------------------------------------------------------------
    // VULNERABLE LOGIC — Time-of-check vs time-of-use race condition
    //
    // This is the classic TOCTOU bug. We check, then act. But between
    // the check and the act, another request can slip through.
    //
    // Step 1: READ the current state from storage
    // Step 2: CHECK if the user qualifies (haven't claimed today)
    // Step 3: COMPUTE the new state (balance + 10)
    // Step 4: WRITE the new state back
    //
    // A second concurrent request can enter at Step 1 while the first is
    // still at Step 3, and they race to write back different states.
    // Whoever writes last wins. The other's update is lost.
    // ------------------------------------------------------------------

    $today = date('Y-m-d');

    // Step 1 & 2: READ and CHECK
    if ($users[$user_id]['last_claim'] === $today) {
        $message = "Already claimed today. Come back tomorrow.";
    } else {
        // Step 3: COMPUTE the new state
        $users[$user_id]['balance'] += 10;
        $users[$user_id]['last_claim'] = $today;

        // Step 4: WRITE the new state
        save_users($store_path, $users);

        $message = "Bonus claimed! +$10. New balance: $" . $users[$user_id]['balance'];
    }
}

// Refresh state for display (in case another request changed it)
$users = load_users($store_path);
$current_user = $users[$user_id] ?? ['balance' => 0, 'last_claim' => null];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Daily Bonus (race condition)</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 720px;
            margin: 2em auto;
        }

        .card {
            border: 1px solid #ccc;
            padding: 16px;
            border-radius: 6px;
        }

        .debug {
            background: #f5f5dc;
            border: 1px solid #999;
            padding: 10px;
            margin-top: 20px;
            font-size: 0.9em;
        }

        .msg {
            padding: 10px;
            margin: 12px 0;
            border-radius: 4px;
        }

        .ok {
            background: #dfd;
            border: 1px solid #393;
        }

        .bad {
            background: #fdd;
            border: 1px solid #c33;
        }

        .balance {
            font-size: 1.4em;
            color: #063;
        }
    </style>
</head>

<body>

    <h2>Daily Bonus (claim once per day)</h2>
    <p class="balance">Your balance: $<?= htmlspecialchars((int)$current_user['balance']) ?></p>
    <p>Last claimed: <?= htmlspecialchars($current_user['last_claim'] ?? 'never') ?></p>

    <?php if ($message): ?>
        <div class="msg <?= strpos($message, 'Already') !== false ? 'bad' : 'ok' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <button type="submit">Claim Daily Bonus ($10)</button>
        </form>
    </div>

    <p style="font-size:0.9em; color:#666;">
        <strong>To trigger the race condition:</strong><br>
        <strong>Single click = works fine</strong> (that's not the race). You need
        <strong>multiple requests SIMULTANEOUSLY</strong> to trigger it.<br><br>
        <strong>Option 1 (bash/curl):</strong> Copy the bash loop from DEBUG section
        and run it in your terminal. This spawns 10 requests in parallel.<br><br>
        <strong>Option 2 (DevTools):</strong> Open DevTools (F12) → Network.
        Quickly click the button 3+ times. If two requests *overlap in time*
        (both still processing while the other is reading), you might see
        balance = $20 instead of $30 (a lost update).
    </p>

    <p><a href="?reset=1">[reset all balances]</a></p>

    <div class="debug">
        <strong>DEBUG</strong><br>
        Your user ID: <code><?= htmlspecialchars($user_id) ?></code><br>
        Store file : <code><?= htmlspecialchars($store_path) ?></code><br>
        Current balance: $<?= htmlspecialchars((int)$current_user['balance']) ?><br>
        <br>
        <strong>⚡ RELIABLE WAY TO TRIGGER THE RACE (use curl):</strong><br>
        <p style="color:#c00; font-weight:bold;">Open a terminal and run this command:</p>
        <code style="display:block; white-space:pre-wrap; background:#eee; padding:8px; margin-top:8px; font-family:monospace;">
for i in {1..10}; do
  curl -X POST http://localhost/vunerability/race-condition.php &
done; wait

# Then refresh this page to see the balance.
# Expected: $100 (10 requests × $10)
# Actual: often $50–$80 due to lost updates from the race condition.
# Run it multiple times — you'll see different totals each time!
        </code>
        <p style="font-size:0.85em; color:#666; margin-top:8px;">
            The <code>&</code> spawns 10 requests in parallel (concurrently).
            The <code>wait</code> pauses until all finish. This forces them to
            interleave and trigger the race. Clicking the button manually is too
            slow — there's too much time between clicks for a race to occur.
        </p>
    </div>

</body>

</html>

<?php
/* =============================================================================
   RACE CONDITIONS  —  when parallel requests collide
   =============================================================================

   A race condition happens when two or more concurrent requests access shared
   state without synchronization, and the final result depends on which request
   finishes first — a "race" to the finish. If the code assumes a single
   request, it breaks under concurrency.

   Classic patterns:
       - Check + Act (check balance, then deduct) — check can be stale
       - Read-Modify-Write (read counter, increment, write back) — lost updates
       - Lazy initialization (if not exists, create) — creates duplicates
       - Two-phase operations (mark as deleted, then delete) — orphaned state

   This page demonstrates the most common one: read-modify-write.

   -----------------------------------------------------------------------------
   EXPLOIT 1 — Rapid clicks
   -----------------------------------------------------------------------------
   Just click the "Claim Daily Bonus" button 3+ times as fast as your mouse
   allows. Each click is a separate HTTP request. If two or more arrive while
   the server is processing the first, they'll interleave.

   Expected: button is disabled after the first click (because today's date was
   set). Actual: sometimes you'll claim multiple bonuses in a single pageload.

   Why: between the time request A reads "last_claim = null" and writes
   "last_claim = today", request B has already read the same "null" state and
   is racing to write back. Whoever writes last overwrites the other's data.

   Symptom: your balance goes up by more than $10, or the "last claimed"
   timestamp is wrong.

   -----------------------------------------------------------------------------
   EXPLOIT 2 — Parallel curl requests
   -----------------------------------------------------------------------------
   The race window is tiny (milliseconds) so browser clicks might not trigger
   it reliably. But parallel HTTP requests *will*. Run this bash script:

       for i in {1..10}; do
         curl -X POST http://localhost/vunerability/race-condition.php &
       done; wait

   This spawns 10 curl processes simultaneously, all hitting the same endpoint.
   They all start reading at nearly the same time.

   Expected result: $100 balance (10 requests × $10).
   Actual result: often $50–$80. The missing $10–50 is lost due to races.

   Example interleaving that loses an update:

       T1: read state (balance: 0, last_claim: null)
       T2: read state (balance: 0, last_claim: null)    ← both see same state
       T1: compute balance := 0 + 10 = 10
       T2: compute balance := 0 + 10 = 10                ← same computation
       T1: write (balance: 10, last_claim: 2026-06-01)
       T2: write (balance: 10, last_claim: 2026-06-01)   ← overwrites T1!

       Result: balance = 10, not 20. T1's update was lost.

   Run the 10-curl loop 5 times and keep score. You'll see different totals
   each time due to the randomness of the race.

   -----------------------------------------------------------------------------
   EXPLOIT 3 — Race conditions on check + act
   -----------------------------------------------------------------------------
   Same idea, slightly different code:

       if (not_deleted) {
           do_something();
           delete();
       }

   If two requests read not_deleted=true concurrently, both can call
   do_something(), leading to:
       - Sending two emails instead of one
       - Processing a payment twice
       - Sending a notification twice
       - Charging the user twice

   Real-world example: Uber once had a race where two concurrent requests both
   thought they were the first to assign a driver to a ride. Both drivers
   accepted the same ride.

   ------- -------

   EXPLOIT 4 — Database-level races (if your code uses a DB)
   -------

   Even with a database, this pattern is racy:

       $row = db.query("SELECT balance FROM accounts WHERE id=?");
       $new = $row['balance'] + 10;
       db.query("UPDATE accounts SET balance=? WHERE id=?", [$new, id]);

   Two database clients run concurrently, both read 100, both compute 110,
   both write 110. Lost update.

   Atomic increment fixes it:

       UPDATE accounts SET balance = balance + 10 WHERE id = ?;
       -- The database ensures this read-and-write happens indivisibly.

   Or use a transaction with row-level locking:

       BEGIN; LOCK IN SHARE MODE; read balance; UPDATE; COMMIT;
       -- No other transaction can modify the row while this one holds it.

   ------- -------

   EXPLOIT 5 — File-based state (this demo's storage)
   -------

   A JSON file on disk (like this demo uses) has NO built-in locking. Two
   processes can read the same file, modify their in-memory copies, then
   write back. Whoever writes second wins; the first writer's changes are lost.

   Fixes:
       1. Use a database with transactions (atomic writes, locking).
       2. Use Redis INCR (atomic increment).
       3. Implement file locking (flock on Unix, LockFile on Windows).
       4. Use a single writer (message queue) that processes requests serially.

   This demo uses raw file I/O to make the race *visible* — in production you
   would never do this. Always use a real database or cache.

   ------- -------

   EXPLOIT 6 — The race doesn't always win
   -------

   This is what makes races insidious: they're intermittent. You run the test
   5 times, and it fails 2 times, passes 3 times, and 1 time you get a bonus.
   Developers say "seems to work fine" and ship it. Then it fails in prod under
   load, and no one can reproduce it.

   That's why races are a nightmare to debug.

   ------- -------

   WHY THIS CODE IS BROKEN
   ------- -------

   1. The state is read, modified, and written back WITHOUT a lock. Another
      request can interleave in the middle.

   2. There is no atomic operation. The DB/file sees "read", then "write",
      as two separate operations, not a single indivisible action.

   3. The state is stored in a flat file (JSON). Files have no transaction
      support, no isolation levels, no locking. Two writers collide.

   4. The logic assumes requests are serial. In the real world, requests
      arrive concurrently and the OS interleaves them at the kernel level.

   ------- -------

   SECURE VERSION (sketch)
   ------- -------

       // 1. Use a database transaction with row-level locking.
       db.begin();
       $row = db.query("SELECT balance FROM accounts WHERE id=? FOR UPDATE");
       // FOR UPDATE locks the row. No other transaction can read/write it
       // until we release the lock.
       $new = $row['balance'] + 10;
       db.query("UPDATE accounts SET balance=? WHERE id=?", [$new, id]);
       db.commit();   // Lock released here.

       // 2. Even better: atomic operations (no explicit read-modify-write).
       db.query("UPDATE accounts SET balance = balance + 10 WHERE id=?");
       // The database guarantees this read-and-increment happens indivisibly.

       // 3. If using files (bad idea): implement locking.
       $fh = fopen($path, 'r+');
       flock($fh, LOCK_EX);       // Exclusive lock (no other process can read/write)
       $data = json_decode(fread($fh, filesize($path)), true);
       $data[$id]['balance'] += 10;
       fseek($fh, 0);
       ftruncate($fh, 0);
       fwrite($fh, json_encode($data));
       flock($fh, LOCK_UN);       // Unlock
       fclose($fh);

       // 4. Use a message queue / job processor that serializes requests.
       //    Only one worker thread processes "claim bonus" requests, so there's
       //    no concurrency. Trade: latency (queuing delay) for correctness.

       // 5. Use a cache with atomic operations (Redis INCR, Memcached CAS).
       $balance = $redis->incr("account:$id:balance:10");
       // Redis INCR is atomic (server-side locking, no client-side races).

============================================================================= */
?>