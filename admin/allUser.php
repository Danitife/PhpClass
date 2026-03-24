<?php
session_start();
include  dirname(__DIR__) . "/database/config.php";
if (!isset($_SESSION['isAdmin']) || isset($_SESSION['isAdmin']) !== TRUE) {
    header("Location: ../dashboard.php");
    exit();
}
$user_stmt = $config->prepare("SELECT * FROM users");
$user_stmt->execute();
$user_response = $user_stmt->get_result();
$users = $user_response->fetch_all(MYSQLI_ASSOC);
print_r($users);
// Display the users in a table
// Add an edit and delete button for each user
// The edit button should redirect to an edit user page with the user id as a query parameter
// The delete button should delete the user from the database and redirect back to the all users page
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Document</title>
</head>

<body>
    <?php include "../components/admin_nav.html"; ?>
    <h1>Welcome Admin</h1>
</body>

</html>