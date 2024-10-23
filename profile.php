<?php
session_start();
if(isset($_SESSION['name'])){
    $user = $_SESSION['name'];
}else{
    $user = "Is not set";
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
    <h1>Welcome to your profile ..... <?php echo $user ?></h1>
</body>
</html>