<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "job_hunt");

if(!$conn){
    echo "Connection failed: " . mysqli_connect_error(); // throw an error when database does not connect
}

$query = "SELECT * FROM users";
$exec = mysqli_query($conn, $query);
$users = mysqli_fetch_all($exec, MYSQLI_ASSOC);

print_r($users);
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

<table class="table">
    <thead>
        <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($users as $user) { ?>
            <tr>
                <td><?php echo $user['first_name']; ?></td>
                <td><?php echo $user['last_name']; ?></td>
                <td><?php echo $user['email']; ?></td>
                <td>
                    <a class="btn btn-secondary" href="one-user.php?id=<?php echo $user['id'] ?>">View User</a>
                    <a class="btn btn-warning" href="">Edit User</a>
                    <a class="btn btn-danger" href="">Delete User</a>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
    
</body>
</html>