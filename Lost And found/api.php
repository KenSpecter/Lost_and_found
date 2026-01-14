<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'email_config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_GET['request']) ? $_GET['request'] : '';

switch($request) {
    case 'login':
        handleLogin($db);
        break;
    case 'items':
        handleItems($db, $method);
        break;
    case 'upload':
        handleUpload();
        break;
    case 'notifications':
        handleNotifications($db, $method);
        break;
    default:
        echo json_encode(array("message" => "Invalid endpoint"));
}

function handleLogin($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(empty($data->username) || empty($data->password)) {
        http_response_code(400);
        echo json_encode(array("message" => "Username and password required"));
        return;
    }

    $query = "SELECT id, username, email, role FROM users WHERE username = :username AND role = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $data->username);
    $stmt->execute();

    if($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($data->password === 'admin123') {
            session_start();
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            echo json_encode(array(
                "message" => "Login successful",
                "user" => $row
            ));
        } else {
            http_response_code(401);
            echo json_encode(array("message" => "Invalid credentials"));
        }
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "User not found"));
    }
}

function handleItems($db, $method) {
    switch($method) {
        case 'GET':
            getItems($db);
            break;
        case 'POST':
            createItem($db);
            break;
        case 'PUT':
            updateItem($db);
            break;
        case 'DELETE':
            deleteItem($db);
            break;
    }
}

function getItems($db) {
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

    $query = "SELECT * FROM items WHERE 1=1";
    
    if(!empty($category)) $query .= " AND category = :category";
    if(!empty($status)) $query .= " AND status = :status";
    if(!empty($type)) $query .= " AND type = :type";
    
    $query .= " ORDER BY " . ($sort === 'date' ? 'created_at DESC' : 'name ASC');
    
    $stmt = $db->prepare($query);
    
    if(!empty($category)) $stmt->bindParam(':category', $category);
    if(!empty($status)) $stmt->bindParam(':status', $status);
    if(!empty($type)) $stmt->bindParam(':type', $type);
    
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function createItem($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO items (name, description, category, type, date_lost, date_found, 
              location, contact_email, contact_phone, school_id, photo_url) 
              VALUES (:name, :description, :category, :type, :date_lost, :date_found, 
              :location, :contact_email, :contact_phone, :school_id, :photo_url)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $data->name);
    $stmt->bindParam(':description', $data->description);
    $stmt->bindParam(':category', $data->category);
    $stmt->bindParam(':type', $data->type);
    $stmt->bindParam(':date_lost', $data->date_lost);
    $stmt->bindParam(':date_found', $data->date_found);
    $stmt->bindParam(':location', $data->location);
    $stmt->bindParam(':contact_email', $data->contact_email);
    $stmt->bindParam(':contact_phone', $data->contact_phone);
    $stmt->bindParam(':school_id', $data->school_id);
    $stmt->bindParam(':photo_url', $data->photo_url);
    
    if($stmt->execute()) {
        $item_id = $db->lastInsertId();
        checkMatchesWithEmail($db, $item_id, $data);
        echo json_encode(array("message" => "Item created", "id" => $item_id));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to create item"));
    }
}

function updateItem($db) {
    $emailService = new EmailNotification();
    $data = json_decode(file_get_contents("php://input"));
    
    // ✅ FIXED: First get item details
    $selectQuery = "SELECT name, contact_email FROM items WHERE id = :id";
    $selectStmt = $db->prepare($selectQuery);
    $selectStmt->bindParam(':id', $data->id);
    $selectStmt->execute();
    $item = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ FIXED: Then update the item
    $updateQuery = "UPDATE items SET status = :status, date_returned = :date_returned WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':status', $data->status);
    $updateStmt->bindParam(':date_returned', $data->date_returned);
    $updateStmt->bindParam(':id', $data->id);
    
    if($updateStmt->execute()) {
        if($data->status === 'returned' && $item) {
            $emailService->notifyItemReturned($item['name'], $item['contact_email']);
            createNotification($db, $data->id, $item['contact_email'], 
                "Your item has been marked as returned! Check your email for details.", "status_change");
        }
        
        echo json_encode(array("message" => "Item updated"));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to update item"));
    }
}

function deleteItem($db) {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    $query = "DELETE FROM items WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        echo json_encode(array("message" => "Item deleted"));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to delete item"));
    }
}

function handleUpload() {
    if(isset($_FILES['photo'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $file_name;
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        
        if(in_array(strtolower($file_extension), $allowed_types)) {
            if(move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                echo json_encode(array("message" => "File uploaded", "url" => $target_file));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Upload failed"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid file type"));
        }
    }
}

function handleNotifications($db, $method) {
    if($method === 'GET') {
        $email = isset($_GET['email']) ? $_GET['email'] : '';
        $query = "SELECT n.*, i.name as item_name FROM notifications n 
                  JOIN items i ON n.item_id = i.id 
                  WHERE n.recipient_email = :email 
                  ORDER BY n.created_at DESC LIMIT 20";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

function createNotification($db, $item_id, $email, $message, $type) {
    error_log("Creating notification for item_id: $item_id, email: $email");
    
    try {
        $query = "INSERT INTO notifications (item_id, recipient_email, message, type) 
                  VALUES (:item_id, :email, :message, :type)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':item_id', $item_id);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        
        $result = $stmt->execute();
        error_log($result ? "✅ Notification created" : "❌ Notification failed");
        return $result;
    } catch(PDOException $e) {
        error_log("❌ Notification error: " . $e->getMessage());
        return false;
    }
}

function checkMatchesWithEmail($db, $item_id, $new_item) {
    error_log("=== MATCHING STARTED ===");
    error_log("New item ID: " . $item_id);
    error_log("New item type: " . $new_item->type);
    error_log("New item name: " . $new_item->name);
    
    $emailService = new EmailNotification();
    $opposite_type = $new_item->type === 'lost' ? 'found' : 'lost';
    error_log("Looking for opposite type: " . $opposite_type);
    
    $query = "SELECT * FROM items WHERE type = :type AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':type', $opposite_type);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($items) . " items to check");
    
    if(count($items) === 0) {
        error_log("No items to compare - exiting");
        return;
    }
    
    foreach($items as $item) {
        error_log("Checking item #" . $item['id'] . ": " . $item['name']);
        
        // ✅ FIXED: Use calculateSimilarity instead of similarText
        $similarity = calculateSimilarity(
            strtolower($new_item->name . ' ' . $new_item->description),
            strtolower($item['name'] . ' ' . $item['description'])
        );
        
        error_log("Similarity score: " . $similarity . "%");
        
        if($similarity > 60) {
            error_log("🎯 MATCH FOUND! Similarity: " . $similarity . "%");
            
            $match_details = array(
                'name' => $new_item->name,
                'description' => $new_item->description,
                'location' => $new_item->location,
                'date' => $new_item->type === 'lost' ? $new_item->date_lost : $new_item->date_found,
                'contact_email' => $new_item->contact_email
            );
            
            error_log("Sending email to: " . $item['contact_email']);
            $result1 = $emailService->notifyMatchFound(
                $item['name'],
                $item['contact_email'],
                $item['type'],
                $match_details
            );
            error_log("Email 1 result: " . ($result1 ? "SUCCESS" : "FAILED"));
            
            $existing_match_details = array(
                'name' => $item['name'],
                'description' => $item['description'],
                'location' => $item['location'],
                'date' => $item['type'] === 'lost' ? $item['date_lost'] : $item['date_found'],
                'contact_email' => $item['contact_email']
            );
            
            error_log("Sending email to: " . $new_item->contact_email);
            $result2 = $emailService->notifyMatchFound(
                $new_item->name,
                $new_item->contact_email,
                $new_item->type,
                $existing_match_details
            );
            error_log("Email 2 result: " . ($result2 ? "SUCCESS" : "FAILED"));
            
            error_log("Creating notification for new item owner");
            $notif1 = createNotification($db, $item_id, $new_item->contact_email,
                "Potential match found! Check your email for details.", "match_found");
            error_log("Notification 1 result: " . ($notif1 ? "SUCCESS" : "FAILED"));
            
            error_log("Creating notification for existing item owner");
            $notif2 = createNotification($db, $item['id'], $item['contact_email'],
                "Potential match found! Check your email for details.", "match_found");
            error_log("Notification 2 result: " . ($notif2 ? "SUCCESS" : "FAILED"));
            
        } else {
            error_log("No match - similarity too low (" . $similarity . "%)");
        }
    }
    
    error_log("=== MATCHING FINISHED ===");
}

function calculateSimilarity($str1, $str2) {
    similar_text($str1, $str2, $percent);
    return $percent;
}
?>
