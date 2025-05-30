<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start logging
$debug_log = fopen("cart_actions_debug.log", "a");
fwrite($debug_log, "\n=== Cart Action Request at " . date('Y-m-d H:i:s') . " ===\n");

// Start session
session_start();
fwrite($debug_log, "Session started\n");

// Log session data
fwrite($debug_log, "SESSION data: " . print_r($_SESSION, true) . "\n");
fwrite($debug_log, "GET data: " . print_r($_GET, true) . "\n");
fwrite($debug_log, "POST data: " . print_r($_POST, true) . "\n");

// Include database connection
if (file_exists('db_connect.php')) {
    include 'db_connect.php';
    fwrite($debug_log, "Database connection included\n");
} else {
    fwrite($debug_log, "ERROR: db_connect.php file not found\n");
    echo "db_connection_error";
    fclose($debug_log);
    exit;
}

// Test parameter for direct access testing
if (isset($_GET['test'])) {
    fwrite($debug_log, "This is a test request\n");
    echo "Test request received successfully. Server is working.";
    fclose($debug_log);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    fwrite($debug_log, "ERROR: User not logged in\n");
    echo "not_logged_in";
    fclose($debug_log);
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];
fwrite($debug_log, "User ID: $user_id\n");

// Get action
$action = $_POST['action'] ?? ($_GET['action'] ?? ''); // Check POST first, then GET
fwrite($debug_log, "Action: $action\n");

// Get product id
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
if ($product_id == 0 && isset($_GET['product_id'])) {
    // For direct URL testing
    $product_id = intval($_GET['product_id']);
}
fwrite($debug_log, "Product ID: $product_id\n");

// Validation
if ($product_id <= 0) {
    fwrite($debug_log, "ERROR: Invalid product ID\n");
    echo "invalid_product";
    fclose($debug_log);
    exit;
}

// Check if database connection is valid
if (!isset($conn) || $conn->connect_error) {
    fwrite($debug_log, "ERROR: Database connection failed\n");
    echo "db_connection_error";
    fclose($debug_log);
    exit;
}

// Log database connection status
fwrite($debug_log, "Database connection status: " . ($conn->ping() ? "Connected" : "Not connected") . "\n");

if ($action == 'add') {
    // Log addition attempt
    fwrite($debug_log, "Attempting to add product $product_id to cart for user $user_id\n");
    
    // Check if product exists in the products table
    $product_check = mysqli_query($conn, "SELECT id FROM products WHERE id='$product_id'");
    if (!$product_check || mysqli_num_rows($product_check) == 0) {
        fwrite($debug_log, "ERROR: Product does not exist in database\n");
        echo "product_not_found";
        fclose($debug_log);
        exit;
    }
    
    // Check if product is already in cart
    $check = mysqli_query($conn, "SELECT * FROM cart WHERE user_id='$user_id' AND product_id='$product_id'");
    
    // Log query
    fwrite($debug_log, "Check query executed: " . ($check !== false ? "Success" : "Failed - " . mysqli_error($conn)) . "\n");
    
    if ($check && mysqli_num_rows($check) > 0) {
        fwrite($debug_log, "Product already in cart\n");
        echo "already_in_cart";
    } else {
        // Insert into cart
        $insert = mysqli_query($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES ('$user_id', '$product_id', 1)");
        
        // Log insert query
        fwrite($debug_log, "Insert query executed: " . ($insert ? "Success" : "Failed - " . mysqli_error($conn)) . "\n");
        
        if ($insert) {
            fwrite($debug_log, "Product added to cart successfully\n");
            echo "added";
        } else {
            fwrite($debug_log, "ERROR: Failed to add product - " . mysqli_error($conn) . "\n");
            echo "insert_failed";
        }
    }
} elseif ($action == 'remove') {
    // Remove from cart
    fwrite($debug_log, "Attempting to remove product $product_id from cart for user $user_id\n");
    
    $delete = mysqli_query($conn, "DELETE FROM cart WHERE user_id='$user_id' AND product_id='$product_id'");
    
    // Log delete query
    fwrite($debug_log, "Delete query executed: " . ($delete ? "Success" : "Failed - " . mysqli_error($conn)) . "\n");
    
    if ($delete) {
        fwrite($debug_log, "Product removed from cart successfully\n");
        echo "removed";
    } else {
        fwrite($debug_log, "ERROR: Failed to remove product - " . mysqli_error($conn) . "\n");
        echo "remove_failed";
    }
} elseif ($action == 'update') {
    // Update quantity
    $qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($qty < 1) $qty = 1;
    
    fwrite($debug_log, "Attempting to update product $product_id quantity to $qty for user $user_id\n");
    
    $update = mysqli_query($conn, "UPDATE cart SET quantity='$qty' WHERE user_id='$user_id' AND product_id='$product_id'");
    
    // Log update query
    fwrite($debug_log, "Update query executed: " . ($update ? "Success" : "Failed - " . mysqli_error($conn)) . "\n");
    
    if ($update) {
        fwrite($debug_log, "Product quantity updated successfully\n");
        echo "updated";
    } else {
        fwrite($debug_log, "ERROR: Failed to update quantity - " . mysqli_error($conn) . "\n");
        echo "update_failed";
    }
} else {
    fwrite($debug_log, "ERROR: Invalid action\n");
    echo "invalid_action";
}

// Close database connection
$conn->close();
fwrite($debug_log, "Database connection closed\n");
fwrite($debug_log, "=== End of request ===\n");
fclose($debug_log);
?>