<?php
// FORMS
// SUPER GLOBALS
$err = null;
if($_GET['err']){
    $err = $_GET['err'];
}
// echo $err;
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
    <?php include "./components/navbar.html" ?>
    <div>
        <?php if($err){
            echo "<h4 class='bg-danger w-25 rounded m-auto text-white text-center p-1'>".$err."</h4>";
        } ?>
    </div>
    <form method="post" action="processInfo.php" class="w-50 rounded shadow mx-auto mt-4 p-3">
        <div class="form-group my-3">
            <label for="" class="text-primary">Name</label>
            <input name="input_name" type="text" class="form-control py-3">
        </div>
        <div class="form-group my-3">
            <label for="" class="text-primary">Email</label>
            <input name="input_email" type="email" class="form-control py-3">
        </div>
        <div class="form-group my-3">
            <label for="" class="text-primary">Password</label>
            <input name="input_password" type="password" class="form-control py-3">
        </div>
        <div class="text-center mt-3">
            <button name="button_name" class="btn btn-primary w-75 py-2">Submit</button>
        </div>
        <p class="text-center">Already have an account? <a class="btn btn-sm btn-success" href="login.php">Login</a></p>
    </form>
</body>
</html>