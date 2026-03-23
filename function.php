<?php

// function - a block of code that can be reused multiple times
// foreach - a loop that iterates over each element in an array
// for - a loop that iterates a specified number of times
// while - a loop that continues as long as a specified condition is true

function greet($val) // function name - greet, parameter - $val
{
    return "Hello, $val!";
}

echo greet("Victor"); // Output: Hello, Victor!, function call - greet("Victor"), argument - "Victor"

$second_name = "Smith";

function greet_another()
{
    global $second_name; // global variable - $second_name
    return "Hello, $second_name!";
}

echo greet_another(); // variable scope - global

// write a function that takes two parameters and returns the sum of those parameters
function addTwo($num1, $num2)
{
    return $num1 + $num2;
}

echo addTwo(5, 10);
// write a function that takes an array of numbers and returns the average of those numbers
// write a function that takes a string and returns the number of words in that string
// write a function that takes a string and returns the string in reverse order
// write a function that takes a string and returns the string in uppercase
// write a function that takes a string and returns the string in lowercase


$favorites = ["pizza", "ice cream", "chocolate", "sushi", "tacos"];
// echo "I love $favorites[0]" . "<br>";
// echo "I love $favorites[1]" . "<br>";
// echo "I love $favorites[2]" . "<br>";
// echo "I love $favorites[3]" . "<br>";
// echo "I love $favorites[4]" . "<br>";

// for ($i = 0; $i < count($favorites); $i++) {
//     echo "I love $favorites[$i]" . "<br>";
// }

// foreach ($favorites as $fav) {
//     echo "I love $fav" . "<br>";
// }

$i = 0;
while ($i < count($favorites)) {
    echo "I love $favorites[$i]" . "<br>";
    $i++;
}

// associative array - an array that uses named keys that you assign to them

$infos = [
    ["name" => "Sam", "age" => 3, "city" => "Ibadan"],
    ["name" => "Sam2", "age" => 32, "city" => "Ibadan2"],
    ["name" => "Sam3", "age" => 33, "city" => "Ibadan3"]
];
echo $infos[1]['city']
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>I love <?php echo $favorites[0]; ?></h1>
    <div>
        <?php for ($i = 0; $i < count($favorites); $i++) {
            echo "<h2>I love $favorites[$i]</h2>";
        } ?>

        <?php foreach ($favorites as $favv) { ?>
            <h1>One of my favorite is <?php echo $favv; ?></h1>
        <?php } ?>

    </div>
</body>

</html>