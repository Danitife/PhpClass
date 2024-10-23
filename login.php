<?php


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>Document</title>
</head>
<body>
    <?php include("./components/navbar.html") ?>
    <form method="post" action="processInfo.php" class="w-50 rounded shadow mx-auto mt-4 p-3">
        <div class="form-group my-3">
            <label for="" class="text-primary">Email</label>
            <input name="login_email" type="email" class="form-control py-3">
        </div>
        <div class="form-group my-3">
            <label for="" class="text-primary">Password</label>
            <input name="login_password" type="password" class="form-control py-3">
        </div>
        <div class="text-center mt-3">
            <button name="login_button" class="btn btn-primary w-75 py-2">Login</button>
        </div>
    </form>
</body>
</html>