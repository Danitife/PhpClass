<?php
include __DIR__ . "/auth/authUser.php";
if (isset($_POST['upload'])) {
    $dir = "images/";
    $file = $dir . basename($_FILES['profile_image']['name']);

    $query = "UPDATE users SET profile_picture='$file' WHERE id='$user[id]'";
    $response = mysqli_query($config, $query);
    if (!$response) {
        echo "Error: " . mysqli_error($config);
        exit();
    } else {
        if (file_exists($file)) {
            echo "File already exists";
            exit();
        }
        move_uploaded_file($_FILES['profile_image']['tmp_name'], $file);
    }
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
    <?php include __DIR__ . "/components/navbar.html"; ?>
    <main class="card w-50 p-4 rounded shadow mx-auto text-center">
        <h1>Welcome to your profile <?php echo $user['username']; ?></h1>
        <img class="w-25 mx-auto" src="https://media.istockphoto.com/id/1337144146/vector/default-avatar-profile-icon-vector.jpg?s=612x612&w=0&k=20&c=BIbFwuv7FxTWvh5S3vB6bkT0Qv8Vn8N5Ffseq84ClGI=" alt="">
        <form action="profile.php" method="post" enctype="multipart/form-data">
            <input name="profile_image" type="file" accept=".png, .jpeg, .jpg">
            <button type="submit" name="upload" class="btn btn-dark">Upload</button>
        </form>
        <h3>Email: <?php echo $user['email']; ?></h3>
    </main>
</body>

</html>