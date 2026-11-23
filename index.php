<?php
session_start();
include "connectdb.php";

if(!isset($_SESSION['user_key'])){
    header("Location: login.php");
    exit;
}
$user = $_SESSION['user_key'];
$user_token = $user['token'];
$token_check_query = "SELECT * FROM tokens WHERE token = '$user_token'";
$resp = mysqli_query($connection, $token_check_query);
if(mysqli_num_rows($resp) == 0){
    header("Location: login.php");
    exit;
}else{
    $token_data = mysqli_fetch_assoc($resp);
    $token_expiration = $token_data['token_expiration'];
    if(time() > $token_expiration){
        // Token has expired, log the user out
        session_destroy();
        header("Location: login.php?error=Session expired, please log in again");
    }
}


echo "Hello, Welcome to PHP class";
echo "By default, Apache server runs on port 80";
echo "<h1>Another thing</h1>";

echo "<br>";
echo ["Dan", "John", "Smith"];

echo "<br>";
print_r(["Dan", "John", "Smith"]);

$the_name_of_the_data = "Value of the data"; // snake case
$theNameOfTheData = "Value of the data"; // camel case
$MyClass = "Value of the data"; // Pascal case || oop || classes

$name = "Samuel";

echo "<br>";

echo "My name is $name";

echo "My name is $name, $name is an instructor in SQI College of ICT, $name is fair";

$DONT_CHANGE = "This is a constant variable"; // constant variable

// string
echo "I like teaching" . "Said by $name";
echo "I like teaching" . "Said by {$name}";

echo "<br>";

echo strlen($the_name_of_the_data);
echo "<br>";
echo strtoupper($the_name_of_the_data);
echo "<br>";
echo strtolower($the_name_of_the_data);

// integer
$my_age = 20;

//float
$my_height = 5.9;

// boolean
$is_student = false;

// object
// $my_object = new stdClass(); 

// array
$my_array = ["Dan", "John", "Smith"];

// if($is_student){
//     echo "<br>";
//     echo "I am a student";
// } else {
//     echo "<br>";
//     echo "I am not a student";
// }

echo $is_student ? "I am a student" : "I am not a student";


// functions

function displayName(){
    global $name;
    // $_GLOBALS['name'];
    echo "<br>";
    echo "My name is Daniel";
    echo "<br>";
    echo "My name is $name";
}

displayName();

function dispNameWithParam($nn){
    echo "<br>";
    echo "My name is $nn";
}
dispNameWithParam("Danicode");


function passwordValidate($pswd){
    if(strlen($pswd) < 8){
        echo "<br>";
        echo "Password is too short";
    } else {
        echo "<br>";
        echo "Password is valid";
    }
}

passwordValidate("daniel");

function dymCal($num1, $num2, $op){
    if($op == "add"){
        echo $num1 + $num2;
    }else if($op == "multiply"){
        echo $num1 * $num2;
    }
}

dymCal(5, 10, "add");
dymCal(5, 10, "multiply");

$systemStatus = "working";

function checkSystem(){
    global $systemStatus;
    if($systemStatus == "maintenance"){
        echo "Site is Offline";
    }else{
        echo "Site is Live";
    }
}

checkSystem();

// ARRAY

$prog_lag = ['python', 'php', 'java', 'java script', 'go', 'rust', 'c#'];
echo count($prog_lag);
echo $prog_lag[5];
echo $prog_lag[count($prog_lag) - 1];

array_push($prog_lag, "c++");
array_pop($prog_lag);

//shift, unshift, slice, splice, merge, sort, reverse

// name: Daniel
$info = [
    ["name" => "Daniel", "age" => 7],
    ["name" => "Samuel", "age" => 12],
    ["name" => "Victor", "age" => 4],
    ["name" => "Jade", "age" => 17],
    ["name" => "Halimat", "age" => 8],
];

echo $info[1]["age"];

for($i = 0; $i < count($prog_lag); $i++){
    echo "<br>";
    echo "I am learning $prog_lag[$i]";
}

for($i = 0; $i < count($info); $i++){
    echo "<br>";
    echo "My name is {$info[$i]['name']} and I am {$info[$i]['age']} years old";
}

foreach($prog_lag as $lang){
    echo "<br>";
    echo "I am learning $lang";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php include 'components/navbar.html'; ?>
    <main>
        <?php foreach($prog_lag as $lang){ ?>
            <h3 style="color: blue;">I am learning <?php echo $lang; ?></h3>
            <button>Click me</button>
        <?php } ?>

        <?php foreach($prog_lag as $lang){
            echo "<h3 style='color: red;'>I am learning $lang</h3>";
            echo "<button>Click me</button>";
        } ?>
    </main>
</body>
</html>

