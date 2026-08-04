<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "job_hunt");

if(!$conn){
    echo "Connection failed: " . mysqli_connect_error(); // throw an error when database does not connect
}else{
    echo "Connection successful";
}

?>