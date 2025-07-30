<?php
session_start();
date_default_timezone_set('Asia/Dhaka'); // Crucial: Set your timezone for consistent time operations

require_once 'db.php';
require_once 'PHPMailer/PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/PHPMailer/src/Exception.php';
require_once 'PHPMailer/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$phpErrors = [];
$phpSuccessMessage = '';

// Retrieve session data for display and processing
$user_id_from_session = $_SESSION['otp_user_id'] ?? null;
$user_email_for_display = $_SESSION['otp_user_email'] ?? '';
$user_name_for_display = $_SESSION['otp_user_name'] ?? '';
$otp_sent_at_timestamp = $_SESSION['otp_sent_at'] ?? 0;
$resend_cooldown = 60; // 60 seconds (1 minute) cooldown for resending OTP

// --- Security Check: Ensure user session data exists for OTP verification ---
if (empty($user_id_from_session) || empty($user_email_for_display) || empty($user_name_for_display)) {
    $_SESSION['login_errors'] = ["Session expired or no pending OTP verification. Please request a new OTP."];
    header("Location: forgot_password.php");
    exit;
}

// Function to generate a random 6-digit OTP
function generateOtp() {
    return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// Function to send OTP email
function sendOtpEmail($conn, $user_id, $user_name, $user_email, $otp, $expiry_minutes) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rh503648@gmail.com'; // Replace with your Gmail
        $mail->Password = 'fhuo zrja wiwy vksd'; // Replace with your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('rh503648@gmail.com', 'Prottoy Foundation');
        $mail->addAddress($user_email);
        $mail->Subject = 'Prottoy Foundation - Your Password Reset OTP';
        $mail->Body = "
            <div style=\"font-family: 'Poppins', sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; background-color: #f9f9f9;\">
                <h2 style=\"color: #4A90E2; text-align: center;\">Password Reset OTP</h2>
                <p style=\"color: #555; line-height: 1.6;\">Dear " . htmlspecialchars($user_name) . ",</p>
                <p style=\"color: #555; line-height: 1.6;\">You have requested a password reset for your Prottoy Foundation account. Please use the One-Time Password (OTP) below to proceed:</p>
                <p style=\"text-align: center; margin: 30px 0;\">
                    <b style=\"display: inline-block; padding: 15px 25px; background-color: #E6F3FF; color: #4A90E2; text-decoration: none; border-radius: 5px; font-size: 24px; letter-spacing: 2px; font-weight: bold; border: 2px dashed #4A90E2;\">
                        " . htmlspecialchars($otp) . "
                    </b>
                </p>
                <p style=\"color: #555; line-height: 1.6;\">This OTP is valid for <b>" . $expiry_minutes . " minutes</b>. Please enter it on the website to reset your password.</p>
                <p style=\"color: #555; line-height: 1.6;\">If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
                <p style=\"color: #888; font-size: 0.9em; text-align: center; margin-top: 40px;\">
                    Best regards,<br>
                    The Prottoy Foundation Team
                </p>
            </div>
        ";
        $mail->AltBody = "Dear " . htmlspecialchars($user_name) . ",\n\nYou have requested a password reset for your Prottoy Foundation account. Please use the One-Time Password (OTP) below to proceed:\n\nOTP: " . htmlspecialchars($otp) . "\n\nThis OTP is valid for " . $expiry_minutes . " minutes. Please enter it on the website to reset your password.\n\nIf you did not request a password reset, please ignore this email. Your password will remain unchanged.\n\nBest regards,\nThe Prottoy Foundation Team";
        $mail->isHTML(true);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP email sending failed for user_id $user_id: " . $mail->ErrorInfo);
        return false;
    }
}

// --- Handle OTP Verification or Resend Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'verify') {
            $otp_entered = trim($_POST['otp'] ?? '');

            if (empty($otp_entered)) {
                $phpErrors[] = "Please enter the OTP.";
            } elseif (strlen($otp_entered) !== 6 || !ctype_digit($otp_entered)) {
                $phpErrors[] = "Invalid OTP format. It should be a 6-digit number.";
            } else {
                // Verify OTP against database
                // IMPORTANT: Ensure expires_at > NOW() and is_used = FALSE
                $sql_verify_otp = "SELECT id FROM password_resets WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() AND is_used = FALSE";
                if ($stmt_verify = $conn->prepare($sql_verify_otp)) {
                    $stmt_verify->bind_param("is", $user_id_from_session, $otp_entered);
                    $stmt_verify->execute();
                    $stmt_verify->store_result();

                    if ($stmt_verify->num_rows == 1) {
                        // OTP is valid and not expired, mark it as used
                        $stmt_verify->bind_result($reset_entry_id);
                        $stmt_verify->fetch();
                        $stmt_verify->close();

                        // Mark OTP as used IMMEDIATELY after successful verification
                        $update_used_sql = "UPDATE password_resets SET is_used = TRUE WHERE id = ?";
                        if ($update_stmt = $conn->prepare($update_used_sql)) {
                            $update_stmt->bind_param("i", $reset_entry_id);
                            if ($update_stmt->execute()) {
                                $update_stmt->close();

                                // Store OTP verification success in session for reset_password.php
                                $_SESSION['reset_otp_verified'] = true;
                                $_SESSION['verified_user_id'] = $user_id_from_session;
                                $_SESSION['verified_user_name'] = $user_name_for_display;
                                $_SESSION['verified_user_email'] = $user_email_for_display;

                                $phpSuccessMessage = "OTP verified successfully. Redirecting to password reset page...";
                                header("Location: reset_password.php");
                                exit;
                            } else {
                                error_log("Failed to mark OTP as used for reset_entry_id: $reset_entry_id - " . $update_stmt->error);
                                $phpErrors[] = "An internal error occurred after OTP verification. Please try again.";
                            }
                        } else {
                            error_log("Failed to prepare update OTP statement: " . $conn->error);
                            $phpErrors[] = "Database error. Please try again.";
                        }

                    } else {
                        // For debugging, fetch relevant OTP data to see why it failed
                        $debug_query = "SELECT otp_code, expires_at, is_used FROM password_resets WHERE user_id = ? ORDER BY expires_at DESC LIMIT 1";
                        if ($debug_stmt = $conn->prepare($debug_query)) {
                            $debug_stmt->bind_param("i", $user_id_from_session);
                            $debug_stmt->execute();
                            $debug_result = $debug_stmt->get_result();
                            $debug_otp = $debug_result->fetch_assoc();
                            if ($debug_otp) {
                                error_log("OTP debug for user $user_id_from_session: Stored OTP: {$debug_otp['otp_code']}, Expires: {$debug_otp['expires_at']}, Used: {$debug_otp['is_used']}. Current Time: " . date('Y-m-d H:i:s'));
                                if ($debug_otp['otp_code'] !== $otp_entered) {
                                    $phpErrors[] = "Invalid OTP. The entered OTP does not match.";
                                } elseif (strtotime($debug_otp['expires_at']) <= time()) {
                                    $phpErrors[] = "Expired OTP. Please request a new one.";
                                } elseif ($debug_otp['is_used']) {
                                    $phpErrors[] = "This OTP has already been used. Please request a new one.";
                                } else {
                                    $phpErrors[] = "Invalid or expired OTP. Please try again or request a new one.";
                                }
                            } else {
                                $phpErrors[] = "No active OTP found for this user. Please request a new one.";
                            }
                            $debug_stmt->close();
                        } else {
                            $phpErrors[] = "Invalid or expired OTP. Please try again or request a new one.";
                        }
                    }
                    // $stmt_verify->close(); // Moved close inside success block to prevent double closing if debug query runs
                } else {
                    error_log("Failed to prepare OTP verification query: " . $conn->error);
                    $phpErrors[] = "Database error during OTP verification. Please try again.";
                }
            }
        } elseif ($action === 'resend') {
            // Check cooldown
            if (time() - $otp_sent_at_timestamp < $resend_cooldown) {
                $time_remaining = $resend_cooldown - (time() - $otp_sent_at_timestamp);
                $phpErrors[] = "Please wait " . $time_remaining . " seconds before resending OTP.";
            } else {
                // Generate and send new OTP
                $new_otp = generateOtp();
                $expiry_minutes = 5;
                $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

                // Delete any old, unused OTPs for this user before inserting new one
                // This ensures there's only one active OTP entry for the user.
                $delete_old_sql = "DELETE FROM password_resets WHERE user_id = ? AND is_used = FALSE";
                if ($delete_stmt = $conn->prepare($delete_old_sql)) {
                    $delete_stmt->bind_param("i", $user_id_from_session);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }

                $insert_sql = "INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)";
                if ($insert_stmt = $conn->prepare($insert_sql)) {
                    $insert_stmt->bind_param("iss", $user_id_from_session, $new_otp, $expires_at);
                    if ($insert_stmt->execute()) {
                        $insert_stmt->close();

                        if (sendOtpEmail($conn, $user_id_from_session, $user_name_for_display, $user_email_for_display, $new_otp, $expiry_minutes)) {
                            $phpSuccessMessage = "A new OTP has been sent to your email.";
                            $_SESSION['otp_sent_at'] = time(); // Update timestamp for new OTP
                            // Refresh the page to update the React timer
                            header("Location: verify_otp.php");
                            exit;
                        } else {
                            $phpErrors[] = "Failed to resend OTP. Please try again.";
                        }
                    } else {
                        error_log("Failed to insert new OTP for user_id $user_id_from_session: " . $insert_stmt->error);
                        $phpErrors[] = "Database error. Could not generate new OTP.";
                    }
                } else {
                    error_log("Failed to prepare new OTP insert: " . $conn->error);
                    $phpErrors[] = "Database error. Could not prepare new OTP.";
                }
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Prottoy Foundation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Your CSS is extensive, so I'm omitting it here for brevity.
           Ensure your CSS includes styling for the .otp-timer class. */
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

            /* Navbar specific colors */
            --navbar-bg: rgba(18, 18, 32, 0.95); /* More opaque dark */
            --navbar-text: #FFFFFF;
            --navbar-link-hover: var(--primary-color);
            --logo-text-color: #FFFFFF;
            --button-secondary-bg: #6C757D;
            --button-secondary-hover: #5A6268;
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
            justify-content: flex-start;
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

        /* Navbar Styling */
        .navbar {
            width: 100%;
            background-color: var(--navbar-bg);
            padding: 18px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .navbar-logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 2em;
            color: var(--logo-text-color);
            text-decoration: none;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .navbar-logo .heart-icon {
            width: 45px;
            height: 45px;
            margin-right: 12px;
            fill: var(--primary-color);
            filter: drop-shadow(0 0 5px rgba(74, 144, 226, 0.6));
            transition: transform 0.3s ease-in-out;
        }

        .navbar-logo:hover .heart-icon {
            transform: scale(1.1) rotate(-5deg);
        }

        .navbar-links {
            display: flex;
            list-style: none;
        }

        .navbar-links li {
            margin-left: 35px;
        }

        .navbar-links a {
            color: var(--navbar-text);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05em;
            transition: color 0.3s ease, transform 0.2s ease;
            position: relative;
            padding-bottom: 6px;
        }

        .navbar-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--accent-color) 100%);
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: width 0.3s ease-out;
        }

        .navbar-links a:hover {
            color: var(--navbar-link-hover);
            transform: translateY(-3px);
        }

        .navbar-links a:hover::after {
            width: 100%;
        }

        .navbar-buttons button {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            margin-left: 20px;
            font-size: 1.05em;
        }

        .navbar-buttons .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: 2px solid var(--primary-color);
            box-shadow: 0 5px 20px rgba(74, 144, 226, 0.5);
        }

        .navbar-buttons .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.6);
        }

        .navbar-buttons .btn-secondary {
            background-color: transparent;
            color: var(--navbar-text);
            border: 2px solid rgba(255, 255, 255, 0.6);
        }

        .navbar-buttons .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Main Content Container */
        #app-container {
            background: rgba(26, 26, 46, 0.98);
            border-radius: 30px;
            padding: 60px;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 550px;
            text-align: center;
            position: relative;
            z-index: 1;
            margin-top: 160px;
            margin-bottom: 60px;
            animation: fadeInScale var(--animation-speed) ease-out forwards;
            border: 2px solid rgba(255, 255, 255, 0.15);
            overflow: hidden;
        }

        @keyframes fadeInScale {
            from {
                transform: translateY(40px) scale(0.85);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 2.8em;
            letter-spacing: 1.5px;
            text-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }

        p.description {
            color: var(--text-color);
            margin-bottom: 40px;
            font-size: 1.05em;
            line-height: 1.8;
            opacity: 0.95;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
            background-color: rgba(74, 144, 226, 0.1); /* Light blue background for the description */
            border: 1px solid var(--primary-color);
            border-radius: 15px;
            padding: 15px 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: fadeIn 0.8s ease-out;
        }

        p.description strong { /* For the bolded user name */
            color: var(--accent-color); /* Highlight name with accent color */
            font-weight: 700;
        }

        p.description span.email-display { /* For the email address */
            font-weight: 600;
            color: var(--primary-color); /* Highlight email with primary color */
        }

        .form-group {
            margin-bottom: 35px;
            text-align: left;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 1.05em;
            opacity: 0.98;
        }

        input[type="text"] {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 1.15em;
            transition: border-color 0.4s ease, box-shadow 0.4s ease, background-color 0.4s ease;
            box-shadow: inset 0 4px 10px rgba(0,0,0,0.4);
            text-align: center; /* Center OTP input */
            letter-spacing: 3px; /* Space out OTP digits */
        }

        input[type="text"]:focus {
            border-color: var(--primary-color);
            background-color: rgba(42, 42, 64, 0.9);
            outline: none;
            box-shadow: 0 0 0 5px rgba(74, 144, 226, 0.5), inset 0 4px 10px rgba(0,0,0,0.5);
        }

        input[type="text"]::placeholder {
            color: rgba(224, 224, 224, 0.7);
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.95em;
            margin-top: 12px;
            text-align: left;
            opacity: 0;
            height: 0;
            overflow: hidden;
            transition: opacity 0.4s ease, height 0.4s ease;
            padding-left: 8px;
            border-left: 3px solid var(--error-color);
        }

        .error-message.show {
            opacity: 1;
            height: auto;
            margin-top: 10px;
        }

        .success-message {
            color: var(--success-color);
            font-size: 1.2em;
            margin-bottom: 35px;
            font-weight: 600;
            animation: fadeIn var(--animation-speed) ease-out;
            border: 2px solid var(--success-color);
            padding: 20px;
            border-radius: 15px;
            background-color: rgba(107, 231, 142, 0.15);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.2);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        button[type="submit"], .resend-otp-btn, .back-to-forgot {
            width: 100%;
            padding: 20px;
            background: linear-gradient(45deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.3em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 1px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            margin-top: 25px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            text-decoration: none; /* For the link that looks like a button */
            display: inline-block; /* For the link that looks like a button */
        }

        .resend-otp-btn {
            background: linear-gradient(45deg, #6C757D 0%, #5A6268 100%); /* Grey for resend */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .resend-otp-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
            background: #5a6268;
        }

        .resend-otp-btn:hover:not(:disabled) {
             background: linear-gradient(45deg, #5A6268 0%, #6C757D 100%);
        }


        button[type="submit"]::before, .resend-otp-btn::before, .back-to-forgot::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transform: skewX(-30deg);
            transition: all 0.4s ease;
        }

        button[type="submit"]:hover::before, .resend-otp-btn:hover::before, .back-to-forgot:hover::before {
            left: 100%;
        }

        button[type="submit"]:hover, .back-to-forgot:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
            background: linear-gradient(45deg, var(--primary-hover) 0%, var(--primary-color) 100%);
        }

        button[type="submit"]:active, .resend-otp-btn:active, .back-to-forgot:active {
            transform: translateY(0);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .otp-timer {
            font-size: 1em;
            color: var(--text-color);
            margin-top: 15px;
            opacity: 0.9;
            animation: pulse 1s infinite alternate;
        }

        .otp-timer.expired {
            color: var(--error-color);
            animation: none;
        }

        @keyframes pulse {
            from { opacity: 0.8; }
            to { opacity: 1; }
        }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
                align-items: flex-start;
            }
            .navbar-logo {
                margin-bottom: 15px;
                font-size: 1.8em;
            }
            .navbar-logo .heart-icon {
                width: 35px;
                height: 35px;
            }
            .navbar-links {
                flex-direction: column;
                width: 100%;
                display: none;
            }
            .navbar-links.active {
                display: flex;
            }
            .navbar-links li {
                margin: 10px 0;
            }
            .navbar-buttons {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-top: 15px;
            }
            .navbar-buttons button {
                margin-left: 0;
                font-size: 1em;
                padding: 10px 20px;
            }
            #app-container {
                margin-top: 120px;
                padding: 40px 30px;
                max-width: 90%;
            }
            h1 {
                font-size: 2.2em;
            }
            p.description {
                font-size: 0.95em;
            }
            input[type="text"] {
                padding: 16px 15px;
                font-size: 1.05em;
            }
            button[type="submit"], .resend-otp-btn, .back-to-forgot {
                padding: 18px;
                font-size: 1.2em;
            }
        }

        @media (max-width: 480px) {
            #app-container {
                margin: 100px 15px 40px;
                padding: 30px 20px;
            }
            h1 {
                font-size: 2em;
                margin-bottom: 15px;
            }
            p.description {
                font-size: 0.9em;
                margin-bottom: 25px;
                max-width: 100%;
            }
            input[type="text"] {
                padding: 14px 12px;
                font-size: 1em;
            }
            button[type="submit"], .resend-otp-btn, .back-to-forgot {
                padding: 16px;
                font-size: 1.1em;
            }
            .navbar-logo {
                font-size: 1.6em;
            }
            .navbar-logo .heart-icon {
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="background-sphere sphere-1"></div>
    <div class="background-sphere sphere-2"></div>
    <div class="background-sphere sphere-3"></div>

    <nav class="navbar">
        <a href="#" class="navbar-logo">
            <svg class="heart-icon" viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C15.09 3.81 16.76 3 18.5 3 21.58 3 24 5.42 24 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
            Prottoy Foundation
        </a>
        <ul class="navbar-links">
            <li><a href="#">Home</a></li>
            <li><a href="#">About Us</a></li>
            <li><a href="#">Programs</a></li>
            <li><a href="#">Impact</a></li>
            <li><a href="#">Contact</a></li>
        </ul>
        <div class="navbar-buttons">
            <button class="btn-secondary">Get Involved</button>
            <button class="btn-primary">Donate Now</button>
        </div>
    </nav>

    <div id="app-container">
        <h1>Verify OTP</h1>
        <p class="description">
            We've sent a 6-digit OTP to **<?php echo htmlspecialchars($user_email_for_display); ?>**. Please enter it below to reset your password. The OTP is valid for 5 minutes.
        </p>
        <div id="root"></div>
    </div>

    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/babel-standalone@6/babel.min.js"></script>

    <script type="text/babel">
        const { useState, useEffect, useRef } = React; // Added useRef

        function VerifyOtpForm({ phpErrors, phpSuccessMessage, otpSentAt, resendCooldown }) {
            const [otp, setOtp] = useState('');
            const [localErrors, setLocalErrors] = useState([]);
            const [localSuccessMessage, setLocalSuccessMessage] = useState('');
            const [timeLeft, setTimeLeft] = useState(0);
            const [canResend, setCanResend] = useState(true);
            const timerRef = useRef(null); // Ref to store interval ID

            const totalOtpValiditySeconds = 5 * 60; // 5 minutes in seconds

            useEffect(() => {
                if (phpErrors && phpErrors.length > 0) {
                    setLocalErrors(phpErrors);
                } else {
                    setLocalErrors([]); // Clear errors if PHP has none
                }
                if (phpSuccessMessage) {
                    setLocalSuccessMessage(phpSuccessMessage);
                } else {
                    setLocalSuccessMessage(''); // Clear success message if PHP has none
                }

                // Initialize timer
                const calculateInitialTimeLeft = () => {
                    const currentTime = Math.floor(Date.now() / 1000); // Current time in seconds
                    const elapsed = currentTime - otpSentAt;
                    return Math.max(0, totalOtpValiditySeconds - elapsed);
                };

                const initialTimeLeft = calculateInitialTimeLeft();
                setTimeLeft(initialTimeLeft);
                // Can resend if the time left is less than (total validity - cooldown)
                // This means the cooldown period from the *last* OTP send has passed.
                setCanResend(initialTimeLeft <= (totalOtpValiditySeconds - resendCooldown));


                // Clear any existing timer
                if (timerRef.current) {
                    clearInterval(timerRef.current);
                }

                // Start new timer if time left
                if (initialTimeLeft > 0) {
                    timerRef.current = setInterval(() => {
                        setTimeLeft((prevTime) => {
                            if (prevTime <= 1) {
                                clearInterval(timerRef.current);
                                timerRef.current = null;
                                setCanResend(true); // Enable resend when timer expires
                                return 0;
                            }
                            // Update canResend if enough time has passed for the cooldown
                            if (prevTime === (totalOtpValiditySeconds - resendCooldown)) {
                                setCanResend(true);
                            }
                            return prevTime - 1;
                        });
                    }, 1000);
                } else {
                     setCanResend(true); // If OTP already expired on load, allow resend
                }


                return () => {
                    // Cleanup on unmount
                    if (timerRef.current) {
                        clearInterval(timerRef.current);
                    }
                };
            }, [phpErrors, phpSuccessMessage, otpSentAt, resendCooldown]);


            const handleVerifySubmit = (e) => {
                const errors = [];
                if (!otp.trim()) {
                    errors.push("Please enter the OTP.");
                } else if (otp.length !== 6 || !/^\d+$/.test(otp)) {
                    errors.push("Invalid OTP. It should be a 6-digit number.");
                }

                // Client-side check for expiry
                if (timeLeft <= 0) {
                    errors.push("OTP has expired. Please request a new one.");
                }

                setLocalErrors(errors);
                if (errors.length > 0) {
                    e.preventDefault();
                }
                // If no client-side errors, form will submit to PHP for server-side validation
            };

            const handleResend = () => {
                // Submit form with resend action
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'verify_otp.php'; // Submit to the same page
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'resend';
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            };

            const formatTime = (seconds) => {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
            };

            return (
                <form method="POST" onSubmit={handleVerifySubmit}>
                    <input type="hidden" name="action" value="verify" />

                    {localSuccessMessage && (
                        <div className="success-message">{localSuccessMessage}</div>
                    )}

                    {localErrors.length > 0 && (
                        localErrors.map((err, index) => (
                            <div key={index} className="error-message show">{err}</div>
                        ))
                    )}

                    <div className="form-group">
                        <label htmlFor="otp">Enter OTP</label>
                        <input
                            type="text"
                            name="otp"
                            id="otp"
                            required
                            maxLength="6"
                            pattern="\d{6}"
                            title="Please enter a 6-digit OTP"
                            placeholder="______"
                            value={otp}
                            onChange={(e) => {
                                setOtp(e.target.value.replace(/\D/, '').substring(0, 6)); // Allow only digits, max 6
                                setLocalErrors([]); // Clear errors on input change
                                setLocalSuccessMessage(''); // Clear success message on input change
                            }}
                        />
                    </div>
                    <div className={`otp-timer ${timeLeft <= 0 ? 'expired' : ''}`}>
                         {timeLeft > 0 ? `OTP expires in ${formatTime(timeLeft)}` : 'OTP expired. Please resend.'}
                    </div>
                    <div>
                        <button type="submit">Verify OTP</button>
                    </div>
                    <div style={{ marginTop: '20px' }}>
                        <button
                            type="button"
                            className="resend-otp-btn"
                            onClick={handleResend}
                            disabled={!canResend || (timeLeft > 0 && timeLeft > (totalOtpValiditySeconds - resendCooldown))} // Disable if not ready or still in initial OTP validity but before resend cooldown
                        >
                            Resend OTP
                        </button>
                    </div>
                    <div style={{ marginTop: '20px' }}>
                        <a href="forgot_password.php" className="back-to-forgot">Request New OTP (Back)</a>
                    </div>
                </form>
            );
        }

        const phpErrors = <?php echo json_encode($phpErrors); ?>;
        const phpSuccessMessage = "<?php echo htmlspecialchars($phpSuccessMessage); ?>";
        const otpSentAt = <?php echo json_encode($otp_sent_at_timestamp); ?>; // Ensure this is the timestamp
        const resendCooldown = <?php echo json_encode($resend_cooldown); ?>;

        ReactDOM.render(
            <VerifyOtpForm
                phpErrors={phpErrors}
                phpSuccessMessage={phpSuccessMessage}
                otpSentAt={otpSentAt}
                resendCooldown={resendCooldown}
            />,
            document.getElementById('root')
        );

         document.addEventListener('contextmenu', (e) => e.preventDefault());
    </script>
</body>
</html>