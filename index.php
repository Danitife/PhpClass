<?php
// VARIABLES
// DATA TYPES
// STRING METHODS

$name = "Daniel"; // string

echo $name;
$walkIn = "and in someone just walked in        ";

$num1 = 30;
$num2 = 65; // integer

echo $num1 + $num2;

$allowed = true; //boolean
$myName = null; //null

$data; //undefined

echo $name . " Is currently in class " . $walkIn . "<br>";

echo strtoupper($walkIn) . "<br>";
echo str_word_count($walkIn);

echo str_replace("in", "out", $walkIn) . "<br>";

echo strlen($walkIn) . "Before trim <br>";

echo strlen(trim($walkIn)) . "After trim";

if ($name == "Daniel") {
    echo "He is a boy 'Welldone'";
} else {
    echo "He is a girl";
}
$rand = 9876543245678902;

echo strtr($rand, "27", "od") . "<br>";

// echo wordwrap($walkIn, 2, "\n");

$arr = ["Dan", "Sam", "Vic", "Tosin"];
echo ("jlkhgfdxsertdyuihojkiu");

for ($i = 0; $i < count($arr); $i++) {
    $errm = $arr[$i];
    echo $errm;
}

foreach ($yy as $arr) {
    echo $yy;
}

print_r($arr);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1><?php echo $rand  ?></h1>

    <div>
        <?php
        for ($i = 0; $i < count($arr); $i++) {
            $errm = $arr[$i];
            $ind = $i +1;
            echo "<h1>$ind. $errm  Surname</h1>";
        }
        ?>
    </div>
</body>

</html>