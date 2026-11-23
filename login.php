<?php
session_start();
include "connectdb.php";

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // ' OR '1'='1
    // $query = "SELECT id, email, password, role FROM users WHERE email=' OR '1'= '1 $email' AND password='$pass'";
    $query = mysqli_prepare($connection, "SELECT id, email, password, role FROM users WHERE email = ? AND password = ?");
    $bind_params = mysqli_bind_param($query, "ss", $email, $pass);
    $user = mysqli_execute($query);

    if(!$user){
        echo "Error executing query: " . mysqli_error($connection);
    }else{
        print_r($user);
    }
    // $resp = mysqli_query($connection, $query);
    // if(mysqli_num_rows($resp) > 0){
    //     $user = mysqli_fetch_assoc($resp);
    //     $new_user = ["email" => $user['email'], "role" => $user['role'], "token" => $token];
    //     print_r($user);
    //     $_SESSION['user_key'] = $new_user;
    //     if($user['role'] == 'admin'){
    //         header("Location: admin.php");
    //         exit;
    //     }else{
    //         header("Location: dashboard.php");
    //         exit;
    //     }
    // }
}
if(isset($_SESSION['user_key'])){
    $user = $_SESSION['user_key'];
    print_r($user);
}

// bin2hex, random_bytes, time()
echo random_bytes(8);
echo bin2hex("My name is Daniel");
echo hex2bin("4d79206e616d652069732044616e69656c");

$token = bin2hex(random_bytes(16));
$token_exp = time() + (60 * 3);

// in your database, create a token table with colums: user_id, token, token_expiration

$token_query = "INSERT INTO tokens (user_id, token, token_expiration) VALUES ($user_id, '$token', $token_exp)";
$resp = mysqli_query($connection, $token_query);
if(!$resp){
    echo "Error inserting token: " . mysqli_error($connection);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe" crossorigin="anonymous">
    <title>Document</title>
</head>
<body>
    <main>
        <form method="post" action="login.php" class="w-50 mx-auto mt-5 shadow p-5 border rounded">
            <?php
                if(!empty($errMessage)){
                    echo "<div class='alert alert-danger'>$errMessage</div>";
                }
            ?>
            <h2>Login</h2>
            <div class="form-group">
                <label class="text-dark">Email</label>
                <input name="email" type="email" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Password</label>
                <input name="password" type="password" class="form-control">
            </div>
            <div class="mt-4">
                <button name="login" type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
    </main>
</body>
</html>