<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Handle profile update
$update_message = '';
$update_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($name) || empty($phone)) {
        $update_message = 'Name and phone are required fields.';
        $update_type = 'error';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $update_message = 'New passwords do not match.';
        $update_type = 'error';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update basic info
            $sql = "UPDATE users SET name = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $phone, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update profile');
            }
            
            // Update password if provided
            if (!empty($new_password)) {
                // Verify current password
                $sql = "SELECT password FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if ($current_password !== $user['password']) {
                    throw new Exception('Current password is incorrect');
                }
                
                // Update password
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_password, $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update password');
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Update session
            $_SESSION['name'] = $name;
            
            $update_message = 'Profile updated successfully!';
            $update_type = 'success';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $update_message = 'Update failed: ' . $e->getMessage();
            $update_type = 'error';
        }
    }
}

// Get user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$sql = "SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
        FROM bookings WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$booking_stats = $stmt->get_result()->fetch_assoc();

$sql = "SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_amount
        FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        WHERE b.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-user-cog"></i> My Profile</h1>
                    <p>Manage your account information and preferences</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php"><i class="fas fa-clock"></i> View Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> My Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="view_seats.php"><i class="fas fa-chair"></i> View Seats</a></li>
                    <li><a href="profile.php" class="active"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>Profile Information</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if ($update_message): ?>
                    <div class="alert alert-<?php echo $update_type; ?>">
                        <i class="fas fa-<?php echo $update_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($update_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Statistics -->
                <div class="grid grid-4">
                    <div class="stats-card">
                        <h3><?php echo $booking_stats['total_bookings']; ?></h3>
                        <p><i class="fas fa-ticket-alt"></i> Total Bookings</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $booking_stats['confirmed_bookings']; ?></h3>
                        <p><i class="fas fa-check-circle"></i> Confirmed</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $payment_stats['completed_payments']; ?></h3>
                        <p><i class="fas fa-credit-card"></i> Payments</p>
                    </div>
                    <div class="stats-card">
                        <h3>৳<?php echo number_format($payment_stats['total_amount'], 2); ?></h3>
                        <p><i class="fas fa-money-bill-wave"></i> Total Spent</p>
                    </div>
                </div>
                
                <!-- Profile Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Personal Information</h3>
                    </div>
                    
                    <form method="POST" class="profile-form">
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="name">
                                    <i class="fas fa-user"></i>
                                    Full Name
                                </label>
                                <input type="text" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    Email Address
                                </label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                       disabled style="background-color: #f8f9fa;">
                                <small style="color: #666;">Email cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="student_id">
                                    <i class="fas fa-id-card"></i>
                                    Student ID
                                </label>
                                <input type="text" id="student_id" value="<?php echo htmlspecialchars($user['student_id'] ?? ''); ?>" 
                                       disabled style="background-color: #f8f9fa;">
                                <small style="color: #666;">Student ID cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">
                                    <i class="fas fa-phone"></i>
                                    Phone Number
                                </label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">
                                <i class="fas fa-user-tag"></i>
                                Account Type
                            </label>
                            <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" 
                                   disabled style="background-color: #f8f9fa;">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h3>Change Password</h3>
                    </div>
                    
                    <form method="POST" class="password-form">
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label for="current_password">
                                    <i class="fas fa-lock"></i>
                                    Current Password
                                </label>
                                <input type="password" id="current_password" name="current_password" 
                                       placeholder="Enter current password">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">
                                    <i class="fas fa-key"></i>
                                    New Password
                                </label>
                                <input type="password" id="new_password" name="new_password" 
                                       placeholder="Enter new password">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="fas fa-key"></i>
                                    Confirm Password
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm new password">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Account Information</h3>
                    </div>
                    
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> Account Details</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li><strong>Member Since:</strong> <?php echo date('M j, Y', strtotime($user['created_at'])); ?></li>
                                <li><strong>Last Login:</strong> <?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></li>
                                <li><strong>Account Status:</strong> <span class="status-badge status-confirmed">Active</span></li>
                                <li><strong>Total Bookings:</strong> <?php echo $booking_stats['total_bookings']; ?></li>
                            </ul>
                        </div>
                        <div>
                            <h4><i class="fas fa-shield-alt"></i> Security Tips</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Use a strong, unique password</li>
                                <li>Never share your login credentials</li>
                                <li>Log out when using shared computers</li>
                                <li>Keep your contact information updated</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                        <a href="bookings.php" class="btn btn-secondary">View All</a>
                    </div>
                    
                    <?php
                    // Get recent bookings
                    $sql = "SELECT b.*, s.departure_time, r.route_name, r.start_location, r.end_location
                            FROM bookings b
                            JOIN schedules s ON b.schedule_id = s.id
                            JOIN routes r ON s.route_id = r.id
                            WHERE b.user_id = ?
                            ORDER BY b.created_at DESC
                            LIMIT 5";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $recent_bookings = $stmt->get_result();
                    ?>
                    
                    <?php if ($recent_bookings->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Booking Code</th>
                                        <th>Route</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['start_location']); ?> → 
                                                <?php echo htmlspecialchars($booking['end_location']); ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view_booking.php?id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-history" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                            <p>No recent activity found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Password validation
        document.querySelector('.password-form').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password.');
                return;
            }
            
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return;
            }
            
            if (newPassword && newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long.');
                return;
            }
        });
    </script>
</body>
</html>
