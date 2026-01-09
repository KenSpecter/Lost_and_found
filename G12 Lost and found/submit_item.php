<?php
include "db.php";

$search = isset($_GET['q']) ? "%" . $_GET['q'] . "%" : "%";
$status = isset($_GET['status']) ? $_GET['status'] : "";
$type = isset($_GET['type']) ? $_GET['type'] : "";

$sql = "SELECT * FROM items WHERE item_name LIKE ?";
if ($status) $sql .= " AND status = '$status'";
if ($type) $sql .= " AND item_type = '$type'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode($items);
?>
