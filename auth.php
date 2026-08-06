<?php
include "connection.php";
session_start();
if(isset($_SESSION['loggedinEmail'])){
    $email = $_SESSION['loggedinEmail'];

    echo "Email: $email";

    $query = "SELECT * FROM users WHERE email='$email'";
    $exec = mysqli_query($conn, $query);

    // $user = mysqli_fetch_all($exec, MYSQLI_ASSOC);
    $user = mysqli_fetch_assoc($exec);
}else{
    header("Location: login.php");
    exit();
}


?>