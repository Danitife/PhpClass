<?php
// 1. Connect to MySQL using mysqli
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// $mysqli = new mysqli("localhost", "user", "password", "bank");
include "database.php";

try {
    // 2. Start the transaction
    $mysqli->begin_transaction();

    // 3. Select and lock the row using FOR UPDATE
    $stmt = $mysqli->prepare("SELECT balance FROM accounts WHERE id = ? FOR UPDATE");
    $id = 1;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();

    if ($account) {
        $newBalance = $account['balance'] + 100;

        // 4. Update the locked row
        $updateStmt = $mysqli->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        $updateStmt->bind_param("di", $newBalance, $id);
        $updateStmt->execute();
    }

    // 5. Commit changes and release the lock
    $mysqli->commit();
    echo "Transaction successful.";

} catch (mysqli_sql_exception $e) {
    // 6. Rollback if anything fails
    $mysqli->rollback();
    echo "Transaction failed: " . $e->getMessage();
}

?>