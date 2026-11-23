<?php
    session_start();
    include "connectdb.php";

    include "is_admin.php";
    // if($_SESSION['user_key']['role'] != 'admin'){
    //     header("Location: dashboard.php");
    //     exit;
    // }


    $query = "SELECT * FROM users";
    $resp = mysqli_query($connection, $query);
    $users = mysqli_fetch_assoc($resp);
    print_r($users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <main>
        <h1>Admin Panel</h1>
        <table class="table">
            <thead>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email Name</th>
                <th>Action</th>
            </thead>
            <tbody>
                <?php foreach($users as $user){ ?>
                    <tr>
                        <td><?php echo $user['first_name'] ?></td>
                        <td><?php echo $user['last_name'] ?></td>
                        <td><?php echo $user['email'] ?></td>
                        <td>
                            <button class="btn btn-secondary">
                                <a href="view-user.php?id=<?php echo $user['id'] ?>">View</a>
                            </button>
                            <button class="btn btn-warning">
                                <a href="edit-user.php?id=<?php echo $user['id'] ?>">Edit</a>
                            </button>
                            <button class="btn btn-danger">Del</button>
                        </td>
                    </tr>
                <?php } ?>
                
            </tbody>
        </table>
    </main>
</body>
</html>