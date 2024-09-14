<?php
session_start();

// Database configuration
include 'Database.php';

// Initialize message variables
$generate_key_message = "";
$manage_key_message = "";
$ban_devices_message = "";
$upload_message = "";
$failed_message = "";

// Determine which tab to show
$current_tab = isset($_SESSION['current_tab']) ? $_SESSION['current_tab'] : 'generate_key';

// Handle key and device operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['generate_key'])) {
        // Create a new key
        $key_name = $_POST['key_name'];
        $expiration_date = $_POST['expiration_date'];
        $usage_limit = intval($_POST['usage_limit']);
        
        $expiration_date = date('Y-m-d', strtotime($expiration_date));
        $key = $key_name;

        $sql = "INSERT INTO api_keys (key_value, expiration_date, usage_limit) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssi", $key, $expiration_date, $usage_limit);

            if ($stmt->execute()) {
                $generate_key_message = "Key Created Successfully, Your Key : " . htmlspecialchars($key) . ".";
            } else {
                $failed_message = "An error occurred while executing the statement: " . $stmt->error . ".";
            }

            $stmt->close();
        } else {
            $failed_message = "An error occurred while preparing the statement: " . $conn->error . ".";
        }

        // Stay on the generate_key tab
        $current_tab = 'generate_key';
        $_SESSION['current_tab'] = $current_tab;
    } elseif (isset($_POST['delete_key']) && !empty($_POST['key_id'])) {
        $key_id = $_POST['key_id'];

        // Begin transaction
        $conn->begin_transaction();
        try {
            // Delete all device records associated with the specified api_key_id
            $sql = "DELETE FROM device_ids WHERE api_key_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $key_id);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new Exception("An error occurred while preparing the statement: " . $conn->error . ".");
            }

            // Delete the key itself
            $sql = "DELETE FROM api_keys WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $key_id);
                if ($stmt->execute()) {
                    $conn->commit();
                    $manage_key_message = "Key Deleted Successfully !";
                } else {
                    throw new Exception("An error occurred while deleting the key: " . $stmt->error . ".");
                }
                $stmt->close();
            } else {
                throw new Exception("An error occurred while preparing the statement: " . $conn->error . ".");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $manage_key_message = $e->getMessage();
        }

        // Stay on the manage_key tab
        $current_tab = 'manage_key';
        $_SESSION['current_tab'] = $current_tab;
    } elseif (isset($_POST['update_expiration']) && !empty($_POST['key_id'])) {
        $key_id = $_POST['key_id'];
        $new_expiration_date = $_POST['new_expiration_date'];
        
        $new_expiration_date = date('Y-m-d', strtotime($new_expiration_date));

        $sql = "UPDATE api_keys SET expiration_date = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $new_expiration_date, $key_id);
            if ($stmt->execute()) {
                $manage_key_message = "Updated New Time Limit For Successful Key !";
            } else {
                $failed_message = "An error occurred while executing the statement: " . $stmt->error . ".";
            }
            $stmt->close();
        } else {
            $failed_message = "An error occurred while preparing the statement: " . $conn->error . ".";
        }

        // Stay on the manage_key tab
        $current_tab = 'manage_key';
        $_SESSION['current_tab'] = $current_tab;
    } elseif (isset($_POST['set_usage_limit']) && !empty($_POST['key_id'])) {
        $key_id = $_POST['key_id'];
        $new_usage_limit = intval($_POST['new_usage_limit']);

        $sql = "UPDATE api_keys SET usage_limit = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ii", $new_usage_limit, $key_id);
            if ($stmt->execute()) {
                $manage_key_message = "Updated User Limit For Key Successfully !";
            } else {
                $failed_message = "An error occurred while executing the statement: " . $stmt->error . ".";
            }
            $stmt->close();
        } else {
            $failed_message = "An error occurred while preparing the statement: " . $conn->error . ".";
        }

        // Stay on the manage_key tab
        $current_tab = 'manage_key';
        $_SESSION['current_tab'] = $current_tab;
    } elseif (isset($_POST['reset_user_count_specific']) && !empty($_POST['key_id'])) {
        $key_id = $_POST['key_id'];

        // Begin transaction
        $conn->begin_transaction();
        try {
            // Delete all device records associated with the specified api_key_id
            $sql = "DELETE FROM device_ids WHERE api_key_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $key_id);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new Exception("An error occurred while preparing the statement: " . $conn->error . ".");
            }

            // Reset current_usage to 0 for the specified API key
            $sql = "UPDATE api_keys SET current_usage = 0 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $key_id);
                if ($stmt->execute()) {
                    $conn->commit();
                    $manage_key_message = "Reset All Users Using 0 Successful !";
                } else {
                    throw new Exception("An error occurred while updating the user count: " . $stmt->error . ".");
                }
                $stmt->close();
            } else {
                throw new Exception("An error occurred while preparing the statement: " . $conn->error . ".");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $manage_key_message = $e->getMessage();
        }

        // Stay on the manage_key tab
        $current_tab = 'manage_key';
        $_SESSION['current_tab'] = $current_tab;
    } elseif (isset($_POST['ban_action']) && !empty($_POST['device_id']) && !empty($_POST['ban_reason'])) {
        $device_id = $_POST['device_id'];
        $ban_reason = $_POST['ban_reason'];

        // Insert or update the banned device
        $sql = "INSERT INTO banned_devices (device_id, ban_reason) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE ban_reason = VALUES(ban_reason), banned_at = CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ss", $device_id, $ban_reason);
            if ($stmt->execute()) {
                $ban_devices_message = "Banned Device ID :" . htmlspecialchars($device_id) . ", With Reason : " . htmlspecialchars($ban_reason) . " !";
            } else {
                $ban_devices_message = "An error occurred while executing the statement: " . $stmt->error . ".";
            }
            $stmt->close();
        } else {
            $failed_message = "An error occurred while preparing the statement: " . $conn->error . ".";
        }

       // Stay on the ban_devices tab
$current_tab = 'ban_devices';
$_SESSION['current_tab'] = $current_tab;
} elseif (isset($_POST['unban_action']) && !empty($_POST['device_id'])) {
    $device_id = $_POST['device_id'];

    // Begin transaction
    $conn->begin_transaction();
    try {
        // Delete the device from the banned_devices table
        $sql = "DELETE FROM banned_devices WHERE device_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $device_id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            $ban_devices_message = "Unbanned Device ID : " . htmlspecialchars($device_id) . " Successfully !";
        } else {
            throw new Exception("An error occurred while preparing the statement: " . $conn->error . ".");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $ban_devices_message = $e->getMessage();
    }

    // Stay on the ban_devices tab
    $current_tab = 'ban_devices';
    $_SESSION['current_tab'] = $current_tab;
}
}


// Handle file upload if a POST request is made
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $uploadDir = 'Scripts/'; // Directory path to store the uploaded file
        $uploadFile = $uploadDir . 'Main.lua'; // Rename the file to Main.lua

        // Check the file extension
        $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($fileType !== 'lua') {
            $failed_message = "Only .lua files are allowed.";
        } else {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $failed_message = "File upload error: " . $file['error'] . ".";
            } else {
                // Move the uploaded file to the target directory
                if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                    $upload_message = "File uploaded successfully.";
                } else {
                    $failed_message = "File upload failed.";
                }
            }
        }
    }
}

// Retrieve key options from the database
$key_options = '';
$sql = "SELECT id, key_value FROM api_keys";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $key_options .= "<option value=\"" . htmlspecialchars($row['id']) . "\">" . htmlspecialchars($row['key_value']) . "</option>";
}

// Retrieve banned devices from the database (distinct device IDs)
$banned_device_options = '';
$sql = "SELECT DISTINCT device_id FROM banned_devices";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $banned_device_options .= "<option value=\"" . htmlspecialchars($row['device_id']) . "\">" . htmlspecialchars($row['device_id']) . "</option>";
}

// Retrieve unique device IDs for ban dropdown
$select_device_options = '';
$sql = "SELECT DISTINCT device_id FROM device_ids";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $select_device_options .= "<option value=\"" . htmlspecialchars($row['device_id']) . "\">" . htmlspecialchars($row['device_id']) . "</option>";
}

$conn->close();
?>