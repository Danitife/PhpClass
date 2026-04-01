<?php
include  dirname(__DIR__) . "/database/config.php";
session_start();
if (!isset($_SESSION['isAdmin']) || isset($_SESSION['isAdmin']) !== TRUE) {
    header("Location: ../dashboard.php");
    exit();
}
if (isset($_POST['addCar'])) {
    $car_name = $_POST['car_name'];
    $car_model = $_POST['car_model'];
    $car_number = $_POST['car_number'];
    $car_color = $_POST['car_color'];
    $booking_price = $_POST['booking_price'];

    $add_car_stmt = $config->prepare("INSERT INTO cars (car_name, car_model, car_number, car_color, booking_price) VALUES (?, ?, ?, ?, ?)");
    $add_car_stmt->bind_param("sssss", $car_name, $car_model, $car_number, $car_color, $booking_price);
    if ($add_car_stmt->execute()) {
        header("Location: cars.php?success=Car added successfully");
        exit();
    } else {
        echo "Error: " . $add_car_stmt->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Car</title>
</head>

<body>
    <h1>Add Car</h1>
    <form action="add_car.php" method="post">
        <div>
            <label for="car_name">Car Name</label>
            <input type="text" id="car_name" name="car_name">
        </div>
        <div>
            <label for="car_model">Car Model</label>
            <input type="text" id="car_model" name="car_model">
        </div>
        <div>
            <label for="car_number">Car Number</label>
            <input type="text" id="car_number" name="car_number">
        </div>
        <div>
            <label for="car_color">Car Color</label>
            <input type="text" id="car_color" name="car_color">
        </div>
        <div>
            <label for="booking_price">Booking Price</label>
            <input type="text" id="booking_price" name="booking_price">
        </div>
        <div>
            <button name="addCar" type="submit">Add Car</button>
        </div>
    </form>
</body>

</html>