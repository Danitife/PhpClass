<?php
$connection = mysqli_connect("localhost", "root", "root", "Blog");

if($connection){
    echo "Database Connected";
}else{
    echo "Something went wrong". mysqli_connect_error();
}
?>