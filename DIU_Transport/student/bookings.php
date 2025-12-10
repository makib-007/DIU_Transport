<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get all bookings for the student
$sql = "SELECT b.*, s.departure_time, s.arrival_time, r.route_name, r.start_location, r.end_location, r.fare,
               p.payment_status, p.amount, p.payment_method
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-ticket-alt"></i> My Bookings</h1>
                    <p>View and manage your transport bookings</p>
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
                    <li><a href="bookings.php" class="active"><i class="fas fa-ticket-alt"></i> My Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="view_seats.php"><i class="fas fa-chair"></i> View Seats</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>Booking History</h2>
                    <div>
                        <a href="schedules.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Book New Trip
                        </a>
                        <button onclick="exportTable('bookings-table', 'csv')" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                
                <!-- Booking Statistics -->
                <?php
                $total_bookings = 0;
                $active_bookings = 0;
                $completed_bookings = 0;
                $pending_payments = 0;
                
                $bookings_array = [];
                while ($booking = $bookings->fetch_assoc()) {
                    $bookings_array[] = $booking;
                    $total_bookings++;
                    if (in_array($booking['status'], ['pending', 'confirmed'])) {
                        $active_bookings++;
                    }
                    if ($booking['status'] === 'completed') {
                        $completed_bookings++;
                    }
                    if ($booking['payment_status'] === 'pending') {
                        $pending_payments++;
                    }
                }
                ?>
                
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
                        <h3><?php echo $pending_payments; ?></h3>
                        <p><i class="fas fa-exclamation-triangle"></i> Pending Payments</p>
                    </div>
                </div>
                
                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Bookings</h3>
                        <div>
                            <input type="text" id="searchInput" placeholder="Search bookings..." 
                                   onkeyup="searchTable('bookings-table', this.value)" 
                                   style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <?php if (count($bookings_array) > 0): ?>
                        <div class="table-container">
                            <table id="bookings-table">
                                <thead>
                                    <tr>
                                        <th>Booking Code</th>
                                        <th>Route</th>
                                        <th>Date & Time</th>
                                        <th>Seat</th>
                                        <th>Fare</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings_array as $booking): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong><br>
                                                <small><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($booking['route_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($booking['start_location']); ?> → 
                                                       <?php echo htmlspecialchars($booking['end_location']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></strong><br>
                                                <small><?php echo date('g:i A', strtotime($booking['departure_time'])); ?> - 
                                                       <?php echo date('g:i A', strtotime($booking['arrival_time'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge status-confirmed">
                                                    Seat <?php echo $booking['seat_number']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong>৳<?php echo number_format($booking['fare'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($booking['payment_status']): ?>
                                                    <span class="status-badge status-<?php echo $booking['payment_status']; ?>">
                                                        <?php echo ucfirst($booking['payment_status']); ?>
                                                    </span><br>
                                                    <small><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'] ?? 'N/A')); ?></small>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">Not Paid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                    <a href="view_booking.php?id=<?php echo $booking['id']; ?>" 
                                                       class="btn btn-secondary" style="padding: 5px 8px; font-size: 0.7rem;">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                        <a href="download_ticket.php?booking_id=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-success" style="padding: 5px 8px; font-size: 0.7rem;">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['payment_status'] === 'pending'): ?>
                                                        <a href="payments.php?booking_id=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-warning" style="padding: 5px 8px; font-size: 0.7rem;">
                                                            <i class="fas fa-credit-card"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                        <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" 
                                                                class="btn btn-danger" style="padding: 5px 8px; font-size: 0.7rem;">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
                
                <!-- Booking Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Booking Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> Booking Status Guide</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li><strong>Pending:</strong> Booking created, payment required</li>
                                <li><strong>Confirmed:</strong> Payment completed, booking confirmed</li>
                                <li><strong>Completed:</strong> Trip completed successfully</li>
                                <li><strong>Cancelled:</strong> Booking cancelled by user or admin</li>
                            </ul>
                        </div>
                        <div>
                            <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Payment must be completed within 24 hours</li>
                                <li>Cancellations can be made up to 2 hours before departure</li>
                                <li>Download your ticket after payment confirmation</li>
                                <li>Bring your student ID for verification</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        booking_id: bookingId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
    </script>
</body>
</html>
