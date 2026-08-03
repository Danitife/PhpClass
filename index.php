<?php
$my_name = "Daniel";

echo $my_name;


// STRING METHODS "" ''

$sent = "This 'is' a string";
$tence = 'This "is" another string';

$greet = "My name is $my_name"; // Variable interpolation works with double quotes
$greet2 = 'My name is $my_name'; // Variable interpolation does NOT work with single quotes


// STRING METHODS

echo strlen($my_name); // Length of the string
echo strtoupper($my_name); // Convert to uppercase
echo strtolower($my_name); // Convert to lowercase
echo str_replace("a", "@", $my_name); // Replace characters
echo substr($my_name, 0, 3); // Get a substring 
echo strpos($my_name, "a"); // Find the position of a character

// CONCATENATION
echo "My name is " . $my_name; // Concatenation with dot operator
echo "My name is $my_name"; // Concatenation with variable interpolation
echo "My name is {$my_name}"; // Concatenation with variable interpolation and braces

// Functions

function addTwoNumbers($a, $b)
{
    return $a + $b;
}

addTwoNumbers(17, 54);

function isPalindrome() {}

isPalindrome("daniel"); // not a palindrome
isPalindrome("madam"); // is a palindrome


// conditional statements (if, ternary operator)
if ($my_name == "Samuel") {
    echo "Hello Samuel!";
} else {
    echo "Hello Stranger!";
}

echo $my_name == "Samuel" ? "Hello Samuel!" : "Hello Stranger!"; // Ternary operator
