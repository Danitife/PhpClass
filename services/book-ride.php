<?php
include dirname(__DIR__) . "/auth/authUser.php";
include dirname(__DIR__) . "/database/config.php";
$car_id = $_GET['car_id'];
$car_query = $config->prepare("SELECT * FROM cars WHERE id=?");
$car_query->bind_param("i", $car_id);
$car_query->execute();
$car_response = $car_query->get_result();
if (!$car_response) {
    echo "Error: " . mysqli_error($config);
    exit();
}
$car = $car_response->fetch_assoc();
if (!$car) {
    echo "Car not found";
    exit();
}
if (isset($_POST['book_now'])) {
    $days = $_POST['days'];
    $amount = $car['booking_price'] * $days;
    echo "You have booked a ride for {$car['car_name']} for {$days} days. Total amount: ₦{$amount}";
    $book_ride_stmt = $config->prepare("INSERT INTO bookings (user_id, car_id, estimated_days_spent, amount_paid) VALUES (?, ?, ?, ?)");
    $book_ride_stmt->bind_param("iiis", $user['id'], $car_id, $days, $amount);
    if ($book_ride_stmt->execute()) {
        $update_car_stmt = $config->prepare("UPDATE cars SET is_booked=1 WHERE id=?");
        $update_car_stmt->bind_param("i", $car_id);
        if ($update_car_stmt->execute()) {
            echo "Car booked successfully!";
        } else {
            echo "Error: " . $update_car_stmt->error;
        }
    }
} else {
    echo "Error: " . $book_ride_stmt->error;
}

// If a car has been booked, the button should be showing "Booked" for other users and "Return Car" for the user that booked the car.


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <main>
        <form action="" method="POST">
            <h1>Book Ride for <?php echo $car['car_name']; ?></h1>
            <h2>Amount per day: <?php echo $car['booking_price']; ?></h2>
            <h1>How many days are you booking for?</h1>
            <input type="number" name="days">
            <button type="submit" name="book_now">Book Now</button>
        </form>
    </main>
</body>

</html>