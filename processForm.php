<?php
$username = $_POST["username"];
$email = $_POST["email"];
$password = $_POST["password"];
$c_password = $_POST["c_password"];
echo $username . "<br>";

if (empty($username)) {
    echo "Username cannot be empty";
    header("Location: forms.php?error=Username cannot be empty");
    return;
}
if (empty($email)) {
    echo "Email cannot be empty";
    header("Location: forms.php?error=Email cannot be empty");
    return;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forms.php?error=Invalid email format");
    return;
}
// validate password
if (strlen($password) < 8) {
    header("Location: forms.php?error=Password must be at least 8 characters");
    return;
}

if (!preg_match("/[A-Z]/", $password)) {
    header("Location: forms.php?error=Password must contain at least one uppercase letter");
    return;
}
if (!preg_match("/[a-z]/", $password)) {
    header("Location: forms.php?error=Password must contain at least one lowercase letter");
    return;
}
if (!preg_match("/[0-9]/", $password)) {
    header("Location: forms.php?error=Password must contain at least one number");
    return;
}
if (!preg_match("/[!@#$%^&*()_+]/", $password)) {
    header("Location: forms.php?error=Password must contain at least one special character");
    return;
}
if ($password !== $c_password) {
    header("Location: forms.php?error=Passwords do not match");
    return;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Hashed password: " . $hashed_password;

include "database/config.php";

// $query = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
// $response = mysqli_query($config, $query);
// if (!$response) {
//     echo "Error: " . mysqli_error($config);
//     // header("Location: forms.php?error=Error inserting data into database");
//     exit();
// } else {
//     echo "User registered successfully!";
//     header("Location: login.php");
//     exit();
// }

// Using prepared statements to prevent SQL injection
try {
    $stmt = $config->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashed_password);
    if ($stmt->execute()) {
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
        header("Location: forms.php?error=Error inserting data into database");
        exit();
    }
} catch (\Exception $e) {
    echo $e->getMessage();
}
