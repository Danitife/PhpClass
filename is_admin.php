<?php
if(!isset($_SESSION['user_key'])){
        header("Location: login.php");
        exit;
    }

    $user = $_SESSION['user_key'];
    $user_email = $user['email'];
    $query = "SELECT role FROM users WHERE email = '$user_email'";
    $resp = mysqli_query($connection, $query);
    if(!$resp){
        echo "Error fetching user role: " . mysqli_error($connection);
    }
    $rez = mysqli_fetch_assoc($resp);
    if($rez !== "admin"){
        header("Location: dashboard.php");
        exit;
    }
?>