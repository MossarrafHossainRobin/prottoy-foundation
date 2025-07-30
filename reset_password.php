<?php
session_start();
require_once 'db.php'; // Ensure this path is correct

// Set the default timezone to Asia/Dhaka for consistent time operations
date_default_timezone_set('Asia/Dhaka');

// Check for database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$errors = [];
$message = '';
$user_name = 'User'; // Default name
$user_email_display = 'your account'; // Default email display
$user_image_url = ''; // Default empty image URL for the user

$redirect_to_login_after_success = false;

// --- Security Check: Ensure user came through the OTP verification process ---
$verified_user_id = $_SESSION['verified_user_id'] ?? null;
$reset_otp_verified = $_SESSION['reset_otp_verified'] ?? false;

if (!$reset_otp_verified || empty($verified_user_id)) {
    // Session is invalid, not verified, or user ID is missing.
    // Clear any related session variables to ensure a clean slate.
    unset($_SESSION['reset_otp_verified']);
    unset($_SESSION['verified_user_id']);
    unset($_SESSION['verified_user_name']);
    unset($_SESSION['verified_user_email']);

    // Redirect to the forgot password page with an error message
    $_SESSION['error_message'] = "Your password reset session has expired or is unauthorized. Please start the password reset process again.";
    header("Location: forgot_password.php"); // Assuming forgot_password.php exists
    exit();
}

// User is verified, fetch their details for display
$user_details_sql = "SELECT name, email, profile_picture_url FROM users WHERE id = ?";
if ($details_stmt = $conn->prepare($user_details_sql)) {
    $details_stmt->bind_param("i", $verified_user_id);
    $details_stmt->execute();
    $details_stmt->bind_result($db_name, $db_email, $db_image_url);
    if ($details_stmt->fetch()) {
        $user_name = htmlspecialchars($db_name);
        $user_email_display = htmlspecialchars($db_email);
        $user_image_url = htmlspecialchars($db_image_url); // Assign fetched image URL
    } else {
        // User not found in DB despite valid session ID, invalidate session and redirect
        unset($_SESSION['reset_otp_verified']);
        unset($_SESSION['verified_user_id']);
        $_SESSION['error_message'] = "User data not found for reset. Please restart the process.";
        header("Location: forgot_password.php");
        exit();
    }
    $details_stmt->close();
} else {
    // Database error fetching user details, invalidate session and redirect
    error_log("Failed to prepare user details statement: " . $conn->error);
    unset($_SESSION['reset_otp_verified']);
    unset($_SESSION['verified_user_id']);
    $_SESSION['error_message'] = "Database error fetching user details. Please restart the process.";
    header("Location: forgot_password.php");
    exit();
}

// --- PHP Logic for Password Update on POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Password validation rules
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }
    if (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    // Added more specific password complexity rules based on your CSS comments
    if (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "Password must include at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "Password must include at least one lowercase letter.";
    }
    if (!preg_match('/\d/', $new_password)) {
        $errors[] = "Password must include at least one number.";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $errors[] = "Password must include at least one special character.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Only proceed with database update if no validation errors
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // --- Core functionality: Update the 'password' column ---
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        if ($update_stmt = $conn->prepare($update_sql)) {
            $update_stmt->bind_param("si", $hashed_password, $verified_user_id);
            if ($update_stmt->execute()) {
                $update_stmt->close();

                // Clear all OTP and verification related session variables after successful reset
                unset($_SESSION['reset_otp_verified']);
                unset($_SESSION['verified_user_id']);
                // The following are technically not strictly needed if redirecting, but good practice
                unset($_SESSION['otp_user_id']);
                unset($_SESSION['otp_user_email']);
                unset($_SESSION['otp_user_name']);
                unset($_SESSION['otp_sent_at']);

                // Set success message for display
                $message = "Your password has been reset successfully. Redirecting to login...";
                $redirect_to_login_after_success = true;

            } else {
                error_log("Failed to execute password update for user ID $verified_user_id: " . $update_stmt->error);
                $errors[] = "Unable to update password. Please try again.";
            }
        } else {
            error_log("Failed to prepare password update statement: " . $conn->error);
            $errors[] = "Database error during password update. Please try again.";
        }
    }
}
$conn->close(); // Close DB connection after all operations

// Fetch any error message from session (e.g., if redirected here due to invalid session)
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear it after displaying
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - Prottoy Foundation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #4A90E2; /* Prottoy-like blue */
            --primary-hover: #357ABD;
            --accent-color: #FFC107; /* Goldenrod for accents */
            --background-dark: #121220; /* Even darker blue-purple */
            --background-light: #1A1A2E; /* Slightly lighter dark blue-purple */
            --text-color: #E8E8E8; /* Lighter text for contrast */
            --input-bg: #2A2A40; /* Darker input background */
            --border-color: #4F4F60; /* Subtle border */
            --error-color: #FF6B6B;
            --success-color: #6BE78E;
            --card-shadow: 0 15px 45px rgba(0, 0, 0, 0.6); /* Deeper, softer shadow */
            --animation-speed: 0.7s;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--background-dark) 0%, var(--background-light) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center; /* Center content vertically */
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: var(--text-color);
            overflow-x: hidden;
            position: relative;
        }

        /* Dynamic Background Elements */
        .background-sphere {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            filter: blur(150px);
            z-index: 0;
        }

        .sphere-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, var(--primary-color) 0%, rgba(74, 144, 226, 0) 70%);
            top: -200px; left: -200px;
            animation: moveSphere1 25s infinite alternate ease-in-out;
        }
        .sphere-2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, var(--accent-color) 0%, rgba(255, 193, 7, 0) 70%);
            bottom: -150px; right: -150px;
            animation: moveSphere2 20s infinite alternate ease-in-out;
            animation-delay: 2s;
        }
        .sphere-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(100, 100, 200, 0.5) 0%, rgba(100, 100, 200, 0) 70%);
            top: 20%; right: 10%;
            animation: moveSphere3 30s infinite alternate ease-in-out;
            animation-delay: 5s;
        }

        /* NEW: Added more spheres for complex background */
        .sphere-4 {
            width: 250px; height: 250px;
            background: radial-gradient(circle, var(--primary-hover) 0%, rgba(53, 123, 189, 0) 70%);
            top: 60%; left: -100px;
            animation: moveSphere4 28s infinite alternate ease-in-out;
            animation-delay: 7s;
        }
        .sphere-5 {
            width: 350px; height: 350px;
            background: radial-gradient(circle, var(--text-color) 0%, rgba(232, 232, 232, 0) 70%);
            bottom: -100px; left: 10%;
            animation: moveSphere5 22s infinite alternate ease-in-out;
            animation-delay: 9s;
            opacity: 0.05; /* Slightly less opaque */
        }

        @keyframes moveSphere1 {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(100px, 80px) rotate(90deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }
        @keyframes moveSphere2 {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-80px, -100px) rotate(-90deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }
        @keyframes moveSphere3 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-50px, 50px) scale(1.1); }
            100% { transform: translate(0, 0) scale(1); }
        }
        /* NEW: Keyframes for additional spheres */
        @keyframes moveSphere4 {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(50px, -50px) rotate(45deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }
        @keyframes moveSphere5 {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            50% { transform: translate(20px, 30px) scale(0.9) rotate(120deg); }
            100% { transform: translate(0, 0) scale(1) rotate(0deg); }
        }

        /* Main Content Area Styling */
        .reset-password-container {
            background: linear-gradient(145deg, rgba(26, 26, 46, 0.99) 0%, rgba(35, 35, 60, 0.99) 100%);
            border-radius: 30px;
            padding: 50px 60px;
            /* Enhanced shadow and border */
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7),
                        inset 0 0 25px rgba(255, 255, 255, 0.08); /* Stronger inner glow */
            border: 2px solid rgba(255, 255, 255, 0.15);
            width: 100%;
            max-width: 550px;
            z-index: 1;
            text-align: center;
            animation: fadeInSlideUp 1s ease-out forwards;
            transition: all 0.4s ease-in-out;
            position: relative;
            overflow: hidden; /* To contain inner pseudo-elements */
        }

        /* NEW: Pseudo-elements for added complexity to the container */
        .reset-password-container::before,
        .reset-password-container::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.05;
            filter: blur(40px);
            z-index: -1; /* Behind content but within the container */
        }

        .reset-password-container::before {
            width: 150px;
            height: 150px;
            top: -70px;
            left: -70px;
            background: var(--primary-color);
            animation: rotateAndMove 15s infinite linear;
        }

        .reset-password-container::after {
            width: 120px;
            height: 120px;
            bottom: -60px;
            right: -60px;
            background: var(--accent-color);
            animation: rotateAndMoveReverse 12s infinite linear;
        }

        @keyframes rotateAndMove {
            0% { transform: translate(0, 0) rotate(0deg); opacity: 0.05; }
            50% { transform: translate(20px, 20px) rotate(180deg); opacity: 0.07; }
            100% { transform: translate(0, 0) rotate(360deg); opacity: 0.05; }
        }

        @keyframes rotateAndMoveReverse {
            0% { transform: translate(0, 0) rotate(0deg); opacity: 0.05; }
            50% { transform: translate(-20px, -20px) rotate(-180deg); opacity: 0.07; }
            100% { transform: translate(0, 0) rotate(-360deg); opacity: 0.05; }
        }


        .reset-password-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.8),
                        inset 0 0 30px rgba(255, 255, 255, 0.1); /* Stronger inner glow on hover */
        }

        @keyframes fadeInSlideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* User Info Section */
        .user-info-section {
            margin-bottom: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-image-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--input-bg) 0%, rgba(42, 42, 64, 0.8) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            border: 4px solid var(--primary-color);
            box-shadow: 0 0 0 8px rgba(74, 144, 226, 0.3),
                        0 8px 20px rgba(0,0,0,0.6),
                        inset 0 0 10px rgba(255,255,255,0.1);
            overflow: hidden;
            transition: all 0.3s ease-in-out;
        }

        .user-image-circle:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 10px rgba(74, 144, 226, 0.5),
                        0 12px 30px rgba(0,0,0,0.8),
                        inset 0 0 15px rgba(255,255,255,0.2);
        }

        .user-image-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        .user-image-circle i {
            font-size: 5em;
            color: var(--primary-color);
            text-shadow: 0 0 10px rgba(74, 144, 226, 0.8);
        }

        .user-greeting {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 8px;
            /* Enhanced text shadow */
            text-shadow: 0 0 10px var(--accent-color), 0 0 20px var(--accent-color), 0 0 30px rgba(255, 193, 7, 0.5);
            letter-spacing: 0.5px;
        }

        .user-email-display {
            font-size: 1.1em;
            color: var(--text-color);
            opacity: 0.9;
            word-break: break-all;
            font-weight: 500;
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 800;
            font-size: 2.8em;
            letter-spacing: 1.5px;
            /* Enhanced text shadow */
            text-shadow: 0 0 15px var(--primary-color), 0 0 25px var(--primary-color), 0 0 40px rgba(74, 144, 226, 0.5);
        }

        .form-group {
            margin-bottom: 30px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--text-color);
            font-size: 1.1em;
            opacity: 0.98;
        }

        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-wrapper input {
            width: 100%;
            padding: 18px 20px;
            padding-right: 60px;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 1.1em;
            transition: border-color 0.4s ease, box-shadow 0.4s ease, background-color 0.4s ease;
            box-shadow: inset 0 5px 12px rgba(0,0,0,0.5);
        }

        .password-input-wrapper input:focus {
            border-color: var(--primary-color);
            background-color: rgba(42, 42, 64, 0.95);
            outline: none;
            box-shadow: 0 0 0 5px rgba(74, 144, 226, 0.6),
                        inset 0 5px 12px rgba(0,0,0,0.6);
        }

        .password-input-wrapper input::placeholder {
            color: rgba(224, 224, 224, 0.7);
        }

        .toggle-password {
            position: absolute;
            right: 20px;
            cursor: pointer;
            color: rgba(224, 224, 224, 0.6);
            font-size: 1.2em;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .toggle-password:hover {
            color: var(--primary-color);
            transform: scale(1.1);
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.95em;
            margin-top: 0; /* Reset margin-top initially */
            text-align: left;
            opacity: 0;
            max-height: 0; /* Use max-height for collapse effect */
            overflow: hidden;
            transition: opacity 0.5s ease, max-height 0.5s ease, margin-top 0.5s ease; /* Animate max-height and margin-top */
            padding: 0 12px; /* Remove initial padding */
            border-radius: 8px;
            background-color: rgba(255, 107, 107, 0.1);
            border-left: 4px solid var(--error-color);
            transform: translateY(10px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3); /* Subtle shadow for errors */
        }

        .error-message.show {
            opacity: 1;
            max-height: 100px; /* Adjust as needed for message content */
            margin-top: 10px;
            padding: 10px 12px;
            transform: translateY(0);
        }

        .success-message {
            color: var(--success-color);
            font-size: 1.2em;
            margin-bottom: 30px;
            font-weight: 700;
            animation: fadeInSlideDown 0.8s ease-out;
            border: 2px solid var(--success-color);
            padding: 20px;
            border-radius: 15px;
            background-color: rgba(107, 231, 142, 0.2);
            box-shadow: 0 6px 15px rgba(0, 255, 0, 0.3);
        }

        @keyframes fadeInSlideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        button[type="submit"] {
            width: 100%;
            padding: 18px;
            background: linear-gradient(45deg, var(--primary-color) 0%, #6a9cde 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.2em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 0.9);
            letter-spacing: 0.8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            margin-top: 20px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transform: skewX(-30deg);
            transition: all 0.5s ease-out;
        }

        button[type="submit"]:hover::before {
            left: 150%;
        }

        button[type="submit"]:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), 0 0 25px var(--primary-color);
            background: linear-gradient(45deg, var(--primary-hover) 0%, var(--primary-color) 100%);
        }

        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
        }

        button[type="submit"]:disabled {
            background: linear-gradient(45deg, #777 0%, #999 100%);
            cursor: not-allowed;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transform: none;
        }

        button[type="submit"]:disabled::before {
            background: none;
        }


        /* Responsive Adjustments */
        @media (max-width: 600px) {
            .reset-password-container {
                max-width: 95%;
                padding: 30px 25px;
                margin: 50px auto; /* Center with margin */
            }
            h1 {
                font-size: 2.2em;
            }
            .user-image-circle {
                width: 100px;
                height: 100px;
            }
            .user-image-circle i {
                font-size: 4em;
            }
            .user-greeting {
                font-size: 1.6em;
            }
            .user-email-display {
                font-size: 1em;
            }
            .password-input-wrapper input {
                padding: 14px 15px;
                padding-right: 50px;
                font-size: 1em;
            }
            .toggle-password {
                right: 15px;
                font-size: 1.1em;
            }
            button[type="submit"] {
                padding: 16px;
                font-size: 1.1em;
            }
        }

        @media (max-width: 400px) {
            .reset-password-container {
                padding: 25px 15px;
            }
            h1 {
                font-size: 1.8em;
                margin-bottom: 20px;
            }
            .user-image-circle {
                width: 80px;
                height: 80px;
            }
            .user-image-circle i {
                font-size: 3.5em;
            }
            .user-greeting {
                font-size: 1.4em;
            }
            .user-email-display {
                font-size: 0.9em;
            }
            .password-input-wrapper input {
                padding: 12px 10px;
                padding-right: 40px;
                font-size: 0.9em;
            }
            .toggle-password {
                right: 10px;
                font-size: 1em;
            }
            button[type="submit"] {
                padding: 14px;
                font-size: 1em;
            }
            .error-message, .success-message {
                font-size: 0.9em;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="background-sphere sphere-1"></div>
    <div class="background-sphere sphere-2"></div>
    <div class="background-sphere sphere-3"></div>
    <div class="background-sphere sphere-4"></div> <div class="background-sphere sphere-5"></div> <div class="reset-password-container">
        <div class="user-info-section">
            <div class="user-image-circle">
                <?php if (!empty($user_image_url)): ?>
                    <img src="<?php echo $user_image_url; ?>" alt="User Image">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <div class="user-greeting">Hello, <?php echo $user_name; ?>!</div>
            <div class="user-email-display"><?php echo $user_email_display; ?></div>
        </div>

        <h1>Reset Your Password</h1>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error-message show"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($redirect_to_login_after_success): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
                <p>You will be redirected to the login page shortly.</p>
            </div>
            <script>
                // Redirect to login page after 3 seconds
                setTimeout(function() {
                    // It's important to use the correct email for redirection,
                    // which in this case is $user_email_display
                    window.location.href = 'login.php?email=<?php echo urlencode($user_email_display); ?>'; // Pass email for pre-fill
                }, 3000);
            </script>
        <?php else: ?>
            <form action="reset_password.php" method="POST">
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Enter your new password">
                        <i class="fas fa-eye toggle-password" id="toggleNewPassword"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm your new password">
                        <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                    </div>
                </div>
                <button type="submit">Update Password</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // JavaScript for toggling password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const newPasswordInput = document.getElementById('new_password');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPasswordInput = document.getElementById('confirm_password');

            function toggleVisibility(input, icon) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }

            if (toggleNewPassword && newPasswordInput) {
                toggleNewPassword.addEventListener('click', function() {
                    toggleVisibility(newPasswordInput, toggleNewPassword);
                });
            }

            if (toggleConfirmPassword && confirmPasswordInput) {
                toggleConfirmPassword.addEventListener('click', function() {
                    toggleVisibility(confirmPasswordInput, toggleConfirmPassword);
                });
            }

            // Client-side validation (optional, as PHP does server-side)
            const form = document.querySelector('form');
            // Only add listener if the success message is not displayed, otherwise the form isn't active
            if (form && !<?php echo json_encode($redirect_to_login_after_success); ?>) {
                form.addEventListener('submit', function(event) {
                    const newPassword = newPasswordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    const errorMessages = [];

                    // Clear previous error messages with animation
                    document.querySelectorAll('.error-message.show').forEach(el => {
                        el.classList.remove('show');
                        el.textContent = ''; // Clear text to ensure proper height calculation on next show
                    });

                    // Add a small delay before showing new errors to allow old ones to animate out
                    setTimeout(() => {
                        if (newPassword.length < 8) {
                            errorMessages.push("Password must be at least 8 characters.");
                        }
                        if (!/[A-Z]/.test(newPassword)) {
                            errorMessages.push("Password must include at least one uppercase letter.");
                        }
                        if (!/[a-z]/.test(newPassword)) {
                            errorMessages.push("Password must include at least one lowercase letter.");
                        }
                        if (!/\d/.test(newPassword)) {
                            errorMessages.push("Password must include at least one number.");
                        }
                        if (!/[^A-Za-z0-9]/.test(newPassword)) {
                            errorMessages.push("Password must include at least one special character.");
                        }
                        if (newPassword !== confirmPassword) {
                            errorMessages.push("New password and confirm password do not match.");
                        }

                        if (errorMessages.length > 0) {
                            event.preventDefault(); // Prevent form submission
                            const formContainer = document.querySelector('.reset-password-container');
                            errorMessages.forEach(msg => {
                                const errorDiv = document.createElement('div');
                                errorDiv.classList.add('error-message');
                                errorDiv.textContent = msg;
                                // Insert before the form or at a designated error container
                                formContainer.insertBefore(errorDiv, form);
                                // Trigger reflow to ensure transition works
                                void errorDiv.offsetWidth;
                                errorDiv.classList.add('show');
                            });
                        }
                    }, 500); // Wait for old errors to hide
                });
            }
        });

         document.addEventListener('contextmenu', (e) => e.preventDefault());
    </script>
</body>
</html>