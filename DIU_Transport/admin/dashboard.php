<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Get admin statistics
$stats = [];

// Total users
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
$result = $conn->query($sql);
$stats['total_students'] = $result->fetch_assoc()['total'];

// Total bookings
$sql = "SELECT COUNT(*) as total FROM bookings";
$result = $conn->query($sql);
$stats['total_bookings'] = $result->fetch_assoc()['total'];

// Active bookings
$sql = "SELECT COUNT(*) as total FROM bookings WHERE status IN ('pending', 'confirmed')";
$result = $conn->query($sql);
$stats['active_bookings'] = $result->fetch_assoc()['total'];

// Total revenue
$sql = "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'completed'";
$result = $conn->query($sql);
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Total buses
$sql = "SELECT COUNT(*) as total FROM buses WHERE status = 'active'";
$result = $conn->query($sql);
$stats['total_buses'] = $result->fetch_assoc()['total'];

// Total routes
$sql = "SELECT COUNT(*) as total FROM routes WHERE status = 'active'";
$result = $conn->query($sql);
$stats['total_routes'] = $result->fetch_assoc()['total'];

// Recent bookings
$sql = "SELECT b.*, u.name as student_name, u.email, s.departure_time, r.route_name, r.start_location, r.end_location
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        ORDER BY b.created_at DESC
        LIMIT 10";
$recent_bookings = $conn->query($sql);

// Recent payments
$sql = "SELECT p.*, b.booking_code, u.name as student_name
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN users u ON b.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 10";
$recent_payments = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-bus"></i> Admin Dashboard</h1>
                    <p>Transport Management System - Daffodil International University</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php"><i class="fas fa-clock"></i> Manage Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> All Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
                    <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                    <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>System Overview</h2>
                    <div>
                        <a href="schedules.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Schedule
                        </a>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="grid grid-3">
                    <div class="stats-card">
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p><i class="fas fa-users"></i> Total Students</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <p><i class="fas fa-ticket-alt"></i> Total Bookings</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $stats['active_bookings']; ?></h3>
                        <p><i class="fas fa-clock"></i> Active Bookings</p>
                    </div>
                    <div class="stats-card">
                        <h3>৳<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                        <p><i class="fas fa-money-bill-wave"></i> Total Revenue</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $stats['total_buses']; ?></h3>
                        <p><i class="fas fa-bus"></i> Active Buses</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $stats['total_routes']; ?></h3>
                        <p><i class="fas fa-route"></i> Active Routes</p>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="grid grid-3">
                        <a href="schedules.php" class="btn btn-primary">
                            <i class="fas fa-clock"></i> Manage Schedules
                        </a>
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> View Bookings
                        </a>
                        <a href="students.php" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Manage Students
                        </a>
                        <a href="payments.php" class="btn btn-warning">
                            <i class="fas fa-credit-card"></i> Payment Status
                        </a>
                        <a href="reports.php" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> Generate Reports
                        </a>
                        <a href="buses.php" class="btn btn-danger">
                            <i class="fas fa-bus"></i> Manage Buses
                        </a>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="grid grid-2">
                    <!-- Recent Bookings -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Bookings</h3>
                            <a href="bookings.php" class="btn btn-secondary">View All</a>
                        </div>
                        
                        <?php if ($recent_bookings->num_rows > 0): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Student</th>
                                            <th>Route</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                                <td>
                                                    <?php echo htmlspecialchars($booking['student_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                                </td>
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
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 20px;">
                                <p>No recent bookings</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Payments -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Payments</h3>
                            <a href="payments.php" class="btn btn-secondary">View All</a>
                        </div>
                        
                        <?php if ($recent_payments->num_rows > 0): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Booking</th>
                                            <th>Student</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($payment['booking_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                                <td><strong>৳<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                                        <?php echo ucfirst($payment['payment_status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 20px;">
                                <p>No recent payments</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>System Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> System Status</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li><strong>Database:</strong> Connected</li>
                                <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                                <li><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                                
                                <li><strong>Timezone:</strong> <?php echo "Dhaka"; ?></li>
                                <li><strong>Developed By:</strong> <?php echo "Deluwar hosen id(241-35-225)"; ?></li>
                                
                            </ul>
                        </div>
                        <div>
                            <h4><i class="fas fa-cog"></i> Quick Settings</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li><a href="schedules.php">Manage Bus Schedules</a></li>
                                <li><a href="students.php">Add/Remove Students</a></li>
                                <li><a href="buses.php">Manage Bus Fleet</a></li>
                                <li><a href="routes.php">Configure Routes</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>
