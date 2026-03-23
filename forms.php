<?php
// document.getElementById("username");
if (isset($_POST["reg_user"])) {
    $username = $_POST["username"];
    echo $username . "<br>";
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
    <form class="w-50 mx-auto border p-4 mt-4 shadow" action="processForm.php" method="post">
        <?php if (isset($_GET['error'])) {
            echo "<div class='alert alert-danger'>" . $_GET['error'] . "</div>";
        } ?>
        <h1>Register</h1>
        <div class="form-group mt-3">
            <label for="">Username</label>
            <input type="text" class="form-control" name="username">
        </div>
        <div class="form-group mt-3">
            <label for="">Email</label>
            <input type="email" class="form-control" name="email">
        </div>
        <div class="form-group mt-3">
            <label for="">Password</label>
            <input type="password" class="form-control" name="password">
        </div>
        <div class="form-group mt-3">
            <label for="">Confirm Password</label>
            <input type="password" class="form-control" name="c_password">
        </div>
        <div class="mt-3">
            <button name="reg_user" class="btn btn-dark">Register</button>
        </div>
    </form>
</body>

</html>