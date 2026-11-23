<?php
include "connectdb.php";
$user_id = "";
if(isset($_GET['id'])){
    $user_id = $_GET['id'];
    // write the query that fetches the user
}

if(isset($_POST['edit_user'])){
    $fn = $_POST['first_name'];
    $ln = $_POST['last_name'];
    $email = $_POST['email'];

    $query = "UPDATE users SET first_name='$fn', last_name='$ln', email='$email' WHERE id=$user_id";
    $resp = mysqli_query($connection, $query);
    if(!$resp){
        $errMessage = "Error updating user: " . mysqli_error($connection);
    } else {
        header("Location: admin.php");
        exit();
    }
}

// download and install composer 
// anyone that wants to access admin.php or perform any action on the user must be an admin.

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
        <form method="post" action="edit-user.php" class="w-50 mx-auto mt-5 shadow p-5 border rounded">
            <?php
                if(!empty($errMessage)){
                    echo "<div class='alert alert-danger'>$errMessage</div>";
                }
            ?>
            <h2>Edit User</h2>
            <div class="form-group">
                <label class="text-dark">First Name</label>
                <input value="<?php echo $user['first_name'] ?>" name="first_name" type="text" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Last Name</label>
                <input value="<?php echo $user['last_name'] ?>" name="last_name" type="text" class="form-control">
            </div>
            <div class="form-group">
                <label class="text-dark">Email</label>
                <input value="<?php echo $user['email'] ?>" name="email" type="email" class="form-control">
            </div>
            
            <div class="mt-4">
                <button name="edit_user" type="submit" class="btn btn-primary">Edit User</button>
            </div>
        </form>
    </main>
</body>
</html>