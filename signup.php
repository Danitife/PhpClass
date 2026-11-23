<?php
// SUPER GLOBALS
// $_GET
// $_POST
// $_REQUEST
// $_SERVER
// $_FILES
// $_ENV
// $_COOKIE
// $_SESSION

$errMessage = "";
if(isset($_GET['error'])){
    $errMessage = $_GET['error'];
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe" crossorigin="anonymous"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe" crossorigin="anonymous">
    <title>Document</title>
</head>
<body>
    <main>
        <form method="post" action="processForm.php" class="w-50 mx-auto mt-5 shadow p-5 border rounded">
            <?php
                if(!empty($errMessage)){
                    echo "<div class='alert alert-danger'>$errMessage</div>";
                }
            ?>
            <h2>Sign Up</h2>
            <div class="form-group">
                <label class="text-dark">First Name</label>
                <input name="first_name" type="text" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Last Name</label>
                <input name="last_name" type="text" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Email</label>
                <input name="email" type="email" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Password</label>
                <input name="password" type="password" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Confirm Password</label>
                <input name="c_pass" type="password" class="form-control">
            </div>
            <div class="mt-4">
                <button name="sineup" type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </main>
</body>
</html>