<?php
include  __DIR__ . "/database/config.php";


if (isset($_POST["login_user"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];
    if (empty($email) || empty($password)) {
        header("Location: login.php?error=Input fields cannot be empty");
        exit();
    }
    $query = "SELECT * FROM users WHERE email='$email'";
    $response = mysqli_query($config, $query);
    if (!$response) {
        echo "Error: " . mysqli_error($config);
        exit();
    }
    $user = mysqli_fetch_assoc($response);
    // $user = mysqli_fetch_all($response, MYSQLI_ASSOC);
    print_r($user);
    if ($user['email'] !== $email) {
        header("Location: login.php?error=Email not found");
        exit();
    }
    if (!password_verify($password, $user['password'])) {
        header("Location: login.php?error=Incorrect password");
        exit();
    }
    // echo $user[0]['email'];
    session_start();
    $random_str = random_bytes(16);
    $token = bin2hex($random_str);
    $token_exp = time() + 60;
    echo $user['id'] . "<br>";

    try {
        // $token_query = "UPDATE tokens SET token='$token', token_exp='$token_exp' WHERE id='$user[id]'";
        $token_stmt = $config->prepare("INSERT INTO tokens (user_id, token, token_exp) VALUES (?, ?, FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE token=?, token_exp=FROM_UNIXTIME(?), updated_at=NOW()");
        // $token_query = "INSERT INTO tokens (user_id, token, token_exp) VALUES ('$user[id]', '$token', FROM_UNIXTIME($token_exp)) ON DUPLICATE KEY UPDATE token='$token', token_exp=FROM_UNIXTIME($token_exp), updated_at=NOW()";
        $token_stmt->bind_param("issss", $user['id'], $token, $token_exp, $token, $token_exp);
        if ($token_stmt->execute()) {
            $_SESSION["token"] = $token;
            header("Location: dashboard.php");
            exit();
        } else {
            echo "Error " . $token_stmt->error;
        }
        // $token_response = mysqli_query($config, $token_query);
        // if (!$token_response) {
        //     echo "Error: " . mysqli_error($config);
        //     exit();
        // } else {
        //     echo "Token updated successfully!";
        // }
        // $_SESSION["token_exp"] = $token_exp;
        // $_SESSION["email"] = $user['email'];
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage();
        //throw $th;
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <form class="w-50 mx-auto border p-4 mt-4 shadow" action="login.php" method="post">
        <?php if (isset($_GET['error'])) {
            echo "<div class='alert alert-danger'>" . $_GET['error'] . "</div>";
        } ?>
        <h1>Login</h1>
        <div class="form-group mt-3">
            <label for="">Email</label>
            <input type="email" class="form-control" name="email">
        </div>
        <div class="form-group mt-3">
            <label for="">Password</label>
            <input type="password" class="form-control" name="password">
        </div>
        <div class="mt-3 d-flex items-center">
            <button name="login_user" class="btn btn-dark">Login</button>
            <p>Don't have an account? <a href="forms.php">Register</a></p>
        </div>
    </form>
</body>

</html>

<!-- Design a car.php page that allows users to add cars to their database -->
<!-- Car name -->
<!-- Car model -->
<!-- Car color -->
<!-- Car number -->
<!-- Car image => a url from your browser -->


<!-- Design a view_car.php page that allows users to retrieve/show cars from their database -->
<!-- It is going to have a filter feature, where you can filter cars by name, model, color, and also by limit -->