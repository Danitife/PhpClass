<?php
$cities = ["Ibadan", "Ikoyi", "Abeokuta", "Ogbomoso"];
echo $cities[2];

$info = ["Oyo", ["Ibadan", "Ogbomoso"], "Lagos", ["Ikoyi", "Ikrd"]];

echo $info[3][0];

// associative array
$dep_cour = ["cyber" => ["Php", "Linux"], "web" => ["JS", "React"], "DS" => ["Python", "ML"]];
echo $dep_cour["web"][1];
print_r($dep_cour);

for ($i = 0; $i < count($cities); $i++) {
    echo $cities[$i] . "<br>";
}

// PHP associative array containing 30 users with Gender included
$users = [
    ["name" => "James Smith", "email" => "james@example.com", "age" => 28, "gender" => "Male"],
    ["name" => "Mary Johnson", "email" => "mary@example.com", "age" => 34, "gender" => "Female"],
    ["name" => "Robert Brown", "email" => "robert@example.com", "age" => 22, "gender" => "Male"],
    ["name" => "Patricia Williams", "email" => "patricia@example.com", "age" => 45, "gender" => "Female"],
    ["name" => "Jennifer Jones", "email" => "jennifer@example.com", "age" => 31, "gender" => "Female"],
    ["name" => "Michael Garcia", "email" => "michael@example.com", "age" => 29, "gender" => "Male"],
    ["name" => "Linda Miller", "email" => "linda@example.com", "age" => 40, "gender" => "Female"],
    ["name" => "David Davis", "email" => "david@example.com", "age" => 37, "gender" => "Male"],
    ["name" => "Elizabeth Rodriguez", "email" => "elizabeth@example.com", "age" => 25, "gender" => "Female"],
    ["name" => "William Martinez", "email" => "william@example.com", "age" => 52, "gender" => "Male"],
    ["name" => "Richard Hernandez", "email" => "richard@example.com", "age" => 41, "gender" => "Male"],
    ["name" => "Susan Lopez", "email" => "susan@example.com", "age" => 33, "gender" => "Female"],
    ["name" => "Joseph Gonzalez", "email" => "joseph@example.com", "age" => 27, "gender" => "Male"],
    ["name" => "Jessica Wilson", "email" => "jessica@example.com", "age" => 30, "gender" => "Female"],
    ["name" => "Thomas Anderson", "email" => "thomas@example.com", "age" => 38, "gender" => "Male"],
    ["name" => "Sarah Thomas", "email" => "sarah@example.com", "age" => 26, "gender" => "Female"],
    ["name" => "Charles Taylor", "email" => "charles@example.com", "age" => 48, "gender" => "Male"],
    ["name" => "Karen Moore", "email" => "karen@example.com", "age" => 35, "gender" => "Female"],
    ["name" => "Christopher Jackson", "email" => "chris@example.com", "age" => 24, "gender" => "Male"],
    ["name" => "Nancy Martin", "email" => "nancy@example.com", "age" => 44, "gender" => "Female"],
    ["name" => "Daniel Lee", "email" => "daniel@example.com", "age" => 32, "gender" => "Male"],
    ["name" => "Lisa Perez", "email" => "lisa@example.com", "age" => 39, "gender" => "Female"],
    ["name" => "Matthew Thompson", "email" => "matthew@example.com", "age" => 21, "gender" => "Male"],
    ["name" => "Betty White", "email" => "betty@example.com", "age" => 50, "gender" => "Female"],
    ["name" => "Anthony Harris", "email" => "anthony@example.com", "age" => 36, "gender" => "Male"],
    ["name" => "Margaret Clark", "email" => "margaret@example.com", "age" => 43, "gender" => "Female"],
    ["name" => "Mark Lewis", "email" => "mark@example.com", "age" => 28, "gender" => "Male"],
    ["name" => "Sandra Robinson", "email" => "sandra@example.com", "age" => 31, "gender" => "Female"],
    ["name" => "Steven Walker", "email" => "steven@example.com", "age" => 29, "gender" => "Male"],
    ["name" => "Ashley Young", "email" => "ashley@example.com", "age" => 23, "gender" => "Female"]
];
// display all users on a table
// Implement a filter that displays the users gender either male or female



// let data = {
//     name: "Dan",
//     age: 30,
// }
$name = "Ntn";
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>I live in <?php echo $cities[0] ?></h1>

    <div>
        <?php
        for ($i = 0; $i < count($cities); $i++) {
            echo "<h3>$cities[$i]</h3>";
        }

        ?>
    </div>

    <div>
        <?php
        foreach ($cities as $city) {
            echo "<h4>$city</h4>";
        }
        ?>
    </div>

    <div>
        <h6><?php echo $name; ?></h6>
        <button><?php $name = "Dan" ?></button>
        <button><?php $name = "Sam" ?></button>
    </div>
</body>

</html>