<?php
session_start();
$email = $_POST['email'];
if(isset($_POST['login'])){
    $password = $_POST['password'];

    if(empty($email) || empty($password)){
        header("Location: login.php?error=Please fill in all required fields");
        exit;
    }

    if(isset($_SESSION['user'])){
        $user = $_SESSION['user'];
        if($email === $user['email'] && $password === $user['password']){
            header("Location: dashboard.php");
            exit;
        }else{
            header("Location: login.php?error=Invalid email or password");
            exit;
        }
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
    <form action="login.php" method="post" class="w-50 mx-auto mt-5 p-3 border border-dark rounded">
        <?php if(isset($_GET['error'])){ ?>

            <div class="alert alert-danger" role="alert">
                <?php echo $_GET['error']; ?>
            </div>
            
        <?php } ?>

        <div class="form-group">
            <label for="email">Email:</label>
            <input class="form-control" type="email" name="email">
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input class="form-control" type="password" name="password">
        </div>
        <div>
            <button name="login" class="btn btn-dark mt-3" type="submit">Login</button>
        </div>
    </form>
</body>
</html>