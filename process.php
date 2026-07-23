<?php
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$email = $_POST['email'];
$password = $_POST['password'];

// if(empty($first_name)){
//     echo "First name is required";
// }

// if(empty($last_name)){
//     echo "Last name is required";
// }

// if(empty($email)){
//     echo "Email is required";
// }

// if(empty($password)){
//     echo "Password is required";
// }

if(empty($first_name) || empty($last_name) || empty($email) || empty($password)){
    // echo "Please fill in all required fields";
    header("Location: forms.php?error=Please fill in all required fields");
    exit;
    // die("Please fill in all required fields");
    // return;
}
if(strlen($first_name) < 3 || strlen($last_name) < 3){
    echo "First name and last name must be at least 3 characters long";
}

// if(filter_var($email, FILTER_VALIDATE_EMAIL) == false){
//     echo "Please enter a valid email address";
// }

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    echo "Please enter a valid email address";
}

if(strlen($password) < 8){
    echo "Password must be at least 8 characters long";
}

if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)){
    echo "Password must contain at least one uppercase letter, one lowercase letter, and one number";
}
echo "First Name: " . $first_name . "<br>";
echo "Last Name: " . $last_name . "<br>";
echo "Email: " . $email . "<br>";
echo "Password: " . $password . "<br>";

$user = [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'password' => $password
];

session_start();
$_SESSION['user'] = $user;
header("Location: login.php");
exit;
?>