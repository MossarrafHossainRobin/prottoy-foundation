<?php
session_start();

require_once 'db.php'; // Ensure this file correctly establishes $conn for MySQLi

// Redirect to login if user is not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Function for sanitizing input
function sanitize_input($data) {
    global $conn; // Access the database connection
    $data = trim($data);
    $data = stripslashes($data);
    // htmlspecialchars is important for outputting to HTML to prevent XSS
    // For DB insertion via bind_param, mysqli_real_escape_string is not strictly needed on the string itself,
    // as prepared statements handle escaping. We still use htmlspecialchars for general cleaning of form values.
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Function to handle profile picture upload for the user dashboard
function handle_user_profile_picture_upload($file_input_name, $user_id, $conn) {
    $upload_dir = 'uploads/profile_pics/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            return ['error' => 'Server error: Cannot create upload directory. Check permissions.'];
        }
    }
    if (!is_writable($upload_dir)) {
        error_log("Upload directory is not writable: " . $upload_dir);
        return ['error' => 'Server error: Upload directory is not writable. Check permissions.'];
    }

    // Initialize $new_profile_picture_path
    $new_profile_picture_path = null;

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return ['error' => 'Upload failed: File is too large. Maximum size is 5MB.'];
        }

        // Use finfo to get actual MIME type for better security
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types)) {
            return ['error' => 'Upload failed: Invalid file type. Only JPG, PNG, and GIF are allowed.'];
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Use a more robust unique ID for filename
        $safe_filename = "user_{$user_id}_" . bin2hex(random_bytes(10)) . '.' . $file_extension;
        $target_file_path = $upload_dir . $safe_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file_path)) {
            $new_profile_picture_path = $target_file_path;
        } else {
            error_log("File upload failed for user_id: {$user_id}. Error code: " . $file['error']);
            return ['error' => 'Server error: Could not save the uploaded file.'];
        }
    }
    return ['success' => $new_profile_picture_path];
}

// Handle POST requests (Profile Update & Password Change)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');

    if ($conn->connect_error) {
        http_response_code(500);
        error_log("Database connection failed: " . $conn->connect_error);
        echo json_encode(['success' => false, 'error' => 'Server error: Cannot connect to the database.']);
        exit;
    }

    $response = ['success' => false];

    // --- Handle Password Change ---
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $response['error'] = 'All password fields are required.';
            echo json_encode($response);
            exit;
        }

        if ($new_password !== $confirm_new_password) {
            $response['error'] = 'New password and confirm password do not match.';
            echo json_encode($response);
            exit;
        }

        if (strlen($new_password) < 6) {
            $response['error'] = 'New password must be at least 6 characters long.';
            echo json_encode($response);
            exit;
        }

        // Fetch current hashed password from DB
        $sql_fetch_pass = "SELECT password FROM users WHERE id = ?";
        $stmt_fetch_pass = $conn->prepare($sql_fetch_pass);
        if ($stmt_fetch_pass === false) {
            $response['error'] = 'Database error during password verification.';
            error_log("SQL prepare failed (fetch password): " . $conn->error);
            echo json_encode($response);
            exit;
        }
        $stmt_fetch_pass->bind_param("i", $user_id);
        $stmt_fetch_pass->execute();
        $result_fetch_pass = $stmt_fetch_pass->get_result();
        $user_data = $result_fetch_pass->fetch_assoc();
        $stmt_fetch_pass->close();

        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            $response['error'] = 'Incorrect current password.';
            echo json_encode($response);
            exit;
        }

        // Hash new password and update
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql_update_pass = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt_update_pass = $conn->prepare($sql_update_pass);
        if ($stmt_update_pass === false) {
            $response['error'] = 'Database error during password update.';
            error_log("SQL prepare failed (update password): " . $conn->error);
            echo json_encode($response);
            exit;
        }
        $stmt_update_pass->bind_param("si", $hashed_new_password, $user_id);
        if ($stmt_update_pass->execute()) {
            $response['success'] = true;
            $response['message'] = 'Password updated successfully!';
        } else {
            $response['error'] = 'Failed to update password. Please try again.';
            error_log("DB execute failed (update password) for user_id {$user_id}: " . $stmt_update_pass->error);
        }
        $stmt_update_pass->close();
        echo json_encode($response);
        exit;
    }

    // --- Handle Profile Update ---
    try {
        $update_fields = [];
        $params = [];
        $types = "";

        $allowed_fields = ['name', 'phone', 'home_address', 'bio', 'interests'];
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $update_fields[] = "`{$field}` = ?";
                $params[] = sanitize_input($_POST[$field]);
                $types .= "s";
            }
        }

        // Handle profile picture upload
        $current_profile_picture_url = '';
        $sql_fetch_current_pic = "SELECT profile_picture_url FROM users WHERE id = ?";
        $stmt_fetch_current_pic = $conn->prepare($sql_fetch_current_pic);
        if ($stmt_fetch_current_pic) {
            $stmt_fetch_current_pic->bind_param("i", $user_id);
            $stmt_fetch_current_pic->execute();
            $result_pic = $stmt_fetch_current_pic->get_result();
            if ($row_pic = $result_pic->fetch_assoc()) {
                $current_profile_picture_url = $row_pic['profile_picture_url'];
            }
            $stmt_fetch_current_pic->close();
        } else {
             error_log("Failed to prepare statement for fetching current profile picture: " . $conn->error);
        }

        $upload_result = handle_user_profile_picture_upload('profile_picture', $user_id, $conn);
        $new_profile_picture_path = null;

        if (isset($upload_result['error'])) {
            $response['error'] = $upload_result['error'];
            echo json_encode($response);
            exit;
        } else {
            $new_profile_picture_path = $upload_result['success'];
            // If a new picture was uploaded or it was explicitly removed, update the field
            if ($new_profile_picture_path !== null) {
                // Delete old picture if a new one was uploaded
                if ($current_profile_picture_url && strpos($current_profile_picture_url, 'placehold.co') === false && file_exists($current_profile_picture_url) && $current_profile_picture_url !== $new_profile_picture_path) {
                    @unlink($current_profile_picture_url);
                }
                $update_fields[] = "`profile_picture_url` = ?";
                $params[] = $new_profile_picture_path;
                $types .= "s";
                $response['profile_picture_url'] = $new_profile_picture_path; // Send back new URL
            }
        }

        if (!empty($update_fields)) {
            $sql = "UPDATE `users` SET " . implode(', ', $update_fields) . ", `updated_at` = NOW() WHERE `id` = ?";
            $params[] = $user_id;
            $types .= "i";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                $response['error'] = 'Server error: Failed to prepare database statement for profile update.';
                error_log("SQL prepare failed (profile update): " . $conn->error);
            } else {
                // Using call_user_func_array to bind parameters dynamically
                $bind_params = [$types];
                foreach ($params as $key => $value) {
                    $bind_params[] = &$params[$key]; // Pass by reference
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_params);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    if (isset($_POST['name'])) {
                        $response['newName'] = sanitize_input($_POST['name']);
                    }
                    $response['message'] = 'Profile updated successfully!';
                } else {
                    $response['error'] = 'Database update failed. Please try again.';
                    error_log("DB execute failed (profile update) for user_id {$user_id}: " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            $response['success'] = true;
            $response['message'] = 'No new information to save.';
        }

    } catch (Exception $e) {
        http_response_code(500);
        $response['error'] = 'A critical server error occurred: ' . $e->getMessage();
        error_log("Exception in profile update for user_id {$user_id}: " . $e->getMessage());
    }

    $conn->close();
    echo json_encode($response);
    exit;
}

// Fetch user profile data
$name = ""; 
$email = ""; 
$phone = $home_address = $profile_picture_url = "";

$sql = "SELECT name, email, phone, home_address,profile_picture_url FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            $name = $user['name'];
            $email = $user['email'];
            $phone = $user['phone'];
            $home_address = $user['home_address'];
            $profile_picture_url = $user['profile_picture_url'];
        }
    } else {
        error_log("Error executing user data fetch: " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("Error preparing user data fetch statement: " . $conn->error);
}

// Fetch active announcements
$announcements = [];
$sql_announcements = "SELECT title, content, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC";
$stmt_announcements = $conn->prepare($sql_announcements);
if ($stmt_announcements) {
    if ($stmt_announcements->execute()) {
        $result_announcements = $stmt_announcements->get_result();
        while ($row = $result_announcements->fetch_assoc()) {
            $announcements[] = $row;
        }
    } else {
        error_log("Error executing announcement fetch: " . $stmt_announcements->error);
    }
    $stmt_announcements->close();
} else {
    error_log("Error preparing announcement fetch statement: " . $conn->error);
}

// Fetch recent activity/notifications (mock data for now, could be replaced with DB later)
$recent_activity = [
    ['type' => 'Login', 'message' => 'Logged in from a new device (IP: 203.0.113.45)', 'timestamp' => '2024-06-23 10:30 AM'],
    ['type' => 'Profile Update', 'message' => 'Updated profile picture', 'timestamp' => '2024-06-22 03:15 PM'],
    ['type' => 'Security', 'message' => 'Password changed successfully', 'timestamp' => '2024-06-18 11:00 AM'],
    ['type' => 'Info', 'message' => 'Welcome to Prottoy Foundation!', 'timestamp' => '2024-06-15 09:00 AM'],
];


$conn->close(); // Close connection after all fetches
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Prottoy Foundation</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <!-- Axios for AJAX requests -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <style>
        /* CSS Variables for a professional and consistent look */
        :root {
            --bs-primary: #1b4e80; /* Deep Blue for primary elements */
            --bs-secondary: #6c757d; /* Standard gray */
            --bs-success: #28a745; /* Green for success */
            --bs-info: #17a2b8;    /* Light blue for info */
            --bs-warning: #ffc107; /* Yellow for warning */
            --bs-danger: #dc3545;  /* Red for danger */
            --bs-dark: #343a40;    /* Dark text */

            --background-color: #f5f7fa; /* Very light subtle background */
            --card-background: #ffffff;
            --border-color: #e0e6ed;
            --text-color: #343a40;
            --text-secondary-color: #606f7b;
            --shadow-light: rgba(0, 0, 0, 0.05);
            --shadow-medium: rgba(0, 0, 0, 0.1);
            --shadow-heavy: rgba(0, 0, 0, 0.2);

            --border-radius-xl: 1.5rem; /* Larger border-radius for overall cards/sections */
            --border-radius-lg: 1rem;   /* Medium border-radius for inputs/buttons */
            --transition-speed: 0.3s;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            color: var(--bs-dark);
        }

        /* Navbar Styling */
        .navbar-custom {
            background-color: var(--bs-dark); /* Dark navbar background */
            box-shadow: 0 4px 15px var(--shadow-medium);
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--card-background) !important; /* White text for brand */
            transition: color var(--transition-speed) ease;
        }
        .navbar-brand:hover {
            color: var(--bs-primary) !important;
        }
        .btn-outline-light { /* Using btn-outline-light for logout for contrast */
            border-color: var(--card-background);
            color: var(--card-background);
            transition: all var(--transition-speed) ease;
            font-weight: 500;
            padding: 0.6rem 1.5rem;
            border-radius: var(--border-radius-lg);
        }
        .btn-outline-light:hover {
            background-color: var(--card-background);
            color: var(--bs-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-light);
        }

        /* Dashboard Container Layout */
        .dashboard-container {
            display: flex;
            gap: 2rem;
            max-width: 1400px;
            margin: 3.5rem auto;
            padding: 0 1.5rem;
            flex-grow: 1;
        }

        /* Sidebar Styling */
        .sidebar {
            flex: 0 0 320px; /* Wider sidebar for profile details */
            background-color: var(--card-background);
            border-radius: var(--border-radius-xl);
            padding: 2.5rem 1.8rem;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow-light);
            height: fit-content;
            position: sticky;
            top: 2rem;
            align-self: flex-start;
        }
        
        /* Main Content Area Styling */
        .main-content {
            flex-grow: 1;
            background-color: var(--card-background);
            border-radius: var(--border-radius-xl);
            padding: 3rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px var(--shadow-light);
        }
        
        /* Profile Picture Wrapper */
        .profile-picture-wrapper {
            position: relative;
            width: 180px; /* Larger profile picture */
            height: 180px;
            margin: 0 auto 1.8rem auto;
            cursor: pointer;
            border-radius: 50%;
            overflow: hidden;
            border: 6px solid var(--card-background);
            box-shadow: 0 0 0 4px var(--bs-primary), 0 8px 25px var(--shadow-medium);
            transition: all var(--transition-speed) ease;
        }
        .profile-picture-wrapper:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 0 0 4px var(--bs-primary), 0 12px 30px var(--shadow-heavy);
        }
        
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-speed) ease;
        }
        .profile-picture-wrapper:hover .profile-img {
            transform: scale(1.08);
        }

        .upload-icon-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 50px;
            background-color: var(--bs-primary);
            color: var(--card-background);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--card-background);
            transition: background-color var(--transition-speed) ease, transform 0.2s ease;
        }
        .profile-picture-wrapper:hover .upload-icon-overlay {
            background-color: var(--bs-dark);
            transform: scale(1.1);
        }
        .upload-icon-overlay i {
            font-size: 1.4rem;
        }

        /* User Info Styling */
        .user-name {
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            word-break: break-word;
            color: var(--text-color);
        }
        .user-email {
            font-weight: 400;
            color: var(--text-secondary-color);
            font-size: 1rem;
            word-break: break-all;
            margin-bottom: 0.2rem;
        }

        /* Navigation Pills (Sidebar) */
        .nav-pills {
            flex-direction: column;
            margin-top: 3rem;
        }
        .nav-pills .nav-item {
            width: 100%;
        }
        .nav-pills .nav-link {
            color: var(--text-color);
            font-weight: 500;
            padding: 1.2rem 1.5rem;
            border-radius: var(--border-radius-lg);
            transition: all var(--transition-speed) ease;
            margin-bottom: 0.8rem; 
            text-align: left;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.1rem;
        }
        .nav-pills .nav-link.active {
            background-color: var(--bs-primary);
            color: var(--card-background);
            box-shadow: 0 5px 15px rgba(27, 78, 128, 0.3); /* Shadow for active state */
            transform: translateX(8px);
        }
        .nav-pills .nav-link:not(.active):hover {
            background-color: var(--background-color);
            color: var(--bs-primary);
            transform: translateX(5px);
        }
        .nav-pills .nav-link i {
            font-size: 1.5rem;
        }

        /* Tab Content Headers */
        .tab-pane h3 {
            font-weight: 700;
            margin-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.25rem;
            color: var(--text-color);
            font-size: 2rem;
        }

        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control {
            border-radius: var(--border-radius-lg); 
            padding: 1rem 1.25rem;
            border: 1px solid var(--border-color);
            background-color: var(--card-background);
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
            font-size: 1rem;
        }
        .form-control:focus {
            background-color: var(--card-background);
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 4px rgba(27, 78, 128, 0.15);
        }
        .form-control::placeholder {
            color: var(--text-secondary-color);
            opacity: 0.7;
        }
        .form-text {
            font-size: 0.85rem;
            color: var(--text-secondary-color);
            margin-top: 0.4rem;
        }
        
        /* Save Button Styling */
        .btn-save {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            padding: 1rem 3rem;
            font-weight: 600;
            border-radius: var(--border-radius-lg);
            transition: all 0.25s ease;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 1.05rem;
            color: var(--card-background);
            box-shadow: 0 4px 10px rgba(27, 78, 128, 0.2);
        }
        .btn-save:hover {
            background-color: var(--bs-dark); /* Deeper blue on hover */
            border-color: var(--bs-dark);
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 8px 20px rgba(27, 78, 128, 0.4);
        }
        .btn-save:disabled {
            background-color: #b0c4de;
            border-color: #b0c4de;
            cursor: not-allowed;
            opacity: 0.8;
            transform: none;
            box-shadow: none;
        }

        /* Generic Button Hover Effects (for green, yellow, red, blue) */
        .btn-primary:hover {
            background-color: #1a436e; /* Darker primary */
            border-color: #1a436e;
        }
        .btn-success:hover {
            background-color: #218838; /* Darker success */
            border-color: #218838;
        }
        .btn-warning:hover {
            background-color: #e0a800; /* Darker warning */
            border-color: #e0a800;
        }
        .btn-danger:hover {
            background-color: #c82333; /* Darker danger */
            border-color: #c82333;
        }
        /* Applying same hover transform to all Bootstrap buttons */
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Activity Feed Styling */
        .activity-item {
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 1.2rem 1.8rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            box-shadow: 0 3px 10px var(--shadow-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .activity-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px var(--shadow-medium);
        }
        .activity-icon {
            font-size: 1.7rem;
            color: var(--bs-primary);
            flex-shrink: 0;
            width: 35px;
            text-align: center;
        }
        .activity-content strong {
            display: block;
            font-size: 1.1rem;
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .activity-content span {
            font-size: 0.9rem;
            color: var(--text-secondary-color);
        }

        /* Announcements Styling (Fiverr-like prominent design) */
        .announcement-card {
            background-color: var(--card-background); /* Simple white background */
            border: 1px solid var(--border-color);
            border-left: 6px solid var(--bs-danger); /* Bold red border for prominence */
            border-radius: var(--border-radius-xl);
            padding: 1.8rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.1); /* Red-tinted shadow */
            transition: all var(--transition-speed) ease;
        }
        .announcement-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 15px 40px rgba(220, 53, 69, 0.25); /* More pronounced red shadow on hover */
        }
        .announcement-card .card-title {
            font-weight: 700;
            color: var(--bs-danger); /* Title in red for announcements */
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .announcement-card .card-title i {
            font-size: 2rem; /* Larger icon */
            color: var(--bs-danger);
        }
        .announcement-card .card-text {
            color: var(--text-color);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .announcement-card .card-footer {
            font-size: 0.9rem;
            color: var(--text-secondary-color);
            border-top: 1px dashed var(--border-color);
            padding-top: 1.2rem;
            margin-top: 1.2rem;
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed; bottom: 30px; right: 30px; color: white; padding: 1.3rem 2.2rem;
            border-radius: var(--border-radius-lg); font-weight: 600; box-shadow: 0 10px 30px var(--shadow-medium);
            transform: translateY(150%); opacity: 0;
            transition: transform 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55), opacity 0.6s ease;
            z-index: 1060;
            min-width: 280px;
            text-align: center;
            font-size: 1.1rem;
        }
        .toast-notification.show { transform: translateY(0); opacity: 1; }
        .toast-notification.success { background-color: var(--bs-success); }
        .toast-notification.error { background-color: var(--bs-danger); }
        .toast-notification.info { background-color: var(--bs-info); }

        /* No data message */
        .no-data-message {
            text-align: center;
            padding: 3rem 1.5rem;
            background-color: var(--card-background);
            border: 1px dashed var(--border-color);
            border-radius: var(--border-radius-xl);
            color: var(--text-secondary-color);
            font-style: italic;
            margin-top: 2rem;
            font-size: 1.1rem;
        }
        .no-data-message p {
            margin-bottom: 0.75rem;
        }
        .no-data-message i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--bs-secondary);
        }

        /* Footer styles */
        .footer {
            background-color: var(--bs-dark);
            color: var(--card-background);
            padding: 2rem 0;
            margin-top: 4rem;
        }
        .footer .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .footer a {
            color: var(--card-background);
            margin: 0 0.8rem;
            transition: color var(--transition-speed) ease;
        }
        .footer a:hover {
            color: var(--bs-primary);
        }
        .footer .social-icons {
            font-size: 1.5rem;
            margin-top: 1rem;
        }
        .footer .social-icons a {
            margin: 0 0.75rem;
        }


        /* Responsive adjustments */
        @media (max-width: 991.98px) { /* Bootstrap 'lg' breakpoint */
            .dashboard-container { 
                flex-direction: column; 
                margin: 2rem auto; 
                padding: 0 1rem;
                gap: 1.5rem;
            }
            .sidebar { 
                flex: 0 0 auto; 
                margin-bottom: 1.5rem; 
                position: static; 
                padding: 1.8rem 1rem;
            }
            .nav-pills { 
                flex-direction: row; 
                flex-wrap: wrap; 
                justify-content: center; 
                margin-top: 1.5rem;
            }
            .nav-pills .nav-item {
                width: auto;
            }
            .nav-pills .nav-link { 
                margin: 0.4rem; 
                padding: 0.75rem 1rem;
                font-size: 0.95rem;
            }
            .main-content { 
                padding: 2rem; 
            }
            .profile-picture-wrapper { 
                width: 120px; 
                height: 120px; 
                margin-bottom: 1.2rem;
                border-width: 4px; /* Adjust border on smaller screens */
            }
            .upload-icon-overlay { 
                width: 40px; 
                height: 40px; 
                font-size: 1rem;
            }
            .user-name { font-size: 1.7rem; margin-bottom: 0.4rem; }
            .user-email { font-size: 0.9rem; }
            .tab-pane h3 { font-size: 1.6rem; margin-bottom: 1.8rem; padding-bottom: 1rem;}
            .btn-save { padding: 0.8rem 2.2rem; font-size: 1rem; }
            .activity-item { padding: 1rem 1.2rem; gap: 0.8rem; }
            .activity-icon { font-size: 1.5rem; width: 30px; }
            .activity-content strong { font-size: 1rem; }
            .activity-content span { font-size: 0.8rem; }
            .announcement-card { padding: 1.5rem; border-left-width: 4px; }
            .announcement-card .card-title { font-size: 1.3rem; gap: 0.8rem;}
            .announcement-card .card-title i { font-size: 1.7rem; }
            .announcement-card .card-text { font-size: 0.95rem; }
            .announcement-card .card-footer { font-size: 0.8rem; padding-top: 1rem; margin-top: 1rem;}
            .toast-notification { bottom: 15px; right: 15px; padding: 1rem 1.5rem; font-size: 0.95rem; min-width: 200px; }
            .footer { margin-top: 2rem; padding: 1.5rem 0; }
        }

        @media (max-width: 767.98px) { /* Bootstrap 'md' breakpoint */
            .main-content { padding: 1.5rem; }
            .form-control { padding: 0.8rem 1rem; font-size: 0.9rem; }
            .btn-save { padding: 0.7rem 1.8rem; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="index.html">Prottoy Foundation</a>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <label for="profile_picture_input" class="profile-picture-wrapper" title="Change profile picture">
                <img id="profile_img_display" src="<?php echo !empty($profile_picture_url) ? htmlspecialchars($profile_picture_url) : 'https://placehold.co/180x180/1b4e80/FFFFFF?text=PF'; ?>" onerror="this.onerror=null; this.src='https://placehold.co/180x180/e0e6ed/606f7b&text=User';" alt="Profile Picture" class="profile-img">
                <div class="upload-icon-overlay">
                    <i class="bi bi-camera-fill"></i>
                </div>
            </label>
            <h1 id="user_name_display" class="user-name"><?php echo htmlspecialchars($name); ?></h1>
            <p class="user-email"><?php echo htmlspecialchars($email); ?></p>

            <ul class="nav nav-pills mt-4" id="dashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                        <i class="bi bi-person-circle me-2"></i> Profile Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="announcements-tab" data-bs-toggle="pill" data-bs-target="#announcements" type="button" role="tab" aria-controls="announcements" aria-selected="false">
                        <i class="bi bi-megaphone me-2"></i> Announcements
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="pill" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                        <i class="bi bi-activity me-2"></i> Recent Activity
                    </button>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="tab-content" id="dashboardTabContent">
                <!-- Profile Settings Tab Pane -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <h3>Account Settings</h3>
                    <form id="userProfileForm" class="mb-5">
                        <input type="file" id="profile_picture_input" name="profile_picture" class="d-none" accept="image/png, image/jpeg, image/gif">
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Your full name">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="e.g., +1 555-123-4567">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="home_address" class="form-label">Address (City, Country)</label>
                            <input type="text" class="form-control" id="home_address" name="home_address" value="<?php echo htmlspecialchars($home_address); ?>" placeholder="e.g., London, United Kingdom">
                        </div>
                  
                        <div class="text-end mt-4">
                            <button type="submit" id="saveButton" class="btn btn-primary btn-save">
                                <span id="saveButtonText">Update Profile</span>
                                <span id="saveButtonSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>

                    <h3 class="mt-5">Change Password</h3>
                    <form id="changePasswordForm">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-4">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-4">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                            <div class="form-text text-muted">Minimum 8 characters.</div>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" id="changePasswordButton" class="btn btn-primary btn-save">
                                <span id="changePasswordButtonText">Change Password</span>
                                <span id="changePasswordSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Announcements Tab Pane -->
                <div class="tab-pane fade" id="announcements" role="tabpanel" aria-labelledby="announcements-tab">
                    <h3>Announcements</h3>
                    <?php if (!empty($announcements)): ?>
                        <div class="announcements-feed">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-card">
                                    <h5 class="card-title">
                                        <i class="bi bi-megaphone-fill"></i> 
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h5>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p> 
                                    <div class="card-footer">
                                        Published on: <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($announcement['created_at']))); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <p><i class="bi bi-megaphone-fill"></i></p>
                            <p>No active announcements at this time.</p>
                            <p>Check back later for important updates!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity Tab Pane -->
                <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                    <h3>Recent Activity</h3>
                    <?php if (!empty($recent_activity)): ?>
                        <div class="activity-feed">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php 
                                            // Dynamic icons based on activity type
                                            switch ($activity['type']) {
                                                case 'Login': echo '<i class="bi bi-box-arrow-in-right"></i>'; break;
                                                case 'Profile Update': echo '<i class="bi bi-pencil-square"></i>'; break;
                                                case 'Security': echo '<i class="bi bi-shield-lock-fill"></i>'; break;
                                                case 'Info': echo '<i class="bi bi-info-circle-fill"></i>'; break;
                                                default: echo '<i class="bi bi-info-circle"></i>'; break;
                                            }
                                        ?>
                                    </div>
                                    <div class="activity-content">
                                        <strong><?php echo htmlspecialchars($activity['message']); ?></strong>
                                        <span><?php echo htmlspecialchars($activity['timestamp']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <p><i class="bi bi-bell-slash"></i></p>
                            <p>No recent activity to display.</p>
                            <p>This section will show your account's important events and notifications.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Toast Notification Element -->
    <div id="toast" class="toast-notification"></div>

    <footer class="footer">
        <div class="container">
            <p class="text-white-50 text-sm mb-3">&copy; <?php echo date('Y'); ?> Prottoy Foundation. All rights reserved.</p>
            <div class="social-icons mb-3">
                <a href="https://facebook.com" target="_blank" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                <a href="https://twitter.com" target="_blank" aria-label="Twitter"><i class="bi bi-twitter"></i></a>
                <a href="https://instagram.com" target="_blank" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                <a href="https://linkedin.com" target="_blank" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
            </div>
            <p class="mt-2 text-white-50"><a href="mailto:info@prottoyfoundation.org" class="text-white-50">info@prottoyfoundation.org</a></p>
        </div>
    </footer>

    <!-- Bootstrap JS (for modals, dropdowns, collapse, tabs) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const profileForm = document.getElementById('userProfileForm');
            const changePasswordForm = document.getElementById('changePasswordForm');
            const profilePictureInput = document.getElementById('profile_picture_input');
            const profileImgDisplay = document.getElementById('profile_img_display');
            const userNameDisplay = document.getElementById('user_name_display');

            const saveButton = document.getElementById('saveButton');
            const saveButtonText = document.getElementById('saveButtonText');
            const saveButtonSpinner = document.getElementById('saveButtonSpinner');

            const changePasswordButton = document.getElementById('changePasswordButton');
            const changePasswordButtonText = document.getElementById('changePasswordButtonText');
            const changePasswordSpinner = document.getElementById('changePasswordSpinner');
            
            const toastElement = document.getElementById('toast');
            let toastTimeout;

            // Function to show toast notifications
            function showToast(message, type = 'success') {
                clearTimeout(toastTimeout);
                toastElement.textContent = message;
                toastElement.className = 'toast-notification'; // Reset classes
                toastElement.classList.add(type, 'show');
                toastTimeout = setTimeout(() => {
                    toastElement.classList.remove('show');
                }, 4000);
            }

            // Handle profile picture preview (client-side)
            profilePictureInput.addEventListener('change', () => {
                const file = profilePictureInput.files[0];
                if (file) {
                    // Basic client-side validation for file type and size
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        showToast('Invalid file type. Please select a JPG, PNG, or GIF.', 'error');
                        profilePictureInput.value = ''; // Clear selected file
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) { // 5MB limit
                        showToast('File is too large. Maximum size is 5MB.', 'error');
                        profilePictureInput.value = ''; // Clear selected file
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        profileImgDisplay.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Handle profile form submission
            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                saveButtonText.textContent = 'Updating...';
                saveButtonSpinner.classList.remove('d-none');
                saveButton.disabled = true;

                const formData = new FormData(profileForm);

                axios.post('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                })
                .then(response => {
                    const data = response.data;
                    if (data.success) {
                        showToast(data.message || 'Profile updated successfully!', 'success');
                        if (data.profile_picture_url) {
                            // Append a timestamp to force browser to reload the image from cache
                            profileImgDisplay.src = data.profile_picture_url + '?t=' + new Date().getTime();
                        }
                        if (data.newName) {
                            userNameDisplay.textContent = data.newName;
                            document.title = 'Dashboard - ' + data.newName;
                        }
                    } else {
                        showToast(data.error || 'An unknown error occurred.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error updating profile:', error);
                    const errorMessage = error.response?.data?.error || 'A server error occurred. Please try again.';
                    showToast(errorMessage, 'error');
                })
                .finally(() => {
                    saveButtonText.textContent = 'Update Profile';
                    saveButtonSpinner.classList.add('d-none');
                    saveButton.disabled = false;
                });
            });

            // Handle password change form submission
            changePasswordForm.addEventListener('submit', (e) => {
                e.preventDefault();

                // Client-side validation for password fields
                const currentPassword = document.getElementById('current_password').value;
                const newPassword = document.getElementById('new_password').value;
                const confirmNewPassword = document.getElementById('confirm_new_password').value;

                if (!currentPassword || !newPassword || !confirmNewPassword) {
                    showToast('All password fields are required.', 'error');
                    return;
                }
                if (newPassword !== confirmNewPassword) {
                    showToast('New password and confirm password do not match.', 'error');
                    return;
                }
                if (newPassword.length < 6) {
                    showToast('New password must be at least 6 characters long.', 'error');
                    return;
                }

                changePasswordButtonText.textContent = 'Changing...';
                changePasswordSpinner.classList.remove('d-none');
                changePasswordButton.disabled = true;

                const formData = new FormData(changePasswordForm);
                formData.append('action', 'change_password'); // Explicitly add action for password change

                axios.post('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                })
                .then(response => {
                    const data = response.data;
                    if (data.success) {
                        showToast(data.message, 'success');
                        // Clear password fields on success
                        document.getElementById('current_password').value = '';
                        document.getElementById('new_password').value = '';
                        document.getElementById('confirm_new_password').value = '';
                    } else {
                        showToast(data.error || 'An unknown error occurred.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error changing password:', error);
                    const errorMessage = error.response?.data?.error || 'A server error occurred. Please try again.';
                    showToast(errorMessage, 'error');
                })
                .finally(() => {
                    changePasswordButtonText.textContent = 'Change Password';
                    changePasswordSpinner.classList.add('d-none');
                    changePasswordButton.disabled = false;
                });
            });
        });
    </script>
</body>
</html>
