<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Handle booking status update
if (isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = $_POST['new_status'];
    
    $sql = "UPDATE bookings SET status = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $new_status, $booking_id);
        if ($stmt->execute()) {
            $success_message = "Booking status updated successfully.";
        } else {
            $error_message = "Failed to update booking status.";
        }
        $stmt->close();
    }
}

// Handle booking deletion
if (isset($_POST['delete_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $sql = "DELETE FROM bookings WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $success_message = "Booking deleted successfully.";
        } else {
            $error_message = "Failed to delete booking.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters
$sql = "SELECT b.*, u.name as student_name, u.email, u.student_id, u.phone,
               s.departure_time, s.arrival_time, s.days_of_week,
               r.route_name, r.start_location, r.end_location, r.fare,
               bus.bus_number, p.payment_status, p.amount as payment_amount
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        JOIN buses bus ON s.bus_id = bus.id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $sql .= " AND b.booking_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$sql .= " ORDER BY b.created_at DESC";

$bookings = null;
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $bookings = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-ticket-alt"></i> All Bookings</h1>
                    <p>Transport Management System - Daffodil International University</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php"><i class="fas fa-clock"></i> Manage Schedules</a></li>
                    <li><a href="bookings.php" class="active"><i class="fas fa-ticket-alt"></i> All Bookings</a></li>
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
                    <h2>Booking Management</h2>
                    <div class="header-actions">
                        <button onclick="exportBookings()" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-inline">
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select name="status" id="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date">Date:</label>
                                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="bookings.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>All Bookings</h3>
                        <div class="search-box">
                            <input type="text" id="bookingSearch" placeholder="Search bookings...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Booking Code</th>
                                        <th>Student</th>
                                        <th>Route</th>
                                        <th>Date & Time</th>
                                        <th>Seat</th>
                                        <th>Fare</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($bookings && $bookings->num_rows > 0): ?>
                                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong><br>
                                                    <small>ID: <?php echo $booking['id']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['student_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($booking['email']); ?></small><br>
                                                    <small>ID: <?php echo htmlspecialchars($booking['student_id']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['route_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($booking['start_location']); ?> → <?php echo htmlspecialchars($booking['end_location']); ?></small><br>
                                                    <small>Bus: <?php echo htmlspecialchars($booking['bus_number']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></strong><br>
                                                    <small><?php echo date('h:i A', strtotime($booking['departure_time'])); ?> - <?php echo date('h:i A', strtotime($booking['arrival_time'])); ?></small><br>
                                                    <small><?php echo htmlspecialchars($booking['days_of_week']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">Seat <?php echo $booking['seat_number']; ?></span>
                                                </td>
                                                <td>
                                                    <strong>৳<?php echo number_format($booking['fare'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($booking['payment_status']): ?>
                                                        <span class="badge badge-<?php echo $booking['payment_status'] === 'completed' ? 'success' : ($booking['payment_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($booking['payment_status']); ?>
                                                        </span><br>
                                                        <small>৳<?php echo number_format($booking['payment_amount'], 2); ?></small>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">No Payment</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : ($booking['status'] === 'cancelled' ? 'danger' : 'secondary')); ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="viewBooking(<?php echo $booking['id']; ?>)" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button onclick="editBookingStatus(<?php echo $booking['id']; ?>, '<?php echo $booking['status']; ?>')" class="btn btn-sm btn-primary" title="Edit Status">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this booking?');">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <button type="submit" name="delete_booking" class="btn btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No bookings found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Booking Status</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="statusForm">
                    <input type="hidden" name="booking_id" id="modalBookingId">
                    <div class="form-group">
                        <label for="new_status">New Status:</label>
                        <select name="new_status" id="new_status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Booking search functionality
        document.getElementById('bookingSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Modal functionality
        function editBookingStatus(bookingId, currentStatus) {
            document.getElementById('modalBookingId').value = bookingId;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function viewBooking(bookingId) {
            // Implement view booking details functionality
            alert('View booking details for ID: ' + bookingId);
        }

        function exportBookings() {
            // Implement export functionality
            alert('Export functionality will be implemented here');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal when clicking X
        document.querySelector('.close').onclick = function() {
            closeModal();
        }
    </script>
</body>
</html>
