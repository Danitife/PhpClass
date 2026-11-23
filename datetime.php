<?php
error_reporting(E_ALL);
require 'vendor/autoload.php';
use Carbon\Carbon;

echo date("Y-m-d H:i:s");
echo "<br>";
echo time();

echo "<br>";
$date = Carbon::now();

$last_week = $date->subweek();
echo "<br>";
echo $last_week->format('Y-m-d H:i:s');

//yesterday, lastmonth, lastyear, 4days ago, 2 weeks ago, 3 months ago, 6 months ago, 5 years ago file reader
?>