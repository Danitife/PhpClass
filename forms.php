<?php

// PHP SUPER GLOBALS
// $_GET
// $_POST
// $_REQUEST
// $_SESSION
// $_SERVER
// $_COOKIE
// $_FILES

$first_name = $_POST['first_name'];
echo $first_name;
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
    <form action="forms.php" method="post" class="w-50 mx-auto mt-5 p-3 border border-dark rounded">
        <div class="form-group">
            <label for="first_name">First Name:</label>
            <input class="form-control" type="text" name="first_name">
        </div>
        <div class="form-group">
            <label for="last_name">Last Name:</label>
            <input class="form-control" type="text" name="last_name">
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input class="form-control" type="email" name="email">
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input class="form-control" type="password" name="password">
        </div>
        <div>
            <button class="btn btn-dark mt-3" type="submit">Register</button>
        </div>
    </form>
</body>
</html>