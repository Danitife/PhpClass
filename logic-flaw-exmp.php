<!-- 
Example 1: Workflow Skipping (Privilege Escalation)

The Flaw: The developer uses multi-step forms (Step 1: Info, Step 2: Payment, Step 3: Success). The code assumes users will follow the sequential UI. However, an attacker can browse directly to the final script (success.php) and pass their user ID via the URL parameter, skipping the payment entirely.
php -->
<?php
// success.php - The final step of a digital download purchase
session_start();

// BUG: The code only checks if a user is logged in, but never 
// verifies if they actually paid for the item in Step 2.
if (!isset($_SESSION['user_id'])) {
    die("Please log in first.");
}

// An attacker can directly visit: success.php?item_id=55
$item_id = (int)$_GET['item_id']; 

// Fetch the digital download link 
// (In a secure app, this query would JOIN a 'payments' or 'orders' table)
echo "<h3>Thank you for your purchase!</h3>";
echo "<p>Your download link for item #{$item_id} is: <a href='/downloads/file_{$item_id}.zip'>Download Now</a></p>";
?>



<!-- Currency / Unit Confusion (Over-crediting)

The Flaw: The platform supports both loyalty points (1 point = $0.01) and direct cash balances. The deposit endpoint accepts a type parameter and an amount. However, the mathematical handler applies the raw numerical amount to the account balance without normalising the unit scales.
php -->
<?php
// deposit.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount']; // e.g., 500
    $type = $_POST['type'];           // e.g., "points" vs "usd"

    if ($amount <= 0) {
        die("Invalid amount");
    }

    // BUG: If type is 'points', the code acknowledges it in the message, 
    // but adds the raw figure directly to a wallet that expects USD values.
    if ($type === 'points') {
        $_SESSION['wallet'] += $amount; 
        echo "Successfully credited {$amount} loyalty points to your USD wallet!";
    } else {
        $_SESSION['wallet'] += $amount;
        echo "Deposited \${$amount} via credit card.";
    }
}
?>


<!-- Example 3: Trusting Client-Side Deletions (Insecure Direct Object Reference)

The Flaw: A user wants to remove an item from their subscription or delete a comment. The server takes the target object ID and the user's identification from the request parameters. It fails to verify if the object actually belongs to the requesting user before executing the destructive action.
php -->
<?php
// delete_comment.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // BUG: Both parameters are trusted directly from the client request.
    $comment_id = (int)$_POST['comment_id'];
    $current_user_id = (int)$_POST['user_id']; 

    // An attacker intercepts the request and changes 'comment_id' 
    // to target someone else's comment, while leaving their own 'user_id'.
    
    // The developer wrote a query that looks safe because it uses parameters,
    // but it executes authorization implicitly based on tainted input.
    $query = "DELETE FROM comments WHERE id = ? AND user_id = ?";
    
    // Execution happens here...
    echo "Deleted comment #{$comment_id} successfully.";
}
?>