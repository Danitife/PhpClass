<?php
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
?>

