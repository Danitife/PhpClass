<?php
$conn = mysqli_connect("localhost", "root", "", "job_hunt");

if(!$conn){
    echo "Connection failed: " . mysqli_connect_error(); // throw an error when database does not connect
}

$query = "SELECT * FROM users";
$exec = mysqli_query($conn, $query);
$users = mysqli_fetch_all($exec);

print_r($users);
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