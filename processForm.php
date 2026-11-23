<?php
if(isset($_POST['sineup'])){
    $fn = $_POST['first_name'];
    $ln = $_POST['last_name'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $c_pass = $_POST['c_pass'];

    // AUTHENTICATE THE INPUT FIELDS


    if(empty($fn)){
        header("Location: signup.php?error=First name is required");
        exit;
    }
    if(strlen($fn) < 3){
        header("Location: signup.php?error=First name must be at least 3 characters");
        exit;
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        header("Location: signup.php?error=Email is not valid");
        exit;
    }
    if(empty($pass)){
        header("Location: signup.php?error=Password is required");
        exit;
    }
    if(strlen($pass) < 8){
        header("Location: signup.php?error=Password must be at least 8 characters");
        exit;
    }
    if(!preg_match("/[A-Z]/", $pass)){
        header("Location: signup.php?error=Password must contain at least one uppercase letter");
        exit;
    }
    if(!preg_match("/[a-z]/", $pass)){
        header("Location: signup.php?error=Password must contain at least one lowercase letter");
        exit;
    }
    if(!preg_match("/[0-9]/", $pass)){
        header("Location: signup.php?error=Password must contain at least one digit");
        exit;
    }
    if(!preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]/", $pass)){
        header("Location: signup.php?error=Password must contain at least one special character");
        exit;
    }

    // SESSION

    // session_start();
    // $_SESSION['fn'] = $fn;
    // $_SESSION['ln'] = $ln;
    // $_SESSION['email'] = $email;
    // $_SESSION['pass'] = $pass;

    // $user = ["fn" => $fn, "ln" => $ln, "email" => $email, "pass" => $pass];

    // $_SESSION['user_key'] = $user;

    // header("Location: login.php");

    // $connection = mysqli_connect("hostname", "username", "password", "database_name")
    // $connection = mysqli_connect("localhost", "root", "", "dani_banks");
    // if(!$connection){
    //     echo "Database could not connect " . mysqli_error($connection);
    // }else{
    //     echo "Database connected";
    // }

    include "connectdb.php";

    $query = "INSERT INTO users (first_name, last_name, email, password) VALUES ('$fn', '$ln', '$email', '$pass')";
    $resp = mysqli_query($connection, $query);
    if(!$resp){
        echo "Something went wrong :" . mysqli_error($connection);
    }else{
        echo "User created successfully";
        header("Location: login.php");
    }

}
?>