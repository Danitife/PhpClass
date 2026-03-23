<?php

// variables are declared
// how to output values on your browser

$the_name_of_the_variable = "Hello World!";
// echo
echo $the_name_of_the_variable;

print $the_name_of_the_variable;


// download and install xampp
// download php => download the zip file from https://downloads.php.net/~windows/releases/archives/php-8.5.3-nts-Win32-vs17-x64.zip
// extract the zip file and copy the php folder to C:\Program Files\php
// add the php folder to the system environment variable PATH
// Go to windows search and type "environment variables" and click on "Edit the system environment variables"
// Click on "Environment Variables" button
// Under "System variables", find the "Path" variable and click on "Edit"
// Click on "New" and add the path to the php folder (e.g., C:\Program Files\php)
// Click "OK" to save the changes
// open the command prompt and type "php -v" to check if php is installed correctly


// DATA TYPES
// PHP supports several data types, including:
// - String
// - Integer
// - Float (or Double)
// - Boolean
// - Array
// - Object
// - NULL


// String
// A string is a sequence of characters enclosed in single quotes ('') or double quotes ("").
$string_variable = "This is a string.";
$single_quoted_string = 'This is also a string.';

$sentence = '$the_name_of_the_variable This is a "sentence"';
echo $sentence;
$single_in_double = "$the_name_of_the_variable This is a 'sentence'";
echo $single_in_double;
//string methods
// strlen() - returns the length of a string
echo strlen($the_name_of_the_variable); // Output: 12
// str_replace() - replaces all occurrences of a search string with a replacement string
echo str_replace("World", "PHP", $the_name_of_the_variable); // Output: Hello PHP!
// strtoupper() - converts a string to uppercase
echo strtoupper($the_name_of_the_variable); // Output: HELLO WORLD!
// strtolower() - converts a string to lowercase
echo strtolower($the_name_of_the_variable); // Output: hello world!
// substr() - returns a portion of a string
echo substr($the_name_of_the_variable, 0, 5); // Output: Hello
// strpos() - finds the position of the first occurrence of a substring in a string
echo strpos($the_name_of_the_variable, "World"); // Output: 6
// str_split() - splits a string into an array of characters
print_r(str_split($the_name_of_the_variable)); // Output: Array ( [0] => H [1] => e [2] => l [3] => l [4] => o [5] =>   [6] => W [7] => o [8] => r [9] => l [10] => d [11] => ! )
// strrev() - reverses a string
echo strrev($the_name_of_the_variable); // Output: !dlroW olleH
// trim() - removes whitespace from the beginning and end of a string
$string_with_whitespace = "   Hello World!   ";
echo trim($string_with_whitespace); // Output: Hello World!
// str_repeat() - repeats a string a specified number of times
echo str_repeat($the_name_of_the_variable, 3); // Output: Hello World!Hello World!Hello World!
// str_shuffle() - randomly shuffles the characters in a string
echo str_shuffle($the_name_of_the_variable); // Output: (randomly shuffled string)
// str_word_count() - counts the number of words in a string
echo str_word_count($the_name_of_the_variable); // Output: 2