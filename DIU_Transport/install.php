<?php
// DiuTransport Installation Script
// This script helps set up the database and verify system requirements

session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Check system requirements
function checkRequirements() {
    $requirements = [];
    
    // PHP version
    $requirements['php_version'] = [
        'name' => 'PHP Version',
        'required' => '7.4.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
    ];
    
    // MySQL extension
    $requirements['mysql'] = [
        'name' => 'MySQL Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('mysqli') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('mysqli')
    ];
    
    // Session support
    $requirements['session'] = [
        'name' => 'Session Support',
        'required' => 'Enabled',
        'current' => function_exists('session_start') ? 'Enabled' : 'Disabled',
        'status' => function_exists('session_start')
    ];
    
    // File permissions
    $requirements['config_writable'] = [
        'name' => 'Config Directory Writable',
        'required' => 'Writable',
        'current' => is_writable('config') ? 'Writable' : 'Not Writable',
        'status' => is_writable('config')
    ];
    
    return $requirements;
}

// Test database connection
function testDatabaseConnection($host, $username, $password, $database) {
    try {
        $conn = new mysqli($host, $username, $password);
        
        if ($conn->connect_error) {
            return ['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error];
        }
        
        // Check if database exists
        $result = $conn->query("SHOW DATABASES LIKE '$database'");
        if ($result->num_rows === 0) {
            // Create database
            if (!$conn->query("CREATE DATABASE `$database`")) {
                return ['success' => false, 'message' => 'Failed to create database: ' . $conn->error];
            }
        }
        
        $conn->select_db($database);
        
        // Test if tables exist
        $result = $conn->query("SHOW TABLES");
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Database is empty. Please import the SQL file.'];
        }
        
        $conn->close();
        return ['success' => true, 'message' => 'Database connection successful!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // Database configuration
        $host = $_POST['host'] ?? 'localhost';
        $username = $_POST['username'] ?? 'root';
        $password = $_POST['password'] ?? '';
        $database = $_POST['database'] ?? 'diutransport';
        
        $test_result = testDatabaseConnection($host, $username, $password, $database);
        
        if ($test_result['success']) {
            // Update config file
            $config_content = "<?php
// Database configuration for XAMPP
\$host = '$host';
\$username = '$username';
\$password = '$password';
\$database = '$database';

// Create connection
\$conn = new mysqli(\$host, \$username, \$password, \$database);

// Check connection
if (\$conn->connect_error) {
    die(\"Connection failed: \" . \$conn->connect_error);
}

// Set charset to utf8
\$conn->set_charset(\"utf8\");
?>";
            
            if (file_put_contents('config/database.php', $config_content)) {
                $success = 'Database configuration updated successfully!';
                $step = 3;
            } else {
                $error = 'Failed to write configuration file. Please check file permissions.';
            }
        } else {
            $error = $test_result['message'];
        }
    }
}

$requirements = checkRequirements();
$all_requirements_met = true;
foreach ($requirements as $req) {
    if (!$req['status']) {
        $all_requirements_met = false;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiuTransport Installation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .install-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .install-content {
            padding: 30px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1e5e9;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        .requirement-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="install-header">
                <div class="logo">
                    <i class="fas fa-bus"></i>
                    <h1>DiuTransport Installation</h1>
                </div>
                <p>Daffodil International University Transport Management System</p>
            </div>
            
            <div class="install-content">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? 'completed' : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? 'completed' : ($step == 2 ? 'active' : ''); ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? 'completed' : ($step == 3 ? 'active' : ''); ?>">3</div>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Step 1: System Requirements -->
                <?php if ($step === 1): ?>
                    <h2>Step 1: System Requirements Check</h2>
                    <p>Please ensure your system meets the following requirements:</p>
                    
                    <div class="card">
                        <?php foreach ($requirements as $req): ?>
                            <div class="requirement-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($req['name']); ?></strong><br>
                                    <small>Required: <?php echo htmlspecialchars($req['required']); ?></small>
                                </div>
                                <div>
                                    <span class="requirement-status <?php echo $req['status'] ? 'status-success' : 'status-error'; ?>">
                                        <?php echo htmlspecialchars($req['current']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($all_requirements_met): ?>
                        <div class="text-center" style="margin-top: 30px;">
                            <a href="?step=2" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Continue to Database Setup
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please fix the above requirements before continuing.
                        </div>
                    <?php endif; ?>
                
                <!-- Step 2: Database Configuration -->
                <?php elseif ($step === 2): ?>
                    <h2>Step 2: Database Configuration</h2>
                    <p>Configure your database connection settings:</p>
                    
                    <form method="POST" class="card">
                        <div class="form-group">
                            <label for="host">Database Host</label>
                            <input type="text" id="host" name="host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Database Username</label>
                            <input type="text" id="username" name="username" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Database Password</label>
                            <input type="password" id="password" name="password" value="">
                        </div>
                        
                        <div class="form-group">
                            <label for="database">Database Name</label>
                            <input type="text" id="database" name="database" value="diutransport" required>
                        </div>
                        
                        <div class="text-center">
                            <a href="?step=1" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-database"></i> Test Connection
                            </button>
                        </div>
                    </form>
                
                <!-- Step 3: Installation Complete -->
                <?php elseif ($step === 3): ?>
                    <h2>Step 3: Installation Complete!</h2>
                    <div class="text-center" style="padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 20px;"></i>
                        <h3>Congratulations!</h3>
                        <p>DiuTransport has been successfully installed on your system.</p>
                        
                        <div class="card" style="margin: 30px 0;">
                            <h4>Next Steps:</h4>
                            <ol style="text-align: left; margin-left: 20px;">
                                <li>Import the database structure using the SQL file: <code>database/diutransport.sql</code></li>
                                <li>Access the application at: <a href="index.php">index.php</a></li>
                                <li>Use the demo accounts to test the system</li>
                            </ol>
                        </div>
                        
                        <div class="card" style="margin: 30px 0;">
                            <h4>Demo Accounts:</h4>
                            <div style="text-align: left;">
                                <p><strong>Student:</strong> student@diu.edu.bd / password123</p>
                                <p><strong>Admin:</strong> admin@diu.edu.bd / admin123</p>
                            </div>
                        </div>
                        
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-rocket"></i> Launch Application
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
