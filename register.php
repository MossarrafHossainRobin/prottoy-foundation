<?php
session_start();
/*if (isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}*/

require_once 'db.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $home_address = trim($_POST['home_address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $selected_role_value = isset($_POST['role']) ? (int)$_POST['role'] : 0;
    $role_to_store = ($selected_role_value === 1) ? 'admin' : 'user';

    $errors = [];

    if (empty($name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($home_address)) {
        $errors[] = "Home Address is required.";
    }
    if (empty($phone) || !preg_match("/^[0-9]{10,15}$/", $phone)) {
        $errors[] = "Valid Phone Number (10-15 digits) is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid Email Address is required.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    $check_sql = "SELECT id FROM users WHERE email = ? OR phone = ?";
    if ($stmt_check = $conn->prepare($check_sql)) {
        $stmt_check->bind_param("ss", $email, $phone);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $errors[] = "Email or Phone Number is already registered.";
        }
        $stmt_check->close();
    } else {
        $errors[] = "Database error during email/phone check.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (name, home_address, phone, email, password, role) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssss", $name, $home_address, $phone, $email, $hashed_password, $role_to_store);

            if ($stmt->execute()) {
                $user_id = $conn->insert_id;

                $sql_donor = "INSERT INTO donors (user_id, designation) VALUES (?, NULL)";
                if ($stmt_donor = $conn->prepare($sql_donor)) {
                    $stmt_donor->bind_param("i", $user_id);
                    $stmt_donor->execute();
                    $stmt_donor->close();
                }

                header("Location: login.php?registration_success=true");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again later. " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: Could not prepare registration statement.";
        }
    }

    if (!empty($errors)) {
        $error_message = implode(" ", $errors);
        $_SESSION['registration_error'] = $error_message;
        header("Location: register.php");
        exit();
    }
}

if (isset($_SESSION['registration_error'])) {
    $error_message = $_SESSION['registration_error'];
    unset($_SESSION['registration_error']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Join Prottoy Foundation by registering to support community empowerment through donations and grants.">
    <meta name="keywords" content="Prottoy Foundation, nonprofit, charity, register, community support, donations">
    <meta name="author" content="Prottoy Foundation">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Register - Prottoy Foundation">
    <meta property="og:description" content="Join Prottoy Foundation by registering to support community empowerment through donations and grants.">
    <meta property="og:image" content="https://yourdomain.com/images/prottoy-heart-logo.png">
    <meta property="og:url" content="https://yourdomain.com/register.php">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Register - Prottoy Foundation">
    <meta name="twitter:description" content="Join Prottoy Foundation to support our mission of empowering communities.">
    <meta name="twitter:image" content="https://yourdomain.com/images/prottoy-heart-logo.png">
    <title>Register - Prottoy Foundation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Register - Prottoy Foundation",
        "url": "https://yourdomain.com/register.php",
        "description": "Register with Prottoy Foundation to join our mission of empowering communities through donations and grants.",
        "publisher": {
            "@type": "Organization",
            "name": "Prottoy Foundation",
            "logo": {
                "@type": "ImageObject",
                "url": "https://yourdomain.com/images/prottoy-heart-logo.png"
            }
        }
    }
    </script>
    <style>
        :root {
            --primary-teal: #00796B;
            --accent-gold: #FFC107;
            --dark-blue-grey: #263238;
            --light-bg-start: #E0F2F7;
            --light-bg-end: #E8F5E9;
            --text-dark: #333;
            --text-light: #f8f9fa;
            --form-section-bg: #f8f9fa;
        }

        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, var(--light-bg-start), var(--light-bg-end));
            color: var(--text-dark);
        }
        .content {
            flex: 1 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        footer {
            flex-shrink: 0;
            background-color: var(--dark-blue-grey);
            color: var(--text-light);
            padding: 1.5rem;
            text-align: center;
        }
        footer a {
            color: var(--text-light);
            margin: 0 0.8rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        footer a:hover {
            color: var(--accent-gold);
        }

        .form-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
            padding: 3.5rem;
            max-width: 1000px;
            width: 100%;
            animation: fadeIn 0.8s ease-out;
            border-left: 8px solid var(--primary-teal);
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-header {
            text-align: center;
        }
        .form-header h2 {
            color: var(--dark-blue-grey);
            font-weight: 700;
            margin-bottom: 0.75rem;
            font-size: 2.8rem;
        }
        .form-header p {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-section-title {
            color: var(--primary-teal);
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-teal);
            padding-bottom: 0.5rem;
            display: inline-block;
            width: 100%;
        }

        .form-group-section {
            background-color: var(--form-section-bg);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.05);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.9rem 1.2rem;
            border: 1px solid #ced4da;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 0.25rem rgba(0, 121, 107, 0.35);
            outline: none;
        }
        .btn-register {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: white;
            font-weight: 600;
            padding: 1rem 2rem;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            font-size: 1.2rem;
        }
        .btn-register:hover {
            background-color: #004D40;
            border-color: #004D40;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .form-text {
            font-size: 0.9em;
            color: #6c757d;
        }
        .btn-back-to-login {
            background-color: transparent;
            border: 2px solid var(--primary-teal);
            color: var(--primary-teal);
            font-weight: 600;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease, color 0.3s ease, transform 0.3s ease;
            text-decoration: none;
        }
        .btn-back-to-login:hover {
            background-color: var(--primary-teal);
            color: white;
            transform: translateY(-2px);
        }

        .password-input-group .input-group-text {
            background-color: #e9ecef;
            border-left: none;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            padding-right: 1.2rem;
        }
        .password-input-group .form-control {
            border-right: none;
            border-radius: 8px 0 0 8px;
        }
        .password-input-group .form-control:focus {
            box-shadow: none;
            border-color: var(--primary-teal);
        }
        .password-input-group:focus-within .input-group-text {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 0.25rem rgba(0, 121, 107, 0.35);
        }

        @media (max-width: 992px) {
            .form-container {
                padding: 2rem;
            }
            .form-header h2 {
                font-size: 2.2rem;
            }
            .form-header p {
                font-size: 1rem;
            }
            .form-group-section {
                padding: 1.5rem;
            }
            .form-section-title {
                font-size: 1.2rem;
            }
            .btn-register {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            .form-header h2 {
                font-size: 1.8rem;
            }
            .form-header p {
                font-size: 0.9rem;
            }
            .btn-back-to-login {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="form-container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h2 class="display-6 mb-0">Join Prottoy Foundation</h2>
                <a href="login.php" class="btn btn-back-to-login">Back to Login</a>
            </div>
            <p class="lead text-center mb-5">Become a part of our mission to empower communities.</p>
            <?php if ($error_message): ?>
                <div class="alert alert-danger text-center" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="needs-validation" novalidate>
                <div class="form-group-section mb-4">
                    <p class="form-section-title">Personal Information</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">
                                Please enter your full name.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="home_address" class="form-label">Home Address</label>
                            <input type="text" class="form-control" id="home_address" name="home_address" required>
                            <div class="invalid-feedback">
                                Please enter your home address.
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" pattern="[0-9]{10,15}" required>
                            <div class="form-text">e.g., 01712345678 (10-15 digits)</div>
                            <div class="invalid-feedback">
                                Please enter a valid phone number (10-15 digits).
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group-section mb-4">
                    <p class="form-section-title">Account Information</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group password-input-group">
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <span class="input-group-text toggle-password" data-target="password">
                                    <i class="fa fa-eye"></i>
                                </span>
                                <div class="invalid-feedback">
                                    Password must be at least 6 characters long.
                                </div>
                            </div>
                            <div class="form-text">Minimum 6 characters.</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group password-input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                <span class="input-group-text toggle-password" data-target="confirm_password">
                                    <i class="fa fa-eye"></i>
                                </span>
                                <div class="invalid-feedback">
                                    Passwords do not match.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group-section mb-4">
                    <p class="form-section-title">Role Selection</p>
                    <div class="mb-4">
                        <label for="role" class="form-label">Intended Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="0" selected>User</option>
                            <option value="1">Admin</option>
                        </select>
                        <div class="form-text text-muted">Select 'Admin' if you want to register as an administrator.</div>
                    </div>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-register btn-lg">Register</button>
                </div>
                <p class="text-center text-muted mt-3">
                    Already a member? <a href="login.php" class="text-decoration-none" style="color: var(--primary-teal); font-weight: 600;">Login here</a>
                </p>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p class="mb-2">&copy; 2025 Prottoy Foundation. All rights reserved.</p>
            <div class="d-flex justify-content-center">
                <a href="https://facebook.com" target="_blank">Facebook</a>
                <a href="https://twitter.com" target="_blank">Twitter</a>
                <a href="https://instagram.com" target="_blank">Instagram</a>
            </div>
            <p class="mt-2">
                <a href="mailto:info@prottoyfoundation.org">info@prottoyfoundation.org</a>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('contextmenu', (e) => {
                e.preventDefault();
            });

            const togglePasswordElements = document.querySelectorAll('.toggle-password');
            togglePasswordElements.forEach(toggle => {
                toggle.addEventListener('click', function () {
                    const targetId = this.dataset.target;
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');

                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            (function () {
                'use strict';
                var forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms)
                    .forEach(function (form) {
                        form.addEventListener('submit', function (event) {
                            if (!form.checkValidity()) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                            const password = document.getElementById('password');
                            const confirm_password = document.getElementById('confirm_password');
                            if (password.value !== confirm_password.value) {
                                confirm_password.setCustomValidity('Passwords do not match');
                            } else {
                                confirm_password.setCustomValidity('');
                            }

                            form.classList.add('was-validated');
                        }, false);

                        const password = document.getElementById('password');
                        const confirm_password = document.getElementById('confirm_password');
                        password.addEventListener('keyup', () => {
                            if (password.value !== confirm_password.value && confirm_password.value !== '') {
                                confirm_password.setCustomValidity('Passwords do not match');
                            } else {
                                confirm_password.setCustomValidity('');
                            }
                        });
                        confirm_password.addEventListener('keyup', () => {
                            if (password.value !== confirm_password.value) {
                                confirm_password.setCustomValidity('Passwords do not match');
                            } else {
                                confirm_password.setCustomValidity('');
                            }
                        });
                    });
            })();
        });
    </script>
</body>
</html>
