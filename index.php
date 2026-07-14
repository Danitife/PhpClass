<?php

echo "My name is Daniel";
$the_name_of_the_variable = "You cannot use hypen"; // snake case
// $hy-pen = Cannot work
$myFirstName = "Daniel"; // Camel Case
$lastName = "Developer";

// STRINGS

echo "Hello World";
echo "My name is Daniel";

// String interpolation
echo "My name is $myFirstName";

echo 'My name is Daniel Dev';
echo 'My name is $myFirstName Dev';

echo "One of the rules is {$the_name_of_the_variable}";

// string concatenation

echo "My first name is ". $myFirstName . " and my last name is ". $lastName . "<br>";

echo strtoupper($myFirstName) . "<br>"; //converts your string to uppercase
echo strtolower($myFirstName) . "<br>"; //converts your string to lowercase
echo strrev($lastName) . "<br>"; // reverse your string
echo strpos($lastName, "p") . "<br>"; // returns the position of a character in a string
echo strlen($lastName) . "<br>"; // return the length of the string
echo str_replace("e", "k", $lastName) . "<br>"; // replaces some characters in the string
echo str_replace("uc", "**", "fuck") . "<br>";

print_r(str_split($lastName)); // converts your string to an array

echo substr($lastName, 0, 4) . "<br>";
echo str_contains($lastName, "l") . "<br>";
echo str_word_count($the_name_of_the_variable) . "<br>";

// inarray

function greet(){
    echo "Hi Daniel !!";
}
greet();

function doSomething($something){
    echo "I am $something";
}

doSomething("Driving");
doSomething("Smiling");

// PHP String 
// TasksTask 1: Reverse a StringTake the string "CloudComputing" and reverse it without using external libraries.
// Task 2: Character CounterCount how many times the letter "e" appears in "Excellent engineering execution".
// Task 3: Word ReplacerReplace the word "bad" with "good" in the sentence "This is a bad day".
// Task 4: Whitespace TrimmerRemove the leading and trailing spaces from the string "  Clean this up  ".
// Task 5: Format CurrencyConvert the number 1250.5 into a string formatted as USD currency ($1,250.50).
// Task 6: Palindrome CheckerWrite a function to check if the string "Racecar" is a palindrome (ignores case).
// Task 7: Substring ExtractorExtract the domain name "example.com" from the email string "user@example.com".
// Task 8: Password HashingConvert the plain text string "Secret123" into a secure, technical bcrypt hash.
// Task 9: Slug GeneratorConvert "PHP 8.1 New Features!" into a URL-friendly slug like "php-8-1-new-features".
// Task 10: String MaskingMask a credit card string "4111111111111234" so it outputs as "************1234".
?>