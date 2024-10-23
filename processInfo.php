<?php
include("./database/connection.php");
if(isset($_POST['button_name'])){
    if(empty($_POST['input_name'])){
        header("Location: forms.php?err=Input field cannot be empty");
        return;
        // die("Input field cannot be empty");
    }
    if(!preg_match("/[0-9]/", $_POST['input_password'])){
        header("Location: forms.php?err=Password must contain atleast one number");
        return;
    }

    $name = $_POST['input_name'];
    $email = $_POST['input_email'];
    $password = $_POST['input_password'];

    $query = "INSERT INTO users(name, email, password) VALUES ('$name', '$email', '$password')";

    if(mysqli_query($connection, $query)){
        echo "Data Saved";
        header("Location: login.php");
    }else{
        echo "Something went very wrong". mysqli_error($connection). mysqli_errno($connection);
        if(mysqli_errno($connection) === 1062){
            header("Location: forms.php?err=Email already taken");
        }
    }

}

if(isset($_POST['login_button'])){
    $login_email = $_POST['login_email'];
    $login_query = "SELECT * FROM users WHERE email='$login_email'";
    
    $response = mysqli_query($connection, $login_query);

    $result = mysqli_fetch_assoc($response);
    if($result){
        print_r($result);
    }else{
        header("Location: login.php?err=User not found, please create an account");
    }

}

?>