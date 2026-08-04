<?php

include "connection.php";
if(isset($_GET['id'])){
    $id = $_GET['id'];

    $query = "SELECT * FROM users WHERE id='$id'";
    $exec = mysqli_query($conn, $query);

    // $user = mysqli_fetch_all($exec, MYSQLI_ASSOC);
    $user = mysqli_fetch_assoc($exec);

    print_r($user);
}

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    
</body>
</html>