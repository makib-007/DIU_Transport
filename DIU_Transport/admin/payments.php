<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Handle payment status update
if (isset($_POST['update_payment_status'])) {
    $payment_id = (int)$_POST['payment_id'];
    $new_status = $_POST['new_payment_status'];
    $transaction_id = $_POST['transaction_id'];
    
    $sql = "UPDATE payments SET payment_status = ?, transaction_id = ?, payment_date = NOW() WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssi", $new_status, $transaction_id, $payment_id);
        if ($stmt->execute()) {
            $success_message = "Payment status updated successfully.";
        } else {
            $error_message = "Failed to update payment status.";
        }
        $stmt->close();
    }
}

// Handle payment deletion
if (isset($_POST['delete_payment'])) {
    $payment_id = (int)$_POST['payment_id'];
    $sql = "DELETE FROM payments WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            $success_message = "Payment deleted successfully.";
        } else {
            $error_message = "Failed to delete payment.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$method_filter = isset($_GET['method']) ? $_GET['method'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters
$sql = "SELECT p.*, b.booking_code, b.booking_date, b.seat_number,
               u.name as student_name, u.email, u.student_id,
               r.route_name, r.start_location, r.end_location,
               s.departure_time, s.arrival_time
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN users u ON b.user_id = u.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $sql .= " AND p.payment_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($method_filter)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(p.created_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$sql .= " ORDER BY p.created_at DESC";

$payments = null;
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $payments = $stmt->get_result();
    $stmt->close();
}

// Get payment statistics
$stats = [];

// Total payments
$sql = "SELECT COUNT(*) as total FROM payments";
$result = $conn->query($sql);
$stats['total_payments'] = $result->fetch_assoc()['total'];

// Total revenue
$sql = "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'completed'";
$result = $conn->query($sql);
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Pending payments
$sql = "SELECT COUNT(*) as total FROM payments WHERE payment_status = 'pending'";
$result = $conn->query($sql);
$stats['pending_payments'] = $result->fetch_assoc()['total'];

// Failed payments
$sql = "SELECT COUNT(*) as total FROM payments WHERE payment_status = 'failed'";
$result = $conn->query($sql);
$stats['failed_payments'] = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-credit-card"></i> Payments</h1>
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
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> All Bookings</a></li>
                    <li><a href="payments.php" class="active"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
                    <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                    <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Payment Management</h2>
                    <div class="header-actions">
                        <button onclick="exportPayments()" class="btn btn-secondary">
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

                <!-- Payment Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_payments']; ?></h3>
                            <p>Total Payments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h3>৳<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['pending_payments']; ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['failed_payments']; ?></h3>
                            <p>Failed Payments</p>
                        </div>
                    </div>
                </div>

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
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="method">Method:</label>
                                <select name="method" id="method">
                                    <option value="">All Methods</option>
                                    <option value="cash" <?php echo $method_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo $method_filter === 'card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="mobile_banking" <?php echo $method_filter === 'mobile_banking' ? 'selected' : ''; ?>>Mobile Banking</option>
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
                                <a href="payments.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>All Payments</h3>
                        <div class="search-box">
                            <input type="text" id="paymentSearch" placeholder="Search payments...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Student</th>
                                        <th>Booking</th>
                                        <th>Route</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($payments && $payments->num_rows > 0): ?>
                                        <?php while ($payment = $payments->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?></strong><br>
                                                    <small>ID: <?php echo $payment['id']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($payment['email']); ?></small><br>
                                                    <small>ID: <?php echo htmlspecialchars($payment['student_id']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['booking_code']); ?></strong><br>
                                                    <small>Date: <?php echo date('M j, Y', strtotime($payment['booking_date'])); ?></small><br>
                                                    <small>Seat: <?php echo $payment['seat_number']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['route_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($payment['start_location']); ?> → <?php echo htmlspecialchars($payment['end_location']); ?></small><br>
                                                    <small><?php echo date('h:i A', strtotime($payment['departure_time'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong>৳<?php echo number_format($payment['amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $payment['payment_status'] === 'completed' ? 'success' : ($payment['payment_status'] === 'pending' ? 'warning' : ($payment['payment_status'] === 'failed' ? 'danger' : 'secondary')); ?>">
                                                        <?php echo ucfirst($payment['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></strong><br>
                                                    <small><?php echo date('h:i A', strtotime($payment['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="editPaymentStatus(<?php echo $payment['id']; ?>, '<?php echo $payment['payment_status']; ?>', '<?php echo htmlspecialchars($payment['transaction_id']); ?>')" class="btn btn-sm btn-primary" title="Edit Status">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this payment?');">
                                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                            <button type="submit" name="delete_payment" class="btn btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No payments found.</td>
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

    <!-- Payment Status Update Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Payment Status</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="payment_id" id="modalPaymentId">
                    <div class="form-group">
                        <label for="new_payment_status">Payment Status:</label>
                        <select name="new_payment_status" id="new_payment_status" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transaction_id">Transaction ID:</label>
                        <input type="text" name="transaction_id" id="transaction_id" placeholder="Enter transaction ID">
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_payment_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Payment search functionality
        document.getElementById('paymentSearch').addEventListener('keyup', function() {
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
        function editPaymentStatus(paymentId, currentStatus, transactionId) {
            document.getElementById('modalPaymentId').value = paymentId;
            document.getElementById('new_payment_status').value = currentStatus;
            document.getElementById('transaction_id').value = transactionId || '';
            document.getElementById('paymentModal').style.display = 'block';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        function exportPayments() {
            // Implement export functionality
            alert('Export functionality will be implemented here');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        }

        // Close modal when clicking X
        document.querySelector('.close').onclick = function() {
            closePaymentModal();
        }
    </script>
</body>
</html>
