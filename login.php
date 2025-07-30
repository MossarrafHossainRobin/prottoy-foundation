<?php
session_start();
require_once 'db.php';

if (!$conn) {
    die("Database connection failed. Please try again later.");
}

// Redirect if already logged in (check for existing session)
if (isset($_SESSION['user_id'])) {
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($user_role);
    $stmt->fetch();
    $stmt->close();
    // CRITICAL: Ensure user_role is also set in session here for consistency
    $_SESSION['user_role'] = $user_role; 
    $redirect_url = ($user_role === 'admin') ? 'admin_dashboard.php' : 'user_dashboard.php';
    header("Location: $redirect_url");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];

    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier)) {
        $errors[] = "Email or Phone Number is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        $query_identifier = $identifier;

        // Corrected SQL: Assuming 'password_hash' column for stored hashed passwords
        $sql = "SELECT id, password, role FROM users WHERE email = ? OR phone = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $query_identifier, $query_identifier);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $hashed_password_from_db, $user_role); // Renamed for clarity
                $stmt->fetch();

                // Verify password against the fetched hashed password
                if (password_verify($password, $hashed_password_from_db)) {
                    session_regenerate_id(true); // Regenerate session ID for security
                    $_SESSION['user_id'] = $id;
                    $_SESSION['user_role'] = $user_role; // <--- THIS IS THE CRITICAL ADDITION!

                    $redirect_url = ($user_role === 'admin') ? 'admin_dashboard.php' : 'user_dashboard.php';
                    header("Location: $redirect_url");
                    exit;
                } else {
                    $errors[] = "Incorrect password.";
                }
            } else {
                $errors[] = "Email or Phone Number not found.";
            }
            $stmt->close();
        }
    }

    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        header("Location: login.php");
        exit;
    }
}
$conn->close();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Prottoy Foundation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-teal: #00796B;
            --accent-gold: #FFC107;
            --dark-blue-grey: #263238;
            --light-bg-start: #E0F2F7;
            --light-bg-end: #E8F5E9;
            --text-dark: #333;
            --text-light: #f8f9fa;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--light-bg-start), var(--light-bg-end));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-dark);
        }
        .content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
            padding: 3.5rem 2.5rem;
            max-width: 450px;
            width: 100%;
            border-left: 8px solid var(--primary-teal);
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.9rem 1.2rem;
            border: 1px solid #ced4da;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 0.25rem rgba(0, 121, 107, 0.35);
            outline: none;
        }
        .btn-primary-custom {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: white;
            font-weight: 600;
            padding: 0.9rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            font-size: 1.1rem;
        }
        .btn-primary-custom:hover {
            background-color: #004D40;
            border-color: #004D40;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
        .navbar {
            background-color: var(--dark-blue-grey) !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            color: var(--text-light) !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .navbar-brand .logo-svg {
            height: 40px;
            width: auto;
            margin-right: 10px;
        }
        .heart-path {
            fill: var(--primary-teal);
        }
        .nav-link-register {
            color: white !important;
            background-color: var(--accent-gold);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            transition: background-color 0.3s ease, transform 0.3s ease;
            text-decoration: none;
            font-weight: 600;
        }
        .nav-link-register:hover {
            background-color: #E6A700;
            transform: translateY(-2px);
        }

        .footer {
            background: var(--dark-blue-grey);
            color: white;
            text-align: center;
            padding: 1.5rem;
        }
        .footer a {
            color: white;
            margin: 0 0.8rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: var(--accent-gold);
        }

        .alert {
            border-radius: 8px;
            font-weight: 500;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
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

        @media (max-width: 768px) {
            .login-card {
                padding: 2.5rem 1.5rem;
                margin: 1rem;
            }
            .navbar-brand {
                font-size: 1.2rem;
            }
            .navbar-brand .logo-svg {
                height: 30px;
            }
            .nav-link-register {
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="index.html">
                <svg class="logo-svg" viewBox="0 0 200 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path class="heart-path" d="M50 20C40 0, 20 0, 10 20C0 40, 20 60, 50 80C80 60, 100 40, 90 20C80 0, 60 0, 50 20Z" fill="#ff6f61"/>
                    <text x="60" y="50" fill="white" font-family="'Poppins', sans-serif" font-size="20" font-weight="600">Prottoy</text>
                </svg>
            </a>
            <div class="ms-auto">
                <a class="nav-link-register" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="content">
        <div class="login-card">
            <h2 class="text-center mb-5 display-5" style="color: var(--dark-blue-grey); font-weight: 700;">Login</h2>
            <?php if (isset($_SESSION['login_errors'])): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php foreach ($_SESSION['login_errors'] as $error): ?>
                        <p class="mb-1"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
                <?php unset($_SESSION['login_errors']); ?>
            <?php endif; ?>
            <?php if (isset($_GET['registration_success'])): ?>
                <div class="alert alert-success text-center alert-dismissible fade show mb-4" role="alert">
                    Registration successful! Please log in.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-4">
                    <label for="identifier" class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-control" id="identifier" name="identifier" required autofocus>
                    <div class="invalid-feedback">
                        Please enter your email or phone number.
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group password-input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="input-group-text toggle-password" data-target="password">
                            <i class="fa fa-eye"></i>
                        </span>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary-custom w-100 mb-3">Login</button>
                <p class="text-center mt-3 mb-0">
                    <a href="forgot_password.php" class="text-decoration-none" style="color: var(--primary-teal); font-weight: 600;">Forgot password?</a>
                </p>
            </form>
        </div>
    </div>

    <footer class="footer mt-auto">
        <p class="mb-0">&copy; 2025 Prottoy Foundation. All rights reserved.</p>
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
                Array.prototype.slice.call(forms).forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            })();
        });
         document.addEventListener('contextmenu', (e) => e.preventDefault());
    </script>
</body>
</html>
