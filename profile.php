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
}

if(isset($_POST['upload'])){
    $path = "images/" . basename($_FILES['profile_picture']['name']);

    echo "Path: $path";
    $file = $_FILES['profile_picture']['tmp_name'];
    if(move_uploaded_file($file, $path)){
        echo "File uploaded successfully";
    }else{
        echo "File upload failed";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Document</title>
</head>
<body>
    <main>
        <?php include 'components/navbar.html'; ?>
        <h1>Profile Page</h1>
        <h1>First Name: <?php echo $user['first_name'] ;?></h1>
        <h1>Last Name: <?php echo $user['last_name'] ;?></h1>

        <form action="profile.php" method="post" enctype="multipart/form-data">
            <input name="profile_picture" type="file">
            <button name="upload">Upload Picture</button>
        </form>
    </main>
</body>
</html>