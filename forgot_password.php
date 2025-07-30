<?php
session_start();
require_once 'db.php';
require_once 'PHPMailer/PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/PHPMailer/src/Exception.php';
require_once 'PHPMailer/PHPMailer/src/SMTP.php';

// Set the default timezone to Asia/Dhaka
date_default_timezone_set('Asia/Dhaka');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- DATABASE CONNECTION CHECK ---
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$phpErrors = [];
$phpSuccessMessage = '';

// Function to generate a random 6-digit OTP
function generateOtp() {
    return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// --- HANDLE FORGOT PASSWORD REQUEST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $phpErrors[] = "Email or Phone Number is required.";
    } else {
        $normalized_identifier = preg_replace('/[^0-9]/', '', $identifier);
        $query_identifier = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : $normalized_identifier;

        $sql = "SELECT id, name, email FROM users WHERE email = ? OR phone = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $query_identifier, $query_identifier);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $user_name, $user_email);
                $stmt->fetch();
                $stmt->close();

                $otp = generateOtp();
                $expiry_minutes = 5; // OTP valid for 5 minutes
                // Use date_default_timezone_set to ensure this is in Dhaka time
                $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

                // Delete any old, unused OTPs for this user to prevent clutter and ensure uniqueness for active OTP
                $delete_old_sql = "DELETE FROM password_resets WHERE user_id = ? AND is_used = FALSE";
                if ($delete_stmt = $conn->prepare($delete_old_sql)) {
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }

                // Store new OTP in password_resets table
                $insert_sql = "INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)";
                if ($insert_stmt = $conn->prepare($insert_sql)) {
                    $insert_stmt->bind_param("iss", $user_id, $otp, $expires_at);
                    if ($insert_stmt->execute()) {
                        $insert_stmt->close();
                        error_log("OTP generated for user ID: $user_id, Email: $user_email, OTP: $otp, Expires At: $expires_at");
                        $_SESSION['otp_user_id'] = $user_id;
                        $_SESSION['otp_user_email'] = $user_email;
                        $_SESSION['otp_user_name'] = $user_name;
                        $_SESSION['otp_sent_at'] = time(); // Mark time OTP was sent
                        error_log("Session variables set in forgot_password: " . json_encode($_SESSION));

                        // Send OTP email with PHPMailer
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
                            $phpSuccessMessage = "An OTP has been sent to your email. Please check your inbox and enter it on the next page.";
                            // Store user_id and email in session for the OTP verification page
                            $_SESSION['otp_user_id'] = $user_id;
                            $_SESSION['otp_user_email'] = $user_email;
                            $_SESSION['otp_user_name'] = $user_name;
                            $_SESSION['otp_sent_at'] = time(); // Mark time OTP was sent

                            header("Location: verify_otp.php");
                            exit;

                        } catch (Exception $e) {
                            error_log("OTP email sending failed for user_id $user_id: " . $mail->ErrorInfo);
                            $phpErrors[] = "Failed to send OTP email. Please try again later.";
                        }

                    } else {
                        error_log("Failed to insert OTP for user_id: $user_id - " . $insert_stmt->error);
                        $phpErrors[] = "Unable to process your request. Please try again.";
                    }
                } else {
                    error_log("Failed to prepare OTP insert: " . $conn->error);
                    $phpErrors[] = "Database error. Please try again.";
                }
            } else {
                $phpErrors[] = "Email or Phone Number not found in our records.";
            }
        } else {
            error_log("Failed to prepare user lookup: " . $conn->error);
            $phpErrors[] = "Database error. Please try again.";
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
    <title>Forgot Password - Prottoy Foundation</title>
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

        button[type="submit"], .back-to-login {
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

        button[type="submit"]::before, .back-to-login::before {
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

        button[type="submit"]:hover::before, .back-to-login:hover::before {
            left: 100%;
        }

        button[type="submit"]:hover, .back-to-login:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
            background: linear-gradient(45deg, var(--primary-hover) 0%, var(--primary-color) 100%);
        }

        button[type="submit"]:active, .back-to-login:active {
            transform: translateY(0);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
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
            button[type="submit"], .back-to-login {
                padding: 18px;
                font-size: 1.2em;
            }
        }

        @media (max-width: 480px) {
            #app-container {
                margin: 100px 15px 40px;
                padding: 30px 20px;
                max-width: 100%;
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
            button[type="submit"], .back-to-login {
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
        <h1>Forgot Password</h1>
        <p class="description">
            No worries! We'll send you a One-Time Password (OTP) to reset your password. Please enter your registered email or phone number below.
        </p>
        <div id="root"></div>
    </div>

    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/babel-standalone@6/babel.min.js"></script>

    <script type="text/babel">
        const { useState, useEffect } = React;

        function ForgotPasswordForm({ phpErrors, phpSuccessMessage }) {
            const [identifier, setIdentifier] = useState('');
            const [localErrors, setLocalErrors] = useState([]);
            const [localSuccessMessage, setLocalSuccessMessage] = useState('');

            useEffect(() => {
                if (phpErrors && phpErrors.length > 0) {
                    setLocalErrors(phpErrors);
                }
                if (phpSuccessMessage) {
                    setLocalSuccessMessage(phpSuccessMessage);
                }
            }, [phpErrors, phpSuccessMessage]);

            const handleSubmit = (e) => {
                const errors = [];
                if (!identifier.trim()) {
                    errors.push("Email or Phone Number cannot be empty.");
                }
                setLocalErrors(errors);

                if (errors.length > 0) {
                    e.preventDefault();
                }
            };

            return (
                <form method="POST" onSubmit={handleSubmit}>
                    {localSuccessMessage && (
                        <div className="success-message">{localSuccessMessage}</div>
                    )}

                    {localErrors.length > 0 && (
                        localErrors.map((err, index) => (
                            <div key={index} className="error-message show">{err}</div>
                        ))
                    )}

                    <div className="form-group">
                        <label htmlFor="identifier">Email or Phone Number</label>
                        <input
                            type="text"
                            name="identifier"
                            id="identifier"
                            required
                            placeholder="Enter your email or phone number"
                            value={identifier}
                            onChange={(e) => {
                                setIdentifier(e.target.value);
                                setLocalErrors([]);
                                setLocalSuccessMessage('');
                            }}
                        />
                    </div>
                    <div>
                        <button type="submit">Send OTP</button>
                    </div>
                    <div style={{ marginTop: '20px' }}>
                        <a href="login.php" className="back-to-login">Back to Login</a>
                    </div>
                </form>
            );
        }

        const phpErrors = <?php echo json_encode($phpErrors); ?>;
        const phpSuccessMessage = "<?php echo htmlspecialchars($phpSuccessMessage); ?>";

        ReactDOM.render(
            <ForgotPasswordForm
                phpErrors={phpErrors}
                phpSuccessMessage={phpSuccessMessage}
            />,
            document.getElementById('root')

        );

         document.addEventListener('contextmenu', (e) => e.preventDefault());
    </script>
</body>
</html>