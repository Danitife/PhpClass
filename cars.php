<?php
include __DIR__ . "/auth/authUser.php";

$all_cars_stmt = $config->prepare("SELECT * FROM cars");
$all_cars_stmt->execute();
$all_cars_response = $all_cars_stmt->get_result();
$cars = $all_cars_response->fetch_all(MYSQLI_ASSOC);

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Document</title>
</head>

<body>
    <main>
        <?php include __DIR__ . "/components/navbar.html"; ?>
        <h1>All Cars</h1>
        <pre><?php if (count($cars) < 1) {
                    echo "No cars found";
                } else {
                    echo "
                <table class='table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Car Name</th>
                            <th>Car Model</th>
                            <th>Car Number</th>
                            <th>Car Color</th>
                            <th>Booking Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>";
                    foreach ($cars as $car) {
                        echo "<tr>
                            <td>{$car['id']}</td>
                            <td>{$car['car_name']}</td>
                            <td>{$car['car_model']}</td>
                            <td>{$car['car_number']}</td>
                            <td>{$car['car_color']}</td>
                            <td>₦{$car['booking_price']}</td>
                            <td><a href='services/book-ride.php?car_id={$car['id']}' class='btn btn-primary'>Book Ride</a></td>
                        </tr>";
                    }
                    echo "
                    </tbody>
                </table>";
                } ?></pre>
    </main>
</body>

</html>