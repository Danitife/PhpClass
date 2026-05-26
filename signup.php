<?php
include "database.php";
session_start();

if (isset($_SESSION['userDetails'])) {
    $user = $_SESSION['userDetails'];
} else {
    echo "USer details does not exist";
}
if (isset($_POST['register'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $username = $_POST['username'];

    if (empty($email) || empty($password) || empty($username)) {
        echo "All fields are required";
        return;
    }

    $query = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')";
    $result = mysqli_query($database, $query);
    if ($result) {
        echo "User registered successfully!";
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($database);
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
</head>

<body>
    <form action="signup.php" method="POST">
        <h1>Register</h1>
        <input name="username" type="text"> <br><br>
        <input name="email" type="text"> <br><br>
        <input name="password" type="password"> <br><br>
        <button name="register">Register</button>
    </form>
</body>

</html>