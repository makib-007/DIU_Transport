<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Get student statistics
$user_id = $_SESSION['user_id'];

// Total bookings
$sql = "SELECT COUNT(*) as total_bookings FROM bookings WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_bookings = $stmt->get_result()->fetch_assoc()['total_bookings'];

// Active bookings
$sql = "SELECT COUNT(*) as active_bookings FROM bookings WHERE user_id = ? AND status IN ('pending', 'confirmed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_bookings = $stmt->get_result()->fetch_assoc()['active_bookings'];

// Total payments
$sql = "SELECT COUNT(*) as total_payments FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        WHERE b.user_id = ? AND p.payment_status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_payments = $stmt->get_result()->fetch_assoc()['total_payments'];

// Recent bookings
$sql = "SELECT b.*, s.departure_time, s.arrival_time, r.route_name, r.start_location, r.end_location, r.fare
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-bus"></i> Student Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php"><i class="fas fa-clock"></i> View Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> My Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="view_seats.php"><i class="fas fa-chair"></i> View Seats</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>Dashboard Overview</h2>
                    <div>
                        <a href="schedules.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Book New Trip
                        </a>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="grid grid-3">
                    <div class="stats-card">
                        <h3><?php echo $total_bookings; ?></h3>
                        <p><i class="fas fa-ticket-alt"></i> Total Bookings</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $active_bookings; ?></h3>
                        <p><i class="fas fa-clock"></i> Active Bookings</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $total_payments; ?></h3>
                        <p><i class="fas fa-credit-card"></i> Completed Payments</p>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="grid grid-4">
                        <a href="schedules.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> View Schedules
                        </a>
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> My Bookings
                        </a>
                        <a href="view_seats.php" class="btn btn-info">
                            <i class="fas fa-chair"></i> View Seats
                        </a>
                        <a href="payments.php" class="btn btn-success">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                    </div>
                </div>
                
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
                                        <th>Booking Code</th>
                                        <th>Route</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Seat</th>
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
                                                <?php echo date('g:i A', strtotime($booking['departure_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($booking['arrival_time'])); ?>
                                            </td>
                                            <td><?php echo $booking['seat_number']; ?></td>
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
                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                    <a href="download_ticket.php?booking_id=<?php echo $booking['id']; ?>" 
                                                       class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem;">
                                                        <i class="fas fa-download"></i> Ticket
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-ticket-alt" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                            <p>No bookings found. <a href="schedules.php">Book your first trip!</a></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Important Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Important Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> Booking Guidelines</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Bookings can be made up to 3 days in advance</li>
                                <li>Seats are allocated on a first-come, first-served basis</li>
                                <li>Payment must be completed within 12 hours of booking</li>
                                <li>Cancellations can be made up to 1 hours before departure</li>
                            </ul>
                        </div>
                        <div>
                            <h4><i class="fas fa-phone"></i> Contact Information</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li><strong>Transport Office:</strong> +8809617901233</li>
                                <li><strong>Emergency:</strong> 09617901212</li>
                                <li><strong>Email:</strong> transport@diu.edu.bd</li>
                                <li><strong>Office Hours:</strong> 8:00 AM - 5:00 PM</li>
                                <li><strong>Developed By:</strong> <?php echo "Deluwar Hosen id:(241-35-225)"; ?></li>
                                
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
