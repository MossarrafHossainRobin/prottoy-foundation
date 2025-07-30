<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// Check if database connection failed
if (!$conn) {
    die("Database connection failed. Please contact support.");
}

// --- FPDF Library Inclusion ---
// You MUST download FPDF (fpdf.php) and place it in the same directory as this file.
// Get FPDF from: http://www.fpdf.org/
require('fpdf/fpdf.php');

$message = '';
$message_type = '';

function sanitize_input($data) {
    global $conn; // Access the global connection object
    $data = trim($data);
    $data = stripslashes($data);
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    return $data;
}

// Function to handle file upload
function handle_profile_picture_upload($file_input_name, $current_pic_url = null) {
    $upload_dir = 'uploads/profile_pics/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            return ['error' => "Server error: Cannot create upload directory. Check permissions."];
        }
    }

    if (!is_writable($upload_dir)) {
        return ['error' => "Server error: Upload directory is not writable. Check permissions."];
    }

    $new_pic_url = $current_pic_url;

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];

        $file_tmp_name = $file['tmp_name'];
        $file_name = $file['name'];
        $file_size = $file['size'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actual_mime_type = finfo_file($finfo, $file_tmp_name);
        finfo_close($finfo);

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_file_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file_ext, $allowed_extensions) || !in_array($actual_mime_type, $allowed_mime_types)) {
            return ['error' => "Invalid file type. Only JPG, JPEG, PNG, GIF allowed. Actual type: " . $actual_mime_type];
        }
        if ($file_size > $max_file_size) {
            return ['error' => "File size exceeds 2MB limit."];
        }

        $unique_file_name = uniqid('profile_') . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
        $destination = $upload_dir . $unique_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            if ($current_pic_url && strpos($current_pic_url, 'placehold.co') === false && file_exists($current_pic_url) && $current_pic_url !== $destination) {
                @unlink($current_pic_url);
            }
            $new_pic_url = $destination;
        } else {
            return ['error' => "Failed to move uploaded file. Error code: " . $file['error']];
        }
    } elseif (isset($_POST['remove_current_pic']) && $_POST['remove_current_pic'] === '1') {
        if ($current_pic_url && strpos($current_pic_url, 'placehold.co') === false && file_exists($current_pic_url)) {
            @unlink($current_pic_url);
        }
        $new_pic_url = '';
    } else {
        $new_pic_url = $current_pic_url;
    }

    return ['success' => $new_pic_url];
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = $_SESSION['user_id']; // Get the logged-in admin's user ID

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $name = sanitize_input($_POST['name']);
                $email = sanitize_input($_POST['email']);
                $password = $_POST['password'];
                $phone = sanitize_input($_POST['phone']);
                $home_address = sanitize_input($_POST['home_address']);
                // Removed bio and interests from input handling
                $role = sanitize_input($_POST['role']);
                $status = isset($_POST['status']) ? 1 : 0;
                $designation = sanitize_input($_POST['designation']);

                $profile_picture_url = '';
                $upload_result = handle_profile_picture_upload('addProfilePic');
                if (isset($upload_result['error'])) {
                    $message = "Upload Error: " . $upload_result['error'];
                    $message_type = 'danger';
                    break;
                } else {
                    $profile_picture_url = $upload_result['success'];
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $check_sql = "SELECT id FROM users WHERE email = ?";
                $stmt_check = $conn->prepare($check_sql);
                if ($stmt_check === false) {
                    $message = "Database error (User Check): " . $conn->error;
                    $message_type = 'danger';
                    break;
                }
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $message = "Error: User with this email already exists.";
                    $message_type = 'danger';
                    if ($profile_picture_url && file_exists($profile_picture_url)) {
                        @unlink($profile_picture_url);
                    }
                } else {
                    // Removed 'bio', 'interests' from INSERT query
                    $sql = "INSERT INTO users (name, email, password, phone, home_address, profile_picture_url, role, status, designation, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        // 'sssssssis' for name, email, password, phone, home_address, profile_picture_url, role, status, designation
                        $stmt->bind_param("sssssssis", $name, $email, $hashed_password, $phone, $home_address, $profile_picture_url, $role, $status, $designation);
                        if ($stmt->execute()) {
                            $message = "User added successfully!";
                            $message_type = 'success';
                        } else {
                            $message = "Error adding user: " . $stmt->error;
                            $message_type = 'danger';
                            if ($profile_picture_url && file_exists($profile_picture_url)) {
                                @unlink($profile_picture_url);
                            }
                        }
                        $stmt->close();
                    } else {
                        $message = "Database error (Add User): " . $conn->error;
                        $message_type = 'danger';
                        if ($profile_picture_url && file_exists($profile_picture_url)) {
                            @unlink($profile_picture_url);
                        }
                    }
                }
                $stmt_check->close();
                break;

            case 'edit_user':
                $id = sanitize_input($_POST['user_id']);
                $name = sanitize_input($_POST['name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $home_address = sanitize_input($_POST['home_address']);
                // Removed bio and interests from input handling
                $role = sanitize_input($_POST['role']);
                $status = isset($_POST['status']) ? 1 : 0;
                $designation = sanitize_input($_POST['designation']);

                $current_pic_sql = "SELECT profile_picture_url FROM users WHERE id = ?";
                $stmt_current_pic = $conn->prepare($current_pic_sql);
                if ($stmt_current_pic === false) {
                    $message = "Database error (Fetch Current Pic): " . $conn->error;
                    $message_type = 'danger';
                    break;
                }
                $stmt_current_pic->bind_param("i", $id);
                $stmt_current_pic->execute();
                $stmt_current_pic->bind_result($current_profile_picture_url);
                $stmt_current_pic->fetch();
                $stmt_current_pic->close();

                $upload_result = handle_profile_picture_upload('editProfilePic', $current_profile_picture_url);
                if (isset($upload_result['error'])) {
                    $message = "Upload Error: " . $upload_result['error'];
                    $message_type = 'danger';
                    break;
                } else {
                    $profile_picture_url = $upload_result['success'];
                }

                $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_check = $conn->prepare($check_sql);
                if ($stmt_check === false) {
                    $message = "Database error (Email Check): " . $conn->error;
                    $message_type = 'danger';
                    break;
                }
                $stmt_check->bind_param("si", $email, $id);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $message = "Error: User with this email already exists.";
                    $message_type = 'danger';
                    if ($profile_picture_url && $profile_picture_url !== $current_profile_picture_url && file_exists($profile_picture_url)) {
                        @unlink($profile_picture_url);
                    }
                } else {
                    // Removed 'bio', 'interests' from UPDATE query
                    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, home_address = ?, profile_picture_url = ?, role = ?, status = ?, designation = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        // 'ssssssisi' for name, email, phone, home_address, profile_picture_url, role, status, designation, id
                        $stmt->bind_param("ssssssisi", $name, $email, $phone, $home_address, $profile_picture_url, $role, $status, $designation, $id);
                        if ($stmt->execute()) {
                            $message = "User updated successfully!";
                            $message_type = 'success';
                        } else {
                            $message = "Error updating user: " . $stmt->error;
                            $message_type = 'danger';
                        }
                        $stmt->close();
                    } else {
                        $message = "Database error (Update User): " . $conn->error;
                        $message_type = 'danger';
                    }
                }
                $stmt_check->close();
                break;

            case 'delete_user': // Individual delete
                // Ensure only admin can delete
                if ($_SESSION['user_role'] !== 'admin') {
                    $message = "Unauthorized: Only administrators can delete users.";
                    $message_type = 'danger';
                    break;
                }
                $id = sanitize_input($_POST['user_id']);
                
                $pic_to_delete_sql = "SELECT profile_picture_url FROM users WHERE id = ?";
                $stmt_pic_to_delete = $conn->prepare($pic_to_delete_sql);
                if ($stmt_pic_to_delete === false) {
                    $message = "Database error (Fetch Pic for Delete): " . $conn->error;
                    $message_type = 'danger';
                    break;
                }
                $stmt_pic_to_delete->bind_param("i", $id);
                $stmt_pic_to_delete->execute();
                $stmt_pic_to_delete->bind_result($pic_url);
                $stmt_pic_to_delete->fetch();
                $stmt_pic_to_delete->close();

                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $message = "User deleted successfully!";
                        $message_type = 'success';
                        if ($pic_url && strpos($pic_url, 'placehold.co') === false && file_exists($pic_url)) {
                            @unlink($pic_url);
                        }
                    } else {
                        $message = "Error deleting user: " . $stmt->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = "Database error (Delete User): " . $conn->error;
                    $message_type = 'danger';
                }
                break;

            case 'reset_password':
                $id = sanitize_input($_POST['user_id']);
                $new_password = $_POST['new_password'];
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("si", $hashed_password, $id);
                    if ($stmt->execute()) {
                        $message = "Password reset successfully!";
                        $message_type = 'success';
                    } else {
                        $message = "Error resetting password: " . $stmt->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = "Database error (Reset Password): " . $conn->error;
                    $message_type = 'danger';
                }
                break;
            
            case 'change_role':
                // Ensure only admin can change role
                if ($_SESSION['user_role'] !== 'admin') {
                    $message = "Unauthorized: Only administrators can change user roles.";
                    $message_type = 'danger';
                    break;
                }
                $id = sanitize_input($_POST['user_id']);
                $role = sanitize_input($_POST['new_role']);

                $sql = "UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("si", $role, $id);
                    if ($stmt->execute()) {
                        $message = "User role updated to '{$role}' successfully!";
                        $message_type = 'success';
                    } else {
                        $message = "Error updating user role: " . $stmt->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = "Database error (Change Role): " . $conn->error;
                    $message_type = 'danger';
                }
                break;
            
            case 'change_status':
                // Ensure only admin can change status
                if ($_SESSION['user_role'] !== 'admin') {
                    $message = "Unauthorized: Only administrators can change user status.";
                    $message_type = 'danger';
                    break;
                }
                $id = sanitize_input($_POST['user_id']);
                $new_status = sanitize_input($_POST['new_status']);

                $sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ii", $new_status, $id);
                    if ($stmt->execute()) {
                        $status_text = ($new_status == 1) ? 'Active' : 'Inactive';
                        $message = "User status updated to '{$status_text}' successfully!";
                        $message_type = 'success';
                    } else {
                        $message = "Error updating user status: " . $stmt->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = "Database error (Change Status): " . $conn->error;
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Redirect after POST to prevent form resubmission
if ($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Display messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Determine which tab to show by default (based on query param or default to users)
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';


// --- Filtering Configuration (Re-defined for PDF & main query) ---
$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_role = isset($_GET['filter_role']) ? sanitize_input($_GET['filter_role']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_input($_GET['filter_status']) : '';
$filter_designation = isset($_GET['filter_designation']) ? sanitize_input($_GET['filter_designation']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'id';
$sort_order = isset($_GET['sort_order']) ? sanitize_input($_GET['sort_order']) : 'ASC';

$allowed_sort_columns = [
    'id', 'name', 'email', 'phone', 'home_address', 'designation',
    'role', 'status', 'created_at', 'updated_at' // Removed bio, interests
];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'id';
}
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Build WHERE Clause and parameters for both count and main query (for users)
$where_clauses_users = [];
$params_users = [];
$types_users = '';

if (!empty($search_query)) {
    // Removed bio, interests from search
    $where_clauses_users[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR home_address LIKE ? OR designation LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params_users = array_merge($params_users, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types_users .= 'sssss'; // Adjusted type string
}
if (!empty($filter_role) && ($filter_role === 'user' || $filter_role === 'admin')) {
    $where_clauses_users[] = "role = ?";
    $params_users[] = $filter_role;
    $types_users .= 's';
}
if ($filter_status !== '') {
    $where_clauses_users[] = "status = ?";
    $params_users[] = (int)$filter_status;
    $types_users .= 'i';
}
if (!empty($filter_designation)) {
    $where_clauses_users[] = "designation LIKE ?";
    $params_users[] = '%' . $filter_designation . '%';
    $types_users .= 's';
}

$where_sql_users = count($where_clauses_users) > 0 ? ' WHERE ' . implode(' AND ', $where_clauses_users) : '';


// --- PDF Download Logic (Before HTML output) ---
if (isset($_GET['action']) && $_GET['action'] == 'download_pdf') {
    $pdf_sql = "SELECT id, name, email, phone, designation, role, status, created_at FROM users" . $where_sql_users . " ORDER BY " . $sort_by . " " . $sort_order;
    $stmt_pdf = $conn->prepare($pdf_sql);
    if ($stmt_pdf === false) {
        die("Error preparing PDF data query: " . $conn->error);
    }

    $pdf_params = $params_users;
    $pdf_types = $types_users;

    if (!empty($pdf_params)) {
        $bind_params_pdf = [$pdf_types];
        foreach ($pdf_params as $key => $value) {
            $bind_params_pdf[] = &$pdf_params[$key];
        }
        call_user_func_array([$stmt_pdf, 'bind_param'], $bind_params_pdf);
    }
    $stmt_pdf->execute();
    $pdf_result = $stmt_pdf->get_result();
    $pdf_users = [];
    while ($row = $pdf_result->fetch_assoc()) {
        $pdf_users[] = $row;
    }
    $stmt_pdf->close();
    
    // PDF Generation
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'User List',0,1,'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(200,220,255);
    $pdf->Cell(10,7,'ID',1,0,'C',true);
    $pdf->Cell(40,7,'Name',1,0,'C',true);
    $pdf->Cell(50,7,'Email',1,0,'C',true);
    $pdf->Cell(25,7,'Phone',1,0,'C',true);
    $pdf->Cell(30,7,'Designation',1,0,'C',true);
    $pdf->Cell(15,7,'Role',1,0,'C',true);
    $pdf->Cell(20,7,'Status',1,1,'C',true);

    $pdf->SetFont('Arial','',10);
    foreach($pdf_users as $user) {
        $status_text = ($user['status'] == 1) ? 'Active' : 'Inactive';
        $pdf->Cell(10,7,$user['id'],1);
        $pdf->Cell(40,7,$user['name'],1);
        $pdf->Cell(50,7,$user['email'],1);
        $pdf->Cell(25,7,$user['phone'],1);
        $pdf->Cell(30,7,$user['designation'],1);
        $pdf->Cell(15,7,ucfirst($user['role']),1);
        $pdf->Cell(20,7,$status_text,1,1);
    }

    $pdf->Output('D', 'user_list.pdf');
    exit();
}


// --- User Data Fetching ---
$total_records_users = 0;
$users = [];

$count_sql_users = "SELECT COUNT(id) FROM users" . $where_sql_users;
$stmt_count_users = $conn->prepare($count_sql_users);
if ($stmt_count_users === false) {
    die("Error preparing count query: " . $conn->error);
}

if (!empty($params_users)) {
    $bind_params_count_users = [$types_users];
    foreach ($params_users as $key => $value) {
        $bind_params_count_users[] = &$params_users[$key];
    }
    call_user_func_array([$stmt_count_users, 'bind_param'], $bind_params_count_users);
}
$stmt_count_users->execute();
$stmt_count_users->bind_result($total_records_users);
$stmt_count_users->fetch();
$stmt_count_users->close();


// Fetch users for display (Removed bio and interests from SELECT)
$sql_users = "SELECT id, name, email, phone, home_address, profile_picture_url, role, status, designation, created_at, updated_at FROM users" . $where_sql_users . " ORDER BY " . $sort_by . " " . $sort_order;
$stmt_users = $conn->prepare($sql_users);
if ($stmt_users === false) {
    die("Error preparing users query: " . $conn->error);
}

if (!empty($params_users)) {
    $bind_params_users = [$types_users];
    foreach ($params_users as $key => $value) {
        $bind_params_users[] = &$params_users[$key];
    }
    call_user_func_array([$stmt_users, 'bind_param'], $bind_params_users);
}
$stmt_users->execute();
$result_users = $stmt_users->get_result();
while ($row = $result_users->fetch_assoc()) {
    $users[] = $row;
}
$stmt_users->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prottoy Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);
            --secondary-gradient: linear-gradient(to right, #0acffe 0%, #495aff 100%);
            --dark-blue-green: #2c3e50;
            --light-gray: #f8f9fa;
            --medium-gray: #dee2e6;
            --text-dark: #343a40;
            --text-light: #6c757d;
            --card-border-radius: 1rem;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-gray);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar Enhancements */
        .navbar {
            background: var(--dark-blue-green) !important;
            box-shadow: var(--shadow-medium);
            padding: 0.75rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            color: #fff !important;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .navbar-brand:hover {
            transform: scale(1.02);
            color: #ffcc00 !important; /* A touch of accent on hover */
        }

        /* Prottoy Logo Specifics */
        .logo-container {
            width: 40px;
            height: 40px;
            position: relative;
            margin-right: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-svg {
            width: 100%;
            height: 100%;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            overflow: visible; /* Allows the glow effect to extend */
        }

        .heart-path {
            fill: #ff6f61; /* Vibrant red-orange */
            transition: all 0.5s ease-in-out;
            stroke: #ff6f61;
            stroke-width: 0px;
            filter: drop-shadow(0 0 2px rgba(255,111,97,0.7)); /* Subtle initial glow */
        }

        .navbar-brand:hover .heart-path {
            fill: #ffd700; /* Gold on hover */
            stroke: #ffd700;
            stroke-width: 1px;
            filter: drop-shadow(0 0 8px rgba(255,215,0,0.9)); /* Stronger glow on hover */
        }

        .logo-text {
            fill: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 600;
            transition: fill 0.3s ease;
        }

        .navbar-brand:hover .logo-text {
            fill: #ffcc00;
        }


        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            margin-right: 0.25rem; /* Add a small gap between nav links */
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0%;
            height: 2px;
            background-color: #ff6f61;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: width 0.3s ease-in-out;
        }

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active-nav-link::after { /* Add active state for the underline */
            width: 80%;
        }

        .navbar-nav .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-nav .nav-link.btn-outline-light {
            border-color: #fff !important;
            color: #fff !important;
            transition: all 0.3s ease;
            margin-left: 0.75rem; /* More space for the button */
        }

        .navbar-nav .nav-link.btn-outline-light:hover {
            background-color: #fff !important;
            color: var(--dark-blue-green) !important;
            transform: translateY(-2px);
        }

        /* Main Content Area */
        .container-fluid.mt-4 {
            flex-grow: 1;
            padding-bottom: 2rem; /* Add some padding at the bottom */
        }

        .custom-card-design {
            border-radius: var(--card-border-radius);
            box-shadow: var(--shadow-medium);
            background: #fff;
            padding: 2.5rem !important;
        }

        h2.text-center {
            font-weight: 700;
            color: var(--dark-blue-green);
            margin-bottom: 2rem !important;
            position: relative;
            padding-bottom: 0.5rem;
        }

        h2.text-center::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        /* Alerts */
        .alert {
            border-radius: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-right: 1.5rem;
        }
        .alert .btn-close {
            filter: invert(1);
        }

        /* Tabs Navigation */
        .nav-tabs {
            border-bottom: 2px solid var(--medium-gray);
        }

        .nav-tabs .nav-item .nav-link {
            color: var(--text-light);
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tabs .nav-item .nav-link:hover {
            color: var(--dark-blue-green);
            background-color: #f0f2f5;
            border-color: #a7b7c7;
        }

        .nav-tabs .nav-item .nav-link.active {
            color: var(--dark-blue-green);
            background-color: #fff;
            border-color: var(--primary-gradient);
            border-bottom-width: 3px;
            font-weight: 700;
        }

        /* Section Headers */
        h4.mb-0 {
            font-weight: 600;
            color: var(--dark-blue-green);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        h4 .badge {
            font-size: 0.8em;
            padding: 0.4em 0.7em;
            border-radius: 0.5rem;
            background-color: #6c757d !important;
        }

        /* Custom Buttons */
        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            opacity: 0.9;
        }

        .btn-secondary-custom {
            background: var(--secondary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .btn-secondary-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            opacity: 0.9;
        }

        /* Form Controls */
        .form-control, .form-select {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid var(--medium-gray);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.25rem rgba(106, 17, 203, 0.25); /* Primary gradient color with transparency */
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        /* Table Styling */
        .custom-table {
            border-collapse: separate;
            border-spacing: 0 0.5rem; /* Space between rows */
        }

        .custom-table thead th {
            background-color: var(--dark-blue-green);
            color: #fff;
            font-weight: 600;
            padding: 1rem 1.2rem;
            border: none;
            vertical-align: middle;
            position: sticky;
            top: 0;
            z-index: 1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .custom-table thead th:first-child {
            border-top-left-radius: 0.75rem;
        }
        .custom-table thead th:last-child {
            border-top-right-radius: 0.75rem;
        }

        .custom-table tbody tr {
            background-color: #fff;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-light);
            transition: all 0.2s ease-in-out;
            margin-bottom: 0.5rem; /* Ensure space between rows */
        }

        .custom-table tbody tr:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            background-color: #fdfdfd;
        }

        .custom-table tbody td {
            padding: 1rem 1.2rem;
            border-top: 1px solid #e9ecef; /* Light border between cells */
        }

        .custom-table tbody tr:first-child td {
            border-top: none;
        }

        .custom-table img.rounded-circle {
            border: 2px solid var(--primary-gradient);
            padding: 2px;
        }

        .custom-table .badge {
            padding: 0.5em 0.8em;
            border-radius: 0.5rem;
            font-weight: 600;
        }

        .custom-table .long-text, .custom-table .email-col {
            max-width: 150px; /* Limit width for long text and emails */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .custom-table .actions-col {
            min-width: 180px; /* Ensure enough space for buttons */
        }

        .custom-table .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Sortable table headers */
        .sortable {
            cursor: pointer;
            position: relative;
        }

        .sortable .sort-icon {
            margin-left: 0.5rem;
            transition: transform 0.2s ease-in-out;
        }

        .sortable .sort-icon.fa-sort-up {
            color: #00ff00; /* Green for ascending */
        }

        .sortable .sort-icon.fa-sort-down {
            color: #ff0000; /* Red for descending */
        }

        /* Pagination */
        .pagination .page-item .page-link {
            border-radius: 0.5rem;
            margin: 0 0.25rem;
            color: var(--dark-blue-green);
            transition: all 0.3s ease;
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-gradient);
            border-color: #6a11cb;
            color: white;
            box-shadow: var(--shadow-light);
        }

        .pagination .page-item .page-link:hover {
            background-color: #e9ecef;
            color: var(--dark-blue-green);
        }

        /* Modals */
        .modal-content {
            border-radius: var(--card-border-radius);
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            border-top-left-radius: var(--card-border-radius);
            border-top-right-radius: var(--card-border-radius);
            padding: 1.5rem;
            border-bottom: none;
            position: relative;
            overflow: hidden;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 140%;
            height: 140%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            transform: rotate(15deg);
            z-index: 0;
        }

        .modal-header .modal-title {
            color: white;
            font-weight: 700;
            z-index: 1;
            position: relative;
        }

        .modal-header.bg-danger {
            background: linear-gradient(45deg, #dc3545, #d62f3f) !important;
        }
        .modal-header.bg-info {
            background: linear-gradient(45deg, #0dcaf0, #0aa2c0) !important;
        }

        .modal-header .btn-close {
            z-index: 1;
        }

        .modal-footer {
            border-top: none;
            padding: 1.5rem;
        }

        .modal .btn {
            border-radius: 0.75rem;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
        }

        .modal .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            transition: all 0.3s ease;
        }

        .modal .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }
        
        /* Specific modal button styling for primary and info buttons */
        #changeRoleModal .modal-header.bg-primary {
            background-color: #9C27B0 !important; /* Original vibrant purple */
            background-image: linear-gradient(45deg, #9C27B0 0%, #7b1fa2 100%) !important;
        }

        #changeRoleModal .btn-primary {
            background-color: #9C27B0 !important;
            border-color: #9C27B0 !important;
            transition: all 0.3s ease;
        }

        #changeRoleModal .btn-primary:hover {
            background-color: #7b1fa2 !important;
            border-color: #7b1fa2 !important;
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .custom-card-design {
                padding: 1.5rem !important;
            }
            .d-flex.flex-wrap.gap-2 {
                justify-content: center;
                width: 100%;
            }
            .d-flex.flex-wrap.gap-2 > .btn {
                flex-grow: 1;
            }
            .custom-table th, .custom-table td {
                font-size: 0.85rem;
                padding: 0.75rem 0.8rem;
            }
            .custom-table .actions-col {
                min-width: unset;
                white-space: nowrap;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand span {
                font-size: 1rem !important;
            }
            .logo-container {
                width: 30px;
                height: 30px;
            }
            .logo-text {
                font-size: 16px;
            }
            .custom-card-design {
                padding: 1rem !important;
            }
            h2.text-center {
                font-size: 1.5rem;
            }
            .custom-table .btn-sm {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
            .col-12.col-md-6.col-lg-2.d-grid {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid container">
            <a class="navbar-brand" href="index.html">
                <div class="logo-container">
                    <svg class="logo-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path class="heart-path" d="M50 25C40 5, 20 5, 10 25C0 45, 20 65, 50 85C80 65, 100 45, 90 25C80 5, 60 5, 50 25Z"/>
                    </svg>
                </div>
                <span class="ms-2 fs-5">Prottoy Admin Dashboard</span>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <span class="badge bg-info ms-2">Admin</span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="index.html">Home</a>
                    <a class="nav-link" href="transaction.php">Accounts</a>
                    <a class="nav-link" href="user_dashboard.php">User Dashboard</a>
                    <a class="nav-link" href="announcements.php">Announcements</a>
                    <a class="nav-link btn btn-outline-light ms-2" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="card shadow-sm p-4 custom-card-design">
            <h2 class="text-center mb-4">Admin Management Panel</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4" id="adminDashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($current_tab === 'users' ? 'active' : ''); ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="<?php echo ($current_tab === 'users' ? 'true' : 'false'); ?>">
                        <i class="fas fa-users me-2"></i> User Management
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="adminDashboardTabContent">
                <div class="tab-pane fade <?php echo ($current_tab === 'users' ? 'show active' : ''); ?>" id="users" role="tabpanel" aria-labelledby="users-tab">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                        <h4 class="mb-0">Users <span class="badge bg-secondary"><?php echo $total_records_users; ?></span></h4>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus-circle me-2"></i>Add New User
                            </button>
                            <button type="button" class="btn btn-secondary-custom" id="exportPdfBtn">
                                <i class="fas fa-file-pdf me-2"></i>Export to PDF
                            </button>
                        </div>
                    </div>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="row g-3 mb-4 align-items-end">
                        <input type="hidden" name="tab" value="users">
                        <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                        <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">

                        <div class="col-md-4 col-lg-3">
                            <label for="search" class="form-label">Search Keywords</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, Email, Phone, etc." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>

                        <div class="col-md-4 col-lg-2">
                            <label for="filter_role" class="form-label">Filter by Role</label>
                            <select name="filter_role" id="filter_role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="user" <?php echo ($filter_role === 'user' ? 'selected' : ''); ?>>User</option>
                                <option value="admin" <?php echo ($filter_role === 'admin' ? 'selected' : ''); ?>>Admin</option>
                            </select>
                        </div>

                        <div class="col-md-4 col-lg-2">
                            <label for="filter_status" class="form-label">Filter by Status</label>
                            <select name="filter_status" id="filter_status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="1" <?php echo ($filter_status === '1' ? 'selected' : ''); ?>>Active</option>
                                <option value="0" <?php echo ($filter_status === '0' ? 'selected' : ''); ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <label for="filter_designation" class="form-label">Filter by Designation</label>
                            <input type="text" name="filter_designation" id="filter_designation" class="form-control" placeholder="e.g., Volunteer" value="<?php echo htmlspecialchars($filter_designation); ?>">
                        </div>
                        
                        <div class="col-md-6 col-lg-2 d-grid">
                            <button type="submit" class="btn btn-primary-custom"><i class="fas fa-filter me-2"></i>Apply Filters</button>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2 d-grid">
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=users" class="btn btn-outline-secondary"><i class="fas fa-redo me-2"></i>Clear Filters</a>
                        </div>
                    </form>

                    <form id="userManagementForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=users">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle custom-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col" class="sortable" data-sort-by="id">Picture</th>
                                        <th scope="col" class="sortable" data-sort-by="name">Name <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'name' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable email-col" data-sort-by="email">Email <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'email' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable" data-sort-by="phone">Phone <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'phone' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable" data-sort-by="home_address">Address <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'home_address' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable" data-sort-by="designation">Designation <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'designation' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable" data-sort-by="role">Role <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'role' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable" data-sort-by="status">Status <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'status' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="sortable" data-sort-by="created_at">Created At <i class="sort-icon fas fa-sort<?php echo ($sort_by === 'created_at' ? ($sort_order === 'ASC' ? '-up' : '-down') : ''); ?>"></i></th>
                                        <th scope="col" class="actions-col text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="table-body-users">
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4 text-muted">No users found matching your criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $serial_number = 1; ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $serial_number++; ?></td>
                                                <td>
                                                    <img src="<?php echo htmlspecialchars($user['profile_picture_url'] ?: 'https://placehold.co/60x60/d4edda/155724?text=PF'); ?>" class="rounded-circle" alt="Profile" style="width: 50px; height: 50px; object-fit: cover;">
                                                </td>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td class="email-col" title="<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                                <td class="long-text" title="<?php echo htmlspecialchars($user['home_address']); ?>"><?php echo htmlspecialchars($user['home_address'] ?: 'N/A'); ?></td>
                                                <td class="long-text" title="<?php echo htmlspecialchars($user['designation']); ?>"><?php echo htmlspecialchars($user['designation'] ?: 'N/A'); ?></td>
                                                <td><span class="badge <?php echo ($user['role'] == 'admin' ? 'bg-info' : 'bg-secondary'); ?>"><?php echo htmlspecialchars(ucfirst($user['role'] ?: 'user')); ?></span></td>
                                                <td>
                                                    <?php if ($user['status'] == 1): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['created_at'] ? date('Y-m-d H:i', strtotime($user['created_at'])) : 'N/A'); ?></td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary change-role-btn"
                                                                data-bs-toggle="modal" data-bs-target="#changeRoleModal"
                                                                data-id="<?php echo $user['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                                data-current-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                                title="Change Role">
                                                            <i class="fas fa-user-tag"></i> Role
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-info change-status-btn"
                                                                data-bs-toggle="modal" data-bs-target="#changeStatusModal"
                                                                data-id="<?php echo $user['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                                data-current-status="<?php echo $user['status']; ?>"
                                                                title="Change Status">
                                                            <i class="fas fa-toggle-on"></i> Status
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-user-btn"
                                                                data-bs-toggle="modal" data-bs-target="#deleteConfirmModal"
                                                                data-id="<?php echo $user['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                                title="Delete User">
                                                            <i class="fas fa-trash-alt"></i> Del
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        Are you sure you want to delete user "<strong id="deleteUserName"></strong>"? This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="changeRoleModalLabel"><i class="fas fa-user-tag me-2"></i>Change Role for <span id="changeRoleUserName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" id="changeRoleId">
                        <div class="mb-3">
                            <label for="newRole" class="form-label">Select New Role</label>
                            <select class="form-select" id="newRole" name="new_role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #9C27B0; border-color: #9C27B0;">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="changeStatusModalLabel"><i class="fas fa-toggle-on me-2"></i>Change Status for <span id="changeStatusUserName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="user_id" id="changeStatusUserId">
                        <p>Current Status: <strong id="currentStatusDisplay"></strong></p>
                        <div class="mb-3">
                            <label for="newStatus" class="form-label">Select New Status</label>
                            <select class="form-select" id="newStatus" name="new_status" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white" style="background-color: #007bff !important; background-image: linear-gradient(45deg, #007bff 0%, #0056b3 100%) !important;">
                    <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="userName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="userName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userEmail" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="userEmail" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userPhone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="userPhone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="userPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="userPassword" name="password" required>
                            </div>
                            <div class="col-12">
                                <label for="userAddress" class="form-label">Home Address</label>
                                <input type="text" class="form-control" id="userAddress" name="home_address">
                            </div>
                            <div class="col-md-6">
                                <label for="userDesignation" class="form-label">Designation</label>
                                <input type="text" class="form-control" id="userDesignation" name="designation">
                            </div>
                            <div class="col-md-6">
                                <label for="userRole" class="form-label">Role</label>
                                <select class="form-select" id="userRole" name="role" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="userStatus" class="form-label">Status</label>
                                <select class="form-select" id="userStatus" name="status" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="profilePicture" class="form-label">Profile Picture</label>
                                <input class="form-control" type="file" id="profilePicture" name="profile_picture">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary-custom">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (for modals, dropdowns, collapse, tabs) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const currentUrlParams = new URLSearchParams(window.location.search);

        // --- Universal modal data population for ALL modals ---
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', function() {
                const targetModalId = this.getAttribute('data-bs-target');
                const targetModal = document.querySelector(targetModalId);

                if (targetModal) {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name'); // For user modals
                    
                    // User Modals (Delete, Change Role, Change Status)
                    if (targetModal.querySelector('#deleteUserId')) targetModal.querySelector('#deleteUserId').value = id;
                    if (targetModal.querySelector('#changeRoleId')) targetModal.querySelector('#changeRoleId').value = id;
                    if (targetModal.querySelector('#changeStatusUserId')) targetModal.querySelector('#changeStatusUserId').value = id;

                    if (targetModal.querySelector('#deleteUserName')) targetModal.querySelector('#deleteUserName').textContent = name;
                    if (targetModal.querySelector('#changeRoleUserName')) targetModal.querySelector('#changeRoleUserName').textContent = name;
                    if (targetModal.querySelector('#changeStatusUserName')) targetModal.querySelector('#changeStatusUserName').textContent = name;

                    // Specific fields for Change Role Modal
                    if (targetModalId === '#changeRoleModal') {
                        const currentRole = this.getAttribute('data-current-role');
                        targetModal.querySelector('#newRole').value = currentRole;
                    } 
                    // Specific fields for Change Status Modal - This is now the main focus for status
                    else if (targetModalId === '#changeStatusModal') {
                        const currentStatus = this.getAttribute('data-current-status');
                        // Display the current status in text
                        targetModal.querySelector('#currentStatusDisplay').textContent = currentStatus === '1' ? 'Active' : 'Inactive';
                        // Set the dropdown to the current status
                        targetModal.querySelector('#newStatus').value = currentStatus;
                    }
                    // Removed all 'else if' conditions for announcement modals
                }
            });
        });

        // Export PDF Button - remains as it's a separate export function
        document.getElementById('exportPdfBtn').addEventListener('click', () => {
            const currentFilters = new URLSearchParams(window.location.search);
            currentFilters.set('action', 'download_pdf');
            window.location.href = `<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?${currentFilters.toString()}`;
        });

        // Keep active tab on reload - now only for 'users' tab
        const adminDashboardTabs = new bootstrap.Tab(document.getElementById('<?php echo $current_tab; ?>-tab'));
        adminDashboardTabs.show();

        document.getElementById('users-tab').addEventListener('shown.bs.tab', function (event) {
            const newParams = new URLSearchParams(window.location.search);
            newParams.set('tab', 'users');
            window.history.pushState(null, '', `?${newParams.toString()}`);
        });

        // Removed the event listener for 'announcements-tab' as it no longer exists

        // Heart paths animation - remains as it's a visual effect
        const heartPaths = document.querySelectorAll('.heart-path');
        heartPaths.forEach(path => {
            path.addEventListener('click', () => {
                const currentFill = path.getAttribute('fill');
                path.setAttribute('fill', currentFill === '#ff6f61' ? '#4CAF50' : '#ff6f61');
            });
        });
    });


            // Disable right-click context menu (optional, for a more app-like feel)
             document.addEventListener('contextmenu', (e) => e.preventDefault());
       
    </script>
</body>
</html>
