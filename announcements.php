<?php
session_start();

// !!! CRITICAL SECURITY WARNING !!!
// Placing administrative functionalities (add, edit, delete announcements)
// directly in a publicly accessible file like announcements.php is a severe security risk.
// This design makes your application vulnerable to unauthorized access and manipulation
// if security checks are ever bypassed. This is NOT recommended for production.
//
// The correct approach is to keep admin features ONLY in admin_dashboard.php,
// and announcements.php should remain read-only for general users.
//
// This implementation is provided ONLY to fulfill your explicit request
// but comes with a strong recommendation to revert to a segregated architecture.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // Make sure db.php exists and connects to your database

$is_admin = isset($_SESSION["user_role"]) && $_SESSION["user_role"] === 'admin';
$admin_name = '';
$admin_email = '';
$current_admin_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;


// Function for sanitizing output data
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Function for sanitizing input data (for admin actions)
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Function to handle file uploads (duplicate of admin_dashboard for self-contained file)
function handle_file_upload($file_input_name, $upload_dir, $allowed_mime_types, $max_size_mb, $prefix = '') {
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

    $uploaded_file_path = null;

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];

        if ($file['size'] > $max_size_mb * 1024 * 1024) {
            return ['error' => 'Upload failed: File is too large. Maximum size is ' . $max_size_mb . 'MB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            error_log("Failed to open fileinfo database.");
            return ['error' => 'Server error: File type validation not available.'];
        }
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types)) {
            return ['error' => 'Upload failed: Invalid file type. Allowed types: ' . implode(', ', $allowed_mime_types) . '. (Detected: ' . $mime_type . ')'];
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = $prefix . bin2hex(random_bytes(10)) . '.' . $file_extension;
        $target_file_path = $upload_dir . $safe_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file_path)) {
            $uploaded_file_path = $target_file_path;
        } else {
            error_log("File upload failed for {$file_input_name}. PHP Error Code: " . $file['error'] . ". File: " . $file['name']);
            return ['error' => 'Server error: Could not save the uploaded file. Check server logs for details.'];
        }
    } else if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_NO_FILE) {
        return ['error' => 'File upload error: ' . $_FILES[$file_input_name]['error']];
    }
    return ['success' => $uploaded_file_path];
}


// --- Handle AJAX POST requests for Admin Actions (Only if admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');

    if ($conn->connect_error) {
        http_response_code(500);
        error_log("Database connection failed: " . $conn->connect_error);
        echo json_encode(['success' => false, 'error' => 'Server error: Cannot connect to the database.']);
        exit;
    }

    $response = ['success' => false];

    // --- Add New Announcement ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_announcement') {
        $title = sanitize_input($_POST['title']);
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $trimmed_content = trim($content);
        
        if (empty($title)) {
            $response['error'] = 'Announcement title cannot be empty.';
            echo json_encode($response);
            exit;
        }

        $file_upload_result = handle_file_upload('announcement_file', 'uploads/announcements_files/', ['application/pdf'], 10, 'announcement_');
        $file_path = $file_upload_result['success'];

        if (isset($file_upload_result['error'])) {
            $response['error'] = $file_upload_result['error'];
            echo json_encode($response);
            exit;
        }

        $final_content = $file_path ? $file_path : $trimmed_content;

        if (empty($final_content)) {
            $response['error'] = 'Announcement content (text or PDF) cannot be empty.';
            echo json_encode($response);
            exit;
        }

        $sql_insert = "INSERT INTO announcements (title, content, is_active, created_at, admin_id) VALUES (?, ?, 1, NOW(), ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert === false) {
            $response['error'] = 'Database error: Could not prepare announcement insert statement. SQL Error: ' . $conn->error;
            error_log("SQL prepare failed (add_announcement): " . $conn->error);
        } else {
            $stmt_insert->bind_param("ssi", $title, $final_content, $current_admin_id);
            if ($stmt_insert->execute()) {
                $response['success'] = true;
                $response['message'] = 'Announcement sent successfully!';
                $response['new_announcement_id'] = $stmt_insert->insert_id;
            } else {
                $response['error'] = 'Failed to send announcement. Please try again. DB Error: ' . $stmt_insert->error;
                error_log("DB execute failed (add_announcement) for admin_id {$current_admin_id}: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
        echo json_encode($response);
        exit;
    }

    // --- Edit Announcement ---
    if (isset($_POST['action']) && $_POST['action'] === 'edit_announcement') {
        $announcement_id = intval($_POST['announcement_id']);
        $title = sanitize_input($_POST['edit_title']);
        $content = isset($_POST['edit_content']) ? $_POST['edit_content'] : '';
        $is_active = isset($_POST['edit_is_active']) ? 1 : 0;
        $remove_current_pdf = isset($_POST['remove_current_pdf']) && $_POST['remove_current_pdf'] === 'true';

        $trimmed_content = trim($content);

        if (empty($title)) {
            $response['error'] = 'Announcement title cannot be empty.';
            echo json_encode($response);
            exit;
        }

        $current_content_from_db = '';
        $sql_fetch_current_content = "SELECT content FROM announcements WHERE id = ?";
        $stmt_fetch_current_content = $conn->prepare($sql_fetch_current_content);
        if ($stmt_fetch_current_content) {
            $stmt_fetch_current_content->bind_param("i", $announcement_id);
            $stmt_fetch_current_content->execute();
            $result_current_content = $stmt_fetch_current_content->get_result();
            if ($row_current_content = $result_current_content->fetch_assoc()) {
                $current_content_from_db = $row_current_content['content'];
            }
            $stmt_fetch_current_content->close();
        } else {
            error_log("Error preparing statement to fetch current announcement content: " . $conn->error);
        }

        $file_upload_result = handle_file_upload('edit_announcement_file', 'uploads/announcements_files/', ['application/pdf'], 10, 'announcement_');
        $new_file_path = $file_upload_result['success'];

        if (isset($file_upload_result['error'])) {
            $response['error'] = $file_upload_result['error'];
            echo json_encode($response);
            exit;
        }

        $final_content_for_db = $trimmed_content;

        if ($new_file_path) {
            $final_content_for_db = $new_file_path;
            if (strpos($current_content_from_db, 'uploads/announcements_files/') === 0 && file_exists($current_content_from_db)) {
                @unlink($current_content_from_db);
            }
        } elseif ($remove_current_pdf && strpos($current_content_from_db, 'uploads/announcements_files/') === 0) {
            if (file_exists($current_content_from_db)) {
                @unlink($current_content_from_db);
            }
            $final_content_for_db = $trimmed_content;
        } elseif (empty($trimmed_content) && empty($new_file_path) && strpos($current_content_from_db, 'uploads/announcements_files/') === 0) {
            $final_content_for_db = $current_content_from_db;
        }

        if (empty($final_content_for_db)) {
            $response['error'] = 'Announcement content (text or PDF) cannot be empty after edit.';
            echo json_encode($response);
            exit;
        }

        $sql_update = "UPDATE announcements SET title = ?, content = ?, is_active = ?, created_at = NOW() WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            $response['error'] = 'Database error: Could not prepare announcement update statement. SQL Error: ' . $conn->error;
            error_log("SQL prepare failed (edit_announcement): " . $conn->error);
        } else {
            $stmt_update->bind_param("ssii", $title, $final_content_for_db, $is_active, $announcement_id);
            if ($stmt_update->execute()) {
                $response['success'] = true;
                $response['message'] = 'Announcement updated successfully!';
            } else {
                $response['error'] = 'Failed to update announcement. DB Error: ' . $stmt_update->error;
                error_log("DB execute failed (edit_announcement) for ID {$announcement_id}: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
        echo json_encode($response);
        exit;
    }

    // --- Toggle Announcement Status ---
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_announcement_status') {
        $announcement_id = intval($_POST['id']);
        $is_active = intval($_POST['is_active']);

        $sql_update = "UPDATE announcements SET is_active = ?, created_at = NOW() WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            $response['error'] = 'Database error: Could not prepare status update statement. SQL Error: ' . $conn->error;
            error_log("SQL prepare failed (toggle_status): " . $conn->error);
        } else {
            $stmt_update->bind_param("ii", $is_active, $announcement_id);
            if ($stmt_update->execute()) {
                $response['success'] = true;
                $response['message'] = 'Announcement status updated successfully!';
            } else {
                $response['error'] = 'Failed to update announcement status. DB Error: ' . $stmt_update->error;
                error_log("DB execute failed (toggle_status) for announcement ID {$announcement_id}: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
        echo json_encode($response);
        exit;
    }

    // --- Delete Announcement ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
        $announcement_id = intval($_POST['id']);

        $sql_get_file = "SELECT content FROM announcements WHERE id = ?";
        $stmt_get_file = $conn->prepare($sql_get_file);
        if ($stmt_get_file === false) {
            error_log("Error preparing get file path statement for deletion: " . $conn->error);
        } else {
            $stmt_get_file->bind_param("i", $announcement_id);
            $stmt_get_file->execute();
            $result_file = $stmt_get_file->get_result();
            if ($row_file = $result_file->fetch_assoc()) {
                $potential_file_path = $row_file['content'];
                if (strpos($potential_file_path, 'uploads/announcements_files/') === 0 && file_exists($potential_file_path)) {
                    if (!@unlink($potential_file_path)) {
                        error_log("Failed to delete file: " . $potential_file_path);
                    }
                }
            }
            $stmt_get_file->close();
        }

        $sql_delete = "DELETE FROM announcements WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        if ($stmt_delete === false) {
            $response['error'] = 'Database error: Could not prepare delete statement. SQL Error: ' . $conn->error;
            error_log("SQL prepare failed (delete_announcement): " . $conn->error);
        } else {
            $stmt_delete->bind_param("i", $announcement_id);
            if ($stmt_delete->execute()) {
                $response['success'] = true;
                $response['message'] = 'Announcement deleted successfully!';
            } else {
                $response['error'] = 'Failed to delete announcement. DB Error: ' . $stmt_delete->error;
                error_log("DB execute failed (delete_announcement) for ID {$announcement_id}: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        }
        echo json_encode($response);
        exit;
    }

    // Default response for unknown admin action
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown admin action requested.']);
    exit;
}
// --- End of Admin POST Handling ---


// --- Handle AJAX GET requests for JSON data ---
// This part is for dynamic fetching by JS for both user and admin view
if (isset($_GET['fetch_json']) && $_GET['fetch_json'] === 'true') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');

    if ($conn->connect_error) {
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    $fetched_announcements = [];
    $sql = "SELECT id, title, content, is_active, created_at FROM announcements ";
    if (!$is_admin) {
        $sql .= "WHERE is_active = 1 "; // Only active for non-admins
    }
    $sql .= "ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $is_pdf = strpos($row['content'], 'uploads/announcements_files/') === 0;
                $display_content = $is_pdf 
                    ? '<a href="' . sanitize_output($row['content']) . '" target="_blank" class="btn btn-sm btn-outline-primary announcement-attachment-btn"><i class="bi bi-file-earmark-pdf-fill me-1"></i> View PDF Attachment</a>'
                    : sanitize_output(nl2br($row['content']));
                
                $fetched_announcements[] = [
                    'id' => $row['id'],
                    'title' => sanitize_output($row['title']),
                    'content_raw' => sanitize_output($row['content']), // For edit modal pre-population
                    'display_content' => $display_content,
                    'is_active' => $row['is_active'], // Include active status for admin view
                    'created_at' => date('F j, Y, g:i a', strtotime($row['created_at']))
                ];
            }
        } else {
            error_log("Error executing announcement fetch for JSON: " . $stmt->error);
            echo json_encode(['error' => 'Failed to fetch announcements.']);
            $conn->close();
            exit;
        }
        $stmt->close();
    } else {
        error_log("Error preparing announcement fetch for JSON: " . $conn->error);
        echo json_encode(['error' => 'Failed to prepare statement.']);
        $conn->close();
        exit;
    }

    // Get total counts for admin mini-overview
    $total_announcements = 0;
    $active_announcements_count = 0;
    if ($is_admin) {
        $sql_counts = "SELECT COUNT(id) AS total, SUM(is_active) AS active FROM announcements";
        $result_counts = $conn->query($sql_counts);
        if ($result_counts && $counts = $result_counts->fetch_assoc()) {
            $total_announcements = $counts['total'];
            $active_announcements_count = $counts['active'];
        }
    }

    $conn->close();
    echo json_encode([
        'success' => true, 
        'announcements' => $fetched_announcements,
        'total_announcements' => $total_announcements, // Sent only if admin
        'active_announcements_count' => $active_announcements_count // Sent only if admin
    ]);
    exit;
}

// --- PHP Logic for Initial Page Load (Non-AJAX GET Request) ---

// Fetch admin info if applicable
if ($is_admin && $current_admin_id) {
    $sql_admin_info = "SELECT name, email FROM users WHERE id = ?";
    $stmt_admin_info = $conn->prepare($sql_admin_info);
    if ($stmt_admin_info) {
        $stmt_admin_info->bind_param("i", $current_admin_id);
        $stmt_admin_info->execute();
        $result_admin_info = $stmt_admin_info->get_result();
        if ($admin_user_data = $result_admin_info->fetch_assoc()) {
            $admin_name = $admin_user_data['name'];
            $admin_email = $admin_user_data['email'];
        }
        $stmt_admin_info->close();
    } else {
        error_log("Error preparing admin info fetch: " . $conn->error);
    }
}

// Initial fetch for the page render (will be updated by JS later)
$announcements_to_display = [];
$sql_initial_fetch = "SELECT id, title, content, is_active, created_at FROM announcements ";
if (!$is_admin) {
    $sql_initial_fetch .= "WHERE is_active = 1 ";
}
$sql_initial_fetch .= "ORDER BY created_at DESC";

$stmt_initial_fetch = $conn->prepare($sql_initial_fetch);
if ($stmt_initial_fetch) {
    if ($stmt_initial_fetch->execute()) {
        $result_initial_fetch = $stmt_initial_fetch->get_result();
        while ($row = $result_initial_fetch->fetch_assoc()) {
            $announcements_to_display[] = $row;
        }
    } else {
        error_log("Error executing initial announcement fetch: " . $stmt_initial_fetch->error);
    }
    $stmt_initial_fetch->close();
} else {
    error_log("Error preparing initial announcement fetch statement: " . $conn->error);
}

// Get initial counts for admin mini-overview
$initial_total_announcements = 0;
$initial_active_announcements_count = 0;
if ($is_admin) {
    $sql_initial_counts = "SELECT COUNT(id) AS total, SUM(is_active) AS active FROM announcements";
    $result_initial_counts = $conn->query($sql_initial_counts);
    if ($result_initial_counts && $counts = $result_initial_counts->fetch_assoc()) {
        $initial_total_announcements = $counts['total'];
        $initial_active_announcements_count = $counts['active'];
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter for a Google-like feel -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <!-- Axios for AJAX requests -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        /* CSS Variables for a professional and consistent look */
        :root {
            --primary-color: #1a73e8; /* Google Blue */
            --secondary-text-color: #5f6368; /* Google Gray */
            --border-color: #dadce0; /* Light border */
            --card-background: #ffffff;
            --page-background: #f8f9fa; /* Very light background */
            --shadow-subtle: rgba(0, 0, 0, 0.05);
            --shadow-medium: rgba(0, 0, 0, 0.1);
            --border-radius-lg: 0.5rem; /* Rounded corners */
            --transition-speed: 0.3s;

            /* Admin Specific Colors */
            --admin-bg: #e6f0ff; /* Light blue background for admin sections */
            --admin-border: #a4c2f4; /* Slightly darker blue border */
            --admin-text: #174ea6; /* Darker blue text */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--page-background);
            color: #202124;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .announcements-feed-container {
            flex-grow: 1;
            padding: 1.5rem;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Admin Info Section (New) */
        .admin-info-card {
            background-color: var(--admin-bg);
            border: 1px solid var(--admin-border);
            border-radius: var(--border-radius-lg);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--admin-text);
            box-shadow: 0 1px 3px var(--shadow-subtle);
        }
        .admin-info-card i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        .admin-info-card h5 {
            margin-bottom: 0;
            font-weight: 600;
            color: var(--admin-text);
        }
        .admin-info-card small {
            color: var(--admin-text);
        }

        /* Admin Counts (New) */
        .admin-counts-card {
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px var(--shadow-subtle);
        }
        .admin-counts-card .count-item {
            font-size: 0.9rem;
            color: var(--secondary-text-color);
        }
        .admin-counts-card .count-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        .admin-counts-card .count-item:not(:last-child) {
            margin-right: 1.5rem;
        }


        /* Add Announcement Form (Admin Only) */
        .add-announcement-form-container {
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px var(--shadow-subtle);
        }
        .add-announcement-form-container .form-control {
            border-radius: var(--border-radius-lg);
            border-color: var(--border-color);
            font-size: 0.95rem;
        }
        .add-announcement-form-container .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 115, 232, 0.25);
        }
        .btn-send-announcement {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
        }
        .btn-send-announcement:hover {
            background-color: #1565c0; /* Darker blue */
            border-color: #1565c0;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        .btn-send-announcement:disabled {
            background-color: #cccccc;
            border-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .form-file-clear-btn {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            color: #555;
            padding: 0.3rem 0.6rem;
            border-radius: var(--border-radius-lg);
            font-size: 0.8rem;
            margin-top: 0.5rem;
            transition: all var(--transition-speed) ease;
        }
        .form-file-clear-btn:hover {
            background-color: #e0e0e0;
            color: #222;
        }


        /* Announcement Card Styling - Google Classroom inspired */
        .announcement-card {
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px var(--shadow-subtle), 0 1px 2px var(--shadow-subtle);
            transition: all var(--transition-speed) ease;
        }
        .announcement-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 6px var(--shadow-medium);
            transform: translateY(-2px);
        }
        /* Style for inactive announcements (Admin view only) */
        .announcement-card.inactive {
            opacity: 0.7;
            background-color: #fcfcfc;
            border-color: #e0e0e0;
        }

        .announcement-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .announcement-icon-wrapper {
            background-color: var(--primary-color);
            color: var(--card-background);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-right: 1rem;
        }

        .announcement-title-and-date {
            flex-grow: 1;
        }

        .announcement-card .card-title {
            font-weight: 500;
            color: #202124;
            margin-bottom: 0.25rem;
            font-size: 1.25rem;
        }

        .announcement-card .card-date {
            font-size: 0.85rem;
            color: var(--secondary-text-color);
        }

        .announcement-card .card-text {
            color: #3c4043;
            font-size: 1rem;
            margin-bottom: 1rem;
            white-space: pre-wrap; /* Preserve line breaks from nl2br */
            word-wrap: break-word; /* Break long words */
        }
        
        .announcement-attachment-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: var(--border-radius-lg);
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--primary-color);
            border-color: var(--border-color);
            background-color: var(--page-background);
            transition: all var(--transition-speed) ease;
        }
        .announcement-attachment-btn:hover {
            background-color: #e8f0fe;
            border-color: #d2e3fc;
            color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 1px 3px var(--shadow-subtle);
        }
        .announcement-attachment-btn i {
            font-size: 1.1rem;
        }

        /* Admin Actions for each card */
        .announcement-admin-actions {
            border-top: 1px dashed var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .announcement-admin-actions .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-lg);
            transition: all var(--transition-speed) ease;
        }
        .announcement-admin-actions .btn-info { /* Edit button */
            background-color: transparent;
            color: #17a2b8; /* Bootstrap Info */
            border-color: #17a2b8;
        }
        .announcement-admin-actions .btn-info:hover {
            background-color: #17a2b8;
            color: white;
        }
        .announcement-admin-actions .btn-danger { /* Delete button */
             background-color: transparent;
             color: #dc3545; /* Bootstrap Danger */
             border-color: #dc3545;
        }
        .announcement-admin-actions .btn-danger:hover {
            background-color: #dc3545;
            color: white;
        }
        /* Toggle Switch for Admin */
        .form-switch .form-check-input {
            margin-top: 0;
        }
        .form-switch .form-check-label {
            margin-left: 0.5rem;
            color: var(--secondary-text-color);
        }
        .form-switch-wrapper {
            display: flex;
            align-items: center;
            margin-right: auto;
            font-size: 0.9rem;
        }


        /* No data message & Loading message */
        .message-container {
            text-align: center;
            padding: 3rem 1.5rem;
            background-color: var(--card-background);
            border: 1px dashed var(--border-color);
            border-radius: var(--border-radius-lg);
            color: var(--secondary-text-color);
            font-style: italic;
            margin-top: 1.5rem; /* Adjusted margin */
            font-size: 1.1rem;
        }
        .message-container p {
            margin-bottom: 0.75rem;
        }
        .message-container i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--secondary-text-color);
        }
        .loading-spinner {
            color: var(--primary-color);
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }


        /* Toast Notification */
        .toast-notification {
            position: fixed; bottom: 30px; right: 30px; color: white; padding: 1rem 1.5rem;
            border-radius: var(--border-radius-lg); font-weight: 600; box-shadow: 0 10px 30px var(--shadow-medium);
            transform: translateY(150%); opacity: 0;
            transition: transform 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55), opacity 0.6s ease;
            z-index: 1060;
            min-width: 250px;
            text-align: center;
            font-size: 1rem;
        }
        .toast-notification.show { transform: translateY(0); opacity: 1; }
        .toast-notification.success { background-color: #28a745; }
        .toast-notification.error { background-color: #dc3545; }
        .toast-notification.info { background-color: #17a2b8; }


        /* Modal Specific Styling */
        .modal-content {
            border-radius: var(--border-radius-lg);
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: var(--border-radius-lg);
            border-top-right-radius: var(--border-radius-lg);
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .modal-title {
            font-weight: 600;
            font-size: 1.5rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            justify-content: flex-end;
        }
        .modal-footer .btn {
            border-radius: var(--border-radius-lg);
            font-weight: 500;
            padding: 0.6rem 1.2rem;
        }
        /* Custom Delete Confirmation Modal Styling */
        #deleteConfirmationModal .modal-header {
            background-color: #dc3545; /* Red for delete confirmation */
        }
        #deleteConfirmationModal .modal-footer .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }


        /* Responsive adjustments */
        @media (max-width: 767.98px) {
             .announcements-feed-container {
                padding: 1rem;
             }
             .admin-info-card {
                padding: 0.8rem 1rem;
                font-size: 0.9rem;
                flex-wrap: wrap;
             }
             .admin-info-card i {
                font-size: 1.2rem;
             }
             .admin-info-card h5 {
                font-size: 1.1rem;
             }
             .admin-info-card small {
                font-size: 0.8rem;
             }
             .admin-counts-card {
                padding: 0.8rem 1rem;
             }
             .admin-counts-card .count-item {
                 font-size: 0.85rem;
                 margin-right: 1rem;
             }
             .admin-counts-card .count-value {
                 font-size: 1.1rem;
             }
             .add-announcement-form-container {
                 padding: 1rem;
             }
             .add-announcement-form-container .form-control {
                 padding: 0.8rem 1rem;
                 font-size: 0.85rem;
             }
             .btn-send-announcement {
                 padding: 0.6rem 1rem;
                 font-size: 0.9rem;
             }

             .announcement-card {
                 padding: 1.25rem 1.5rem;
                 margin-bottom: 1rem;
             }
             .announcement-header {
                 margin-bottom: 0.8rem;
             }
             .announcement-icon-wrapper {
                 width: 36px;
                 height: 36px;
                 font-size: 1.1rem;
                 margin-right: 0.8rem;
             }
             .announcement-card .card-title {
                 font-size: 1.1rem;
             }
             .announcement-card .card-date {
                 font-size: 0.75rem;
             }
             .announcement-card .card-text {
                 font-size: 0.95rem;
                 margin-bottom: 0.8rem;
             }
             .announcement-attachment-btn {
                 padding: 0.5rem 0.8rem;
                 font-size: 0.85rem;
             }
             .announcement-attachment-btn i {
                 font-size: 1rem;
             }
             .announcement-admin-actions {
                 flex-direction: column;
                 align-items: flex-start;
                 gap: 0.5rem;
             }
             .announcement-admin-actions .btn {
                 width: 100%;
                 text-align: center;
                 padding: 0.6rem 1rem;
             }
             .form-switch-wrapper {
                 width: 100%;
                 justify-content: flex-start;
                 margin-bottom: 0.5rem;
                 margin-right: 0;
             }
             .message-container {
                 padding: 2rem 1rem;
                 font-size: 1rem;
             }
             .toast-notification {
                bottom: 15px; right: 15px; padding: 0.8rem 1.2rem; font-size: 0.9rem; min-width: unset;
             }
             .modal-header {
                padding: 1rem;
             }
             .modal-title {
                font-size: 1.2rem;
             }
             .modal-body {
                padding: 1rem;
             }
             .modal-footer {
                padding: 0.8rem 1rem;
             }
             .modal-footer .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
             }
        }
    </style>
</head>
<body>
    <div class="announcements-feed-container">
        <?php if ($is_admin): ?>
            <div class="admin-info-card">
                <i class="bi bi-person-fill-gear"></i>
                <div>
                    <h5>Welcome, <?php echo sanitize_output($admin_name); ?> (Admin)</h5>
                    <small>Logged in as: <?php echo sanitize_output($admin_email); ?></small>
                </div>
            </div>

            <div class="admin-counts-card d-flex justify-content-around mb-3">
                <div class="count-item text-center">
                    <div class="count-value" id="totalAnnouncementsCount">
                        <?php echo $initial_total_announcements; ?>
                    </div>
                    <div>Total</div>
                </div>
                <div class="count-item text-center">
                    <div class="count-value text-success" id="activeAnnouncementsCount">
                        <?php echo $initial_active_announcements_count; ?>
                    </div>
                    <div>Active</div>
                </div>
                <div class="count-item text-center">
                    <div class="count-value text-secondary" id="inactiveAnnouncementsCount">
                        <?php echo $initial_total_announcements - $initial_active_announcements_count; ?>
                    </div>
                    <div>Inactive</div>
                </div>
            </div>


            <div class="add-announcement-form-container">
                <h5 class="mb-3">Publish New Announcement</h5>
                <form id="addAnnouncementForm">
                    <div class="mb-3">
                        <label for="announcement_title" class="form-label visually-hidden">Title</label>
                        <input type="text" class="form-control" id="announcement_title" name="title" placeholder="Announcement Title" required>
                    </div>
                    <div class="mb-3">
                        <label for="announcement_content" class="form-label visually-hidden">Content</label>
                        <textarea class="form-control" id="announcement_content" name="content" rows="4" placeholder="What do you want to announce?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="announcement_file" class="form-label">Attach PDF (Optional)</label>
                        <input class="form-control" type="file" id="announcement_file" name="announcement_file" accept="application/pdf">
                        <small class="form-text text-muted">Max 10MB. Attaching a PDF will override text content for display.</small>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-send-announcement" id="addAnnouncementButton" disabled>
                            <span id="addAnnouncementButtonText">Send Announcement</span>
                            <span id="addAnnouncementSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="announcements-feed" id="announcementsFeed">
            <!-- Announcements will be loaded here by JavaScript -->
            <div class="message-container" id="loadingMessage">
                <i class="bi bi-arrow-clockwise loading-spinner"></i>
                <p>Loading announcements...</p>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal (Admin Only) -->
    <?php if ($is_admin): ?>
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAnnouncementForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_announcement_id" name="announcement_id">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Content (Text)</label>
                            <textarea class="form-control" id="edit_content" name="edit_content" rows="6"></textarea>
                            <small class="form-text text-muted">This will be displayed if no PDF is uploaded. New PDF will replace current content.</small>
                        </div>
                        <div class="mb-3" id="currentPdfSection">
                            <label class="form-label d-block">Current PDF Attachment</label>
                            <span id="currentPdfLink"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 form-file-clear-btn" id="removeCurrentPdfBtn">Remove PDF</button>
                            <input type="hidden" name="remove_current_pdf" id="remove_current_pdf_flag" value="false">
                        </div>
                        <div class="mb-3">
                            <label for="edit_announcement_file" class="form-label">Upload New PDF (Optional)</label>
                            <input class="form-control" type="file" id="edit_announcement_file" name="edit_announcement_file" accept="application/pdf">
                            <small class="form-text text-muted">Upload a new PDF to replace the current content. Max 10MB.</small>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" name="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Active (Visible to users)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveEditButton">
                            <span id="saveEditButtonText">Save Changes</span>
                            <span id="saveEditSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal (Admin Only) -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    Are you sure you want to delete this announcement?
                    <input type="hidden" id="confirm_delete_id">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Notification Element -->
    <div id="toast" class="toast-notification"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const is_admin = <?php echo $is_admin ? 'true' : 'false'; ?>;
            const contentTruncateLength = 250; // Max characters before "Read More"

            const announcementsFeedContainer = document.querySelector('.announcements-feed-container');
            let announcementsFeed = document.getElementById('announcementsFeed');
            let loadingMessage = document.getElementById('loadingMessage');
            let noAnnouncementsMessage = null; // Will be created dynamically if needed

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

            // Function to fetch and display announcements (for both users and admins)
            function fetchAndDisplayAnnouncements() {
                // Show loading message
                if (loadingMessage) {
                    loadingMessage.style.display = 'block';
                }
                if (announcementsFeed) {
                    announcementsFeed.innerHTML = ''; // Clear existing content while loading
                }
                if (noAnnouncementsMessage) {
                    noAnnouncementsMessage.style.display = 'none';
                }


                axios.get(`announcements.php?fetch_json=true&admin_view=${is_admin ? 'true' : 'false'}`)
                    .then(response => {
                        const data = response.data;
                        if (data.success && data.announcements) {
                            // Hide loading message
                            if (loadingMessage) {
                                loadingMessage.style.display = 'none';
                            }

                            // Update admin counts if admin is logged in
                            if (is_admin) {
                                document.getElementById('totalAnnouncementsCount').textContent = data.total_announcements;
                                document.getElementById('activeAnnouncementsCount').textContent = data.active_announcements_count;
                                document.getElementById('inactiveAnnouncementsCount').textContent = data.total_announcements - data.active_announcements_count;
                            }


                            if (data.announcements.length > 0) {
                                if (!announcementsFeed) { // Create feed container if it was removed
                                    announcementsFeed = document.createElement('div');
                                    announcementsFeed.id = 'announcementsFeed';
                                    announcementsFeed.className = 'announcements-feed';
                                    announcementsFeedContainer.appendChild(announcementsFeed);
                                }
                                if (noAnnouncementsMessage) { // Hide no announcements message
                                    noAnnouncementsMessage.style.display = 'none';
                                }

                                const newAnnouncementsHtml = data.announcements.map(announcement => {
                                    const isActive = announcement.is_active === '1' || announcement.is_active === 1;
                                    const cardClass = is_admin && !isActive ? 'inactive' : '';

                                    let contentHtml = announcement.display_content;
                                    let contentIsPdf = announcement.display_content.includes('View PDF Attachment'); // Simple check for PDF link

                                    if (!contentIsPdf) {
                                        // Handle "Read More/Less" for text content
                                        const rawContent = announcement.content_raw; // Use raw content for truncation
                                        if (rawContent.length > contentTruncateLength) {
                                            const truncatedContent = rawContent.substring(0, contentTruncateLength) + '...';
                                            contentHtml = `
                                                <span class="full-content d-none">${rawContent.replace(/\n/g, '<br>')}</span>
                                                <span class="truncated-content">${truncatedContent.replace(/\n/g, '<br>')}</span>
                                                <button class="btn btn-link btn-sm read-more-btn">Read More</button>
                                            `;
                                        } else {
                                            contentHtml = rawContent.replace(/\n/g, '<br>');
                                        }
                                    }

                                    let adminActionsHtml = '';
                                    if (is_admin) {
                                        adminActionsHtml = `
                                            <div class="announcement-admin-actions">
                                                <div class="form-switch-wrapper">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input announcement-toggle" type="checkbox" role="switch" id="toggle_${announcement.id}" ${isActive ? 'checked' : ''}>
                                                        <label class="form-check-label" for="toggle_${announcement.id}">${isActive ? 'Active' : 'Inactive'}</label>
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-info btn-edit-announcement" data-id="${announcement.id}" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal">
                                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-delete-announcement" data-id="${announcement.id}" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal">
                                                    <i class="bi bi-trash me-1"></i> Delete
                                                </button>
                                            </div>
                                        `;
                                    }

                                    return `
                                        <div class="announcement-card ${cardClass}" data-id="${announcement.id}">
                                            <div class="announcement-header">
                                                <div class="announcement-icon-wrapper">
                                                    <i class="bi bi-megaphone-fill"></i> 
                                                </div>
                                                <div class="announcement-title-and-date">
                                                    <h5 class="card-title">
                                                        ${announcement.title}
                                                    </h5>
                                                    <div class="card-date">
                                                        Published on: ${announcement.created_at}
                                                        ${is_admin && !isActive ? '<span class="badge bg-secondary ms-2">Inactive</span>' : ''}
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="card-text">${contentHtml}</p> 
                                            ${adminActionsHtml}
                                        </div>
                                    `;
                                }).join('');

                                if (announcementsFeed.innerHTML.trim() !== newAnnouncementsHtml.trim()) {
                                    announcementsFeed.innerHTML = newAnnouncementsHtml;
                                    attachGeneralEventListeners(); // Attach general listeners (read more)
                                    if (is_admin) {
                                        attachAdminEventListeners(); // Attach admin specific listeners
                                    }
                                }

                            } else {
                                // No announcements
                                if (announcementsFeed) {
                                    announcementsFeed.innerHTML = '';
                                }
                                if (!noAnnouncementsMessage) {
                                    noAnnouncementsMessage = document.createElement('div');
                                    noAnnouncementsMessage.id = 'noAnnouncementsMessage';
                                    noAnnouncementsMessage.className = 'message-container';
                                    noAnnouncementsMessage.innerHTML = `<p><i class="bi bi-megaphone"></i></p><p>No active announcements at this time.</p><p>Check back later for important updates!</p>`;
                                    announcementsFeedContainer.appendChild(noAnnouncementsMessage);
                                }
                                noAnnouncementsMessage.style.display = 'block';
                            }
                        } else if (data.error) {
                            console.error('Server error fetching announcements:', data.error);
                            if (announcementsFeed) announcementsFeed.innerHTML = '';
                            if (noAnnouncementsMessage) { noAnnouncementsMessage.style.display = 'none'; }
                            if (loadingMessage) { loadingMessage.style.display = 'none'; }
                            announcementsFeedContainer.innerHTML = `<div class="alert alert-warning message-container" role="alert">Error loading announcements: ${data.error}</div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Network error fetching announcements:', error);
                        if (announcementsFeed) announcementsFeed.innerHTML = '';
                        if (noAnnouncementsMessage) { noAnnouncementsMessage.style.display = 'none'; }
                        if (loadingMessage) { loadingMessage.style.display = 'none'; }
                        announcementsFeedContainer.innerHTML = `<div class="alert alert-danger message-container" role="alert">Could not connect to fetch announcements. Please check your internet connection or server status.</div>`;
                    });
            }

            // --- General JavaScript Logic (for all users) ---
            function attachGeneralEventListeners() {
                document.querySelectorAll('.read-more-btn').forEach(button => {
                    button.removeEventListener('click', toggleReadMore); // Prevent duplicate
                    button.addEventListener('click', toggleReadMore);
                });
            }

            function toggleReadMore(event) {
                const cardText = event.target.closest('.card-text');
                const fullContent = cardText.querySelector('.full-content');
                const truncatedContent = cardText.querySelector('.truncated-content');
                const button = event.target;

                if (fullContent.classList.contains('d-none')) {
                    fullContent.classList.remove('d-none');
                    truncatedContent.classList.add('d-none');
                    button.textContent = 'Read Less';
                } else {
                    fullContent.classList.add('d-none');
                    truncatedContent.classList.remove('d-none');
                    button.textContent = 'Read More';
                }
            }


            // --- Admin Specific JavaScript Logic ---
            if (is_admin) {
                const addAnnouncementForm = document.getElementById('addAnnouncementForm');
                const addAnnouncementButton = document.getElementById('addAnnouncementButton');
                const addAnnouncementButtonText = document.getElementById('addAnnouncementButtonText');
                const addAnnouncementSpinner = document.getElementById('addAnnouncementSpinner');
                const announcementContentInput = document.getElementById('announcement_content');
                const announcementFileInput = document.getElementById('announcement_file');

                const editAnnouncementModalElement = document.getElementById('editAnnouncementModal');
                const editAnnouncementModal = new bootstrap.Modal(editAnnouncementModalElement);
                const editAnnouncementForm = document.getElementById('editAnnouncementForm');
                const editAnnouncementIdInput = document.getElementById('edit_announcement_id');
                const editTitleInput = document.getElementById('edit_title');
                const editContentInput = document.getElementById('edit_content');
                const editIsActiveToggle = document.getElementById('edit_is_active');
                const currentPdfSection = document.getElementById('currentPdfSection');
                const currentPdfLink = document.getElementById('currentPdfLink');
                const removeCurrentPdfBtn = document.getElementById('removeCurrentPdfBtn');
                const removeCurrentPdfFlag = document.getElementById('remove_current_pdf_flag');
                const editAnnouncementFileInput = document.getElementById('edit_announcement_file');

                const saveEditButton = document.getElementById('saveEditButton');
                const saveEditButtonText = document.getElementById('saveEditButtonText');
                const saveEditSpinner = document.getElementById('saveEditSpinner');

                const deleteConfirmationModalElement = document.getElementById('deleteConfirmationModal');
                const deleteConfirmationModal = new bootstrap.Modal(deleteConfirmationModalElement);
                const confirmDeleteIdInput = document.getElementById('confirm_delete_id');
                const confirmDeleteButton = document.getElementById('confirmDeleteButton');


                // Enable/Disable add announcement button
                const toggleAddButtonState = () => {
                    const titleFilled = document.getElementById('announcement_title').value.trim() !== '';
                    const contentOrFile = announcementContentInput.value.trim() !== '' || announcementFileInput.files.length > 0;
                    addAnnouncementButton.disabled = !(titleFilled && contentOrFile);
                };
                addAnnouncementForm.addEventListener('input', toggleAddButtonState);
                announcementFileInput.addEventListener('change', toggleAddButtonState);
                toggleAddButtonState(); // Initial check


                // Handle add announcement form submission
                addAnnouncementForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    addAnnouncementButtonText.textContent = 'Sending...';
                    addAnnouncementSpinner.classList.remove('d-none');
                    addAnnouncementButton.disabled = true;

                    const formData = new FormData(addAnnouncementForm);
                    formData.append('action', 'add_announcement');

                    axios.post('announcements.php', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    })
                    .then(response => {
                        const data = response.data;
                        if (data.success) {
                            showToast(data.message, 'success');
                            addAnnouncementForm.reset();
                            toggleAddButtonState();
                            fetchAndDisplayAnnouncements();
                        } else {
                            showToast(data.error || 'An unknown error occurred.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error adding announcement:', error);
                        const errorMessage = error.response?.data?.error || 'A server error occurred. Please try again.';
                        showToast(errorMessage, 'error');
                    })
                    .finally(() => {
                        addAnnouncementButtonText.textContent = 'Send Announcement';
                        addAnnouncementSpinner.classList.add('d-none');
                        toggleAddButtonState();
                    });
                });

                // Attach admin specific event listeners (edit, delete, toggle)
                function attachAdminEventListeners() {
                    document.querySelectorAll('.announcement-toggle').forEach(toggle => {
                        toggle.removeEventListener('change', handleToggleAnnouncementStatus);
                        toggle.addEventListener('change', handleToggleAnnouncementStatus);
                    });

                    document.querySelectorAll('.btn-edit-announcement').forEach(button => {
                        button.removeEventListener('click', handleEditAnnouncementClick);
                        button.addEventListener('click', handleEditAnnouncementClick);
                    });

                    document.querySelectorAll('.btn-delete-announcement').forEach(button => {
                        button.removeEventListener('click', handleDeleteConfirmation); // Open confirmation modal first
                        button.addEventListener('click', handleDeleteConfirmation);
                    });
                }

                // Handler for toggling announcement status
                function handleToggleAnnouncementStatus(event) {
                    const announcementId = event.target.closest('.announcement-card').dataset.id;
                    const isActive = event.target.checked ? 1 : 0;
                    const label = event.target.nextElementSibling;

                    axios.post('announcements.php', new URLSearchParams({
                        action: 'toggle_announcement_status',
                        id: announcementId,
                        is_active: isActive
                    }))
                    .then(response => {
                        const data = response.data;
                        if (data.success) {
                            showToast(data.message, 'success');
                            const card = event.target.closest('.announcement-card');
                            if (isActive) {
                                card.classList.remove('inactive');
                                label.textContent = 'Active';
                            } else {
                                card.classList.add('inactive');
                                label.textContent = 'Inactive';
                            }
                            fetchAndDisplayAnnouncements();
                        } else {
                            showToast(data.error || 'Failed to update status.', 'error');
                            event.target.checked = !isActive;
                            label.textContent = !isActive ? 'Active' : 'Inactive';
                        }
                    })
                    .catch(error => {
                        console.error('Error toggling status:', error);
                        const errorMessage = error.response?.data?.error || 'A server error occurred. Failed to update status.';
                        showToast(errorMessage, 'error');
                        event.target.checked = !isActive;
                        label.textContent = !isActive ? 'Active' : 'Inactive';
                    });
                }

                // Handler for clicking Edit button
                function handleEditAnnouncementClick(event) {
                    const announcementId = event.target.closest('button').dataset.id;
                    editAnnouncementIdInput.value = announcementId;
                    
                    editAnnouncementForm.reset();
                    currentPdfSection.classList.add('d-none');
                    currentPdfLink.innerHTML = '';
                    removeCurrentPdfFlag.value = 'false';
                    editAnnouncementFileInput.value = '';


                    axios.get(`announcements.php?action=fetch_announcement_for_edit&id=${announcementId}`)
                        .then(response => {
                            const data = response.data;
                            if (data.success && data.announcement) {
                                const ann = data.announcement;
                                editTitleInput.value = ann.title;
                                editContentInput.value = ann.content;
                                editIsActiveToggle.checked = ann.is_active === 1;
                                
                                if (ann.file_path) {
                                    currentPdfSection.classList.remove('d-none');
                                    currentPdfLink.innerHTML = `<a href="${ann.file_path}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Current PDF</a>`;
                                    removeCurrentPdfBtn.classList.remove('d-none');
                                } else {
                                    currentPdfSection.classList.add('d-none');
                                    currentPdfLink.innerHTML = '';
                                    removeCurrentPdfBtn.classList.add('d-none');
                                }

                            } else {
                                showToast(data.error || 'Failed to load announcement for editing.', 'error');
                                editAnnouncementModal.hide();
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching announcement for edit:', error);
                            showToast('A network error occurred. Failed to load announcement for editing.', 'error');
                            editAnnouncementModal.hide();
                        });
                }

                // Handle remove current PDF button
                removeCurrentPdfBtn.addEventListener('click', () => {
                    currentPdfSection.classList.add('d-none');
                    currentPdfLink.innerHTML = '';
                    removeCurrentPdfFlag.value = 'true';
                    removeCurrentPdfBtn.classList.add('d-none');
                    editAnnouncementFileInput.value = '';
                    showToast('PDF marked for removal on save. Upload new file or add text content.', 'info');
                });


                // Handle Edit Form Submission
                editAnnouncementForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    saveEditButtonText.textContent = 'Saving...';
                    saveEditSpinner.classList.remove('d-none');
                    saveEditButton.disabled = true;

                    const formData = new FormData(editAnnouncementForm);
                    formData.append('action', 'edit_announcement');
                    formData.append('edit_is_active', editIsActiveToggle.checked ? 1 : 0);

                    axios.post('announcements.php', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    })
                    .then(response => {
                        const data = response.data;
                        if (data.success) {
                            showToast(data.message, 'success');
                            editAnnouncementModal.hide();
                            fetchAndDisplayAnnouncements();
                        } else {
                            showToast(data.error || 'An unknown error occurred.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error editing announcement:', error);
                        const errorMessage = error.response?.data?.error || 'A server error occurred. Please try again.';
                        showToast(errorMessage, 'error');
                    })
                    .finally(() => {
                        saveEditButtonText.textContent = 'Save Changes';
                        saveEditSpinner.classList.add('d-none');
                        saveEditButton.disabled = false;
                    });
                });

                // Open Delete Confirmation Modal
                function handleDeleteConfirmation(event) {
                    const announcementId = event.target.closest('button').dataset.id;
                    confirmDeleteIdInput.value = announcementId;
                    deleteConfirmationModal.show();
                }

                // Handle actual deletion after confirmation
                confirmDeleteButton.addEventListener('click', () => {
                    const announcementId = confirmDeleteIdInput.value;
                    const cardToDelete = document.querySelector(`.announcement-card[data-id="${announcementId}"]`);

                    deleteConfirmationModal.hide(); // Hide the modal immediately

                    axios.post('announcements.php', new URLSearchParams({
                        action: 'delete_announcement',
                        id: announcementId
                    }))
                    .then(response => {
                        const data = response.data;
                        if (data.success) {
                            showToast(data.message, 'success');
                            if (cardToDelete) {
                                cardToDelete.remove();
                            }
                            fetchAndDisplayAnnouncements(); // Refresh the list to update counts/messages
                        } else {
                            showToast(data.error || 'Failed to delete announcement.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting announcement:', error);
                        const errorMessage = error.response?.data?.error || 'A server error occurred. Failed to delete announcement.';
                        showToast(errorMessage, 'error');
                    });
                });

                // Initial attachment of admin event listeners when the page loads
                attachAdminEventListeners();
            }
            // --- End of Admin Specific JavaScript Logic ---

            // Fetch and display announcements initially and then every 5 seconds (5000 ms)
            setInterval(fetchAndDisplayAnnouncements, 5000); 
            fetchAndDisplayAnnouncements();
        });
    </script>
</body>
</html>
