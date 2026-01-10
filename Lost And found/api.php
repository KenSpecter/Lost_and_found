<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'config.php';

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
    case 'match':
        handleMatching($db);
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
    
    if(!empty($category)) {
        $query .= " AND category = :category";
    }
    if(!empty($status)) {
        $query .= " AND status = :status";
    }
    if(!empty($type)) {
        $query .= " AND type = :type";
    }
    
    $query .= " ORDER BY " . ($sort === 'date' ? 'created_at DESC' : 'name ASC');
    
    $stmt = $db->prepare($query);
    
    if(!empty($category)) $stmt->bindParam(':category', $category);
    if(!empty($status)) $stmt->bindParam(':status', $status);
    if(!empty($type)) $stmt->bindParam(':type', $type);
    
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($items);
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
        checkMatches($db, $item_id, $data);
        echo json_encode(array("message" => "Item created", "id" => $item_id));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to create item"));
    }
}

function updateItem($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "UPDATE items SET status = :status, date_returned = :date_returned 
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':status', $data->status);
    $stmt->bindParam(':date_returned', $data->date_returned);
    $stmt->bindParam(':id', $data->id);
    
    if($stmt->execute()) {
        if($data->status === 'returned') {
            createNotification($db, $data->id, $data->contact_email, 
                             "Your item has been marked as returned!", "status_change");
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
                echo json_encode(array(
                    "message" => "File uploaded",
                    "url" => $target_file
                ));
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
    $query = "INSERT INTO notifications (item_id, recipient_email, message, type) 
              VALUES (:item_id, :email, :message, :type)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $type);
    
    return $stmt->execute();
}

function checkMatches($db, $item_id, $new_item) {
    $opposite_type = $new_item->type === 'lost' ? 'found' : 'lost';
    
    $query = "SELECT * FROM items WHERE type = :type AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':type', $opposite_type);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($items as $item) {
        $similarity = similarText(
            strtolower($new_item->name . ' ' . $new_item->description),
            strtolower($item['name'] . ' ' . $item['description'])
        );
        
        if($similarity > 60) {
            createNotification($db, $item_id, $new_item->contact_email,
                "Potential match found for your " . $new_item->type . " item!", "match_found");
            
            createNotification($db, $item['id'], $item['contact_email'],
                "Potential match found for your " . $item['type'] . " item!", "match_found");
        }
    }
}

function similarText($str1, $str2) {
    similar_text($str1, $str2, $percent);
    return $percent;
}

function handleMatching($db) {
    checkMatches($db, 0, (object)array(
        'type' => 'lost',
        'name' => 'test',
        'description' => 'test',
        'contact_email' => 'test@test.com'
    ));
    echo json_encode(array("message" => "Matching completed"));
}
?>