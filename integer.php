<?php

$num1 = 35;
$num2 = 15;

echo $num1 + $num2; // Output: 50
echo $num1 - $num2; // Output: 20
echo $num1 * $num2; // Output: 525
echo $num1 / $num2; // Output: 2.3333333333333335
echo $num1 % $num2 . "<br>"; // Output: 5


// increment and decrement operators
// increment operator (++)
// decrement operator (--)
// float (or double) - a number with a decimal point
$float_num = 3.14;

echo number_format((float)$num1, 2) . "<br>";

// boolean - a data type that can have only two values: true or false
echo $num1 > $num2 . "<br>"; // Output: true
echo $num1 < $num2 . "<br>"; // Output: false
echo $num1 == $num2 . "<br>"; // Output: false
echo $num1 != $num2 . "<br>"; // Output: true


// array - a data type that can hold multiple values in a single variable
// $countries = array("USA", "Canada", "UK", "Australia");
$countries = ["USA", "Canada", "UK", "Australia"];
echo $countries[2] . "<br>"; // Output: UK
echo $countries[3] . "<br>"; // Output: Australia
echo $countries[0] . "<br>"; // Output: USA
echo count($countries) . "<br>"; // Output: 4

// array methods
// array_push() - adds one or more elements to the end of an array
array_push($countries, "Germany", "France");
print_r($countries); // Output: Array ( [0] => USA [1] => Canada [2] => UK [3] => Australia [4] => Germany [5] =>
// array_pop() - removes the last element from an array
array_pop($countries);
print_r($countries); // Output: Array ( [0] => USA [1] => Canada [2] => UK [3] => Australia [4] => Germany )
// array_shift() - removes the first element from an array
// array_unshift() - adds one or more elements to the beginning of an array
// array_slice() - returns a portion of an array
print_r(array_slice($countries, 1, 3)); // Output: Array ( [0] => Canada [1] => UK [2] => Australia )
// array_splice() - removes a portion of an array and replaces it with something else
array_splice($countries, 2, 2, ["Italy", "Spain"]);
print_r($countries); // Output: Array ( [0] => USA [1] => Canada [2] => Italy [3] => Spain [4] => Germany )
echo "<br>";
// array_merge() - merges one or more arrays into one array
$hobbies = ["Reading", "Traveling", "Cooking"];
$merged_array = array_merge($countries, $hobbies);
print_r($merged_array); // Output: Array ( [0] => USA [1] => Canada [2] => Italy [3] => Spain [4] => Germany [5] => Reading [6] => Traveling [7] => Cooking )
echo "<br>";
// array_diff() - compares two arrays and returns the differences
$array1 = ["a", "b", "c", "d"];
$array2 = ["c", "d", "e", "f"];
print_r(array_diff($array1, $array2)); // Output: Array ( [0] => a [1] => b )
// array_intersect() - compares two arrays and returns the common elements
// array_search() - searches an array for a specific value and returns its key
echo array_search("Italy", $countries) . "<br>"; // Output: 2
// array_keys() - returns all the keys of an array
// array_values() - returns all the values of an array
// array_map() - applies a callback function to the elements of an array
// array_filter() - filters the elements of an array using a callback function
// array_reduce() - reduces the array to a single value using a callback function
// array_key_exists() - checks if a key exists in an array
// in_array() - checks if a value exists in an array
// sort() - sorts an array in ascending order
// rsort() - sorts an array in descending order
// asort() - sorts an array in ascending order according to the value
