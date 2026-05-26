<?php

//oop => object oriented programming
// $database = new mysqli('localhost', 'root', '', 'vulnerable_app');
$database = mysqli_connect('localhost', 'root', '', 'bowen_commerce');

if (!$database) {
    die("Connection failed: " . mysqli_connect_error());
}
