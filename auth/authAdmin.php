<?php
session_start();
if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== TRUE) {
    header("Location: ../dashboard.php");
    exit();
}
