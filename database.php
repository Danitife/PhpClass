<?php

//oop => object oriented programming
// $database = new mysqli('localhost', 'root', '', 'vulnerable_app');
$database = mysqli_connect('127.0.0.1', 'root', '', 'blog');

if (!$database) {
    die("Connection failed: " . mysqli_connect_error());
}
