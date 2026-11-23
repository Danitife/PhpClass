<?php
$connection = mysqli_connect("localhost", "root", "", "dani_banks");
    if(!$connection){
        echo "Database could not connect " . mysqli_error($connection);
    }else{
        echo "Database connected";
    }
?>