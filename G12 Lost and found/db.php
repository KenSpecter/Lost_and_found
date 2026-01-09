<?php
include "db.php";

$item = $_POST['item_name'];
$type = $_POST['item_type'];
$desc = $_POST['description'];
$loc = $_POST['location'];
$sid = $_POST['school_id'];
$phone = $_POST['phone'];
$date = date("Y-m-d");

$imagePath = "";
if (!empty($_FILES["image"]["name"][0])) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $imagePath = $targetDir . basename($_FILES["image"]["name"][0]);
    move_uploaded_file($_FILES["image"]["tmp_name"][0], $imagePath);
}

$stmt = $conn->prepare("INSERT INTO items (item_name,item_type,description,location,school_id,phone,date_found) VALUES (?,?,?,?,?,?,?)");
$stmt->bind_param("sssssss", $item, $type, $desc, $loc, $sid, $phone, $date);
$stmt->execute();

echo "success";
?>
