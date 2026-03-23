<?php
$config = mysqli_connect("localhost", "root", "", "car_rental");
// $config = mysqli_connect("your_host.com", "your_username", "your_password", "your_database_name");

// check if the connection is successful
if (!$config) {
    echo "Connection failed: " . mysqli_connect_error();
}
