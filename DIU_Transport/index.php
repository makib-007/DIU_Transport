<?php
session_start();

/*
 |--------------------------------------------------------------
 | Simple login (NO hashing) for local testing
 |--------------------------------------------------------------
 | This version compares the submitted password to the stored
 | password as plain text. Do NOT use in production.
 |--------------------------------------------------------------
*/

// If already logged in, redirect by role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit();
}

// Database connection
require_once 'config/database.php';

$error_message = '';
$success_message = '';
$form_mode = 'login'; // Default to login form

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type'])) {
        if ($_POST['form_type'] === 'login') {
            // Handle login
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            if ($email === '' || $password === '') {
                $error_message = 'Please fill in all fields.';
            } else {
                $sql = "SELECT * FROM users WHERE email = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows === 1) {
                        $user = $result->fetch_assoc();

                        // Debug: Check user data (remove this in production)
                        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                            echo "Debug Info:<br>";
                            echo "Email: " . $user['email'] . "<br>";
                            echo "Password in DB: " . $user['password'] . "<br>";
                            echo "Submitted Password: " . $password . "<br>";
                            echo "Status: " . $user['status'] . "<br>";
                            echo "Role: " . $user['role'] . "<br>";
                            exit();
                        }

                        // Plain-text comparison (for testing only)
                        if ($password === $user['password']) {
                            // Check if user is active
                            if ($user['status'] === 'active') {
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['name'] = $user['name'];
                                $_SESSION['role'] = $user['role'];

                                if ($user['role'] === 'admin') {
                                    header('Location: admin/dashboard.php');
                                } else {
                                    header('Location: student/dashboard.php');
                                }
                                exit();
                            } else {
                                $error_message = 'Your account is not active. Please contact administrator.';
                            }
                        } else {
                            $error_message = 'Invalid email or password.';
                        }
                    } else {
                        $error_message = 'Invalid email or password.';
                    }

                    $stmt->close();
                } else {
                    $error_message = 'Failed to prepare the login query.';
                }
            }
        } elseif ($_POST['form_type'] === 'signup') {
            // Handle signup
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

            // Validation
            if ($name === '' || $email === '' || $password === '' || $confirm_password === '' || $student_id === '' || $phone === '') {
                $error_message = 'Please fill in all fields.';
            } elseif ($password !== $confirm_password) {
                $error_message = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error_message = 'Password must be at least 6 characters long.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Please enter a valid email address.';
            } else {
                // Check if email already exists
                $check_sql = "SELECT id FROM users WHERE email = ?";
                if ($check_stmt = $conn->prepare($check_sql)) {
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {
                        $error_message = 'Email address already exists.';
                    } else {
                        // Check if student ID already exists
                        $check_student_sql = "SELECT id FROM users WHERE student_id = ?";
                        if ($check_student_stmt = $conn->prepare($check_student_sql)) {
                            $check_student_stmt->bind_param("s", $student_id);
                            $check_student_stmt->execute();
                            $check_student_result = $check_student_stmt->get_result();

                            if ($check_student_result->num_rows > 0) {
                                $error_message = 'Student ID already exists.';
                            } else {
                                // Insert new user
                                $insert_sql = "INSERT INTO users (name, email, password, role, student_id, phone, status) VALUES (?, ?, ?, 'student', ?, ?, 'active')";
                                if ($insert_stmt = $conn->prepare($insert_sql)) {
                                    $insert_stmt->bind_param("sssss", $name, $email, $password, $student_id, $phone);
                                    
                                    if ($insert_stmt->execute()) {
                                        $success_message = 'Account created successfully! You can now login.';
                                        $form_mode = 'login'; // Switch to login form
                                    } else {
                                        $error_message = 'Failed to create account. Please try again.';
                                    }
                                    $insert_stmt->close();
                                } else {
                                    $error_message = 'Failed to prepare the signup query.';
                                }
                            }
                            $check_student_stmt->close();
                        } else {
                            $error_message = 'Failed to check student ID.';
                        }
                    }
                    $check_stmt->close();
                } else {
                    $error_message = 'Failed to check email.';
                }
            }
        }
    }
}

// Handle form mode toggle
if (isset($_GET['mode'])) {
    $form_mode = $_GET['mode'] === 'signup' ? 'signup' : 'login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiuTransport - Daffodil International University</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .auth-header .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .auth-header .logo i {
            font-size: 2.5em;
            margin-right: 15px;
        }

        .auth-header h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 700;
        }

        .auth-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9em;
        }

        .auth-body {
            padding: 30px;
        }

        .form-toggle {
            display: flex;
            margin-bottom: 25px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
        }

        .form-toggle button {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .form-toggle button.active {
            background: #667eea;
            color: white;
        }

        .form-toggle button:not(.active) {
            color: #6c757d;
        }

        .form-toggle button:hover:not(.active) {
            background: #e9ecef;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn i {
            margin-right: 8px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 8px;
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .auth-footer p {
            margin: 0 0 15px 0;
            color: #6c757d;
            font-size: 0.9em;
        }

        .demo-accounts {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .demo-accounts h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 0.9em;
        }

        .demo-account {
            font-size: 0.8em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .demo-account strong {
            color: #495057;
        }

        .password-requirements {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 5px;
        }

        .student-id-format {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-bus"></i>
                    <h1>DiuTransport</h1>
                </div>
                <p>Daffodil International University Transport Management System</p>
            </div>

            <div class="auth-body">
                <div class="form-toggle">
                    <button type="button" class="<?php echo $form_mode === 'login' ? 'active' : ''; ?>" onclick="switchForm('login')">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <button type="button" class="<?php echo $form_mode === 'signup' ? 'active' : ''; ?>" onclick="switchForm('signup')">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </button>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <div class="form-section <?php echo $form_mode === 'login' ? 'active' : ''; ?>" id="login-form">
                    <form method="POST" onsubmit="return validateLoginForm()">
                        <input type="hidden" name="form_type" value="login">
                        
                        <div class="form-group">
                            <label for="login-email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input
                                type="email"
                                id="login-email"
                                name="email"
                                required
                                value="<?php echo isset($_POST['email']) && $_POST['form_type'] === 'login' ? htmlspecialchars($_POST['email']) : ''; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="login-password">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <input type="password" id="login-password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </button>
                    </form>
                </div>

                <!-- Signup Form -->
                <div class="form-section <?php echo $form_mode === 'signup' ? 'active' : ''; ?>" id="signup-form">
                    <form method="POST" onsubmit="return validateSignupForm()">
                        <input type="hidden" name="form_type" value="signup">
                        
                        <div class="form-group">
                            <label for="signup-name">
                                <i class="fas fa-user"></i>
                                Full Name
                            </label>
                            <input
                                type="text"
                                id="signup-name"
                                name="name"
                                required
                                value="<?php echo isset($_POST['name']) && $_POST['form_type'] === 'signup' ? htmlspecialchars($_POST['name']) : ''; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="signup-email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input
                                type="email"
                                id="signup-email"
                                name="email"
                                required
                                value="<?php echo isset($_POST['email']) && $_POST['form_type'] === 'signup' ? htmlspecialchars($_POST['email']) : ''; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="signup-student-id">
                                <i class="fas fa-id-card"></i>
                                Student ID
                            </label>
                            <input
                                type="text"
                                id="signup-student-id"
                                name="student_id"
                                required
                                placeholder="e.g., 241-35-225"
                                value="<?php echo isset($_POST['student_id']) && $_POST['form_type'] === 'signup' ? htmlspecialchars($_POST['student_id']) : ''; ?>"
                            >
                            <div class="student-id-format">Format: YYY-DD-XXX (e.g., 241-35-225)</div>
                        </div>

                        <div class="form-group">
                            <label for="signup-phone">
                                <i class="fas fa-phone"></i>
                                Phone Number
                            </label>
                            <input
                                type="tel"
                                id="signup-phone"
                                name="phone"
                                required
                                placeholder="+8801846182502"
                                value="<?php echo isset($_POST['phone']) && $_POST['form_type'] === 'signup' ? htmlspecialchars($_POST['phone']) : ''; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="signup-password">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <input type="password" id="signup-password" name="password" required>
                            <div class="password-requirements">Password must be at least 6 characters long</div>
                        </div>

                        <div class="form-group">
                            <label for="signup-confirm-password">
                                <i class="fas fa-lock"></i>
                                Confirm Password
                            </label>
                            <input type="password" id="signup-confirm-password" name="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Create Account
                        </button>
                    </form>
                </div>

                <div class="auth-footer">
                    <p>Need help? Contact the transport office.</p>
                    <div class="demo-accounts">
                        <h4>Demo Accounts:</h4>
                        <div class="demo-account">
                            <strong>Student:</strong> student@diu.edu.bd / 123456789
                        </div>
                        <div class="demo-account">
                            <strong>Admin:</strong> admin@diu.edu.bd / 123456789
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchForm(mode) {
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('mode', mode);
            window.history.pushState({}, '', url);

            // Update toggle buttons
            document.querySelectorAll('.form-toggle button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Show/hide forms
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(mode + '-form').classList.add('active');
        }

        function validateLoginForm() {
            const email = document.getElementById('login-email').value.trim();
            const password = document.getElementById('login-password').value;

            if (!email || !password) {
                alert('Please fill in all fields.');
                return false;
            }

            if (!isValidEmail(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            return true;
        }

        function validateSignupForm() {
            const name = document.getElementById('signup-name').value.trim();
            const email = document.getElementById('signup-email').value.trim();
            const studentId = document.getElementById('signup-student-id').value.trim();
            const phone = document.getElementById('signup-phone').value.trim();
            const password = document.getElementById('signup-password').value;
            const confirmPassword = document.getElementById('signup-confirm-password').value;

            if (!name || !email || !studentId || !phone || !password || !confirmPassword) {
                alert('Please fill in all fields.');
                return false;
            }

            if (!isValidEmail(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            if (!isValidStudentId(studentId)) {
                alert('Please enter a valid student ID (format: YYY-DD-XXX).');
                return false;
            }

            if (!isValidPhone(phone)) {
                alert('Please enter a valid phone number.');
                return false;
            }

            if (password.length < 6) {
                alert('Password must be at least 6 characters long.');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }

            return true;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidStudentId(studentId) {
            const studentIdRegex = /^\d{3}-\d{2}-\d{3}$/;
            return studentIdRegex.test(studentId);
        }

        function isValidPhone(phone) {
            const phoneRegex = /^\+880\d{10}$/;
            return phoneRegex.test(phone);
        }

        // Auto-focus on first input when form switches
        document.addEventListener('DOMContentLoaded', function() {
            const activeForm = document.querySelector('.form-section.active');
            if (activeForm) {
                const firstInput = activeForm.querySelector('input');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });
    </script>
</body>
</html>
