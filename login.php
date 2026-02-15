<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "mydb.itap.purdue.edu";
$db_username = "g1151934"; 
$db_password = "group24"; 
$database = $db_username;

$conn = new mysqli($servername, $db_username, $db_password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ==================== HANDLE LOGIN ====================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $input_password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($input_username) || empty($input_password)) {
        die("Error: Username and password are required.");
    }

    $stmt = $conn->prepare("SELECT Password, Role FROM User WHERE Username = ?");
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $input_username);

    if ($stmt->execute()) {
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($stored_password, $role);
            $stmt->fetch();

            if ($input_password === $stored_password) {
                $_SESSION['username'] = $input_username;
                $_SESSION['role'] = $role;

                // Redirect based on role
                if ($role === 'SupplyChainManager') {
                    header("Location: scmanager.php");
                    exit();
                } elseif ($role === 'SeniorManager') {
                    header("Location: seniormanager.php");
                    exit();
                } else {
                    die("Error: Unknown role - " . htmlspecialchars($role));
                }
            } else {
                die("Error: Invalid password for user '" . htmlspecialchars($input_username) . "'");
            }
        } else {
            die("Error: No user found with username '" . htmlspecialchars($input_username) . "'");
        }
    } else {
        die("Execute error: " . $stmt->error);
    }

    $stmt->close();
} else {
    die("Error: Invalid request method. Please submit the login form.");
}

$conn->close();
?>