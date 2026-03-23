<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location:" . dirname(__DIR__) . "/login.php?error=Please login to access the dashboard");
    exit();
}
$token = $_SESSION['token'];
include  dirname(__DIR__) . "/database/config.php";
$token_query = "SELECT * FROM tokens WHERE token='$token'";
$token_response = mysqli_query($config, $token_query);
if (!$token_response) {
    echo "Error: " . mysqli_error($config);
    exit();
}
$token_data = mysqli_fetch_assoc($token_response);
print_r($token_data);
if (time() > strtotime($token_data['token_exp'])) {
    // header("Location:" . dirname(__DIR__) . "/login.php?error=Session expired, please login again");
    header("Location: login.php?error=Session expired, please login again");
    exit();
}

$user_query = "SELECT * FROM users WHERE id='$token_data[user_id]'";
$user_response = mysqli_query($config, $user_query);
$user = mysqli_fetch_assoc($user_response);
