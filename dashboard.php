<?php
// include dirname(__DIR__) . "/auth/authUser.php";
include __DIR__ . "/auth/authUser.php";

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
    <h1>Welcome to your dashboard <?php echo $user['username']; ?></h1>
</body>

</html>