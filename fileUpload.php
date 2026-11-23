<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$path = "images/";
$is_uploaded = false;
if(isset($_POST['submit']) && isset($_FILES['img'])){
    $img_name = $_FILES['img']['name'];
    $img_size = $_FILES['img']['size'];

    echo "<br>";
    echo "Image Name: $img_name";
    echo "<br>";
    echo "Image Size: $img_size";
    if($img_size > 2000000){ // 2MB
        echo "<br>";
        echo "Image is too large, upload a smaller image";
    }
    $img_name = strtolower($img_name). time();

    $file = $path . $img_name; // this is what is saved in the database
    echo "<br>";
    echo "File Path: $file";
    try{
        if(move_uploaded_file($_FILES['img']['tmp_name'], $file)){
            echo "<br>";
            // write a query that puts the path in the database
            $picture_quey = "INSERT";// the query goes here
            echo "Image Uploaded Successfully";
            $is_uploaded = true;
        }else{
            echo "<br>";
            echo "Image Upload Failed";
        }
    }catch(Exception $e){
        echo "<br>";
        echo "Error: " . $e->getMessage();
    }
}
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
        <form action="fileUpload.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="img">
            <button name="submit" type="submit">Upload</button>
        </form>

        <img src="<?php echo $is_uploaded ? "$file" : 'images/test.png'; ?>" alt="Image suppose dey here">
    </main>
</body>
</html>